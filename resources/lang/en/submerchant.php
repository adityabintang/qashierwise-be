<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sub-Merchant Management Language Lines (English)
    |--------------------------------------------------------------------------
    |
    | The following language lines are used for sub-merchant management features
    | including listing, registration, balance management, transactions,
    | withdrawals, and notifications.
    |
    */

    // Page Titles
    'dashboard_title' => 'Sub-Merchant Dashboard - QashierWise',
    'register_title' => 'Register as Sub-Merchant - QashierWise',
    'balance_title' => 'Balance Management - QashierWise',
    'transactions_title' => 'Transaction History - QashierWise',
    'qris_title' => 'Generate QRIS - QashierWise',
    'provider_settings_title' => 'Provider Settings - QashierWise',
    'migrate_title' => 'Migrate to BYOK - QashierWise',

    // Dashboard
    'dashboard' => 'Sub-Merchant Dashboard',
    'welcome' => 'Welcome to Sub-Merchant',
    'overview' => 'Overview',
    'quick_actions' => 'Quick Actions',
    'recent_activity' => 'Recent Activity',

    // Registration
    'register_as_submerchant' => 'Register as Sub-Merchant',
    'start_accepting_qris' => 'Start accepting QRIS payments from your customers',
    'already_registered' => 'Already Registered',
    'already_registered_message' => 'You are already registered as a sub-merchant.',
    'go_to_dashboard' => 'Go to Dashboard',
    'not_registered' => 'Not Registered',
    'not_registered_message' => 'You need to register as a sub-merchant to generate QRIS codes.',
    'register_now' => 'Register Now',

    // Registration Form
    'business_name' => 'Business Name',
    'business_name_placeholder' => 'Enter your business name',
    'business_address' => 'Business Address',
    'business_address_placeholder' => 'Enter your business address',
    'business_phone' => 'Business Phone',
    'business_phone_placeholder' => 'Enter your business phone number',
    'business_email' => 'Business Email',
    'business_email_placeholder' => 'Enter your business email',
    'bank_name' => 'Bank Name',
    'bank_name_placeholder' => 'Select your bank',
    'account_number' => 'Account Number',
    'account_number_placeholder' => 'Enter your account number',
    'account_holder_name' => 'Account Holder Name',
    'account_holder_name_placeholder' => 'Enter account holder name',

    // Terms and Conditions
    'accept_terms' => 'I accept the terms and conditions',
    'terms_message' => 'I understand and agree that a <strong>2.5% platform fee</strong> will be deducted from each successful transaction.',
    'platform_fee_notice' => 'A 2.5% platform fee is deducted from each successful transaction',

    // Registration Steps
    'registration_steps' => 'How it works:',
    'step_register' => 'Register as a sub-merchant to start accepting QRIS payments',
    'step_configure' => 'Configure your payment provider credentials in Provider Settings',
    'step_generate' => 'Generate QRIS codes for your customers',
    'step_fee' => 'A 2.5% platform fee is deducted from each successful transaction',

    // Balance Management
    'balance' => 'Balance',
    'balance_overview' => 'Balance Overview',
    'available_balance' => 'Available Balance',
    'pending_balance' => 'Pending Balance',
    'total_balance' => 'Total Balance',
    'total_earnings' => 'Total Earnings',
    'total_withdrawn' => 'Total Withdrawn',
    'balance_history' => 'Balance History',
    'balance_details' => 'Balance Details',

    // Withdrawals
    'withdraw' => 'Withdraw',
    'withdraw_funds' => 'Withdraw Funds',
    'withdrawal' => 'Withdrawal',
    'withdrawal_request' => 'Withdrawal Request',
    'withdrawal_amount' => 'Withdrawal Amount',
    'withdrawal_history' => 'Withdrawal History',
    'request_withdrawal' => 'Request Withdrawal',
    'withdrawal_requested' => 'Withdrawal requested successfully',
    'withdrawal_approved' => 'Withdrawal approved',
    'withdrawal_rejected' => 'Withdrawal rejected',
    'withdrawal_pending' => 'Withdrawal pending',
    'withdrawal_processing' => 'Processing withdrawal',
    'withdrawal_completed' => 'Withdrawal completed',
    'withdrawal_failed' => 'Withdrawal failed',
    'minimum_withdrawal' => 'Minimum withdrawal amount is :amount',
    'insufficient_balance' => 'Insufficient balance',
    'withdrawal_fee' => 'Withdrawal Fee',
    'net_withdrawal' => 'Net Withdrawal Amount',

    // Bank Account
    'bank_account' => 'Bank Account',
    'bank_account_info' => 'Bank Account Information',
    'bank_details' => 'Bank Details',
    'account_holder' => 'Account Holder',
    'update_bank_account' => 'Update Bank Account',

    // Transactions
    'transactions' => 'Transactions',
    'transaction_history' => 'Transaction History',
    'transaction_details' => 'Transaction Details',
    'recent_transactions' => 'Recent Transactions',
    'all_transactions' => 'All Transactions',
    'no_transactions' => 'No transactions yet',
    'no_transactions_message' => 'Generate your first QRIS to get started',
    'transaction_id' => 'Transaction ID',
    'order_id' => 'Order ID',
    'reference_id' => 'Reference ID',
    'amount' => 'Amount',
    'gross_amount' => 'Gross Amount',
    'net_amount' => 'Net Amount',
    'platform_fee' => 'Platform Fee',
    'status' => 'Status',
    'date' => 'Date',
    'time' => 'Time',
    'created_at' => 'Created',
    'paid_at' => 'Paid At',
    'expires_at' => 'Expires At',
    'provider' => 'Provider',

    // Transaction Status
    'status_pending' => 'Pending',
    'status_settlement' => 'Settlement',
    'status_success' => 'Success',
    'status_paid' => 'Paid',
    'status_failed' => 'Failed',
    'status_expired' => 'Expired',
    'status_cancelled' => 'Cancelled',
    'status_active' => 'Active',

    // Transaction Filters
    'filter_all' => 'All',
    'filter_today' => 'Today',
    'filter_week' => 'This Week',
    'filter_month' => 'This Month',
    'filter_pending' => 'Pending',
    'filter_success' => 'Success',
    'filter_failed' => 'Failed',

    // QRIS Generation
    'generate_qris' => 'Generate QRIS',
    'qris_generation' => 'QRIS Generation',
    'qris_code' => 'QRIS Code',
    'qris_history' => 'QRIS History',
    'new_qris' => 'New QRIS',
    'create_qris' => 'Create QRIS',
    'qris_details' => 'QRIS Details',
    'qris_generated' => 'QRIS generated successfully',
    'qris_generation_failed' => 'QRIS generation failed',
    'scan_to_pay' => 'Scan to Pay',
    'valid_until' => 'Valid Until',
    'can_be_used' => 'Can be used',
    'cannot_be_used' => 'Cannot be used',

    // QRIS Form
    'customer_name' => 'Customer Name',
    'customer_name_placeholder' => 'Enter customer name (optional)',
    'customer_email' => 'Customer Email',
    'customer_email_placeholder' => 'Enter customer email (optional)',
    'amount_placeholder' => 'Enter amount',
    'description' => 'Description',
    'description_placeholder' => 'Enter description (optional)',
    'expiry_minutes' => 'Expiry Minutes',
    'expiry_minutes_placeholder' => 'Default: 60 minutes',

    // Provider Settings
    'provider_settings' => 'Provider Settings',
    'manage_providers' => 'Manage Payment Providers',
    'manage_provider_credentials' => 'Manage your payment provider credentials',
    'active_provider' => 'Active Provider',
    'no_active_provider' => 'No Active Provider',
    'all_qris_use_provider' => 'All QRIS generation will use this provider',
    'configure_provider_message' => 'Please configure and activate a provider to generate QRIS',
    'provider_configured' => 'Provider configured successfully',
    'provider_activated' => 'Provider activated successfully',
    'provider_validation_success' => 'Provider validated successfully',
    'provider_validation_failed' => 'Provider validation failed',

    // Migration
    'migrate_to_byok' => 'Migrate to BYOK',
    'byok_migration' => 'Bring Your Own Key Migration',
    'migration_description' => 'Migrate your existing credentials to the new BYOK system',
    'migration_status' => 'Migration Status',
    'migration_complete' => 'Migration completed successfully',
    'migration_failed' => 'Migration failed',
    'start_migration' => 'Start Migration',

    // Notifications
    'payment_received' => 'Payment received',
    'payment_received_message' => 'Payment of :amount has been received for order :order_id',
    'qris_expired' => 'QRIS expired',
    'qris_expired_message' => 'QRIS code for order :order_id has expired',
    'withdrawal_approved_message' => 'Your withdrawal request of :amount has been approved',
    'withdrawal_rejected_message' => 'Your withdrawal request of :amount has been rejected',
    'withdrawal_completed_message' => 'Your withdrawal of :amount has been completed',
    'balance_updated' => 'Balance updated',
    'balance_updated_message' => 'Your balance has been updated',

    // Actions
    'view_details' => 'View Details',
    'download_qr' => 'Download QR',
    'share_link' => 'Share Link',
    'copy_link' => 'Copy Link',
    'cancel_transaction' => 'Cancel Transaction',
    'refresh' => 'Refresh',
    'export' => 'Export',
    'print' => 'Print',
    'configure' => 'Configure',
    'edit' => 'Edit',
    'save' => 'Save',
    'cancel' => 'Cancel',
    'submit' => 'Submit',
    'close' => 'Close',
    'back' => 'Back',
    'next' => 'Next',
    'confirm' => 'Confirm',
    'revalidate' => 'Revalidate',
    'set_as_active' => 'Set as Active',
    'currently_active' => 'Currently Active',

    // Status Messages
    'loading' => 'Loading...',
    'saving' => 'Saving...',
    'processing' => 'Processing...',
    'generating' => 'Generating...',
    'please_wait' => 'Please wait...',
    'success' => 'Success',
    'error' => 'Error',
    'warning' => 'Warning',

    // Error Messages
    'error_occurred' => 'An error occurred',
    'try_again' => 'Please try again',
    'invalid_amount' => 'Invalid amount',
    'amount_required' => 'Amount is required',
    'amount_must_positive' => 'Amount must be positive',
    'merchant_not_active' => 'Sub-merchant is not active',
    'merchant_not_found' => 'Sub-merchant not found',
    'transaction_not_found' => 'Transaction not found',
    'only_pending_can_cancel' => 'Only pending transactions can be cancelled',
    'provider_not_configured' => 'Provider not configured',
    'no_active_provider_configured' => 'No active payment provider configured',
    'registration_failed' => 'Registration failed',
    'validation_failed' => 'Validation failed',
    'missing_required_fields' => 'Missing required fields',

    // Success Messages
    'registration_success' => 'Registration successful',
    'update_success' => 'Updated successfully',
    'transaction_cancelled' => 'Transaction cancelled successfully',
    'link_copied' => 'Link copied to clipboard',
    'qr_downloaded' => 'QR code downloaded',

    // Empty States
    'no_data' => 'No data available',
    'no_balance_history' => 'No balance history yet',
    'no_withdrawals' => 'No withdrawal requests yet',
    'no_providers' => 'No providers configured',

    // Common Labels
    'total' => 'Total',
    'subtotal' => 'Subtotal',
    'fee' => 'Fee',
    'net' => 'Net',
    'gross' => 'Gross',
    'currency' => 'Rp',
    'optional' => 'Optional',
    'required' => 'Required',
    'yes' => 'Yes',
    'no' => 'No',

    // Time
    'minutes' => 'minutes',
    'hours' => 'hours',
    'days' => 'days',
    'ago' => 'ago',
    'remaining' => 'remaining',
    'never' => 'Never',

    // Provider Settings Page
    'coming_soon' => 'Coming Soon',
    'integration_coming_soon' => 'Integration coming soon',
    'not_configured_yet' => 'Not configured yet',
    'last_validated' => 'Last Validated',
    'api_key' => 'API Key',
    'callback_token' => 'Callback Token',
    'server_key' => 'Server Key',
    'client_key' => 'Client Key',
    'enter_api_key' => 'Enter API Key',
    'enter_callback_token' => 'Enter Callback Token',
    'enter_server_key' => 'Enter Server Key',
    'enter_client_key' => 'Enter Client Key',
    'save_validate' => 'Save & Validate',
    'provider_save_success' => 'Provider configured and validated successfully!',
    'provider_save_failed' => 'Failed to save provider credentials',
    'provider_not_found' => 'Provider not found',
    'set_active_confirm' => 'Set :provider as your active provider? All QRIS generation will use this provider.',
    'set_active_failed' => 'Failed to set active provider',
    'validation_error' => 'An error occurred during validation',
    'status_valid' => 'Valid',
    'status_invalid' => 'Invalid',
    'status_pending_validation' => 'Pending',

];
