# ToolDispatcher

## Tanggung jawab
Owns the tool layer: definisi tool yang dikirim ke LLM, dispatch dari nama tool ke
handler class, dan loop kecil untuk search → re-prompt LLM dengan tool results.

## Input → Output
Tiga entry points:
- `intentNeedsTools(UserIntent): bool` — apakah LLM call butuh tool definitions.
- `getToolDefinitionsForAgent(AiAgent): ?array` — assembled per agent (base + QRIS).
- `handleToolCalls(toolCalls, conversation, account, contact, aiAgent, iteration=1)`
  — eksekusi tool calls, optional re-call LLM dengan results, kirim reply ke customer.

Per-tool dispatch via `executeToolCall(name, args, userId, conversation, aiAgent)`
yang return string hasil tool. Dispatched ke:

| Tool name | Handler class & method |
|---|---|
| get_all_products | CatalogTools::getAllProducts |
| search_products | CatalogTools::searchProducts |
| search_multiple_products | CatalogTools::searchMultipleProducts |
| get_product_details | CatalogTools::getProductDetails |
| add_to_cart | CartTools::addByName / addMultiById / addById (overload) |
| get_cart_summary | CartTools::summary |
| remove_from_cart | CartTools::remove |
| clear_cart | CartTools::clear |
| confirm_order | CheckoutTools::confirmOrder |
| set_order_notes | CheckoutTools::setOrderNotes |
| generate_qris | PaymentTools::generateQris |
| check_payment_status | PaymentTools::checkPaymentStatus |

## Dependencies
- 4 tool group: CatalogTools, CartTools, CheckoutTools, PaymentTools
- ReplySender (kirim balasan akhir)
- LlmClient (re-call LLM untuk search → action loop)

## State
Tidak menulis langsung. Tool handler menulis (cart, order, qris, conversation).
Side effect dispatcher: append assistant message ke conversation history.

## Edge cases
- **Iteration cap = 3**. Search → add loop > 3 → kirim "saya kesulitan" + stop.
- **detectSearchLoop**: kalau 2+ pesan assistant terakhir mengandung "HASIL PENCARIAN"
  atau "produk yang saya temukan", abort instead of looping again.
- **Truncate tool result to 1500 chars** saat re-call LLM (token economy).
- **Empty content fallback**: kalau LLM return content kosong setelah tool call,
  `generateContextualFallback` mencoba bangun reply berdasarkan tool names &
  cart state. `generateSimpleFallback` untuk kasus tanpa tool calls.
- **search_products & search_multiple_products** hasilnya internal sentinel
  (`FOUND:...`, `NOT_FOUND...`) — customer tidak boleh melihat ini langsung. Kalau
  hanya tool ini yang dipanggil, LLM wajib di-call lagi untuk format.
