# Requirements: Migrasi Subscription dari Polar ke Midtrans

## 1. Overview

Mengganti sistem subscription dari Polar.sh ke Midtrans Subscription API untuk landing page dan payment flow. Sistem akan menggunakan Midtrans untuk recurring payment dengan dukungan installment dan recurring subscription.

**Feature Name**: midtrans-subscription-migration

**Dokumentasi Referensi**: https://docs.midtrans.com/reference/create-subscription

## 2. User Stories

### 2.1 Sebagai User
**Story**: Sebagai user yang ingin berlangganan, saya ingin melakukan pembayaran subscription melalui Midtrans sehingga saya dapat menggunakan berbagai metode pembayaran yang tersedia di Midtrans.

**Acceptance Criteria**:
- User dapat melihat pricing plans di landing page
- User dapat memilih plan (Standard atau Pro)
- User diarahkan ke Midtrans payment page untuk checkout
- User dapat melakukan pembayaran dengan berbagai metode (credit card, e-wallet, bank transfer, dll)
- Setelah pembayaran berhasil, subscription user aktif
- User menerima notifikasi pembayaran berhasil

### 2.2 Sebagai User dengan Subscription Aktif
**Story**: Sebagai user dengan subscription aktif, saya ingin subscription saya diperpanjang otomatis setiap bulan sehingga saya tidak perlu melakukan pembayaran manual.

**Acceptance Criteria**:
- Subscription diperpanjang otomatis setiap periode (bulanan)
- User menerima notifikasi sebelum perpanjangan
- User dapat melihat status subscription di dashboard
- User dapat membatalkan subscription kapan saja
- Setelah dibatalkan, subscription tetap aktif hingga akhir periode

### 2.3 Sebagai Admin/Developer
**Story**: Sebagai admin, saya ingin menerima webhook notification dari Midtrans sehingga sistem dapat update status subscription secara real-time.

**Acceptance Criteria**:
- Sistem menerima webhook dari Midtrans untuk payment notification
- Sistem menerima webhook untuk recurring notification
- Sistem menerima webhook untuk pay account notification
- Webhook signature tervalidasi dengan benar
- Status subscription terupdate otomatis berdasarkan webhook

## 3. Functional Requirements

### 3.1 Subscription Plan Configuration
- **FR-3.1.1**: Sistem harus mendukung minimal 2 plan: Standard dan Pro
- **FR-3.1.2**: Setiap plan memiliki konfigurasi:
  - Nama plan
  - Harga bulanan
  - Interval pembayaran (monthly)
  - Daftar fitur
  - Metadata tambahan
- **FR-3.1.3**: Plan configuration disimpan di config file dan database

### 3.2 Checkout Flow
- **FR-3.2.1**: User dapat memilih plan dari landing page
- **FR-3.2.2**: Sistem membuat subscription di Midtrans menggunakan Create Subscription API
- **FR-3.2.3**: User diarahkan ke Midtrans payment page
- **FR-3.2.4**: Setelah pembayaran berhasil, user diarahkan kembali ke success URL
- **FR-3.2.5**: Setelah pembayaran gagal/dibatalkan, user diarahkan ke cancel URL

### 3.3 Subscription Management
- **FR-3.3.1**: Sistem menyimpan subscription data di database:
  - Midtrans subscription ID
  - Midtrans customer ID
  - Plan name
  - Status (active, cancelled, expired)
  - Current period start & end
  - Cancelled at (jika dibatalkan)
- **FR-3.3.2**: User dapat melihat status subscription di dashboard
- **FR-3.3.3**: User dapat membatalkan subscription
- **FR-3.3.4**: User dapat mengakses customer portal untuk manage subscription

### 3.4 Webhook Integration
- **FR-3.4.1**: Sistem menerima webhook dari Midtrans di endpoint: `/api/webhooks/midtrans`
- **FR-3.4.2**: Webhook endpoints yang harus dikonfigurasi:
  - Payment Notification URL: untuk notifikasi pembayaran pertama
  - Recurring Notification URL: untuk notifikasi pembayaran recurring
  - Pay Account Notification URL: untuk notifikasi status pay account
- **FR-3.4.3**: Sistem memvalidasi webhook signature menggunakan SHA512
- **FR-3.4.4**: Sistem memproses webhook events:
  - `transaction.success`: Pembayaran berhasil
  - `transaction.pending`: Pembayaran pending
  - `transaction.failed`: Pembayaran gagal
  - `subscription.created`: Subscription dibuat
  - `subscription.updated`: Subscription diupdate
  - `subscription.cancelled`: Subscription dibatalkan
  - `subscription.expired`: Subscription expired

### 3.5 Backward Compatibility
- **FR-3.5.1**: Data subscription Polar yang sudah ada tetap dipertahankan
- **FR-3.5.2**: User dengan subscription Polar aktif tetap dapat menggunakan sistem
- **FR-3.5.3**: Sistem dapat membedakan subscription dari Polar vs Midtrans
- **FR-3.5.4**: Tidak ada migrasi otomatis dari Polar ke Midtrans (user baru menggunakan Midtrans)

### 3.6 Trial Period
- **FR-3.6.1**: User baru mendapat trial period 14 hari
- **FR-3.6.2**: Setelah trial expired, user harus subscribe untuk melanjutkan
- **FR-3.6.3**: Trial period tidak menggunakan Midtrans subscription

## 4. Non-Functional Requirements

### 4.1 Security
- **NFR-4.1.1**: Webhook signature harus divalidasi untuk setiap request
- **NFR-4.1.2**: Midtrans credentials (server key, client key) disimpan di environment variables
- **NFR-4.1.3**: Sensitive data tidak di-log

### 4.2 Performance
- **NFR-4.2.1**: Webhook processing harus < 5 detik
- **NFR-4.2.2**: Checkout session creation harus < 3 detik
- **NFR-4.2.3**: Webhook processing menggunakan queue untuk async processing

### 4.3 Reliability
- **NFR-4.3.1**: Webhook harus idempotent (dapat diproses multiple kali tanpa side effect)
- **NFR-4.3.2**: Failed webhook processing harus di-log untuk investigation
- **NFR-4.3.3**: Sistem harus handle Midtrans API downtime gracefully

### 4.4 Maintainability
- **NFR-4.4.1**: Code harus mengikuti Laravel best practices
- **NFR-4.4.2**: Service layer untuk Midtrans subscription logic
- **NFR-4.4.3**: Comprehensive logging untuk debugging

## 5. Technical Constraints

### 5.1 API Integration
- Menggunakan Midtrans Subscription API v1
- Base URL Production: `https://api.midtrans.com/v1`
- Base URL Sandbox: `https://api.sandbox.midtrans.com/v1`
- Authentication: Basic Auth dengan Server Key

### 5.2 Database Schema
- Menggunakan tabel `subscriptions` yang sudah ada
- Menambah kolom untuk Midtrans-specific data jika diperlukan
- Migration harus reversible

### 5.3 Environment Configuration
Required environment variables:
```
MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_SUBSCRIPTION_SUCCESS_URL=
MIDTRANS_SUBSCRIPTION_CANCEL_URL=
```

## 6. Dependencies

### 6.1 External Services
- Midtrans Subscription API
- Midtrans Payment Gateway

### 6.2 Internal Services
- Existing SubscriptionService
- Existing User model
- Existing Subscription model
- Queue system (Redis/Database)

## 7. Out of Scope

### 7.1 Tidak Termasuk dalam Scope
- Migrasi otomatis subscription Polar ke Midtrans
- Refund handling
- Proration untuk upgrade/downgrade plan
- Multiple payment methods selection (menggunakan default Midtrans)
- Invoice generation
- Email notification (hanya WhatsApp notification jika diperlukan)

## 8. Success Metrics

### 8.1 Functional Success
- ✅ User dapat subscribe menggunakan Midtrans
- ✅ Recurring payment berjalan otomatis
- ✅ Webhook processing 100% success rate
- ✅ Zero data loss pada subscription status

### 8.2 Technical Success
- ✅ All tests passing (unit + integration)
- ✅ Webhook signature validation 100% success
- ✅ API response time < 3 detik
- ✅ Zero production errors dalam 1 minggu pertama

## 9. Risks and Mitigations

### 9.1 Risk: Midtrans API Downtime
**Mitigation**: 
- Implement retry mechanism dengan exponential backoff
- Queue webhook processing
- Graceful error handling dengan user-friendly messages

### 9.2 Risk: Webhook Signature Validation Failure
**Mitigation**:
- Comprehensive testing dengan Midtrans sandbox
- Detailed logging untuk debugging
- Fallback mechanism untuk manual verification

### 9.3 Risk: Data Inconsistency
**Mitigation**:
- Database transactions untuk atomic updates
- Idempotent webhook processing
- Reconciliation job untuk verify data consistency

### 9.4 Risk: Backward Compatibility Issues
**Mitigation**:
- Maintain Polar integration code
- Feature flag untuk gradual rollout
- Comprehensive testing dengan existing Polar subscriptions

## 10. Questions and Assumptions

### 10.1 Assumptions
- Midtrans subscription API sudah tersedia dan stable
- User sudah memiliki Midtrans account
- Webhook endpoint dapat diakses dari Midtrans servers
- Existing database schema dapat accommodate Midtrans data

### 10.2 Open Questions
- ❓ Apakah perlu support untuk multiple currencies?
- ❓ Apakah perlu support untuk annual subscription?
- ❓ Bagaimana handling untuk failed recurring payment?
- ❓ Apakah perlu dunning management untuk failed payments?

## 11. Acceptance Criteria Summary

### 11.1 Must Have
- ✅ User dapat subscribe via Midtrans
- ✅ Recurring payment berjalan otomatis
- ✅ Webhook integration berfungsi
- ✅ Subscription status terupdate real-time
- ✅ Backward compatibility dengan Polar

### 11.2 Should Have
- ✅ Customer portal untuk manage subscription
- ✅ Comprehensive error handling
- ✅ Detailed logging

### 11.3 Nice to Have
- ⭕ Email notification untuk subscription events
- ⭕ Admin dashboard untuk monitor subscriptions
- ⭕ Analytics untuk subscription metrics
