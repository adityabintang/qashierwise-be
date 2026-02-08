# Midtrans Subscription Architecture

## Overview

This document provides visual architecture diagrams and explanations for the Midtrans subscription integration.

## System Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         User Interface                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐         │
│  │ Landing Page │  │   Pricing    │  │   Manage     │         │
│  │ (welcome)    │  │   Page       │  │ Subscription │         │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘         │
└─────────┼──────────────────┼──────────────────┼─────────────────┘
          │                  │                  │
          ▼                  ▼                  ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Application Layer                          │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │           SubscriptionController                         │  │
│  │  - index()           - success()                         │  │
│  │  - createCheckout()  - cancel()                          │  │
│  │  - manage()          - cancelSubscription()              │  │
│  └────────┬─────────────────────────────────────────────────┘  │
│           │                                                     │
│           ▼                                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │           SubscriptionService                            │  │
│  │  - createMidtransSubscription()                          │  │
│  │  - processMidtransWebhook()                              │  │
│  │  - updateFromMidtransWebhook()                           │  │
│  │  - getUserSubscriptionStatus()                           │  │
│  └────────┬─────────────────────────────────────────────────┘  │
│           │                                                     │
│           ▼                                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │       MidtransSubscriptionService                        │  │
│  │  - createSubscription()                                  │  │
│  │  - getSubscription()                                     │  │
│  │  - cancelSubscription()                                  │  │
│  │  - updateSubscription()                                  │  │
│  └────────┬─────────────────────────────────────────────────┘  │
└───────────┼─────────────────────────────────────────────────────┘
            │
            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    External Services                            │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │              Midtrans API                                │  │
│  │  - POST /v1/subscriptions                               │  │
│  │  - GET /v1/subscriptions/{id}                           │  │
│  │  - POST /v1/subscriptions/{id}/disable                  │  │
│  │  - POST /v1/subscriptions/{id}/enable                   │  │
│  └──────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
            │
            │ (Webhooks)
            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Webhook Handler                              │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │       MidtransWebhookController                          │  │
│  │  - handleSubscriptionWebhook()                           │  │
│  │  - validateSignature()                                   │  │
│  │  - processWebhook()                                      │  │
│  └──────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```


## Subscription Creation Flow

```
┌──────┐                                                    ┌──────────┐
│ User │                                                    │ Midtrans │
└───┬──┘                                                    └────┬─────┘
    │                                                            │
    │ 1. Click "Subscribe"                                      │
    ├──────────────────────────────────────────────────────────►│
    │                                                            │
    │ 2. Select Plan (Standard/Pro)                             │
    ├──────────────────────────────────────────────────────────►│
    │                                                            │
    │ 3. POST /subscription/checkout                            │
    │    {plan_id: "standard"}                                  │
    ├──────────────────────────────────────────────────────────►│
    │                                                            │
    │                    ┌─────────────────────┐                │
    │                    │ SubscriptionController│              │
    │                    │  createCheckout()    │               │
    │                    └──────────┬──────────┘                │
    │                               │                            │
    │                               ▼                            │
    │                    ┌─────────────────────┐                │
    │                    │ SubscriptionService │                │
    │                    │ createMidtrans...() │                │
    │                    └──────────┬──────────┘                │
    │                               │                            │
    │                               ▼                            │
    │                    ┌─────────────────────┐                │
    │                    │ MidtransSubscription│                │
    │                    │ Service             │                │
    │                    │ createSubscription()│                │
    │                    └──────────┬──────────┘                │
    │                               │                            │
    │                               │ 4. POST /v1/subscriptions │
    │                               ├───────────────────────────►│
    │                               │                            │
    │                               │ 5. Subscription Created   │
    │                               │◄───────────────────────────┤
    │                               │                            │
    │                    ┌──────────▼──────────┐                │
    │                    │ Save to Database    │                │
    │                    │ - midtrans_sub_id   │                │
    │                    │ - status: pending   │                │
    │                    └─────────────────────┘                │
    │                                                            │
    │ 6. Redirect to Midtrans Payment Page                      │
    │◄───────────────────────────────────────────────────────────┤
    │                                                            │
    │ 7. Complete Payment                                        │
    ├──────────────────────────────────────────────────────────►│
    │                                                            │
    │ 8. Redirect to Success URL                                │
    │◄───────────────────────────────────────────────────────────┤
    │                                                            │
    │                                                            │
    │                               ┌────────────────────────────┤
    │                               │ 9. Webhook: Payment Success│
    │                               ▼                            │
    │                    ┌─────────────────────┐                │
    │                    │ MidtransWebhook     │                │
    │                    │ Controller          │                │
    │                    │ handleWebhook()     │                │
    │                    └──────────┬──────────┘                │
    │                               │                            │
    │                               ▼                            │
    │                    ┌─────────────────────┐                │
    │                    │ Update Database     │                │
    │                    │ - status: active    │                │
    │                    │ - period_start      │                │
    │                    │ - period_end        │                │
    │                    └─────────────────────┘                │
    │                                                            │
    │ 10. Access Premium Features                               │
    ├──────────────────────────────────────────────────────────►│
    │                                                            │
```


## Webhook Processing Flow

```
┌──────────┐                                              ┌──────────┐
│ Midtrans │                                              │   App    │
└────┬─────┘                                              └────┬─────┘
     │                                                         │
     │ 1. Payment Event Occurs                                │
     │    (settlement/pending/failed)                         │
     │                                                         │
     │ 2. POST /api/webhooks/midtrans                         │
     │    {                                                    │
     │      transaction_id: "txn_123",                        │
     │      order_id: "sub_xxx_1",                            │
     │      transaction_status: "settlement",                 │
     │      signature_key: "abc123..."                        │
     │    }                                                    │
     ├────────────────────────────────────────────────────────►│
     │                                                         │
     │                              ┌──────────────────────────┤
     │                              │ MidtransWebhookController│
     │                              │ handleSubscriptionWebhook│
     │                              └──────────┬───────────────┘
     │                                         │
     │                                         ▼
     │                              ┌──────────────────────────┐
     │                              │ 3. Validate Signature    │
     │                              │    SHA512(order_id +     │
     │                              │    status_code +         │
     │                              │    gross_amount +        │
     │                              │    server_key)           │
     │                              └──────────┬───────────────┘
     │                                         │
     │                                         ▼
     │                              ┌──────────────────────────┐
     │                              │ 4. Check Idempotency     │
     │                              │    Cache::has(           │
     │                              │    "webhook_processed:   │
     │                              │     {transaction_id}")   │
     │                              └──────────┬───────────────┘
     │                                         │
     │                                         ▼
     │                              ┌──────────────────────────┐
     │                              │ 5. Route Event Type      │
     │                              │    - Payment Notification│
     │                              │    - Recurring Payment   │
     │                              │    - Account Status      │
     │                              └──────────┬───────────────┘
     │                                         │
     │                                         ▼
     │                              ┌──────────────────────────┐
     │                              │ 6. Process Event         │
     │                              │    SubscriptionService   │
     │                              │    processMidtrans...()  │
     │                              └──────────┬───────────────┘
     │                                         │
     │                                         ▼
     │                              ┌──────────────────────────┐
     │                              │ 7. Update Database       │
     │                              │    DB::transaction {     │
     │                              │      update status       │
     │                              │      update period       │
     │                              │    }                     │
     │                              └──────────┬───────────────┘
     │                                         │
     │                                         ▼
     │                              ┌──────────────────────────┐
     │                              │ 8. Mark as Processed     │
     │                              │    Cache::put(           │
     │                              │    "webhook_processed",  │
     │                              │    true, 24h)            │
     │                              └──────────┬───────────────┘
     │                                         │
     │ 9. Response: 200 OK                     │
     │◄────────────────────────────────────────┤
     │    {status: "ok"}                       │
     │                                         │
```


## Database Schema

```
┌─────────────────────────────────────────────────────────────────┐
│                        subscriptions                            │
├─────────────────────────────────────────────────────────────────┤
│ id                        BIGINT PRIMARY KEY                    │
│ user_id                   BIGINT FOREIGN KEY → users.id         │
│ polar_subscription_id     VARCHAR(255) NULLABLE                 │
│ polar_customer_id         VARCHAR(255) NULLABLE                 │
│ midtrans_subscription_id  VARCHAR(255) NULLABLE (NEW)           │
│ midtrans_customer_id      VARCHAR(255) NULLABLE (NEW)           │
│ provider                  VARCHAR(50) DEFAULT 'polar' (NEW)     │
│ plan_name                 VARCHAR(50)                           │
│ status                    VARCHAR(50)                           │
│ current_period_start      TIMESTAMP                             │
│ current_period_end        TIMESTAMP                             │
│ cancelled_at              TIMESTAMP NULLABLE                    │
│ metadata                  JSON NULLABLE (NEW)                   │
│ created_at                TIMESTAMP                             │
│ updated_at                TIMESTAMP                             │
├─────────────────────────────────────────────────────────────────┤
│ INDEXES:                                                        │
│   - idx_subscriptions_user_id (user_id)                        │
│   - idx_subscriptions_midtrans_id (midtrans_subscription_id)   │
│   - idx_subscriptions_provider (provider)                      │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ belongs to
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                           users                                 │
├─────────────────────────────────────────────────────────────────┤
│ id                        BIGINT PRIMARY KEY                    │
│ name                      VARCHAR(255)                          │
│ email                     VARCHAR(255) UNIQUE                   │
│ phone                     VARCHAR(20) NULLABLE                  │
│ created_at                TIMESTAMP                             │
│ updated_at                TIMESTAMP                             │
└─────────────────────────────────────────────────────────────────┘
```

### Metadata JSON Structure

```json
{
  "failure_count": 0,
  "last_failure_at": null,
  "last_payment_at": "2026-01-24T00:00:00Z",
  "payment_method": "credit_card",
  "card_last_four": "1114",
  "custom_fields": {}
}
```


## Component Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                      Frontend Layer                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐         │
│  │ welcome.blade│  │pricing.blade │  │manage.blade  │         │
│  │              │  │              │  │              │         │
│  │ - Hero       │  │ - Plan Cards │  │ - Status     │         │
│  │ - Features   │  │ - Subscribe  │  │ - Cancel     │         │
│  │ - Pricing    │  │   Buttons    │  │ - History    │         │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘         │
│         │                 │                  │                  │
└─────────┼─────────────────┼──────────────────┼──────────────────┘
          │                 │                  │
          └─────────────────┴──────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Controller Layer                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │         SubscriptionController                           │  │
│  │                                                          │  │
│  │  + index(): View                                         │  │
│  │  + createCheckout(Request): RedirectResponse            │  │
│  │  + success(Request): RedirectResponse                   │  │
│  │  + cancel(Request): RedirectResponse                    │  │
│  │  + error(Request): RedirectResponse                     │  │
│  │  + manage(): View                                        │  │
│  │  + cancelSubscription(Request): RedirectResponse        │  │
│  └────────────────────────┬─────────────────────────────────┘  │
│                           │                                     │
│  ┌────────────────────────▼─────────────────────────────────┐  │
│  │      MidtransWebhookController                           │  │
│  │                                                          │  │
│  │  + handleSubscriptionWebhook(Request): JsonResponse     │  │
│  │  - validateSignature(array): bool                       │  │
│  │  - processWebhook(array): void                          │  │
│  └──────────────────────────────────────────────────────────┘  │
│                           │                                     │
└───────────────────────────┼─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                     Service Layer                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │           SubscriptionService                            │  │
│  │                                                          │  │
│  │  + createMidtransSubscription(User, string, string)     │  │
│  │  + processMidtransWebhook(array): void                  │  │
│  │  + updateFromMidtransWebhook(string, array): void       │  │
│  │  + getUserSubscriptionStatus(User): array               │  │
│  │  + canAccessFeature(User, string): bool                 │  │
│  │  + cancelSubscription(Subscription): bool               │  │
│  │  - activateSubscription(Subscription): void             │  │
│  │  - handlePaymentStatus(Subscription, string): void      │  │
│  └────────────────────────┬─────────────────────────────────┘  │
│                           │                                     │
│  ┌────────────────────────▼─────────────────────────────────┐  │
│  │      MidtransSubscriptionService                         │  │
│  │                                                          │  │
│  │  + createSubscription(User, string, string): array      │  │
│  │  + getSubscription(string): ?array                      │  │
│  │  + cancelSubscription(string): bool                     │  │
│  │  + updateSubscription(string, array): array             │  │
│  │  + enableSubscription(string): bool                     │  │
│  │  - buildPayload(User, string, string): array            │  │
│  │  - handleApiError(Response, User, string): void         │  │
│  └──────────────────────────────────────────────────────────┘  │
│                           │                                     │
└───────────────────────────┼─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Model Layer                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │              Subscription Model                          │  │
│  │                                                          │  │
│  │  + user(): BelongsTo                                     │  │
│  │  + isActive(): bool                                      │  │
│  │  + isCancelled(): bool                                   │  │
│  │  + isPending(): bool                                     │  │
│  │  + daysRemaining(): int                                  │  │
│  │  + canAccess(string): bool                               │  │
│  └──────────────────────────────────────────────────────────┘  │
│                           │                                     │
│  ┌────────────────────────▼─────────────────────────────────┐  │
│  │                 User Model                               │  │
│  │                                                          │  │
│  │  + subscription(): HasOne                                │  │
│  │  + hasActiveSubscription(): bool                         │  │
│  │  + canAccessFeature(string): bool                        │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```


## Recurring Payment Flow

```
┌──────────┐                                              ┌──────────┐
│ Midtrans │                                              │   App    │
└────┬─────┘                                              └────┬─────┘
     │                                                         │
     │ Month 1: Initial Payment                                │
     │ ─────────────────────────────────────────────────────► │
     │                                                         │
     │ Webhook: Payment Success                                │
     │ ─────────────────────────────────────────────────────► │
     │                                                         │
     │                              ┌──────────────────────────┤
     │                              │ Status: active           │
     │                              │ Period: Jan 24 - Feb 24  │
     │                              └──────────────────────────┘
     │                                                         │
     │ ... 30 days pass ...                                    │
     │                                                         │
     │ Month 2: Automatic Charge                               │
     │ ─────────────────────────────────────────────────────► │
     │                                                         │
     │ Webhook: Recurring Payment Success                      │
     │ ─────────────────────────────────────────────────────► │
     │                                                         │
     │                              ┌──────────────────────────┤
     │                              │ Status: active           │
     │                              │ Period: Feb 24 - Mar 24  │
     │                              └──────────────────────────┘
     │                                                         │
     │ ... 30 days pass ...                                    │
     │                                                         │
     │ Month 3: Automatic Charge (Failed)                      │
     │ ─────────────────────────────────────────────────────► │
     │                                                         │
     │ Webhook: Payment Failed                                 │
     │ ─────────────────────────────────────────────────────► │
     │                                                         │
     │                              ┌──────────────────────────┤
     │                              │ Status: active (grace)   │
     │                              │ Failure count: 1         │
     │                              │ Grace period: 3 days     │
     │                              └──────────────────────────┘
     │                                                         │
     │ Retry 1 (Day 1)                                         │
     │ ─────────────────────────────────────────────────────► │
     │ Webhook: Payment Failed                                 │
     │ ─────────────────────────────────────────────────────► │
     │                                                         │
     │ Retry 2 (Day 2)                                         │
     │ ─────────────────────────────────────────────────────► │
     │ Webhook: Payment Failed                                 │
     │ ─────────────────────────────────────────────────────► │
     │                                                         │
     │ Retry 3 (Day 3)                                         │
     │ ─────────────────────────────────────────────────────► │
     │ Webhook: Payment Failed                                 │
     │ ─────────────────────────────────────────────────────► │
     │                                                         │
     │                              ┌──────────────────────────┤
     │                              │ Status: suspended        │
     │                              │ Failure count: 3         │
     │                              │ Access: blocked          │
     │                              └──────────────────────────┘
     │                                                         │
```


## Cancellation Flow

```
┌──────┐                                                    ┌──────────┐
│ User │                                                    │   App    │
└───┬──┘                                                    └────┬─────┘
    │                                                            │
    │ 1. Navigate to Manage Subscription                        │
    ├──────────────────────────────────────────────────────────►│
    │                                                            │
    │                              ┌─────────────────────────────┤
    │                              │ Show current subscription   │
    │                              │ - Plan: Pro                 │
    │                              │ - Status: Active            │
    │                              │ - Next billing: Feb 24      │
    │                              │ [Cancel Subscription]       │
    │                              └─────────────────────────────┘
    │                                                            │
    │ 2. Click "Cancel Subscription"                            │
    ├──────────────────────────────────────────────────────────►│
    │                                                            │
    │                              ┌─────────────────────────────┤
    │                              │ Show confirmation dialog    │
    │                              │ "Are you sure?"             │
    │                              │ [Yes] [No]                  │
    │                              └─────────────────────────────┘
    │                                                            │
    │ 3. Confirm Cancellation                                   │
    ├──────────────────────────────────────────────────────────►│
    │                                                            │
    │                              ┌─────────────────────────────┤
    │                              │ SubscriptionController      │
    │                              │ cancelSubscription()        │
    │                              └──────────┬──────────────────┘
    │                                         │
    │                                         ▼
    │                              ┌─────────────────────────────┐
    │                              │ SubscriptionService         │
    │                              │ cancelSubscription()        │
    │                              └──────────┬──────────────────┘
    │                                         │
    │                                         ▼
    │                              ┌─────────────────────────────┐
    │                              │ MidtransSubscriptionService │
    │                              │ cancelSubscription()        │
    │                              └──────────┬──────────────────┘
    │                                         │
    │                                         │ POST /disable
    │                                         ├──────────────────►│
    │                                         │                   │
    │                                         │◄──────────────────┤
    │                                         │                   │
    │                              ┌──────────▼──────────────────┐
    │                              │ Update Database             │
    │                              │ - status: cancelled         │
    │                              │ - cancelled_at: now()       │
    │                              │ - period_end: unchanged     │
    │                              └─────────────────────────────┘
    │                                                            │
    │ 4. Show Success Message                                   │
    │◄───────────────────────────────────────────────────────────┤
    │    "Subscription cancelled. Access until Feb 24"          │
    │                                                            │
    │ ... User continues to have access until period end ...    │
    │                                                            │
    │                              ┌─────────────────────────────┤
    │                              │ Feb 24: Period ends         │
    │                              │ - status: expired           │
    │                              │ - access: blocked           │
    │                              └─────────────────────────────┘
    │                                                            │
```


## State Diagram

```
                    ┌─────────────┐
                    │   New User  │
                    └──────┬──────┘
                           │
                           │ Sign Up
                           ▼
                    ┌─────────────┐
                    │    Trial    │◄──────────────┐
                    │  (14 days)  │               │
                    └──────┬──────┘               │
                           │                      │
                ┌──────────┴──────────┐           │
                │                     │           │
                │ Trial Expires       │ Subscribe │
                ▼                     ▼           │
         ┌─────────────┐       ┌─────────────┐   │
         │   Expired   │       │   Pending   │───┘
         └─────────────┘       └──────┬──────┘
                                      │
                           ┌──────────┴──────────┐
                           │                     │
                    Payment Success      Payment Failed
                           │                     │
                           ▼                     ▼
                    ┌─────────────┐       ┌─────────────┐
                    │   Active    │       │  Cancelled  │
                    └──────┬──────┘       └─────────────┘
                           │
                ┌──────────┼──────────┐
                │          │          │
         User Cancels  Period Ends  Payment Fails
                │          │          │
                ▼          ▼          ▼
         ┌─────────────┐ ┌─────────────┐ ┌─────────────┐
         │  Cancelled  │ │   Expired   │ │  Suspended  │
         │(until end)  │ │             │ │(grace period)│
         └─────────────┘ └─────────────┘ └──────┬──────┘
                                                 │
                                      ┌──────────┴──────────┐
                                      │                     │
                               Payment Success      Grace Expires
                                      │                     │
                                      ▼                     ▼
                               ┌─────────────┐       ┌─────────────┐
                               │   Active    │       │  Cancelled  │
                               └─────────────┘       └─────────────┘
```

### State Descriptions

- **Trial**: New user with 14-day free trial
- **Pending**: Subscription created, awaiting first payment
- **Active**: Subscription active with valid payment
- **Cancelled**: User cancelled, access until period end
- **Expired**: Subscription period ended, no access
- **Suspended**: Payment failed, in grace period (3 days)


## Deployment Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         Production                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                    Load Balancer                         │  │
│  │                   (HTTPS/SSL)                            │  │
│  └────────────────────────┬─────────────────────────────────┘  │
│                           │                                     │
│           ┌───────────────┼───────────────┐                    │
│           │               │               │                    │
│           ▼               ▼               ▼                    │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐          │
│  │  Web Server  │ │  Web Server  │ │  Web Server  │          │
│  │   (Nginx)    │ │   (Nginx)    │ │   (Nginx)    │          │
│  │              │ │              │ │              │          │
│  │  Laravel App │ │  Laravel App │ │  Laravel App │          │
│  └──────┬───────┘ └──────┬───────┘ └──────┬───────┘          │
│         │                │                │                    │
│         └────────────────┼────────────────┘                    │
│                          │                                     │
│                          ▼                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                   Database (MySQL)                       │  │
│  │                                                          │  │
│  │  - subscriptions table                                   │  │
│  │  - users table                                           │  │
│  │  - Read Replicas for scaling                            │  │
│  └──────────────────────────────────────────────────────────┘  │
│                          │                                     │
│                          ▼                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                   Cache (Redis)                          │  │
│  │                                                          │  │
│  │  - Subscription status cache                             │  │
│  │  - Webhook idempotency cache                             │  │
│  │  - Session storage                                       │  │
│  └──────────────────────────────────────────────────────────┘  │
│                          │                                     │
│                          ▼                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                   Queue (Redis)                          │  │
│  │                                                          │  │
│  │  - Webhook processing jobs                               │  │
│  │  - Notification jobs                                     │  │
│  │  - Reconciliation jobs                                   │  │
│  └──────────────────────────────────────────────────────────┘  │
│                          │                                     │
│                          ▼                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                Queue Workers (3+)                        │  │
│  │                                                          │  │
│  │  - Process background jobs                               │  │
│  │  - Auto-scaling based on queue depth                     │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
                          │
                          │ HTTPS
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│                    External Services                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                  Midtrans API                            │  │
│  │                                                          │  │
│  │  - api.midtrans.com                                      │  │
│  │  - Subscription management                               │  │
│  │  - Payment processing                                    │  │
│  └──────────────────────────────────────────────────────────┘  │
│                          │                                     │
│                          │ Webhooks                            │
│                          ▼                                     │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │              Webhook Endpoint                            │  │
│  │                                                          │  │
│  │  POST /api/webhooks/midtrans                             │  │
│  │  - Signature validation                                  │  │
│  │  - Rate limiting                                         │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Infrastructure Components

1. **Load Balancer**: Distributes traffic across web servers
2. **Web Servers**: Multiple instances for high availability
3. **Database**: MySQL with read replicas for scaling
4. **Cache**: Redis for session and data caching
5. **Queue**: Redis for background job processing
6. **Workers**: Dedicated queue workers for async processing

### Scaling Considerations

- **Horizontal Scaling**: Add more web servers as traffic grows
- **Database Scaling**: Use read replicas for read-heavy operations
- **Queue Workers**: Auto-scale based on queue depth
- **Cache**: Use Redis cluster for high availability


## Security Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      Security Layers                            │
└─────────────────────────────────────────────────────────────────┘

Layer 1: Network Security
┌─────────────────────────────────────────────────────────────────┐
│  - HTTPS/TLS 1.3 encryption                                     │
│  - Firewall rules (allow only 80, 443)                          │
│  - DDoS protection                                              │
│  - IP whitelisting for admin access                             │
└─────────────────────────────────────────────────────────────────┘

Layer 2: Application Security
┌─────────────────────────────────────────────────────────────────┐
│  - CSRF protection (except webhooks)                            │
│  - XSS protection                                               │
│  - SQL injection prevention (Eloquent ORM)                      │
│  - Rate limiting (60 req/min for webhooks)                      │
│  - Input validation and sanitization                            │
└─────────────────────────────────────────────────────────────────┘

Layer 3: Authentication & Authorization
┌─────────────────────────────────────────────────────────────────┐
│  - User authentication (Laravel Sanctum)                        │
│  - Session management                                           │
│  - Password hashing (bcrypt)                                    │
│  - Role-based access control                                    │
└─────────────────────────────────────────────────────────────────┘

Layer 4: API Security
┌─────────────────────────────────────────────────────────────────┐
│  - Midtrans API authentication (Basic Auth)                     │
│  - Webhook signature validation (SHA512)                        │
│  - API key rotation policy                                      │
│  - Secure credential storage (env variables)                    │
└─────────────────────────────────────────────────────────────────┘

Layer 5: Data Security
┌─────────────────────────────────────────────────────────────────┐
│  - Database encryption at rest                                  │
│  - Sensitive data masking in logs                               │
│  - PCI DSS compliance (via Midtrans)                            │
│  - Regular backups with encryption                              │
└─────────────────────────────────────────────────────────────────┘

Layer 6: Monitoring & Logging
┌─────────────────────────────────────────────────────────────────┐
│  - Security event logging                                       │
│  - Failed authentication tracking                               │
│  - Suspicious activity alerts                                   │
│  - Audit trail for all transactions                             │
└─────────────────────────────────────────────────────────────────┘
```

### Security Best Practices

1. **Credential Management**:
   - Store API keys in environment variables
   - Never commit credentials to version control
   - Rotate keys every 90 days
   - Use different keys for sandbox/production

2. **Webhook Security**:
   - Always validate signatures
   - Use HTTPS endpoints only
   - Implement rate limiting
   - Log all webhook attempts

3. **Data Protection**:
   - Never log sensitive data (card numbers, CVV)
   - Mask PII in logs
   - Encrypt data at rest and in transit
   - Follow GDPR/privacy regulations

4. **Access Control**:
   - Implement least privilege principle
   - Use role-based access control
   - Audit admin actions
   - Require MFA for admin accounts


## Monitoring Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    Application Metrics                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │              Subscription Metrics                        │  │
│  │                                                          │  │
│  │  - New subscriptions per day                             │  │
│  │  - Active subscriptions count                            │  │
│  │  - Churn rate                                            │  │
│  │  - MRR (Monthly Recurring Revenue)                       │  │
│  │  - Conversion rate (trial → paid)                        │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │               Payment Metrics                            │  │
│  │                                                          │  │
│  │  - Payment success rate                                  │  │
│  │  - Payment failure rate                                  │  │
│  │  - Average payment processing time                       │  │
│  │  - Failed payment recovery rate                          │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │               Webhook Metrics                            │  │
│  │                                                          │  │
│  │  - Webhooks received per hour                            │  │
│  │  - Webhook processing time                               │  │
│  │  - Webhook failure rate                                  │  │
│  │  - Invalid signature attempts                            │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                 API Metrics                              │  │
│  │                                                          │  │
│  │  - API response time (p50, p95, p99)                     │  │
│  │  - API error rate                                        │  │
│  │  - API timeout rate                                      │  │
│  │  - Rate limit hits                                       │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Logging System                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │            Application Logs                              │  │
│  │                                                          │  │
│  │  - storage/logs/laravel.log                              │  │
│  │  - storage/logs/subscription.log                         │  │
│  │  - Rotation: daily, keep 14 days                         │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │              Error Tracking                              │  │
│  │                                                          │  │
│  │  - Exception logging                                     │  │
│  │  - Stack traces                                          │  │
│  │  - User context                                          │  │
│  │  - Integration with Sentry/Bugsnag                       │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Alerting System                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │              Critical Alerts                             │  │
│  │                                                          │  │
│  │  - Payment gateway down                                  │  │
│  │  - High error rate (>5% in 5 min)                        │  │
│  │  - Database connection failures                          │  │
│  │  - Webhook signature validation failures                 │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │              Warning Alerts                              │  │
│  │                                                          │  │
│  │  - High payment failure rate (>10%)                      │  │
│  │  - Slow API response time (>3s)                          │  │
│  │  - Queue depth growing                                   │  │
│  │  - Disk space low                                        │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │            Notification Channels                         │  │
│  │                                                          │  │
│  │  - Email (for non-critical)                              │  │
│  │  - Slack (for all alerts)                                │  │
│  │  - SMS (for critical only)                               │  │
│  │  - PagerDuty (for production incidents)                  │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Key Monitoring Dashboards

1. **Subscription Health Dashboard**:
   - Active subscriptions trend
   - New vs cancelled subscriptions
   - Revenue metrics (MRR, ARR)
   - Churn rate

2. **Payment Performance Dashboard**:
   - Payment success/failure rates
   - Payment method distribution
   - Average transaction value
   - Failed payment recovery

3. **System Health Dashboard**:
   - API response times
   - Error rates
   - Queue depth
   - Server resources (CPU, memory, disk)

4. **Webhook Monitoring Dashboard**:
   - Webhook delivery rate
   - Processing time
   - Failure rate
   - Retry attempts

## References

- [Laravel Architecture](https://laravel.com/docs/architecture)
- [Midtrans Integration Guide](https://docs.midtrans.com/)
- [System Design Patterns](https://www.patterns.dev/)
