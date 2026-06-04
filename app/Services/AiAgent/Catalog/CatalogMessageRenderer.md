# CatalogMessageRenderer

## Tanggung jawab
Semua "kirim WhatsApp message" rendering untuk state machine catalog/order flow.
Orchestrator (CatalogOrderFlowService) memutuskan APA yang dikirim berdasarkan
state transition; class ini memutuskan BAGAIMANA setiap pesan dirender dan dipush
via WhatsApp Cloud API.

## Input → Output
14 method renderer. Tiap method:
- Input: `WhatsAppAccount`, `WhatsAppContact`, dependencies state (e.g. conversation,
  items, parsed delivery info, AiAgent untuk feature flags)
- Output: `void` (atau `bool` untuk reminder yang bisa skip kalau no QRIS)
- Side effect: WhatsApp API call via Netflie SDK

Berdasarkan state machine state:
- `sendCartConfirmation` — konfirmasi cart (Konfirmasi / Batal)
- `sendFulfillmentButtons` — pilih Pickup / Delivery / Reservasi (sesuai config)
- `sendDeliveryInfoPrompt` — minta info delivery free-text
- `sendDeliveryInfoSummary` — render parsed info + Edit / Konfirmasi
- `sendOrderSummary` — final review + Konfirmasi / Batal
- `sendDripPrompt` — nudge customer mid-flow (dipakai untuk off-context text)
- `resendPaymentLink` — re-show QRIS link (untuk drip "Lanjutkan" di payment state)
- `sendPaymentReminder` — compact "sisa N menit" untuk follow-up scheduler
- `sendStateReminder` (via CatalogOrderFlowService delegator) — dispatch per state

Standalone:
- `sendFallbackWithMenuButton`, `sendCancelledReply`, `sendGreetingWithMenuButton`
- `sendReservationHandoff` — kirim reservation URL

## Dependencies
- WhatsApp Cloud API (Netflie SDK)
- `CatalogOrderFlowService` constants (BTN_*, STATE_*)
- `OrderService::DEFAULT_TAX_RATE` (untuk render order summary)

## State
Mostly stateless render. Kecuali `resendPaymentLink` yang clear `conversation->order_context.last_qris_transaction_id` saat QRIS expired (defensive cleanup).

## Edge cases
- **Fallback ke plain text di setiap interactive sender**. Kalau Meta API gagal
  (account permission, network), customer tetap dapat reply via `sendText`.
- **Truncate body ke 1020 chars** (WhatsApp limit 1024 untuk interactive).
- **`sendFulfillmentButtons` cap 3 buttons**. Pickup selalu ada; Delivery & Reservasi
  bergantung config. Total max 3 mengikuti WhatsApp limit.
- **`sendDeliveryInfoSummary`** punya dua mode: kalau parser tidak menemukan field
  apapun → tampilkan raw + minta re-send. Kalau setidaknya satu field ditemukan →
  tampilkan struktur dengan flag untuk yang missing.
- **`sendPaymentReminder` vs `resendPaymentLink`**: keduanya re-show QRIS link.
  Bedanya:
  - `sendPaymentReminder` = compact (untuk follow-up scheduler periodic)
  - `resendPaymentLink` = full context (untuk drip "Lanjutkan" button)
