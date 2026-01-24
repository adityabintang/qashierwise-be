# Security Testing Guide

## Overview
This guide provides comprehensive security testing procedures for the Midtrans subscription integration to ensure data protection, authentication, and authorization are properly implemented.

## Security Requirements

### Authentication & Authorization
- All subscription endpoints require authentication
- Users can only access their own subscription data
- Webhook endpoints are public but validated
- Admin functions properly protected

### Data Protection
- API credentials stored in environment variables
- Sensitive data not logged
- HTTPS enforced for all communications
- Database credentials encrypted

### Input Validation
- All user inputs validated
- Webhook signatures verified
- SQL injection prevention
- XSS protection

### Rate Limiting
- Webhook endpoints rate limited
- API endpoints throttled
- Brute force protection

## Security Testing Tools

### 1. Built-in Security Script

```bash
# Run automated security tests
php security_test.php
```

This script tests:
- Environment variable security
- Webhook signature validation
- Route protection
- CSRF protection
- HTTPS enforcement
- Sensitive data logging
- Rate limiting
- Input validation
- Authorization checks

### 2. OWASP ZAP (Zed Attack Proxy)

Install OWASP ZAP:
```bash
# Download from https://www.zaproxy.org/download/
```

Run automated scan:
```bash
# Start ZAP in daemon mode
zap.sh -daemon -port 8080

# Run spider scan
zap-cli spider https://staging.yourapp.com

# Run active scan
zap-cli active-scan https://staging.yourapp.com

# Generate report
zap-cli report -o security_report.html -f html
```

### 3. Burp Suite

Use Burp Suite for:
- Manual security testing
- Request interception
- Parameter tampering
- Session management testing

### 4. SQLMap (SQL Injection Testing)

```bash
# Test for SQL injection
sqlmap -u "https://staging.yourapp.com/subscription/checkout" \
  --cookie="session=YOUR_SESSION" \
  --data="plan_id=standard" \
  --level=5 --risk=3
```

## Security Test Scenarios

### Scenario 1: Authentication Testing

**Objective**: Verify authentication is properly enforced

**Test Steps**:

1. **Test unauthenticated access**:
```bash
# Should return 401/302
curl -X GET https://staging.yourapp.com/subscription/manage

# Should return 401/302
curl -X POST https://staging.yourapp.com/subscription/checkout \
  -d "plan_id=standard"
```

2. **Test with invalid token**:
```bash
curl -X GET https://staging.yourapp.com/subscription/manage \
  -H "Authorization: Bearer invalid_token"
```

3. **Test with expired token**:
```bash
# Use an expired JWT token
curl -X GET https://staging.yourapp.com/subscription/manage \
  -H "Authorization: Bearer EXPIRED_TOKEN"
```

**Expected Results**:
- All requests without valid authentication rejected
- Proper error messages returned
- No sensitive data leaked in error responses

### Scenario 2: Authorization Testing

**Objective**: Verify users can only access their own data

**Test Steps**:

1. **Test accessing another user's subscription**:
```bash
# Login as User A
# Try to access User B's subscription
curl -X GET https://staging.yourapp.com/api/subscription/USER_B_ID \
  -H "Authorization: Bearer USER_A_TOKEN"
```

2. **Test modifying another user's subscription**:
```bash
curl -X POST https://staging.yourapp.com/subscription/cancel \
  -H "Authorization: Bearer USER_A_TOKEN" \
  -d "subscription_id=USER_B_SUBSCRIPTION_ID"
```

**Expected Results**:
- Access denied (403 Forbidden)
- No data from other users returned
- Proper authorization checks in place

### Scenario 3: Webhook Signature Validation

**Objective**: Verify webhook signatures are properly validated

**Test Steps**:

1. **Test with valid signature**:
```bash
# Calculate valid signature
ORDER_ID="test_123"
STATUS_CODE="200"
GROSS_AMOUNT="99000.00"
SERVER_KEY="your_server_key"

SIGNATURE=$(echo -n "${ORDER_ID}${STATUS_CODE}${GROSS_AMOUNT}${SERVER_KEY}" | sha512sum | cut -d' ' -f1)

curl -X POST https://staging.yourapp.com/api/webhooks/midtrans \
  -H "Content-Type: application/json" \
  -d "{
    \"order_id\": \"$ORDER_ID\",
    \"status_code\": \"$STATUS_CODE\",
    \"gross_amount\": \"$GROSS_AMOUNT\",
    \"signature_key\": \"$SIGNATURE\"
  }"
```

2. **Test with invalid signature**:
```bash
curl -X POST https://staging.yourapp.com/api/webhooks/midtrans \
  -H "Content-Type: application/json" \
  -d "{
    \"order_id\": \"test_123\",
    \"status_code\": \"200\",
    \"gross_amount\": \"99000.00\",
    \"signature_key\": \"invalid_signature\"
  }"
```

3. **Test with missing signature**:
```bash
curl -X POST https://staging.yourapp.com/api/webhooks/midtrans \
  -H "Content-Type: application/json" \
  -d "{
    \"order_id\": \"test_123\",
    \"status_code\": \"200\",
    \"gross_amount\": \"99000.00\"
  }"
```

**Expected Results**:
- Valid signatures accepted (200 OK)
- Invalid signatures rejected (400/401)
- Missing signatures rejected
- Proper error logging

### Scenario 4: Input Validation Testing

**Objective**: Verify all inputs are properly validated

**Test Steps**:

1. **Test SQL injection**:
```bash
curl -X POST https://staging.yourapp.com/subscription/checkout \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d "plan_id=standard' OR '1'='1"
```

2. **Test XSS**:
```bash
curl -X POST https://staging.yourapp.com/subscription/checkout \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d "plan_id=<script>alert('XSS')</script>"
```

3. **Test invalid data types**:
```bash
curl -X POST https://staging.yourapp.com/subscription/checkout \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d "plan_id=999999"
```

4. **Test missing required fields**:
```bash
curl -X POST https://staging.yourapp.com/subscription/checkout \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d ""
```

**Expected Results**:
- All malicious inputs rejected
- Proper validation error messages
- No code execution
- No database errors

### Scenario 5: CSRF Protection Testing

**Objective**: Verify CSRF protection is enabled

**Test Steps**:

1. **Test POST without CSRF token**:
```bash
curl -X POST https://staging.yourapp.com/subscription/checkout \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d "plan_id=standard"
```

2. **Test with invalid CSRF token**:
```bash
curl -X POST https://staging.yourapp.com/subscription/checkout \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-CSRF-TOKEN: invalid_token" \
  -d "plan_id=standard"
```

**Expected Results**:
- Requests without valid CSRF token rejected (419)
- Webhook endpoint exempt from CSRF
- Proper error messages

### Scenario 6: Rate Limiting Testing

**Objective**: Verify rate limiting prevents abuse

**Test Steps**:

1. **Test webhook rate limiting**:
```bash
# Send 100 requests rapidly
for i in {1..100}; do
  curl -X POST https://staging.yourapp.com/api/webhooks/midtrans \
    -H "Content-Type: application/json" \
    -d "{\"test\": \"data_$i\"}" &
done
wait
```

2. **Test API rate limiting**:
```bash
# Send multiple requests to subscription endpoint
for i in {1..60}; do
  curl -X GET https://staging.yourapp.com/subscription/manage \
    -H "Authorization: Bearer YOUR_TOKEN"
  sleep 1
done
```

**Expected Results**:
- Rate limit enforced (429 Too Many Requests)
- Proper retry-after headers
- Legitimate requests not blocked

### Scenario 7: Session Management Testing

**Objective**: Verify session security

**Test Steps**:

1. **Test session fixation**:
```bash
# Try to use a pre-set session ID
curl -X GET https://staging.yourapp.com/login \
  -H "Cookie: laravel_session=attacker_session_id"
```

2. **Test session timeout**:
```bash
# Login and wait for session timeout
# Try to access protected resource
```

3. **Test concurrent sessions**:
```bash
# Login from multiple locations
# Verify session handling
```

**Expected Results**:
- Session fixation prevented
- Sessions expire properly
- Concurrent sessions handled correctly

### Scenario 8: Sensitive Data Exposure

**Objective**: Verify sensitive data is protected

**Test Steps**:

1. **Check error messages**:
```bash
# Trigger various errors
curl -X POST https://staging.yourapp.com/subscription/checkout \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d "plan_id=invalid"
```

2. **Check API responses**:
```bash
# Verify no credentials in responses
curl -X GET https://staging.yourapp.com/api/subscription/status \
  -H "Authorization: Bearer YOUR_TOKEN"
```

3. **Check logs**:
```bash
# Review logs for exposed credentials
grep -i "server_key\|client_key\|password" storage/logs/laravel.log
```

**Expected Results**:
- No credentials in error messages
- No sensitive data in API responses
- No credentials in logs
- Proper data masking

## Security Checklist

### Pre-Deployment Security Checklist

#### Configuration
- [ ] All credentials in environment variables
- [ ] No hardcoded secrets in code
- [ ] HTTPS enforced
- [ ] Proper CORS configuration
- [ ] Debug mode disabled in production

#### Authentication & Authorization
- [ ] All subscription routes protected
- [ ] Webhook routes public but validated
- [ ] User can only access own data
- [ ] Admin routes properly protected
- [ ] Session security configured

#### Input Validation
- [ ] All inputs validated
- [ ] SQL injection prevention
- [ ] XSS protection
- [ ] CSRF protection enabled
- [ ] File upload validation (if applicable)

#### Data Protection
- [ ] Sensitive data encrypted
- [ ] Database credentials secure
- [ ] API keys rotated regularly
- [ ] Logs don't contain secrets
- [ ] Backups encrypted

#### Network Security
- [ ] HTTPS enforced
- [ ] TLS 1.2+ required
- [ ] Security headers configured
- [ ] Rate limiting enabled
- [ ] DDoS protection configured

#### Monitoring & Logging
- [ ] Security events logged
- [ ] Failed login attempts tracked
- [ ] Suspicious activity monitored
- [ ] Alerts configured
- [ ] Log retention policy set

## Security Headers

### Recommended Headers

Add these headers to your web server configuration:

```nginx
# Nginx configuration
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

### Verify Headers

```bash
# Check security headers
curl -I https://staging.yourapp.com

# Use online tools
# https://securityheaders.com/
# https://observatory.mozilla.org/
```

## Vulnerability Scanning

### Automated Scanning

```bash
# Run Nikto web scanner
nikto -h https://staging.yourapp.com

# Run Nmap port scan
nmap -sV -sC staging.yourapp.com

# Run WPScan (if using WordPress)
wpscan --url https://staging.yourapp.com
```

### Dependency Scanning

```bash
# Check for vulnerable dependencies
composer audit

# Check npm packages
npm audit

# Use Snyk
snyk test
```

## Penetration Testing

### Manual Testing Checklist

- [ ] Authentication bypass attempts
- [ ] Authorization bypass attempts
- [ ] Session management testing
- [ ] Input validation testing
- [ ] Business logic testing
- [ ] API security testing
- [ ] File upload testing
- [ ] Error handling testing

### Automated Penetration Testing

Consider hiring professional penetration testers or using:
- HackerOne
- Bugcrowd
- Synack

## Incident Response

### Security Incident Procedure

1. **Detect**: Monitor logs and alerts
2. **Contain**: Isolate affected systems
3. **Investigate**: Determine scope and impact
4. **Remediate**: Fix vulnerabilities
5. **Recover**: Restore normal operations
6. **Review**: Post-incident analysis

### Emergency Contacts

- Security Team: security@yourcompany.com
- DevOps Team: devops@yourcompany.com
- Management: management@yourcompany.com

## Compliance

### Data Protection Regulations

- **GDPR**: EU data protection
- **PCI DSS**: Payment card data
- **SOC 2**: Security controls
- **ISO 27001**: Information security

### Compliance Checklist

- [ ] Data encryption at rest
- [ ] Data encryption in transit
- [ ] Access controls implemented
- [ ] Audit logging enabled
- [ ] Data retention policy
- [ ] Privacy policy updated
- [ ] Terms of service updated

## Security Best Practices

### Development
1. Follow secure coding guidelines
2. Use parameterized queries
3. Validate all inputs
4. Sanitize all outputs
5. Use security linters

### Deployment
1. Use infrastructure as code
2. Automate security testing
3. Implement CI/CD security gates
4. Use secrets management
5. Enable audit logging

### Operations
1. Regular security updates
2. Vulnerability scanning
3. Penetration testing
4. Security training
5. Incident response drills

## Related Documentation

- [Staging Deployment Guide](MIDTRANS_STAGING_DEPLOYMENT.md)
- [Error Handling](ERROR_HANDLING.md)
- [Webhook Configuration](MIDTRANS_WEBHOOK_CONFIGURATION.md)
