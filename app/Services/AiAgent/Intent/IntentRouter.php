<?php

namespace App\Services\AiAgent\Intent;

use App\Enums\UserIntent;
use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Services\AiAgent\Reply\ReplySender;
use App\Services\CatalogOrderFlowService;

/**
 * Decides whether a customer message can be answered without calling the LLM.
 *
 * Five short-circuit branches live here:
 *   1. Catalog mode menu/order/search → Meta Catalog MPM
 *   2. Greeting (any mode)            → "Lihat Menu" button + canned hello
 *   3. Catalog mode unknown/off-topic → fallback with menu button
 *   4. POS checkout signal            → hand off to state machine
 *   5. Order disabled hard guard      → static "feature off" reply
 *
 * Each branch costs us nothing in LLM tokens, and behaves identically across
 * deployments — so the LLM call (and its variance) is reserved for messages
 * that actually need natural-language understanding.
 *
 * NOT in scope:
 *   - NEXT_MENU_PAGE pagination (kept in AiAgentService because it shares the
 *     deep menu-rendering helpers)
 *   - flow_state takeover (handled upstream in AiAgentService::processMessage)
 */
class IntentRouter
{
    public function __construct(
        protected CatalogOrderFlowService $catalogOrderFlow,
        protected ReplySender $replySender,
    ) {}

    /**
     * Try to handle the message without an LLM call. Returns true when handled
     * (caller should return immediately), false to continue to the LLM path.
     */
    public function tryShortCircuit(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        string $messageText,
        UserIntent $detected,
    ): bool {
        if ($aiAgent->isOrderEnabled()) {
            if ($this->tryCatalogModeMenu($account, $contact, $conversation, $aiAgent, $messageText, $detected)) {
                return true;
            }
            if ($this->tryGreeting($account, $contact, $conversation, $aiAgent, $messageText, $detected)) {
                return true;
            }
            if ($this->tryCatalogModeFallback($account, $contact, $conversation, $aiAgent, $messageText, $detected)) {
                return true;
            }
        }

        if ($this->tryPosCheckoutHandoff($account, $contact, $conversation, $aiAgent, $messageText, $detected)) {
            return true;
        }

        if ($this->tryOrderDisabledGuard($account, $contact, $conversation, $aiAgent, $detected)) {
            return true;
        }

        return false;
    }

    /**
     * In catalog mode, any menu/order/search/next-page intent jumps straight
     * to the Meta Catalog UI. The LLM never sees the message.
     */
    protected function tryCatalogModeMenu(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        string $messageText,
        UserIntent $detected,
    ): bool {
        if (! $aiAgent->isCatalogActive()) {
            return false;
        }
        if (! in_array($detected, [UserIntent::VIEW_MENU, UserIntent::ORDER, UserIntent::NEXT_MENU_PAGE, UserIntent::SEARCH_PRODUCT], true)) {
            return false;
        }

        $conversation->addMessage('human', $messageText);
        $conversation->addMessage('ai', 'Mengirim katalog produk…');
        $this->catalogOrderFlow->sendCatalog($account, $contact, $aiAgent);

        return true;
    }

    /**
     * Greetings get a canned "hello + Lihat Menu" reply in both modes. Saves
     * tokens on the lowest-information message in the funnel.
     */
    protected function tryGreeting(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        string $messageText,
        UserIntent $detected,
    ): bool {
        if ($detected !== UserIntent::GREETING) {
            return false;
        }

        $conversation->addMessage('human', $messageText);
        $greeting = $this->buildGreeting($aiAgent);
        $this->catalogOrderFlow->sendGreetingWithMenuButton($account, $contact, $aiAgent, $greeting);
        $conversation->addMessage('ai', $greeting);

        return true;
    }

    /**
     * Catalog-mode-only: unknown/off-topic gets a "I only help with orders"
     * reply. POS mode keeps these going to the LLM because typed product
     * names without keywords classify as UNKNOWN (e.g. "ayam bakar madu 2").
     */
    protected function tryCatalogModeFallback(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        string $messageText,
        UserIntent $detected,
    ): bool {
        if (! $aiAgent->isCatalogActive()) {
            return false;
        }
        if (! in_array($detected, [UserIntent::UNKNOWN, UserIntent::OFF_TOPIC], true)) {
            return false;
        }

        $conversation->addMessage('human', $messageText);
        $reply = $detected === UserIntent::OFF_TOPIC
            ? 'Maaf, saya hanya melayani pemesanan. Tap *Lihat Menu* untuk mulai memesan.'
            : 'Maaf, saya kurang paham pesan Anda. Tap *Lihat Menu* untuk melihat daftar menu, atau sebutkan nama menu yang ingin dipesan.';
        $this->catalogOrderFlow->sendFallbackWithMenuButton($account, $contact, $aiAgent, $reply);
        $conversation->addMessage('ai', $reply);

        return true;
    }

    /**
     * POS mode: once the customer has items in their LLM cart and signals
     * checkout, hand control to the same state machine the catalog uses for
     * fulfillment → delivery → payment.
     */
    protected function tryPosCheckoutHandoff(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        string $messageText,
        UserIntent $detected,
    ): bool {
        if (! $aiAgent->isOrderEnabled() || $aiAgent->isCatalogActive()) {
            return false;
        }
        if (empty($conversation->getCart())) {
            return false;
        }

        $wantsCheckout = $detected === UserIntent::CHECKOUT
            || preg_match('/^\s*(selesai|sudah|udah|cukup|itu saja|itu aja|lanjut(kan)?)\s*$/iu', $messageText);

        if (! $wantsCheckout) {
            return false;
        }

        $conversation->addMessage('human', $messageText);

        return (bool) $this->catalogOrderFlow->startPosOrderFlow($account, $contact, $conversation, $aiAgent);
    }

    /**
     * Hard guard for merchants who disabled ordering: any menu/order-related
     * intent gets a static reply (with reservation link if that feature is on).
     * Saves an LLM call AND keeps the wording consistent.
     */
    protected function tryOrderDisabledGuard(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        UserIntent $detected,
    ): bool {
        if ($aiAgent->isOrderEnabled() || ! $detected->isOrderOrMenuRelated()) {
            return false;
        }

        \Illuminate\Support\Facades\Log::info('AI agent blocked before LLM (order disabled)', [
            'agent_id' => $aiAgent->id,
            'contact_id' => $contact->id,
            'intent' => $detected->value,
            'reservation_enabled' => $aiAgent->isReservationEnabled(),
        ]);

        $message = $this->orderDisabledMessage($aiAgent);
        $conversation->addMessage('human', '');
        $conversation->addMessage('ai', $message);
        $this->replySender->send($account, $contact->wa_id, $message);

        return true;
    }

    /**
     * Static reply when ordering is disabled, with optional reservation hint.
     * Moved here from AiAgentService so the guard branch is self-contained;
     * AiAgentService keeps a thin facade method that delegates for backwards
     * compatibility with the test controller.
     */
    public function orderDisabledMessage(AiAgent $aiAgent): string
    {
        if ($aiAgent->isReservationEnabled()) {
            $reservationUrl = $aiAgent->getReservationFormUrl();
            if ($reservationUrl) {
                return "Maaf, fitur order/menu via chat sedang nonaktif. Untuk reservasi, silakan isi form: {$reservationUrl}";
            }

            return 'Maaf, fitur order/menu via chat sedang nonaktif. Namun reservasi masih tersedia.';
        }

        return 'Maaf, fitur order/menu via chat sedang nonaktif saat ini.';
    }

    /**
     * Build the canned greeting. Merchant override (settings.greeting_message)
     * wins; otherwise a sensible default with business name + bot name.
     */
    public function buildGreeting(AiAgent $aiAgent): string
    {
        $custom = $aiAgent->settings['greeting_message'] ?? null;
        if (is_string($custom) && trim($custom) !== '') {
            return $custom;
        }

        $business = $this->businessName($aiAgent);
        $botName = $aiAgent->bot_name ?: 'asisten kami';

        return "Halo! Selamat datang di *{$business}*. 👋\n\n"
            ."Saya {$botName}, asisten pemesanan Anda. "
            .'Tap *Lihat Menu* untuk mulai memesan, atau kirim pesan jika ada yang ingin ditanyakan.';
    }

    /**
     * Resolve the merchant's business name. Priority:
     *   1. explicit business_info.name
     *   2. default store name
     *   3. generic fallback
     * Never uses bot_name (that's the assistant, not the business).
     */
    protected function businessName(AiAgent $aiAgent): string
    {
        $info = $aiAgent->business_info ?? [];
        if (! empty($info['name']) && is_string($info['name'])) {
            return trim($info['name']);
        }

        $storeName = optional($aiAgent->defaultStore)->name;
        if (! empty($storeName)) {
            return trim($storeName);
        }

        return 'toko kami';
    }
}
