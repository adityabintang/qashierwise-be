# Midtrans Setup Documentation

Welcome to the Midtrans setup documentation for the subscription migration project. This README helps you navigate all the setup resources.

## 🚀 Quick Start (Choose Your Path)

### Path 1: I Want to Start NOW (5 minutes)
👉 **Read**: [`../MIDTRANS_SETUP_QUICKSTART.md`](../MIDTRANS_SETUP_QUICKSTART.md)

Perfect for developers who want to get up and running immediately with sandbox testing.

### Path 2: I Want Complete Understanding
👉 **Read**: [`MIDTRANS_ACCOUNT_SETUP_GUIDE.md`](MIDTRANS_ACCOUNT_SETUP_GUIDE.md)

Comprehensive guide covering every aspect of setup, from sandbox to production.

### Path 3: I Want a Checklist
👉 **Use**: [`MIDTRANS_SETUP_CHECKLIST.md`](MIDTRANS_SETUP_CHECKLIST.md)

Step-by-step checklist format to track your progress through setup.

## 📚 Documentation Index

### Setup Documentation
1. **[Quick Start Guide](../MIDTRANS_SETUP_QUICKSTART.md)** - 5-minute setup
2. **[Complete Setup Guide](MIDTRANS_ACCOUNT_SETUP_GUIDE.md)** - Comprehensive instructions
3. **[Setup Checklist](MIDTRANS_SETUP_CHECKLIST.md)** - Progress tracking

### Technical Documentation
4. **[Configuration Guide](MIDTRANS_CONFIGURATION.md)** - Configuration details
5. **[Webhook Handling](MIDTRANS_WEBHOOK_HANDLING.md)** - Webhook integration
6. **[Testing Guide](MIDTRANS_TESTING.md)** - Testing procedures
7. **[Architecture](MIDTRANS_ARCHITECTURE.md)** - System architecture
8. **[API Reference](MIDTRANS_SUBSCRIPTION_API.md)** - API documentation
9. **[Error Handling](MIDTRANS_ERROR_HANDLING.md)** - Error handling strategies

### Tools
10. **[Configuration Checker](../check_midtrans_config.php)** - Automated verification script

## 🎯 Setup Workflow

```
┌─────────────────────────────────────────────────────────────┐
│                    SETUP WORKFLOW                            │
└─────────────────────────────────────────────────────────────┘

1. SANDBOX SETUP (Development)
   ├── Create sandbox account
   ├── Get API credentials
   ├── Configure .env
   ├── Setup webhooks (with ngrok)
   └── Test with test cards
   
2. VERIFY CONFIGURATION
   ├── Run: php check_midtrans_config.php
   ├── Clear cache: php artisan config:clear
   └── Test subscription creation
   
3. STAGING DEPLOYMENT
   ├── Deploy with sandbox credentials
   ├── Configure staging webhooks
   ├── Run integration tests
   └── Verify end-to-end flow
   
4. PRODUCTION SETUP
   ├── Create production account
   ├── Complete KYC (1-3 days)
   ├── Get production credentials
   ├── Configure production webhooks
   └── Deploy with monitoring
```

## 🔧 Tools and Scripts

### Configuration Checker
**File**: `../check_midtrans_config.php`

Automated script that verifies your Midtrans configuration:

```bash
php check_midtrans_config.php
```

**Checks**:
- ✓ Config file existence
- ✓ Environment variables
- ✓ API credentials
- ✓ Key type validation
- ✓ Plans configuration
- ✓ URLs configuration
- ✓ Service classes
- ✓ Routes registration

## 📖 Documentation by Role

### For Developers
Start here:
1. [Quick Start Guide](../MIDTRANS_SETUP_QUICKSTART.md)
2. [Configuration Guide](MIDTRANS_CONFIGURATION.md)
3. [Webhook Handling](MIDTRANS_WEBHOOK_HANDLING.md)
4. [Testing Guide](MIDTRANS_TESTING.md)

### For DevOps/Deployment
Start here:
1. [Complete Setup Guide](MIDTRANS_ACCOUNT_SETUP_GUIDE.md)
2. [Setup Checklist](MIDTRANS_SETUP_CHECKLIST.md)
3. [Configuration Guide](MIDTRANS_CONFIGURATION.md)
4. [Error Handling](MIDTRANS_ERROR_HANDLING.md)

### For Project Managers
Start here:
1. [Setup Checklist](MIDTRANS_SETUP_CHECKLIST.md)
2. [Complete Setup Guide](MIDTRANS_ACCOUNT_SETUP_GUIDE.md)
3. [Documentation Index](MIDTRANS_DOCUMENTATION_INDEX.md)

## 🎓 Key Concepts

### Sandbox vs Production
- **Sandbox**: Testing environment with test credentials
- **Production**: Live environment with real payments
- **Key Difference**: Sandbox keys start with `SB-`, production keys start with `Mid-`

### Webhook Types
Three webhook URLs (all point to same endpoint):
1. **Payment Notification**: First payment events
2. **Recurring Notification**: Recurring payment events
3. **Pay Account Notification**: Account status events

### Subscription Plans
Configured in `config/subscription.php`:
- **Standard**: Rp 99,000/month
- **Pro**: Rp 199,000/month

## 🔐 Security Checklist

- [ ] API credentials stored in environment variables
- [ ] Credentials never committed to git
- [ ] Different keys for sandbox/production
- [ ] Webhook signature validation enabled
- [ ] HTTPS used in production
- [ ] Rate limiting configured
- [ ] Sensitive data not logged

## 🧪 Testing Checklist

### Sandbox Testing
- [ ] Subscription creation works
- [ ] Payment with test card succeeds
- [ ] Webhook received and processed
- [ ] Subscription status updates
- [ ] Cancellation works

### Production Testing
- [ ] Small amount test transaction
- [ ] Webhook delivery verified
- [ ] Subscription activation confirmed
- [ ] Recurring payment tested
- [ ] Monitoring in place

## 📊 Environment Configuration

### Local Development
```env
MIDTRANS_SERVER_KEY=SB-Mid-server-xxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxx
MIDTRANS_IS_PRODUCTION=false
```

### Staging
```env
MIDTRANS_SERVER_KEY=SB-Mid-server-xxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxx
MIDTRANS_IS_PRODUCTION=false
```

### Production
```env
MIDTRANS_SERVER_KEY=Mid-server-xxx
MIDTRANS_CLIENT_KEY=Mid-client-xxx
MIDTRANS_IS_PRODUCTION=true
```

## 🆘 Troubleshooting

### Quick Fixes

| Problem | Solution | Documentation |
|---------|----------|---------------|
| Webhook not received | Check ngrok, firewall | [Setup Guide](MIDTRANS_ACCOUNT_SETUP_GUIDE.md#troubleshooting) |
| Signature validation failed | Verify Server Key | [Webhook Handling](MIDTRANS_WEBHOOK_HANDLING.md) |
| Subscription creation failed | Check credentials | [Error Handling](MIDTRANS_ERROR_HANDLING.md) |
| Config not loading | Run `config:clear` | [Configuration Guide](MIDTRANS_CONFIGURATION.md) |

### Getting Help

1. **Check Documentation**: Start with relevant guide above
2. **Run Checker**: `php check_midtrans_config.php`
3. **Check Logs**: `tail -f storage/logs/laravel.log`
4. **Midtrans Support**: support@midtrans.com
5. **Midtrans Docs**: https://docs.midtrans.com

## 📞 Support Resources

### Official Midtrans
- **Documentation**: https://docs.midtrans.com
- **API Reference**: https://docs.midtrans.com/reference/create-subscription
- **Support Email**: support@midtrans.com
- **Sandbox Dashboard**: https://dashboard.sandbox.midtrans.com
- **Production Dashboard**: https://dashboard.midtrans.com

### Project Documentation
- **All Docs**: See files in this directory
- **Quick Start**: [`../MIDTRANS_SETUP_QUICKSTART.md`](../MIDTRANS_SETUP_QUICKSTART.md)
- **Task Summary**: [`../TASK_15_MIDTRANS_SETUP_SUMMARY.md`](../TASK_15_MIDTRANS_SETUP_SUMMARY.md)

## 🎯 Next Steps After Setup

1. **Development Phase**
   - Complete sandbox setup
   - Test subscription flows
   - Verify webhook processing
   - Run unit tests

2. **Staging Phase**
   - Deploy to staging
   - Run integration tests
   - Test all payment scenarios
   - Verify monitoring

3. **Production Phase**
   - Complete KYC
   - Get production credentials
   - Deploy to production
   - Monitor closely

## 📝 Documentation Maintenance

This documentation is maintained as part of the Midtrans subscription migration project.

**Last Updated**: January 24, 2026
**Version**: 1.0
**Status**: Complete

## 🎉 Quick Links

- 🚀 [Quick Start (5 min)](../MIDTRANS_SETUP_QUICKSTART.md)
- 📖 [Complete Guide](MIDTRANS_ACCOUNT_SETUP_GUIDE.md)
- ✅ [Checklist](MIDTRANS_SETUP_CHECKLIST.md)
- 🔧 [Config Checker](../check_midtrans_config.php)
- 📊 [Task Summary](../TASK_15_MIDTRANS_SETUP_SUMMARY.md)

---

**Ready to start?** Choose your path above and begin your Midtrans setup journey! 🚀
