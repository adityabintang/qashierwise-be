# Implementation Plan: Multi-Language Localization

## Overview

This implementation plan breaks down the multi-language localization into discrete coding tasks. The approach starts with setting up the translation file structure, implementing the localization middleware, then systematically translating all features. Each task builds on previous work with integrated testing.

## Tasks

- [x] 1. Setup Translation File Structure and Configuration
  - Create `resources/lang/en` and `resources/lang/id` directories
  - Create base translation files: `common.php`, `validation.php`
  - Update `config/app.php` to define supported locales: ['en', 'id']
  - Add `language_preference` column to users table migration
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

- [ ]* 1.1 Write unit tests for translation file structure


  - Test translation files exist for all locales
  - Test translation keys are consistent across locales
  - _Requirements: 1.1, 1.2, 1.3_

- [x] 2. Implement LocalizationMiddleware
  - Create `LocalizationMiddleware` class
  - Implement locale detection from: user preference → session → Accept-Language header → default
  - Implement `parseAcceptLanguage()` method
  - Register middleware in HTTP kernel
  - _Requirements: 2.1, 2.2, 2.3, 2.4_

- [ ]* 2.1 Write property test for locale persistence
  - **Property 1: Locale Persistence**
  - **Validates: Requirements 2.3, 2.4, 2.5**

- [ ]* 2.2 Write unit tests for LocalizationMiddleware
  - Test locale detection from authenticated user
  - Test locale detection from session
  - Test locale detection from Accept-Language header
  - Test fallback to default locale
  - Test invalid locale handling
  - _Requirements: 2.1, 2.2, 2.3, 2.4_

- [x] 3. Create LanguageSwitcher Component
  - Create `LanguageSwitcher` Blade component
  - Implement language selection dropdown
  - Create language switching route
  - Implement session and user preference update
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

- [ ]* 3.1 Write unit tests for LanguageSwitcher
  - Test language switching for authenticated users
  - Test language switching for guests
  - Test preference persistence
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

- [x] 4. Create Authentication Translation Files
  - Create `resources/lang/en/auth.php` with all auth-related translations
  - Create `resources/lang/id/auth.php` with Indonesian translations
  - Include: login, register, password reset, email verification, error messages
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [ ]* 4.1 Write property test for translation key resolution
  - **Property 2: Translation Key Resolution**
  - **Validates: Requirements 1.1, 1.2, 1.3**

- [ ]* 4.2 Write unit tests for auth translations
  - Test all auth translation keys exist in both locales
  - Test auth pages display correct language
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [x] 5. Create Dashboard Translation Files
  - Create `resources/lang/en/dashboard.php` with dashboard translations
  - Create `resources/lang/id/dashboard.php` with Indonesian translations
  - Include: menu items, page titles, buttons, table headers, placeholders
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

- [ ]* 5.1 Write unit tests for dashboard translations
  - Test all dashboard translation keys exist
  - Test dashboard displays correct language
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

- [x] 6. Create WhatsApp Features Translation Files
  - Create `resources/lang/en/whatsapp.php` with WhatsApp translations
  - Create `resources/lang/id/whatsapp.php` with Indonesian translations
  - Include: account connection, message status, contact management, templates, errors
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

- [ ]* 6.1 Write unit tests for WhatsApp translations
  - Test all WhatsApp translation keys exist
  - Test WhatsApp interface displays correct language
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

- [x] 7. Create POS Translation Files
  - Create `resources/lang/en/pos.php` with POS translations
  - Create `resources/lang/id/pos.php` with Indonesian translations
  - Include: menu items, products, categories, orders, tables, staff, reports
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [ ]* 7.1 Write unit tests for POS translations
  - Test all POS translation keys exist
  - Test POS interface displays correct language
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [x] 8. Create AI Agent Translation Files
  - Create `resources/lang/en/ai_agent.php` with AI agent translations
  - Create `resources/lang/id/ai_agent.php` with Indonesian translations
  - Include: configuration, status messages, conversation history, settings, errors
  - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5_

- [ ]* 8.1 Write unit tests for AI agent translations
  - Test all AI agent translation keys exist
  - Test AI agent interface displays correct language
  - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5_

- [x] 9. Create Payment and QRIS Translation Files
  - Create `resources/lang/en/payments.php` with payment translations
  - Create `resources/lang/id/payments.php` with Indonesian translations
  - Include: QRIS generation, payment status, transactions, provider settings, notifications
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

- [ ]* 9.1 Write unit tests for payment translations
  - Test all payment translation keys exist
  - Test payment interface displays correct language
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

- [x] 10. Create Sub-Merchant Management Translation Files
  - Create `resources/lang/en/submerchant.php` with sub-merchant translations
  - Create `resources/lang/id/submerchant.php` with Indonesian translations
  - Include: listing, creation, balance, transactions, withdrawals, notifications
  - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

- [ ]* 10.1 Write unit tests for sub-merchant translations
  - Test all sub-merchant translation keys exist
  - Test sub-merchant interface displays correct language
  - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

- [x] 11. Create Subscription Management Translation Files
  - Create `resources/lang/en/subscription.php` with subscription translations
  - Create `resources/lang/id/subscription.php` with Indonesian translations
  - Include: plan names, descriptions, status, billing, history, upgrades, notifications
  - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

- [ ]* 11.1 Write unit tests for subscription translations
  - Test all subscription translation keys exist
  - Test subscription interface displays correct language
  - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

- [x] 12. Create Landing Page Translation Files
  - Create `resources/lang/en/landing.php` with landing page translations
  - Create `resources/lang/id/landing.php` with Indonesian translations
  - Include: hero section, features, benefits, pricing, FAQ, testimonials, footer, CTA buttons
  - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5, 15.6, 15.7, 15.8, 15.9, 15.10_

- [ ]* 12.1 Write unit tests for landing page translations
  - Test all landing page translation keys exist
  - Test landing page displays correct language
  - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5, 15.6, 15.7, 15.8, 15.9, 15.10_

- [x] 13. Create Notification and Alert Translation Files
  - Create `resources/lang/en/messages.php` with notification translations
  - Create `resources/lang/id/messages.php` with Indonesian translations
  - Include: success messages, error messages, warnings, confirmations, toasts
  - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_

- [ ]* 13.1 Write unit tests for notification translations
  - Test all notification translation keys exist
  - Test notifications display correct language
  - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_

- [x] 14. Implement Localization Helper Functions
  - Create `LocalizationHelper` class with helper methods
  - Implement `trans()`, `getCurrentLocale()`, `isLocaleSupported()`, `getSupportedLocales()`
  - Implement `formatDate()` with locale-aware formatting
  - Implement `formatCurrency()` for Rupiah formatting
  - Register helpers in service provider
  - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5_

- [ ]* 14.1 Write property test for date formatting consistency
  - **Property 5: Date Formatting Consistency**
  - **Validates: Requirements 12.1, 12.2**

- [ ]* 14.2 Write property test for currency formatting
  - **Property 6: Currency Formatting Correctness**
  - **Validates: Requirements 12.2, 12.3**

- [ ]* 14.3 Write unit tests for helper functions
  - Test date formatting in different locales
  - Test currency formatting
  - Test locale detection functions
  - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5_

- [x] 15. Implement API Response Localization
  - Create `ApiResponse` class with localized response methods
  - Implement `success()` and `error()` methods with message translation
  - Update all API endpoints to use localized responses
  - Implement Accept-Language header handling
  - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5_

- [ ]* 15.1 Write property test for API response localization
  - **Property 7: API Response Localization**
  - **Validates: Requirements 13.1, 13.2, 13.3**

- [ ]* 15.2 Write unit tests for API response localization

  - Test API responses in different locales
  - Test Accept-Language header handling
  - Test error message localization
  - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5_

- [ ]* 16. Implement Email Localization
  - Create localized email templates for all notification types
  - Implement email sending with user's language preference
  - Create `resources/lang/en/emails.php` and `resources/lang/id/emails.php`
  - Update all email notifications to use localized templates
  - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5_

- [ ]* 16.1 Write unit tests for email localization
  - Test emails sent in user's preferred language
  - Test email template rendering
  - Test fallback to English when preference not set
  - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5_

- [x] 17. Implement Missing Translation Logging
  - Configure missing translation logging in development
  - Create logging channel for missing translations
  - Implement handler in AppServiceProvider
  - Add monitoring for untranslated keys
  - _Requirements: 16.1, 16.2, 16.3, 16.4, 16.5_

- [ ]* 17.1 Write unit tests for missing translation handling
  - Test missing translation logging
  - Test fallback behavior
  - _Requirements: 16.1, 16.2, 16.3, 16.4, 16.5_

- [x] 18. Update All Views and Blade Templates
  - Update authentication views to use translation keys
  - Update dashboard views to use translation keys
  - Update all feature-specific views to use translation keys
  - Update landing page to use translation keys
  - Replace all hardcoded strings with `__()` or `@trans()` directives
  - _Requirements: 3.1, 4.1, 5.1, 6.1, 7.1, 8.1, 9.1, 10.1, 11.1, 15.1_

- [ ]* 18.1 Write integration tests for view localization
  - Test all views render in both languages
  - Test language switching updates view content
  - _Requirements: All_

- [x] 19. Implement Blade Directives
  - Create `@trans()` directive for inline translations
  - Create `@locale()` directive for current locale
  - Register directives in AppServiceProvider
  - _Requirements: 1.1, 1.2, 1.3_

- [ ]* 19.1 Write unit tests for Blade directives
  - Test @trans() directive
  - Test @locale() directive
  - _Requirements: 1.1, 1.2, 1.3_

- [x] 20. Implement SEO and Meta Tags Localization
  - Add hreflang tags for language versions
  - Translate meta descriptions and keywords
  - Update sitemap for language variants
  - Implement language-specific URL structure if needed
  - _Requirements: 15.9, 15.10_

- [ ]* 20.1 Write unit tests for SEO localization
  - Test hreflang tags
  - Test meta tag translations
  - _Requirements: 15.9, 15.10_

- [ ]* 21. Checkpoint - Ensure all tests pass
  - Run all unit tests for localization
  - Run all property-based tests
  - Run integration tests
  - Verify 100% translation coverage
  - Verify no untranslated strings in UI
  - _Requirements: All_

- [ ]* 21.1 Write comprehensive integration tests
  - Test complete user flow in both languages
  - Test language switching across all features
  - Test email notifications in both languages
  - Test API responses in both languages
  - _Requirements: All_

- [ ]* 22. Documentation and Configuration
  - Document translation file structure and naming conventions
  - Create guide for adding new translations
  - Document localization helper functions
  - Add code comments and docstrings
  - Update README with localization support
  - Create translation checklist for new features
  - _Requirements: All_

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Property tests validate universal correctness properties
- Unit tests validate specific examples and edge cases
- Integration tests validate end-to-end flows
- All tests should run with minimum 100 iterations for property-based tests
- Translation coverage should reach 100% for both languages
- Missing translation logging helps identify gaps during development
