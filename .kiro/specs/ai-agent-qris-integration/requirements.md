# Requirements Document

## Introduction

Fitur ini mengintegrasikan AI Agent WhatsApp dengan sistem Order POS dan QRIS yang sudah ada. Saat ini terdapat dua masalah utama:

1. **Order tidak masuk ke sistem POS** - Meskipun `OrderService::create()` dipanggil, order dari AI Agent tidak muncul di menu Orders/Payments/Transactions karena tidak ada Payment record yang dibuat dan tidak ada link ke user_id
2. **QRIS tidak ter-generate dengan benar** - AI Agent membuat URL palsu seperti `https://qrcode.qashierwise.id/gopay/...` alih-alih memanggil `QrisService` yang sudah tersedia

Integrasi ini akan:
- Memastikan order dari AI Agent tercatat dengan benar di sistem POS
- Membuat Payment record yang terhubung dengan Order
- Generate QRIS yang valid melalui payment provider yang dikonfigurasi (Midtrans, Xendit, Doku, Duitku)
- Menghubungkan QrisTransaction dengan Order untuk tracking pembayaran

## Glossary

- **AI_Agent**: Sistem chatbot WhatsApp yang memproses pesan pelanggan dan dapat melakukan pemesanan otomatis
- **QRIS**: Quick Response Code Indonesian Standard - standar QR code untuk pembayaran di Indonesia
- **QrisService**: Service yang sudah ada untuk generate QRIS melalui berbagai payment provider
- **SubMerchant**: Entitas merchant yang terdaftar dalam sistem untuk menerima pembayaran QRIS
- **Payment_Provider**: Penyedia layanan pembayaran (Midtrans, Xendit, Doku, Duitku)
- **LLM_Tool**: Function yang dapat dipanggil oleh LLM untuk melakukan aksi tertentu
- **Conversation_Context**: Data konteks percakapan termasuk keranjang belanja dan pending order
- **Order**: Entitas pesanan di sistem POS yang berisi items, total, dan status
- **Payment**: Entitas pembayaran yang terhubung dengan Order
- **OrderService**: Service yang sudah ada untuk membuat dan mengelola Order

## Requirements

### Requirement 1: Order dari AI Agent Tercatat di Sistem POS

**User Story:** As a business owner, I want orders from AI Agent to appear in my POS dashboard (Orders/Payments/Transactions), so that I can track all sales from WhatsApp.

#### Acceptance Criteria

1. WHEN AI Agent creates an order, THE Order SHALL be associated with the user's Store via default_store_id
2. WHEN AI Agent creates an order, THE Order SHALL have a source field indicating 'whatsapp_ai' origin
3. WHEN AI Agent creates an order, THE Order SHALL include customer information from WhatsApp contact
4. WHEN viewing Orders in dashboard, THE System SHALL display orders from AI Agent alongside POS orders
5. WHEN filtering orders, THE System SHALL allow filtering by source (POS vs WhatsApp AI)

### Requirement 2: Payment Record Creation dengan QRIS

**User Story:** As a business owner, I want a Payment record created when AI Agent generates QRIS, so that I can track payment status in my dashboard.

#### Acceptance Criteria

1. WHEN AI Agent generates QRIS for an order, THE System SHALL create a Payment record linked to the Order
2. WHEN Payment record is created, THE Payment SHALL have method 'qris' and status 'pending'
3. WHEN QRIS payment is completed (webhook), THE Payment status SHALL be updated to 'paid'
4. WHEN Payment is completed, THE Order status SHALL be updated to 'paid'
5. WHEN viewing Payments in dashboard, THE System SHALL display QRIS payments from AI Agent

### Requirement 3: Generate QRIS Tool untuk AI Agent

**User Story:** As a business owner, I want my AI Agent to generate real QRIS codes when customers want to pay, so that customers can complete payment directly through WhatsApp conversation.

#### Acceptance Criteria

1. WHEN AI Agent's qris_enabled is true AND a customer confirms an order, THE AI_Agent SHALL have access to a `generate_qris` LLM tool
2. WHEN the `generate_qris` tool is called, THE AI_Agent SHALL invoke QrisService to create a valid QRIS transaction
3. WHEN QRIS generation succeeds, THE AI_Agent SHALL return the QR code URL and payment details to the customer
4. IF QRIS generation fails due to no active provider, THEN THE AI_Agent SHALL inform the customer that payment is temporarily unavailable
5. IF QRIS generation fails due to provider error, THEN THE AI_Agent SHALL log the error and provide a fallback message

### Requirement 4: Link QrisTransaction dengan Order

**User Story:** As a business owner, I want QRIS transactions linked to orders, so that I can see which payment belongs to which order.

#### Acceptance Criteria

1. THE QrisTransaction model SHALL have an optional order_id foreign key
2. WHEN AI Agent generates QRIS for an order, THE QrisTransaction SHALL be linked to that Order
3. WHEN viewing transaction details, THE System SHALL show the associated Order information
4. WHEN QRIS payment is completed, THE System SHALL automatically update the linked Order status

### Requirement 5: SubMerchant Association untuk AI Agent

**User Story:** As a business owner, I want my AI Agent to use my configured SubMerchant for QRIS generation, so that payments are correctly routed to my account.

#### Acceptance Criteria

1. THE AI_Agent model SHALL have access to SubMerchant through the user's account
2. WHEN generating QRIS, THE AI_Agent SHALL use the user's active SubMerchant
3. IF no SubMerchant is configured for the user, THEN THE AI_Agent SHALL disable QRIS functionality and inform the user
4. WHEN a user enables qris_enabled on AI Agent, THE System SHALL validate that a SubMerchant exists and is active

### Requirement 6: QRIS Payment Flow dalam Conversation

**User Story:** As a customer, I want to receive a valid QRIS code in my WhatsApp chat after confirming an order, so that I can pay immediately.

#### Acceptance Criteria

1. WHEN a customer confirms an order AND qris_enabled is true, THE AI_Agent SHALL automatically generate QRIS for the order total
2. WHEN QRIS is generated, THE AI_Agent SHALL send a message containing the QR code image URL, amount, and expiry time
3. WHEN QRIS is generated, THE AI_Agent SHALL store the order_id and qris_transaction_id in the conversation context
4. WHILE a QRIS payment is pending, THE AI_Agent SHALL be able to check payment status when customer asks

### Requirement 7: Payment Status Checking

**User Story:** As a customer, I want to ask the AI Agent about my payment status, so that I can know if my payment was successful.

#### Acceptance Criteria

1. THE AI_Agent SHALL have access to a `check_payment_status` LLM tool
2. WHEN the `check_payment_status` tool is called, THE AI_Agent SHALL query the QrisTransaction and Payment status
3. WHEN payment status is 'paid', THE AI_Agent SHALL confirm the payment and thank the customer
4. WHEN payment status is 'pending', THE AI_Agent SHALL inform the customer that payment is still being processed
5. WHEN payment status is 'expired', THE AI_Agent SHALL offer to generate a new QRIS code

### Requirement 8: QRIS Configuration Validation

**User Story:** As a business owner, I want the system to validate my QRIS configuration before enabling it on AI Agent, so that I don't enable a broken feature.

#### Acceptance Criteria

1. WHEN a user attempts to enable qris_enabled on AI Agent, THE System SHALL check for active payment provider credentials
2. WHEN a user attempts to enable qris_enabled on AI Agent, THE System SHALL check for active SubMerchant
3. IF validation fails, THEN THE System SHALL return a descriptive error message explaining what needs to be configured
4. WHEN all validations pass, THE System SHALL enable qris_enabled and confirm to the user

### Requirement 9: QRIS Message Formatting

**User Story:** As a customer, I want to receive a well-formatted payment message with clear instructions, so that I can easily complete the payment.

#### Acceptance Criteria

1. WHEN sending QRIS to customer, THE AI_Agent SHALL format the message with order summary, total amount, QR code URL, and expiry time
2. WHEN sending QRIS to customer, THE AI_Agent SHALL include payment instructions in Bahasa Indonesia
3. THE AI_Agent SHALL format currency amounts using Indonesian Rupiah format (Rp X.XXX)
4. WHEN QRIS expires, THE AI_Agent SHALL clearly communicate the expiry and offer alternatives

### Requirement 10: Webhook Integration untuk Payment Completion

**User Story:** As a business owner, I want payments from AI Agent QRIS to be automatically confirmed when customer pays, so that orders are processed without manual intervention.

#### Acceptance Criteria

1. WHEN payment provider sends webhook for successful payment, THE System SHALL update QrisTransaction status to 'paid'
2. WHEN QrisTransaction is marked as paid, THE System SHALL update linked Payment record to 'paid'
3. WHEN Payment is marked as paid, THE System SHALL update linked Order status to 'paid'
4. WHEN payment is confirmed, THE System SHALL optionally notify the AI Agent to send confirmation to customer


### Requirement 11: Test Endpoint QRIS Integration

**User Story:** As a business owner, I want to test QRIS generation through the AI Agent test interface in dashboard, so that I can verify the integration works before going live.

#### Acceptance Criteria

1. WHEN testing AI Agent with qris_enabled via test endpoint, THE Test_Endpoint SHALL have access to `generate_qris` tool
2. WHEN testing AI Agent with qris_enabled via test endpoint, THE Test_Endpoint SHALL have access to `check_payment_status` tool
3. WHEN QRIS is generated via test endpoint, THE System SHALL create a real QrisTransaction record
4. WHEN testing payment flow, THE Test_Endpoint SHALL return the same response format as production WhatsApp flow
5. THE Test_Endpoint SHALL use the same QrisService and OrderService as production to ensure consistency
6. WHEN test generates QRIS, THE Test_Endpoint SHALL display the QR code URL in the test response for verification
