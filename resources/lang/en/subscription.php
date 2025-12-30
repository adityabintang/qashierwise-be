<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription Management Language Lines (English)
    |--------------------------------------------------------------------------
    |
    | The following language lines are used for subscription-related features
    | including plan names, descriptions, status, billing, history, upgrades,
    | and subscription notifications.
    |
    */

    // Page Titles
    'subscription_title' => 'Subscription - QashierWise',
    'manage_subscription_title' => 'Manage Subscription - QashierWise',
    'billing_history_title' => 'Billing History - QashierWise',
    'upgrade_plan_title' => 'Upgrade Plan - QashierWise',

    // Section Titles
    'subscription' => 'Subscription',
    'manage_subscription' => 'Manage Subscription',
    'billing_history' => 'Billing History',
    'current_plan' => 'Current Plan',
    'plan_details' => 'Plan Details',
    'billing_information' => 'Billing Information',
    'payment_method' => 'Payment Method',

    // Plan Names
    'plan_free_trial' => 'Basic (Free Trial)',
    'plan_standard' => 'Standard',
    'plan_pro' => 'Pro',
    'plan_basic' => 'Basic',

    // Plan Descriptions
    'plan_free_trial_desc' => 'Try all basic features for free',
    'plan_standard_desc' => 'Perfect for growing businesses',
    'plan_pro_desc' => 'Advanced features for power users',

    // Plan Features
    'features' => 'Features',
    'all_features' => 'All Features',
    'included_features' => 'Included Features',
    'feature_basic_dashboard' => 'Basic dashboard access',
    'feature_limited_contacts' => 'Limited contact management',
    'feature_standard_templates' => 'Standard message templates',
    'feature_unlimited_contacts' => 'Unlimited contact management',
    'feature_custom_templates' => 'Custom message templates',
    'feature_basic_analytics' => 'Basic analytics reports',
    'feature_email_support' => 'Email support',
    'feature_full_api_access' => 'Full API access',
    'feature_advanced_analytics' => 'Advanced analytics reports',
    'feature_webhook_integration' => 'Webhook integration',
    'feature_priority_support' => 'Priority support',
    'feature_multi_user' => 'Multi-user support',

    // Subscription Status
    'status' => 'Status',
    'status_trial' => 'Trial',
    'status_active' => 'Active',
    'status_cancelled' => 'Cancelled',
    'status_expired' => 'Expired',
    'status_trial_expired' => 'Trial Expired',
    'status_past_due' => 'Past Due',
    'status_incomplete' => 'Incomplete',

    // Status Messages
    'trial_active' => 'Your trial is active',
    'subscription_active' => 'Your subscription is active',
    'subscription_cancelled' => 'Subscription cancelled',
    'subscription_expired' => 'Your subscription has expired',
    'trial_expired' => 'Your trial has expired',
    'payment_past_due' => 'Payment is past due',

    // Trial Information
    'trial_period' => 'Trial Period',
    'trial_days_remaining' => ':days days remaining',
    'trial_ends_on' => 'Trial ends on :date',
    'trial_expired_message' => 'Your trial period has ended. Upgrade to continue using all features.',
    'days_remaining' => 'days remaining',

    // Billing Period
    'billing_period' => 'Billing Period',
    'current_period' => 'Current Period',
    'next_billing' => 'Next billing',
    'next_billing_date' => 'Next Billing Date',
    'period_start' => 'Period Start',
    'period_end' => 'Period End',
    'billing_cycle' => 'Billing Cycle',
    'monthly' => 'Monthly',
    'yearly' => 'Yearly',
    'annual' => 'Annual',

    // Pricing
    'price' => 'Price',
    'price_per_month' => ':price/month',
    'price_per_year' => ':price/year',
    'free' => 'Free',
    'starting_at' => 'Starting at',
    'billed_monthly' => 'Billed monthly',
    'billed_annually' => 'Billed annually',
    'save_percentage' => 'Save :percentage%',

    // Actions
    'upgrade_now' => 'Upgrade Now',
    'upgrade_plan' => 'Upgrade Plan',
    'downgrade_plan' => 'Downgrade Plan',
    'change_plan' => 'Change Plan',
    'cancel_subscription' => 'Cancel Subscription',
    'reactivate_subscription' => 'Reactivate Subscription',
    'renew_subscription' => 'Renew Subscription',
    'view_plans' => 'View Plans',
    'choose_plan' => 'Choose Plan',
    'select_plan' => 'Select Plan',
    'current_plan_label' => 'Current Plan',
    'manage_billing' => 'Manage Billing',
    'update_payment_method' => 'Update Payment Method',
    'view_invoices' => 'View Invoices',
    'download_invoice' => 'Download Invoice',

    // Cancellation
    'cancel_subscription_confirm' => 'Are you sure you want to cancel your subscription?',
    'cancel_reason' => 'Reason for cancellation',
    'cancel_reason_placeholder' => 'Please tell us why you\'re cancelling (optional)',
    'cancel_at_period_end' => 'Cancel at period end',
    'cancel_immediately' => 'Cancel immediately',
    'access_until' => 'Access until',
    'cancelled_notice' => 'Your subscription has been cancelled. You will have access until :date',
    'reactivate_notice' => 'You can reactivate your subscription at any time before :date',

    // Upgrade/Downgrade
    'upgrade_to' => 'Upgrade to :plan',
    'downgrade_to' => 'Downgrade to :plan',
    'switch_to' => 'Switch to :plan',
    'upgrade_benefits' => 'Upgrade Benefits',
    'downgrade_warning' => 'Downgrading will limit some features',
    'plan_comparison' => 'Plan Comparison',
    'compare_plans' => 'Compare Plans',
    'recommended' => 'Recommended',
    'most_popular' => 'Most Popular',
    'best_value' => 'Best Value',

    // Billing History
    'invoice_number' => 'Invoice #',
    'invoice_date' => 'Invoice Date',
    'invoice_amount' => 'Amount',
    'invoice_status' => 'Status',
    'invoice_paid' => 'Paid',
    'invoice_pending' => 'Pending',
    'invoice_failed' => 'Failed',
    'invoice_refunded' => 'Refunded',
    'no_invoices' => 'No invoices yet',
    'view_invoice' => 'View Invoice',
    'download' => 'Download',
    'payment_date' => 'Payment Date',
    'payment_amount' => 'Payment Amount',
    'payment_status' => 'Payment Status',

    // Payment Information
    'card_ending_in' => 'Card ending in :last4',
    'expires' => 'Expires :date',
    'no_payment_method' => 'No payment method on file',
    'add_payment_method' => 'Add Payment Method',
    'default_payment_method' => 'Default Payment Method',

    // Notifications
    'subscription_upgraded' => 'Subscription upgraded successfully',
    'subscription_downgraded' => 'Subscription downgraded successfully',
    'subscription_cancelled_success' => 'Subscription cancelled successfully',
    'subscription_reactivated' => 'Subscription reactivated successfully',
    'payment_method_updated' => 'Payment method updated successfully',
    'trial_started' => 'Your trial has started',
    'trial_ending_soon' => 'Your trial is ending soon',
    'trial_ended' => 'Your trial has ended',
    'subscription_renewed' => 'Subscription renewed successfully',
    'payment_successful' => 'Payment processed successfully',
    'payment_failed' => 'Payment failed',
    'payment_retry' => 'We will retry your payment',

    // Error Messages
    'subscription_not_found' => 'Subscription not found',
    'invalid_plan' => 'Invalid plan selected',
    'upgrade_failed' => 'Failed to upgrade subscription',
    'downgrade_failed' => 'Failed to downgrade subscription',
    'cancellation_failed' => 'Failed to cancel subscription',
    'payment_failed_message' => 'Your payment could not be processed. Please update your payment method.',
    'checkout_failed' => 'Failed to create checkout session',
    'portal_access_failed' => 'Failed to access customer portal',
    'already_subscribed' => 'You already have an active subscription',
    'cannot_downgrade_trial' => 'Cannot downgrade during trial period',

    // Success Messages
    'checkout_success' => 'Checkout completed successfully',
    'subscription_created' => 'Subscription created successfully',
    'plan_changed' => 'Plan changed successfully',
    'billing_updated' => 'Billing information updated successfully',

    // Customer Portal
    'customer_portal' => 'Customer Portal',
    'open_customer_portal' => 'Open Customer Portal',
    'manage_in_portal' => 'Manage in Portal',
    'portal_description' => 'Manage your subscription, billing, and payment methods',

    // Trial Banner
    'trial_banner_title' => 'You\'re on a free trial',
    'trial_banner_message' => 'Upgrade now to unlock all features',
    'trial_expired_banner_title' => 'Your trial has expired',
    'trial_expired_banner_message' => 'Upgrade to continue using QashierWise',

    // Subscription Limits
    'limit_reached' => 'Limit reached',
    'upgrade_to_unlock' => 'Upgrade to unlock this feature',
    'feature_not_available' => 'This feature is not available in your current plan',
    'upgrade_required' => 'Upgrade required',
    'contact_limit_reached' => 'Contact limit reached. Upgrade to add more contacts.',
    'template_limit_reached' => 'Template limit reached. Upgrade to create more templates.',

    // Common Labels
    'loading' => 'Loading...',
    'processing' => 'Processing...',
    'please_wait' => 'Please wait...',
    'confirm' => 'Confirm',
    'cancel' => 'Cancel',
    'close' => 'Close',
    'save' => 'Save',
    'back' => 'Back',
    'continue' => 'Continue',
    'learn_more' => 'Learn More',
    'get_started' => 'Get Started',

    // Checkout
    'checkout' => 'Checkout',
    'complete_checkout' => 'Complete Checkout',
    'checkout_loading' => 'Loading checkout...',
    'redirecting_to_checkout' => 'Redirecting to checkout...',
    'secure_checkout' => 'Secure Checkout',
    'powered_by_polar' => 'Powered by Polar',

    // Proration
    'proration_credit' => 'Proration Credit',
    'proration_charge' => 'Proration Charge',
    'prorated_amount' => 'Prorated Amount',
    'immediate_charge' => 'You will be charged immediately',
    'credit_applied' => 'Credit will be applied to your next invoice',

    // Refunds
    'refund_requested' => 'Refund requested',
    'refund_processed' => 'Refund processed',
    'refund_amount' => 'Refund Amount',
    'refund_date' => 'Refund Date',

    // Support
    'need_help' => 'Need help?',
    'contact_support' => 'Contact Support',
    'support_message' => 'Have questions about your subscription? Contact our support team.',

    // Time
    'per_month' => 'per month',
    'per_year' => 'per year',
    'month' => 'month',
    'year' => 'year',
    'day' => 'day',
    'days' => 'days',

];
