# IntentRouter

## Tanggung jawab
Memutuskan apakah pesan masuk bisa dijawab tanpa LLM call. Kalau ya, render & kirim
balasan; kalau tidak, biarkan caller (AiAgentService::processMessage) lanjut ke LLM.

## Input → Output
- **Input**: WhatsAppAccount, WhatsAppContact, AiAgentConversation, AiAgent,
  string $messageText, UserIntent $detected.
- **Output**: `bool` — `true` = sudah ditangani (caller stop), `false` = lanjut ke LLM.

5 short-circuit branch (dievaluasi berurutan):
1. Catalog mode + intent menu/order/search → kirim Meta Catalog MPM
2. Greeting (semua mode) → render "Lihat Menu" button
3. Catalog mode + UNKNOWN/OFF_TOPIC → fallback dengan menu button
4. POS mode + cart not empty + checkout signal → handoff ke state machine
5. Order disabled + intent order-related → reply "fitur off" + reservasi link kalau ada

## Dependencies
- `CatalogOrderFlowService` — `sendCatalog`, `sendGreetingWithMenuButton`,
  `sendFallbackWithMenuButton`, `startPosOrderFlow` (semua via delegator façade).
- `ReplySender` — untuk order-disabled reply.

## State
Read-only. Method `tryShortCircuit` tidak menulis ke DB selain `conversation->addMessage`
untuk track yang dijawab. Tidak menyentuh state machine.

## Edge cases
- **NEXT_MENU_PAGE TIDAK ditangani di sini** — dipisah ke AiAgentService karena
  butuh `CatalogTools::handleNextMenuPage` yang punya logic pagination yang dalam.
- Greeting menang atas intent lain (cek pertama). Jadi "halo mau pesan" tetap
  diklasifikasikan sebagai GREETING di `UserIntent::detect` (karena ada keyword
  "pesan"). Cek di-skip → fallthrough ke ORDER.
- `buildGreeting` & `orderDisabledMessage` di-publish karena AiAgentService
  menyimpan facade method untuk backward-compat dengan AiAgentController test endpoint.
