<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment and QRIS Language Lines (English)
    |--------------------------------------------------------------------------
    |
    | The following language lines are used for payment-related features
    | including QRIS generation, payment status, transactions, provider settings,
    | and payment notifications.
    |
    */

    // Page Titles
    'qris_payment_title' => 'QRIS Payment - :order_id',
    'provider_settings_title' => 'Provider Settings - QashierWise',
    'generate_qris_title' => 'Generate QRIS - QashierWise',
    'transactions_title' => 'Transactions - QashierWise',
    'balance_title' => 'Balance - QashierWise',

    // QRIS Payment Page
    'qris_payment' => 'QRIS Payment',
    'merchant' => 'Merchant',
    'payment_amount' => 'Payment Amount',
    'total_payment' => 'Total Payment',
    'valid_until' => 'Valid Until',
    'order_id' => 'Order ID',
    'powered_by' => 'Powered by QashierWise',

    // Payment Status
    'payment_successful' => 'Payment Successful!',
    'payment_expired' => 'Payment Expired',
    'payment_cancelled' => 'Payment Cancelled',
    'payment_pending' => 'Payment Pending',
    'thank_you_payment' => 'Thank you for your payment',
    'payment_time_expired' => 'Payment time has expired',
    'transaction_cancelled' => 'This transaction has been cancelled',
    'qr_code_unavailable' => 'QR Code not available',
    'expired' => 'Expired',

    // Payment Instructions
    'payment_instructions' => 'Payment Instructions:',
    'instruction_1' => 'Open your e-wallet or mobile banking app',
    'instruction_2' => 'Select Scan QR or QRIS menu',
    'instruction_3' => 'Scan the QR code above',
    'instruction_4' => 'Confirm payment',

    // Transaction Status
    'status_pending' => 'Pending',
    'status_settlement' => 'Settlement',
    'status_success' => 'Success',
    'status_paid' => 'Paid',
    'status_failed' => 'Failed',
    'status_expired' => 'Expired',
    'status_cancelled' => 'Cancelled',
    'status_active' => 'Active',

    // Provider Settings
    'provider_settings' => 'Provider Settings',
    'manage_provider_credentials' => 'Manage your payment provider credentials',
    'active_provider' => 'Active Provider',
    'no_active_provider' => 'No Active Provider',
    'all_qris_use_provider' => 'All QRIS generation will use this provider',
    'configure_provider_message' => 'Please configure and activate a provider to generate QRIS',
    'provider_cards' => 'Provider Cards',

    // Provider Names
    'provider_doku' => 'Doku',
    'provider_xendit' => 'Xendit',
    'provider_midtrans' => 'Midtrans',
    'provider_duitku' => 'Duitku',

    // Provider API Types
    'snap_api' => 'SNAP API Integration',
    'qr_codes_api' => 'QR Codes API',
    'qris_charge_api' => 'QRIS Charge API',
    'invoice_api' => 'Invoice API',

    // Provider Status
    'coming_soon' => 'Coming Soon',
    'integration_coming_soon' => 'Integration coming soon',
    'not_configured' => 'Not configured yet',
    'last_validated' => 'Last Validated',
    'valid' => 'Valid',
    'invalid' => 'Invalid',
    'currently_active' => 'Currently Active',

    // Provider Actions
    'configure_provider' => 'Configure :provider',
    'revalidate' => 'Revalidate',
    'set_as_active' => 'Set as Active',
    'edit' => 'Edit',

    // Provider Configuration Modal
    'configure' => 'Configure',
    'api_key' => 'API Key',
    'callback_token' => 'Callback Token',
    'server_key' => 'Server Key',
    'client_key' => 'Client Key',
    'enter_api_key' => 'Enter :provider API Key',
    'enter_callback_token' => 'Enter :provider Callback Token',
    'enter_server_key' => 'Enter :provider Server Key',
    'enter_client_key' => 'Enter :provider Client Key',
    'required_field' => 'Required field',
    'save_and_validate' => 'Save & Validate',
    'saving' => 'Saving...',

    // Provider Messages
    'provider_configured_success' => 'Provider configured and validated successfully!',
    'provider_save_failed' => 'Failed to save provider credentials',
    'provider_validation_failed' => 'Validation failed',
    'provider_not_found' => 'Provider not found',
    'provider_not_supported' => 'Provider :provider is not supported',
    'no_active_provider_configured' => 'No active payment provider configured. Please configure a provider in settings.',
    'provider_not_configured' => 'Provider :provider is not properly configured. Please validate your credentials.',
    'provider_invalid' => 'Provider is not properly configured. Please validate your credentials in settings.',

    // QRIS Generation
    'generate_qris' => 'Generate QRIS',
    'qris_code' => 'QRIS Code',
    'qris_generated' => 'QRIS generated successfully',
    'qris_generation_failed' => 'QRIS generation failed',
    'amount' => 'Amount',
    'description' => 'Description',
    'customer_name' => 'Customer Name',
    'customer_email' => 'Customer Email',
    'expiry_minutes' => 'Expiry Minutes',
    'generate' => 'Generate',
    'generating' => 'Generating...',

    // Transaction List
    'transactions' => 'Transactions',
    'transaction_history' => 'Transaction History',
    'transaction_id' => 'Transaction ID',
    'provider' => 'Provider',
    'date' => 'Date',
    'time' => 'Time',
    'net_amount' => 'Net Amount',
    'platform_fee' => 'Platform Fee',
    'reference_id' => 'Reference ID',
    'paid_at' => 'Paid At',
    'expires_at' => 'Expires At',
    'no_transactions' => 'No transactions yet',
    'view_details' => 'View Details',
    'download_qr' => 'Download QR',
    'share_link' => 'Share Link',
    'cancel_transaction' => 'Cancel Transaction',

    // Balance
    'balance' => 'Balance',
    'available_balance' => 'Available Balance',
    'pending_balance' => 'Pending Balance',
    'total_earnings' => 'Total Earnings',
    'withdraw' => 'Withdraw',
    'withdrawal_request' => 'Withdrawal Request',
    'withdrawal_amount' => 'Withdrawal Amount',
    'bank_account' => 'Bank Account',
    'account_number' => 'Account Number',
    'account_holder' => 'Account Holder',
    'request_withdrawal' => 'Request Withdrawal',

    // Notifications
    'payment_received' => 'Payment received',
    'payment_received_message' => 'Payment of :amount has been received',
    'qris_expired' => 'QRIS expired',
    'qris_expired_message' => 'QRIS code for order :order_id has expired',
    'withdrawal_approved' => 'Withdrawal approved',
    'withdrawal_approved_message' => 'Your withdrawal request of :amount has been approved',
    'withdrawal_rejected' => 'Withdrawal rejected',
    'withdrawal_rejected_message' => 'Your withdrawal request of :amount has been rejected',

    // Error Messages
    'merchant_not_active' => 'Sub-merchant is not active',
    'amount_must_positive' => 'Amount must be positive',
    'merchant_must_have_user' => 'Sub-merchant must be associated with a user',
    'transaction_not_found' => 'Transaction not found',
    'only_pending_can_cancel' => 'Only pending transactions can be cancelled',
    'invalid_webhook_signature' => 'Invalid webhook signature',
    'webhook_processing_failed' => 'Failed to process webhook',
    'transaction_update_failed' => 'Failed to update transaction',

    // Success Messages
    'transaction_cancelled' => 'Transaction cancelled successfully',
    'withdrawal_requested' => 'Withdrawal requested successfully',
    'provider_activated' => 'Provider activated successfully',
    'credentials_saved' => 'Credentials saved successfully',
    'credentials_validated' => 'Credentials validated successfully',

    // Validation Messages
    'invalid_amount' => 'Invalid amount',
    'invalid_provider' => 'Invalid provider',
    'invalid_credentials' => 'Invalid credentials',
    'missing_required_fields' => 'Missing required fields',

    // Common Actions
    'view' => 'View',
    'cancel' => 'Cancel',
    'confirm' => 'Confirm',
    'close' => 'Close',
    'save' => 'Save',
    'back' => 'Back',
    'refresh' => 'Refresh',
    'retry' => 'Retry',

    // Common Labels
    'loading' => 'Loading...',
    'processing' => 'Processing...',
    'please_wait' => 'Please wait...',
    'no_data' => 'No data available',
    'error_occurred' => 'An error occurred',
    'try_again' => 'Please try again',

    // Currency
    'currency_symbol' => 'Rp',
    'currency_format' => 'Rp :amount',

    // Time
    'minutes' => 'minutes',
    'hours' => 'hours',
    'days' => 'days',
    'ago' => 'ago',
    'remaining' => 'remaining',

];
