# Tasks: Migrasi Subscription dari Polar ke Midtrans

## Phase 1: Database & Configuration Setup

- [x] 1. Database Migration
  - [x] 1.1 Create migration file for subscriptions table enhancements
  - [x] 1.2 Add midtrans_subscription_id column (nullable)
  - [x] 1.3 Add midtrans_customer_id column (nullable)
  - [x] 1.4 Add provider column (default 'polar')
  - [x] 1.5 Add metadata JSON column (nullable)
  - [x] 1.6 Add indexes for midtrans_subscription_id and provider
  - [x] 1.7 Test migration up and down

- [x] 2. Configuration Files
  - [x] 2.1 Create config/subscription.php
  - [x] 2.2 Add Midtrans configuration (server_key, client_key, is_production)
  - [x] 2.3 Add subscription URLs (success, cancel, error)
  - [x] 2.4 Add plans configuration (standard, pro)
  - [x] 2.5 Update .env.example with new variables
  - [x] 2.6 Document environment variables in README

## Phase 2: Service Layer Implementation

- [x] 3. MidtransSubscriptionService
  - [x] 3.1 Create MidtransSubscriptionService class
  - [x] 3.2 Implement constructor with config loading
  - [x] 3.3 Implement createSubscription() method
  - [x] 3.4 Implement getSubscription() method
  - [x] 3.5 Implement cancelSubscription() method
  - [x] 3.6 Implement updateSubscription() method
  - [x] 3.7 Implement enableSubscription() method
  - [x] 3.8 Add error handling and logging
  - [ ]* 3.9 Write unit tests for MidtransSubscriptionService

- [x] 4. Enhanced SubscriptionService
  - [x] 4.1 Add processMidtransWebhook() method
  - [x] 4.2 Add createMidtransSubscription() method
  - [x] 4.3 Add updateFromMidtransWebhook() method
  - [x] 4.4 Update getUserSubscriptionStatus() to support Midtrans
  - [x] 4.5 Update canAccessFeature() to support Midtrans
  - [x] 4.6 Add helper methods for Midtrans data mapping
  - [ ]* 4.7 Write unit tests for enhanced methods

## Phase 3: Controller Implementation

- [x] 5. SubscriptionController
  - [x] 5.1 Create SubscriptionController class
  - [x] 5.2 Implement index() method (pricing page)
  - [x] 5.3 Implement createCheckout() method
  - [x] 5.4 Implement success() callback method
  - [x] 5.5 Implement cancel() callback method
  - [x] 5.6 Implement error() callback method
  - [x] 5.7 Implement manage() method (subscription management)
  - [x] 5.8 Implement cancelSubscription() method
  - [x] 5.9 Add validation and error handling
  - [x]* 5.10 Write controller tests

- [x] 6. Enhanced MidtransWebhookController
  - [x] 6.1 Add handleSubscriptionWebhook() method
  - [x] 6.2 Implement webhook signature validation
  - [x] 6.3 Implement event routing logic
  - [x] 6.4 Add support for payment notification events
  - [x] 6.5 Add support for recurring notification events
  - [x] 6.6 Add support for pay account notification events
  - [x] 6.7 Add idempotency handling
  - [x] 6.8 Add comprehensive logging
  - [ ]* 6.9 Write webhook controller tests

## Phase 4: Frontend Updates

- [x] 7. Landing Page Updates
  - [x] 7.1 Update pricing section in welcome.blade.php
  - [x] 7.2 Add subscription buttons with Midtrans integration
  - [x] 7.3 Update CTA buttons to point to new routes
  - [x] 7.4 Add loading states for checkout process
  - [ ]* 7.5 Test responsive design

- [x] 8. Subscription Management Views
  - [x] 8.1 Create subscription/pricing.blade.php view
  - [x] 8.2 Create subscription/manage.blade.php view
  - [x] 8.3 Create subscription/success.blade.php view
  - [x] 8.4 Create subscription/cancel.blade.php view
  - [x] 8.5 Create subscription/error.blade.php view
  - [x] 8.6 Add subscription status display in dashboard
  - [x] 8.7 Add cancel subscription UI

## Phase 5: Routes & Middleware

- [x] 9. Routes Configuration
  - [x] 9.1 Add subscription routes in routes/web.php
  - [x] 9.2 Add webhook route in routes/api.php
  - [x] 9.3 Apply auth middleware to subscription routes
  - [x] 9.4 Exclude webhook route from CSRF protection
  - [x] 9.5 Add rate limiting to webhook endpoint
  - [x] 9.6 Test all routes

## Phase 6: Testing

- [ ]* 10. Unit Tests
  - [ ]* 10.1 Write tests for MidtransSubscriptionService
  - [ ]* 10.2 Write tests for enhanced SubscriptionService
  - [ ]* 10.3 Write tests for SubscriptionController
  - [ ]* 10.4 Write tests for MidtransWebhookController
  - [ ]* 10.5 Write tests for Subscription model enhancements
  - [ ]* 10.6 Ensure 100% code coverage for critical paths

- [ ]* 11. Integration Tests
  - [ ]* 11.1 Test complete subscription creation flow
  - [ ]* 11.2 Test webhook processing flow
  - [ ]* 11.3 Test subscription cancellation flow
  - [ ]* 11.4 Test payment success scenario
  - [ ]* 11.5 Test payment failure scenario
  - [ ]* 11.6 Test recurring payment scenario
  - [ ]* 11.7 Test backward compatibility with Polar

- [ ]* 12. Property-Based Tests
  - [ ]* 12.1 Test subscription creation idempotency
  - [ ]* 12.2 Test webhook signature validation
  - [ ]* 12.3 Test subscription status consistency
  - [ ]* 12.4 Test trial period calculation
  - [ ]* 12.5 Test feature access control

## Phase 7: Documentation

- [x] 13. Technical Documentation
  - [x] 13.1 Document API integration details
  - [x] 13.2 Document webhook event handling
  - [x] 13.3 Document configuration options
  - [x] 13.4 Document error handling strategies
  - [x] 13.5 Create architecture diagrams
  - [x] 13.6 Document testing procedures

- [x] 14. User Documentation
  - [x] 14.1 Create subscription user guide
  - [x] 14.2 Document payment methods
  - [x] 14.3 Document cancellation process
  - [x] 14.4 Create FAQ section
  - [x] 14.5 Add troubleshooting guide

## Phase 8: Deployment Preparation

- [x] 15. Midtrans Account Setup
  - [x] 15.1 Create Midtrans sandbox account
  - [x] 15.2 Configure sandbox settings
  - [x] 15.3 Setup webhook URLs in sandbox
  - [ ]* 15.4 Test with sandbox credentials
  - [x] 15.5 Create production Midtrans account
  - [x] 15.6 Configure production settings
  - [x] 15.7 Setup webhook URLs in production

- [x] 16. Environment Configuration
  - [x] 16.1 Add Midtrans credentials to staging .env
  - [x] 16.2 Add Midtrans credentials to production .env
  - [x] 16.3 Configure webhook URLs
  - [x] 16.4 Configure redirect URLs
  - [ ]* 16.5 Test environment variables loading

- [x] 17. Monitoring Setup
  - [x] 17.1 Add logging for subscription events
  - [x] 17.2 Add logging for webhook events
  - [x] 17.3 Setup error alerting
  - [x] 17.4 Setup performance monitoring
  - [x] 17.5 Create monitoring dashboard
  - [x] 17.6 Document monitoring procedures

## Phase 9: Staging Deployment

- [x] 18. Staging Deployment
  - [x] 18.1 Deploy code to staging
  - [x] 18.2 Run database migrations
  - [x] 18.3 Verify configuration
  - [ ]* 18.4 Test subscription creation
  - [ ]* 18.5 Test webhook delivery
  - [ ]* 18.6 Test all payment scenarios
  - [ ]* 18.7 Test cancellation flow
  - [x] 18.8 Verify backward compatibility
  - [x] 18.9 Performance testing
  - [x] 18.10 Security testing

## Phase 10: Production Deployment

- [ ] 19. Production Deployment
  - [ ] 19.1 Create deployment checklist
  - [ ] 19.2 Schedule maintenance window
  - [ ] 19.3 Backup database
  - [ ] 19.4 Deploy code to production
  - [ ] 19.5 Run database migrations
  - [ ] 19.6 Verify configuration
  - [ ]* 19.7 Test subscription creation
  - [ ] 19.8 Verify webhook delivery
  - [ ] 19.9 Monitor for errors
  - [ ] 19.10 Verify existing Polar subscriptions still work

- [ ] 20. Post-Deployment
  - [ ] 20.1 Monitor subscription creation rate
  - [ ] 20.2 Monitor webhook processing
  - [ ] 20.3 Monitor error rates
  - [ ] 20.4 Monitor performance metrics
  - [ ] 20.5 Collect user feedback
  - [ ] 20.6 Address any issues
  - [ ] 20.7 Document lessons learned
  - [ ] 20.8 Plan for future enhancements

## Notes

### Priority Levels
- **Critical**: Must be completed for MVP
- **High**: Important for production readiness
- **Medium**: Nice to have, can be done post-launch
- **Low**: Future enhancements

### Dependencies
- Tasks 1-2 must be completed before starting Phase 2
- Tasks 3-4 must be completed before starting Phase 3
- Tasks 5-6 must be completed before starting Phase 4
- All implementation tasks must be completed before Phase 6 (Testing)
- Testing must be completed before Phase 9 (Staging Deployment)

### Estimated Timeline
- Phase 1: 2 days
- Phase 2: 5 days
- Phase 3: 4 days
- Phase 4: 3 days
- Phase 5: 1 day
- Phase 6: 5 days
- Phase 7: 2 days
- Phase 8: 2 days
- Phase 9: 3 days
- Phase 10: 2 days

**Total: ~29 days (approximately 6 weeks)**

### Risk Mitigation
- Keep Polar integration intact for rollback
- Test thoroughly in sandbox before production
- Deploy during low-traffic hours
- Have rollback plan ready
- Monitor closely for first 48 hours after deployment
