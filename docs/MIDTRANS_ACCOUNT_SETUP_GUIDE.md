# Midtrans Account Setup Guide

This guide walks you through setting up Midtrans accounts for both sandbox (testing) and production environments for the subscription migration feature.

## Table of Contents
1. [Sandbox Account Setup](#sandbox-account-setup)
2. [Sandbox Configuration](#sandbox-configuration)
3. [Webhook URL Setup (Sandbox)](#webhook-url-setup-sandbox)
4. [Production Account Setup](#production-account-setup)
5. [Production Configuration](#production-configuration)
6. [Webhook URL Setup (Production)](#webhook-url-setup-production)
7. [Testing Your Setup](#testing-your-setup)
8. [Troubleshooting](#troubleshooting)

---

## Sandbox Account Setup

### Step 1: Create Midtrans Sandbox Account

1. **Visit Midtrans Registration Page**
   - Go to: https://dashboard.sandbox.midtrans.com/register
   - Or visit: https://midtrans.com and click "Sign Up"

2. **Fill Registration Form**
   - Business Name: Your company/project name
   - Email: Your business email
   - Phone Number: Your contact number
   - Password: Create a strong password
   - Accept Terms & Conditions

3. **Verify Email**
   - Check your email inbox
   - Click the verification link sent by Midtrans
   - Complete email verification

4. **Login to Dashboard**
   - Go to: https://dashboard.sandbox.midtrans.com
   - Login with your credentials
   - You should see the Midtrans Dashboard

### Step 2: Complete Business Profile

1. **Navigate to Settings**
   - Click on "Settings" in the left sidebar
   - Select "General Settings"

2. **Fill Business Information**
   - Business Type: Select your business type
   - Business Category: Select appropriate category
   - Business Description: Brief description of your business
   - Website URL: Your application URL
   - Business Address: Complete address information

3. **Save Settings**
   - Click "Save" to update your profile

---

## Sandbox Configuration

### Step 3: Get API Credentials

1. **Navigate to Access Keys**
   - In the dashboard, go to "Settings" → "Access Keys"
   - You'll see two types of keys:
     - **Server Key**: Used for backend API calls
     - **Client Key**: Used for frontend integration

2. **Copy Credentials**
   - Copy the **Server Key** (starts with `SB-Mid-server-`)
   - Copy the **Client Key** (starts with `SB-Mid-client-`)
   - Store these securely - you'll need them for configuration

3. **Update .env File**
   ```env
   # Midtrans Sandbox Configuration
   MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxxxxxxxxxx
   MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxxxxxxxxxx
   MIDTRANS_IS_PRODUCTION=false
   
   # Subscription URLs (update with your domain)
   MIDTRANS_SUBSCRIPTION_SUCCESS_URL=http://localhost:8000/subscription/success
   MIDTRANS_SUBSCRIPTION_CANCEL_URL=http://localhost:8000/subscription/cancel
   MIDTRANS_SUBSCRIPTION_ERROR_URL=http://localhost:8000/subscription/error
   ```

### Step 4: Enable Subscription Feature

1. **Navigate to Subscription Settings**
   - Go to "Settings" → "Subscription"
   - Or check "Payment Settings" for subscription options

2. **Enable Subscription**
   - Toggle "Enable Subscription" to ON
   - Configure subscription settings if available

3. **Configure Payment Methods**
   - Go to "Settings" → "Payment Configuration"
   - Enable payment methods you want to support:
     - Credit Card (Recommended for subscriptions)
     - GoPay
     - Bank Transfer
     - Other e-wallets

---

## Webhook URL Setup (Sandbox)

### Step 5: Configure Webhook URLs

Midtrans requires three webhook URLs for subscription handling:

1. **Navigate to Webhook Configuration**
   - Go to "Settings" → "Configuration"
   - Look for "Notification URL" or "Webhook URL" section

2. **Set Webhook URLs**
   
   **For Local Development:**
   - You'll need to expose your local server using ngrok or similar tool
   - Install ngrok: https://ngrok.com/download
   - Run: `ngrok http 8000`
   - Copy the HTTPS URL (e.g., `https://abc123.ngrok.io`)

   **Configure these URLs in Midtrans:**
   
   a. **Payment Notification URL** (for first payment):
   ```
   https://your-domain.com/api/webhooks/midtrans
   ```
   Or for local testing:
   ```
   https://abc123.ngrok.io/api/webhooks/midtrans
   ```

   b. **Recurring Notification URL** (for recurring payments):
   ```
   https://your-domain.com/api/webhooks/midtrans
   ```

   c. **Pay Account Notification URL** (for account status):
   ```
   https://your-domain.com/api/webhooks/midtrans
   ```

   **Note**: All three URLs can point to the same endpoint. Our controller handles all webhook types.

3. **HTTP Method**
   - Select: **POST**

4. **Save Configuration**
   - Click "Save" or "Update"
   - Midtrans will send a test notification to verify the URL

### Step 6: Test Webhook Connection

1. **Use Midtrans Webhook Tester**
   - In the dashboard, look for "Webhook Testing" or "Test Notification"
   - Send a test webhook to verify your endpoint is reachable

2. **Check Application Logs**
   - Monitor your Laravel logs: `storage/logs/laravel.log`
   - You should see webhook received logs

3. **Verify Signature Validation**
   - Ensure test webhooks pass signature validation
   - Check for any errors in logs

---

## Production Account Setup

### Step 7: Create Midtrans Production Account

1. **Visit Midtrans Production Registration**
   - Go to: https://dashboard.midtrans.com/register
   - **Important**: This is different from sandbox!

2. **Fill Registration Form**
   - Use the same business information as sandbox
   - Email: Can be the same or different
   - Complete all required fields

3. **Verify Email**
   - Check email and verify your account

4. **Complete KYC (Know Your Customer)**
   - Midtrans requires business verification for production
   - Prepare these documents:
     - Business registration documents (SIUP, TDP, or similar)
     - Tax ID (NPWP)
     - Bank account information
     - Director/Owner ID card
     - Business address proof

5. **Submit Documents**
   - Upload all required documents
   - Wait for Midtrans verification (usually 1-3 business days)

6. **Approval Notification**
   - You'll receive email when approved
   - Login to production dashboard: https://dashboard.midtrans.com

---

## Production Configuration

### Step 8: Get Production API Credentials

1. **Login to Production Dashboard**
   - Go to: https://dashboard.midtrans.com
   - Login with production credentials

2. **Navigate to Access Keys**
   - Go to "Settings" → "Access Keys"
   - Copy production credentials:
     - **Server Key** (starts with `Mid-server-`)
     - **Client Key** (starts with `Mid-client-`)

3. **Update Production .env**
   ```env
   # Midtrans Production Configuration
   MIDTRANS_SERVER_KEY=Mid-server-xxxxxxxxxxxxxxxx
   MIDTRANS_CLIENT_KEY=Mid-client-xxxxxxxxxxxxxxxx
   MIDTRANS_IS_PRODUCTION=true
   
   # Production URLs
   MIDTRANS_SUBSCRIPTION_SUCCESS_URL=https://yourdomain.com/subscription/success
   MIDTRANS_SUBSCRIPTION_CANCEL_URL=https://yourdomain.com/subscription/cancel
   MIDTRANS_SUBSCRIPTION_ERROR_URL=https://yourdomain.com/subscription/error
   ```

### Step 9: Configure Production Settings

1. **Enable Subscription Feature**
   - Go to "Settings" → "Subscription"
   - Enable subscription functionality

2. **Configure Payment Methods**
   - Go to "Settings" → "Payment Configuration"
   - Enable production payment methods
   - Configure payment method settings

3. **Set Business Information**
   - Ensure all business details are accurate
   - Add business logo if desired
   - Configure email templates for notifications

---

## Webhook URL Setup (Production)

### Step 10: Configure Production Webhooks

1. **Navigate to Webhook Configuration**
   - In production dashboard: "Settings" → "Configuration"

2. **Set Production Webhook URLs**
   
   **Payment Notification URL:**
   ```
   https://yourdomain.com/api/webhooks/midtrans
   ```

   **Recurring Notification URL:**
   ```
   https://yourdomain.com/api/webhooks/midtrans
   ```

   **Pay Account Notification URL:**
   ```
   https://yourdomain.com/api/webhooks/midtrans
   ```

3. **Verify HTTPS**
   - Production webhooks MUST use HTTPS
   - Ensure your SSL certificate is valid
   - Test SSL: https://www.ssllabs.com/ssltest/

4. **Save Configuration**
   - Click "Save"
   - Midtrans will verify the URLs

### Step 11: Test Production Webhooks

1. **Use Test Mode in Production**
   - Some production dashboards have test notification feature
   - Send test webhook to verify connectivity

2. **Monitor Initial Transactions**
   - Create a test subscription with small amount
   - Monitor webhook delivery
   - Check application logs

---

## Testing Your Setup

### Sandbox Testing

1. **Test Subscription Creation**
   ```bash
   # Use Midtrans test credentials
   # Create a test subscription through your application
   ```

2. **Test Payment Methods**
   - Use Midtrans test cards:
     - Success: `4811 1111 1111 1114`
     - Failure: `4911 1111 1111 1113`
     - CVV: `123`
     - Expiry: Any future date

3. **Test Webhook Events**
   - Complete a test payment
   - Verify webhook is received
   - Check subscription status updates

### Production Testing

1. **Small Amount Test**
   - Create subscription with minimum amount
   - Complete real payment
   - Verify webhook delivery
   - Check subscription activation

2. **Recurring Payment Test**
   - Wait for first recurring payment (or trigger manually if possible)
   - Verify recurring webhook received
   - Check subscription renewal

---

## Troubleshooting

### Common Issues

#### 1. Webhook Not Received

**Problem**: Application doesn't receive webhooks

**Solutions**:
- Verify webhook URL is publicly accessible
- Check firewall settings
- Ensure HTTPS is working (production)
- Use ngrok for local testing
- Check Midtrans dashboard for webhook delivery logs

#### 2. Signature Validation Failed

**Problem**: Webhook signature validation fails

**Solutions**:
- Verify Server Key is correct in .env
- Check signature calculation logic
- Ensure payload is not modified before validation
- Check for encoding issues

#### 3. Subscription Not Created

**Problem**: Subscription creation fails

**Solutions**:
- Verify API credentials are correct
- Check if subscription feature is enabled
- Review API request payload
- Check Midtrans API logs in dashboard
- Verify payment method is enabled

#### 4. Payment Method Not Available

**Problem**: Desired payment method not showing

**Solutions**:
- Enable payment method in dashboard
- Check payment method configuration
- Verify business category supports the method
- Contact Midtrans support if needed

#### 5. Local Development Issues

**Problem**: Can't test webhooks locally

**Solutions**:
- Use ngrok: `ngrok http 8000`
- Update webhook URL in Midtrans dashboard
- Use ngrok HTTPS URL
- Keep ngrok running during testing

### Getting Help

1. **Midtrans Documentation**
   - API Docs: https://docs.midtrans.com
   - Subscription API: https://docs.midtrans.com/reference/create-subscription

2. **Midtrans Support**
   - Email: support@midtrans.com
   - Dashboard: Use "Help" or "Support" section
   - Response time: Usually 1-2 business days

3. **Community**
   - Midtrans Developer Community
   - Stack Overflow: Tag `midtrans`

---

## Checklist

### Sandbox Setup
- [ ] Created Midtrans sandbox account
- [ ] Verified email address
- [ ] Completed business profile
- [ ] Copied Server Key and Client Key
- [ ] Updated .env with sandbox credentials
- [ ] Enabled subscription feature
- [ ] Configured payment methods
- [ ] Set up webhook URLs
- [ ] Tested webhook connectivity
- [ ] Tested subscription creation
- [ ] Tested payment flow

### Production Setup
- [ ] Created Midtrans production account
- [ ] Completed KYC verification
- [ ] Received production approval
- [ ] Copied production Server Key and Client Key
- [ ] Updated production .env
- [ ] Enabled subscription feature
- [ ] Configured payment methods
- [ ] Set up production webhook URLs
- [ ] Verified HTTPS/SSL
- [ ] Tested webhook connectivity
- [ ] Performed test transaction
- [ ] Monitored first real subscription

---

## Security Best Practices

1. **Credential Management**
   - Never commit credentials to git
   - Use environment variables
   - Rotate keys periodically
   - Use different keys for sandbox/production

2. **Webhook Security**
   - Always validate signatures
   - Use HTTPS in production
   - Implement rate limiting
   - Log suspicious requests

3. **Testing**
   - Test thoroughly in sandbox before production
   - Use test cards in sandbox only
   - Monitor production closely after deployment

---

## Next Steps

After completing this setup:

1. **Update Application Configuration**
   - Verify all environment variables are set
   - Test configuration loading
   - Clear config cache: `php artisan config:clear`

2. **Run Integration Tests**
   - Test subscription creation
   - Test webhook processing
   - Test payment flows

3. **Deploy to Staging**
   - Deploy with sandbox credentials
   - Test end-to-end flow
   - Verify webhook delivery

4. **Deploy to Production**
   - Update to production credentials
   - Monitor closely
   - Have rollback plan ready

---

## Support

For issues specific to this implementation, refer to:
- `docs/MIDTRANS_CONFIGURATION.md`
- `docs/MIDTRANS_WEBHOOK_HANDLING.md`
- `docs/MIDTRANS_TESTING.md`

For Midtrans-specific issues, contact Midtrans support.
