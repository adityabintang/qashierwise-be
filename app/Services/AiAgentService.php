<?php

namespace App\Services;

use App\Enums\UserIntent;
use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Models\Order;
use App\Models\QrisTransaction;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use Illuminate\Support\Facades\Log;

class AiAgentService
{
    protected WhatsAppAccountService $whatsappAccountService;

    protected OrderService $orderService;

    protected QrisService $qrisService;

    protected ConversationSummarizer $conversationSummarizer;

    protected IntentTracker $intentTracker;

    protected ConversationGuard $conversationGuard;

    protected CatalogOrderFlowService $catalogOrderFlow;

    protected \App\Services\AiAgent\Reply\ReplySender $replySender;

    protected \App\Services\AiAgent\Tools\ProductResolver $productResolver;

    protected \App\Services\AiAgent\LLM\LlmClient $llmClient;

    protected \App\Services\AiAgent\Intent\IntentRouter $intentRouter;

    protected \App\Services\AiAgent\Tools\CatalogTools $catalogTools;

    protected \App\Services\AiAgent\Tools\CartTools $cartTools;

    protected \App\Services\AiAgent\Checkout\CheckoutTools $checkoutTools;

    protected \App\Services\AiAgent\Payment\PaymentTools $paymentTools;

    protected \App\Services\AiAgent\Tools\ToolDispatcher $toolDispatcher;

    public function __construct(
        WhatsAppAccountService $whatsappAccountService,
        OrderService $orderService,
        QrisService $qrisService,
        ConversationSummarizer $conversationSummarizer,
        IntentTracker $intentTracker,
        ConversationGuard $conversationGuard,
        CatalogOrderFlowService $catalogOrderFlow,
        \App\Services\AiAgent\Reply\ReplySender $replySender,
        \App\Services\AiAgent\Tools\ProductResolver $productResolver,
        \App\Services\AiAgent\LLM\LlmClient $llmClient,
        \App\Services\AiAgent\Intent\IntentRouter $intentRouter,
        \App\Services\AiAgent\Tools\CatalogTools $catalogTools,
        \App\Services\AiAgent\Tools\CartTools $cartTools,
        \App\Services\AiAgent\Checkout\CheckoutTools $checkoutTools,
        \App\Services\AiAgent\Payment\PaymentTools $paymentTools,
        \App\Services\AiAgent\Tools\ToolDispatcher $toolDispatcher,
    ) {
        $this->whatsappAccountService = $whatsappAccountService;
        $this->orderService = $orderService;
        $this->qrisService = $qrisService;
        $this->conversationSummarizer = $conversationSummarizer;
        $this->intentTracker = $intentTracker;
        $this->conversationGuard = $conversationGuard;
        $this->catalogOrderFlow = $catalogOrderFlow;
        $this->replySender = $replySender;
        $this->productResolver = $productResolver;
        $this->llmClient = $llmClient;
        $this->intentRouter = $intentRouter;
        $this->catalogTools = $catalogTools;
        $this->cartTools = $cartTools;
        $this->checkoutTools = $checkoutTools;
        $this->paymentTools = $paymentTools;
        $this->toolDispatcher = $toolDispatcher;
    }

    /**
     * Process incoming message and generate AI response.
     */
    public function processMessage(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        string $messageText
    ): void {
        Log::info('AI agent: processing message', [
            'contact_id' => $contact->id,
            'wa_id_hash' => substr(hash('sha256', $contact->wa_id), 0, 12),
            'message_len' => strlen($messageText),
        ]);

        try {
            // Get AI Agent for this account
            $aiAgent = AiAgent::where('whatsapp_account_id', $account->id)
                ->where('is_active', true)
                ->first();

            if (! $aiAgent) {
                return;
            }

            // Get or create conversation
            $conversation = $this->getOrCreateConversation($aiAgent->id, $contact->id);

            // Catalog flow takeover: when a catalog-driven flow is in progress,
            // route text input through CatalogOrderFlowService so the LLM doesn't
            // hijack the state machine. The webhook already routes for delivery
            // info; this is defense-in-depth and also nudges the user when text
            // arrives while we're waiting on a button.
            if ($conversation->getFlowState() !== null) {
                // Universal escape hatch: typing "batal" / "cancel" / "stop"
                // at ANY point in the catalog flow resets state and frees the
                // customer. Previously the bot told them to "kirim batal" but
                // no handler was wired up — leaving them stuck.
                if (preg_match('/^\s*(batal|cancel|stop|berhenti|gajadi|gak\s+jadi|tidak\s+jadi)\.?\s*$/iu', $messageText)) {
                    Log::info('Catalog flow cancelled by customer', [
                        'ai_agent_id' => $aiAgent->id,
                        'contact_wa_id' => $contact->wa_id,
                        'previous_state' => $conversation->getFlowState(),
                    ]);
                    $conversation->clearFlowState();
                    $conversation->clearDeliveryContext();
                    $conversation->clearCatalogItems();
                    $conversation->clearPendingOrder();
                    $this->catalogOrderFlow->sendCancelledReply($account, $contact, $aiAgent);

                    return;
                }

                if ($conversation->getFlowState() === CatalogOrderFlowService::STATE_AWAITING_PAYMENT) {
                    $qrisTransaction = $conversation->getCurrentQrisTransaction();
                    if ($qrisTransaction && $qrisTransaction->isExpired()) {
                        $qrisTransaction->markAsExpired();
                        $qrisTransaction->save();

                        $conversation->clearFlowState();
                        $conversation->clearPaymentContext();
                        $conversation->clearPendingOrder();
                        $conversation->clearCart();
                        $conversation->clearCatalogItems();
                        $conversation->clearDeliveryContext();

                        $orderContext = $conversation->order_context ?? [];
                        unset($orderContext['last_qris_transaction_id']);
                        $conversation->order_context = $orderContext;
                        $conversation->save();

                        $this->replySender->send(
                            $account,
                            $contact->wa_id,
                            "⏰ Pembayaran pesanan sebelumnya sudah kadaluarsa, jadi pesanan dibatalkan."
                        );

                        // State is now clean — continue handling the customer's
                        // current message normally (greeting/menu) instead of
                        // dead-ending on the expiry notice.
                        $this->processMessage($account, $contact, $messageText);

                        return;
                    }
                }

                $handled = $this->catalogOrderFlow->handleDeliveryInfoText(
                    $account,
                    $contact,
                    $conversation,
                    $aiAgent,
                    $messageText
                );

                if (! $handled) {
                    // Off-context message during an active flow -> contextual drip
                    // with "Lanjutkan" / "Batal" buttons (keeps flow consistent).
                    $this->catalogOrderFlow->sendDripPrompt($account, $contact, $aiAgent, $conversation);
                }

                return;
            }

            // Single classification for the rest of processMessage. Context lets
            // the enum disambiguate cases like "alamat" — order address vs.
            // question about the cafe's location — based on conversation state.
            $detected = UserIntent::detect($messageText, [
                'flow_state' => $conversation->getFlowState(),
                'has_cart' => ! empty($conversation->getCart()),
            ]);

            // Pre-LLM short-circuits (greeting, catalog menu, POS checkout
            // handoff, order-disabled guard). Returning true means the message
            // was fully handled without an LLM call.
            if ($this->intentRouter->tryShortCircuit($account, $contact, $conversation, $aiAgent, $messageText, $detected)) {
                return;
            }

            // Deterministic pagination — short-circuit the LLM when the customer
            // explicitly asks for the next page of products. Kept here (not in
            // IntentRouter) because handleNextMenuPage shares deep menu helpers
            // with the tool dispatcher.
            if ($aiAgent->isOrderEnabled() && $detected === UserIntent::NEXT_MENU_PAGE) {
                $this->catalogTools->handleNextMenuPage($conversation, $account, $contact, $aiAgent);

                return;
            }

            $conversation->addMessage('human', $messageText);

            $userIntent = $detected;
            $guardedReply = $this->conversationGuard->resolve($conversation, $aiAgent, $messageText, $userIntent);
            if ($guardedReply !== null) {
                Log::info('Conversation guard handled response without LLM', [
                    'conversation_id' => $conversation->id,
                    'intent' => $userIntent->value,
                ]);

                $conversation->addMessage('ai', $guardedReply);
                $this->replySender->send($account, $contact->wa_id, $guardedReply);

                return;
            }

            // Check if summarization is needed
            $shouldSummarize = $this->conversationSummarizer->shouldSummarize($conversation);

            // DISABLED: Intent change detection (too slow - adds extra LLM call)
            // Intent changes will be detected naturally during regular summarization
            // if (!$shouldSummarize && $conversation->hasSummary()) {
            //     try {
            //         $currentSummary = $conversation->getSummary();
            //         $currentIntent = $currentSummary['intent'] ?? null;
            //
            //         if ($currentIntent) {
            //             // Get the last few messages to detect intent change
            //             $recentMessages = $conversation->getRecentMessages(3);
            //
            //             // Generate a quick summary to check intent
            //             $quickSummary = $this->conversationSummarizer->generateSummary($recentMessages);
            //
            //             if ($quickSummary && isset($quickSummary['intent'])) {
            //                 $newIntent = $quickSummary['intent'];
            //
            //                 // Check if intent has changed
            //                 if ($this->intentTracker->hasIntentChanged($conversation, $newIntent)) {
            //                     Log::info('Intent change detected, triggering summarization', [
            //                         'conversation_id' => $conversation->id,
            //                         'old_intent' => $currentIntent,
            //                         'new_intent' => $newIntent,
            //                     ]);
            //
            //                     $shouldSummarize = true;
            //                 }
            //             }
            //         }
            //     } catch (\Exception $e) {
            //         Log::warning('Intent change detection failed', [
            //             'conversation_id' => $conversation->id,
            //             'error' => $e->getMessage(),
            //         ]);
            //         // Continue without intent-based summarization
            //     }
            // }

            if ($shouldSummarize) {
                try {
                    Log::info('Triggering conversation summarization', [
                        'conversation_id' => $conversation->id,
                        'message_count' => count($conversation->messages ?? []),
                    ]);

                    // Generate summary
                    $summary = $this->conversationSummarizer->generateSummary($conversation->messages ?? []);

                    if ($summary) {
                        // Store summary
                        $this->conversationSummarizer->storeSummary($conversation, $summary);

                        // Update intent tracker
                        if (isset($summary['intent'])) {
                            $this->intentTracker->updateIntent($conversation, $summary['intent']);
                        }

                        Log::info('Summary generated and stored successfully', [
                            'conversation_id' => $conversation->id,
                            'intent' => $summary['intent'] ?? 'unknown',
                        ]);
                    } else {
                        Log::warning('Summary generation returned null, continuing without summary', [
                            'conversation_id' => $conversation->id,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Summarization failed, continuing with full message history', [
                        'conversation_id' => $conversation->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue without summary - system will use full message history
                }
            }

            // Build system prompt with intent-based optimization
            $systemPrompt = $aiAgent->buildSystemPrompt($account->user_id, $messageText);

            // Get conversation context (summary + recent messages OR full messages)
            $messages = $this->conversationSummarizer->getContextForLLM($conversation);

            // Log context usage for monitoring
            $hasSummary = $conversation->hasSummary();
            Log::info('Using conversation context for LLM', [
                'conversation_id' => $conversation->id,
                'using_summary' => $hasSummary,
                'context_message_count' => count($messages),
                'total_message_count' => count($conversation->messages ?? []),
                'user_intent' => $userIntent->value,
            ]);

            // Conditional tool loading based on intent (saves ~500-800 tokens for simple messages)
            $tools = null;
            if ($this->toolDispatcher->intentNeedsTools($userIntent)) {
                $tools = $this->toolDispatcher->getToolDefinitionsForAgent($aiAgent);
            }

            $response = $this->callLLM($systemPrompt, $messages, $tools, $aiAgent);

            if (isset($response['tool_calls'])) {
                $this->toolDispatcher->handleToolCalls(
                    $response['tool_calls'],
                    $conversation,
                    $account,
                    $contact,
                    $aiAgent,
                );

                return;
            }

            $assistantMessage = $response['content'] ?? '';

            if (empty(trim($assistantMessage))) {
                Log::warning('LLM returned empty content in main response', [
                    'conversation_id' => $conversation->id,
                    'account_id' => $account->id,
                ]);
                $assistantMessage = $this->toolDispatcher->generateSimpleFallback($conversation);
            }

            $conversation->addMessage('ai', $assistantMessage);
            $this->replySender->send($account, $contact->wa_id, $assistantMessage);

        } catch (\Exception $e) {
            Log::error('AI Agent Error: '.$e->getMessage(), [
                'account_id' => $account->id,
                'contact_id' => $contact->id,
                'trace' => $e->getTraceAsString(),
            ]);

            // Send fallback message
            $this->replySender->send(
                $account,
                $contact->wa_id,
                'Maaf, sistem sedang sibuk. Mohon coba lagi.'
            );
        }
    }

    /**
     * Get or create conversation for AI agent and contact.
     */
    public function getOrCreateConversation(int $aiAgentId, int $contactId): AiAgentConversation
    {
        // Clean up expired conversations first
        AiAgentConversation::where('expires_at', '<', now())->delete();

        $conversation = AiAgentConversation::where('ai_agent_id', $aiAgentId)
            ->where('whatsapp_contact_id', $contactId)
            ->first();

        if (! $conversation || $conversation->isExpired()) {
            $conversation = AiAgentConversation::create([
                'ai_agent_id' => $aiAgentId,
                'whatsapp_contact_id' => $contactId,
                'messages' => [],
                'order_context' => [],
                'expires_at' => now()->addHours(24),
            ]);
        }

        return $conversation;
    }

    /**
     * Public entry point for callers that still depend on the old signature
     * (e.g. AiAgentController test endpoint). Internally delegates to
     * App\Services\AiAgent\LLM\LlmClient.
     */
    public function callLLM(string $systemPrompt, array $messages, ?array $tools = null, ?AiAgent $aiAgent = null): array
    {
        return $this->llmClient->call($systemPrompt, $messages, $tools, $aiAgent);
    }

    /**
     * Backwards-compatible shim for AiAgentController test endpoints. The
     * canonical implementation lives in IntentRouter.
     */
    public function getOrderDisabledMessage(AiAgent $aiAgent): string
    {
        return $this->intentRouter->orderDisabledMessage($aiAgent);
    }

    /**
     * Check if message is confirmation.
     */
    protected function isConfirmation(string $message): bool
    {
        $message = strtolower(trim($message));

        return in_array($message, ['ya', 'yes', 'iya', 'ok', 'oke', 'konfirmasi', 'setuju']);
    }

    /**
     * Check if message is rejection.
     */
    protected function isRejection(string $message): bool
    {
        $message = strtolower(trim($message));

        return in_array($message, ['tidak', 'no', 'batal', 'cancel', 'gak', 'nggak']);
    }

    /**
     * Send payment confirmation for POS manual orders (no AI conversation).
     * Sends directly to customer_phone stored on the linked order.
     */
    private function sendPosOrderPaymentConfirmation(QrisTransaction $qrisTransaction): void
    {
        try {
            $order = $qrisTransaction->linkedOrder;

            if (! $order || empty($order->customer_phone)) {
                Log::info('POS order has no customer_phone, skipping payment confirmation', [
                    'qris_transaction_id' => $qrisTransaction->id,
                    'linked_order_id' => $qrisTransaction->linked_order_id,
                ]);

                return;
            }

            // Get WhatsApp account for the store owner
            $storeUserId = $order->store->user_id;
            $account = $this->whatsappAccountService->getActiveAccount($storeUserId);

            if (! $account) {
                Log::warning('No active WhatsApp account for POS payment confirmation', [
                    'store_user_id' => $storeUserId,
                    'order_id' => $order->id,
                ]);

                return;
            }

            $phone = preg_replace('/[^0-9]/', '', $order->customer_phone);
            $formattedAmount = 'Rp '.number_format($qrisTransaction->amount, 0, ',', '.');

            $message = "✅ *Pembayaran Berhasil!*\n\n";
            $message .= "No. Pesanan: #{$order->order_number}\n";
            $message .= "Jumlah: {$formattedAmount}\n";

            if ($order->delivery_type === Order::DELIVERY_TYPE_DELIVERY && $order->alamat) {
                $message .= "\n🚚 Pesanan Anda sedang disiapkan dan akan segera diantar ke:\n";
                $message .= "📍 {$order->alamat}\n";
                $message .= "\nMohon siapkan diri untuk menerima pesanan. Terima kasih! 🙏";
            } else {
                $message .= "\nPesanan Anda sedang diproses. Terima kasih! 🙏";
            }

            $this->replySender->send($account, $phone, $message);

            Log::info('POS order payment confirmation sent via WhatsApp', [
                'order_id' => $order->id,
                'customer_phone' => $phone,
                'qris_transaction_id' => $qrisTransaction->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send POS order payment confirmation', [
                'error' => $e->getMessage(),
                'qris_transaction_id' => $qrisTransaction->id,
            ]);
        }
    }

    /**
     * Send payment confirmation to customer via WhatsApp.
     * Called when QRIS payment is completed via webhook.
     */
    public function sendPaymentConfirmation(QrisTransaction $qrisTransaction): void
    {
        try {
            // Find conversation linked to this QRIS transaction
            $conversation = AiAgentConversation::where('current_qris_transaction_id', $qrisTransaction->id)
                ->first();

            if (! $conversation) {
                Log::info('No AI conversation for QRIS transaction, falling back to POS order confirmation', [
                    'qris_transaction_id' => $qrisTransaction->id,
                    'linked_order_id' => $qrisTransaction->linked_order_id,
                ]);
                // Fallback: POS manual order — send directly to customer_phone on the linked order
                $this->sendPosOrderPaymentConfirmation($qrisTransaction);

                return;
            }

            // Get WhatsApp contact and account
            $contact = $conversation->whatsappContact()
                ->withoutGlobalScope('userContacts')
                ->first();
            $aiAgent = $conversation->aiAgent;

            if (! $contact || ! $aiAgent) {
                Log::warning('Missing contact or AI agent for payment confirmation', [
                    'conversation_id' => $conversation->id,
                    'has_contact' => $contact !== null,
                    'has_ai_agent' => $aiAgent !== null,
                ]);

                return;
            }

            $account = $aiAgent->whatsappAccount()
                ->withoutGlobalScope('userAccounts')
                ->first();
            if (! $account || ! $account->is_active) {
                Log::warning('WhatsApp account not available for payment confirmation', [
                    'ai_agent_id' => $aiAgent->id,
                ]);

                return;
            }

            // Format confirmation message
            $formattedAmount = 'Rp '.number_format($qrisTransaction->amount, 0, ',', '.');
            $order = $conversation->getCurrentOrder();

            $message = "✅ Pembayaran Berhasil!\n\n";
            $message .= "Jumlah: {$formattedAmount}\n";
            $message .= "No. Transaksi: {$qrisTransaction->order_id}\n";

            if ($order) {
                $message .= "No. Pesanan: {$order->order_number}\n";
            }

            $deliveryType = $conversation->order_context['delivery_type'] ?? null;
            if ($deliveryType === Order::DELIVERY_TYPE_DELIVERY) {
                $address = $conversation->order_context['delivery_address'] ?? null;
                $message .= "\n🚚 Pesanan Anda sedang disiapkan dan akan segera diantar";
                if ($address) {
                    $message .= " ke:\n📍 {$address}";
                }
                $message .= "\n\nMohon siapkan diri untuk menerima pesanan. Terima kasih! 🙏";
            } else {
                $message .= "\nTerima kasih atas pembayaran Anda! Pesanan sedang diproses. 🙏";
            }

            // Send WhatsApp message
            $this->replySender->send($account, $contact->wa_id, $message);

            // Add message to conversation history
            $conversation->addMessage('ai', $message);

            // Clear payment context + catalog flow state so the AWAITING_PAYMENT
            // drip stops once payment succeeds.
            $conversation->clearPaymentContext();
            $conversation->clearCart();
            $conversation->clearPendingOrder();
            $conversation->clearCatalogItems();
            $conversation->clearDeliveryContext();
            $conversation->clearFlowState();

            Log::info('Payment confirmation sent via WhatsApp', [
                'qris_transaction_id' => $qrisTransaction->id,
                'conversation_id' => $conversation->id,
                'contact_wa_id' => $contact->wa_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send payment confirmation', [
                'error' => $e->getMessage(),
                'qris_transaction_id' => $qrisTransaction->id,
            ]);
        }
    }
}
