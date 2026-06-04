# AI Agent — Service Map

Peta tingkat tinggi service AI Agent. Setiap sub-folder punya tanggung jawab
domain tunggal. Untuk detail satu class, lihat `<ClassName>.md` di sub-folder
yang sama (kalau ada). Detail konfigurasi merchant ada di `../ai-agent/config.md`,
detail alur state-machine ada di `../ai-agent/flow.md`.

---

## Alur Utama (per pesan masuk)

```
Meta WhatsApp Cloud API
        │  POST /api/whatsapp/webhook
        ▼
WhatsAppWebhookController
        │  type=text   ──► dispatch ProcessAiAgentMessage (queue: ai-agent)
        │  type=order  ──► CatalogOrderFlowService::handleCatalogOrderReceived
        │  interactive ──► CatalogOrderFlowService::handleButtonReply
        ▼
ProcessAiAgentMessage::handle
        │
        ▼
AiAgentService::processMessage   ─── orchestrator, tinggal 596 LOC
        │
        ├─ flow_state takeover    (CatalogOrderFlowService state machine)
        │
        ├─ IntentRouter::tryShortCircuit
        │     │  greeting / catalog menu / catalog fallback /
        │     │  POS checkout handoff / order-disabled guard
        │     └─ delegates render to CatalogMessageRenderer
        │
        ├─ NEXT_MENU_PAGE deterministic
        │     └─ CatalogTools::handleNextMenuPage
        │
        ├─ LLM call (LlmClient::call)
        │     │  tool definitions from ToolDispatcher
        │     │
        │     └─ ToolDispatcher::handleToolCalls
        │            │  CatalogTools  — menu, search, details
        │            │  CartTools     — add (via ProductResolver), remove, summary
        │            │  CheckoutTools — confirm_order, set_notes
        │            │  PaymentTools  — generate_qris, check_status
        │            │
        │            └─ ReplySender::send
        │
        └─ Otherwise: ToolDispatcher::generateSimpleFallback
                      └─ ReplySender::send
```

## Latar Belakang (state machine)

Setelah customer mengkonfirmasi cart (catalog atau POS), kontrol pindah ke
state machine di `CatalogOrderFlowService`:

```
CONFIRMING_CART
      │  klik Konfirmasi
      ▼
AWAITING_FULFILLMENT
      │  klik Pickup/Delivery/Reservasi
      ├──► Pickup    ──► CONFIRMING_ORDER_SUMMARY ──► OrderCreator
      ├──► Delivery  ──► AWAITING_DELIVERY_INFO
      │                       │  customer kirim teks
      │                       │  └─ DeliveryInfoParser (parseFree / extractLabeled)
      │                       ▼
      │                  CONFIRMING_DELIVERY_INFO
      │                       │  klik Konfirmasi
      │                       ▼
      │                  CONFIRMING_ORDER_SUMMARY ──► OrderCreator ──► QRIS
      └──► Reservasi ──► render handoff URL, exit state
```

`OrderCreator` membuat row `Order` + items, lalu optional QRIS via `PaymentTools`,
lalu set state ke `AWAITING_PAYMENT` (delivery) atau clear state (pickup).

## Sub-folder

| Folder | Class | Tanggung jawab |
|---|---|---|
| `Reply/` | ReplySender | Kirim WhatsApp text message |
| `LLM/` | LlmClient | Call BytePlus ARK + retry + analytics |
| `Intent/` | IntentRouter | Short-circuit pre-LLM (5 branch) |
| `Tools/` | ToolDispatcher | LLM tool layer + fallback generators |
| `Tools/` | CatalogTools, CartTools | Tool handlers per domain |
| `Tools/` | ProductResolver | Fuzzy match nama produk → matched/ambiguous/not_found |
| `Checkout/` | CheckoutTools | Tool `confirm_order` + `set_order_notes` |
| `Payment/` | PaymentTools | Tool `generate_qris` + `check_payment_status` |
| `Catalog/` | CatalogMessageRenderer | Semua sendX render method untuk state machine |
| `Catalog/` | OrderCreator | Catalog flow → Order DB row + post-create reply |
| `Catalog/` | DeliveryInfoParser | Parse free-text delivery info |
| `Followup/` | FollowupScheduler | Schedule SendFollowupJob dengan cycle-id idempotency |
| `Drip/` | DripScheduler | Schedule rows di ai_agent_drip_schedules |
| `Drip/` | DripContentRenderer | Render konten Sequence A drip message |

## Aturan Update

- Tambah class baru di sub-folder yang sesuai domainnya (aturan 1)
- Setiap class harus bisa dijelaskan tanggung jawabnya dalam 1 kalimat (aturan 2)
- Update peta di atas jika ada perubahan **routing utama** (rule of thumb: jika
  ada arrow baru di diagram, file ini wajib di-update)
- `<ClassName>.md` companion hanya untuk class yang alur-nya **tidak obvious**
  dari nama class & method (aturan 4)
