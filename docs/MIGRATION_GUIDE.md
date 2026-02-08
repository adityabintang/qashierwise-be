# QRIS BYOK Migration Guide

This guide explains how to migrate existing Midtrans QRIS users to the new BYOK (Bring Your Own Key) system.

## Overview

The BYOK system allows users to provide their own payment provider credentials with enhanced security through AES-256 encryption and Row Level Security (RLS). This migration preserves all existing transaction history while upgrading to the new secure system.

## Migration Methods

### Method 1: Web UI (Recommended for End Users)

The web-based migration wizard provides a user-friendly step-by-step process:

1. **Automatic Detection**: Users who need migration will see a prompt when accessing QRIS features
2. **Migration Wizard**: Navigate to `/dashboard/sub-merchant/migrate` or click "Migrate Now" in the prompt
3. **Step-by-Step Process**:
   - **Step 1**: Introduction explaining the benefits and what's changing
   - **Step 2**: Enter Midtrans credentials (Server Key and Client Key)
   - **Step 3**: Success confirmation with migration details

#### Features:
- Real-time credential validation
- Clear error messages
- Transaction history preservation
- Secure credential encryption
- Option to skip and migrate later

#### Migration Prompt:
- Automatically shown to eligible users
- Can be dismissed for 24 hours
- Reappears until migration is complete
- Includes transaction count and benefits

### Method 2: Console Command (For Administrators)

The console command provides bulk migration capabilities and administrative control:

```bash
# View eligible users (dry run)
php artisan qris:migrate-to-byok --dry-run

# Migrate specific user
php artisan qris:migrate-to-byok --user-id=123

# Migrate all eligible users (interactive)
php artisan qris:migrate-to-byok --all

# Rollback migration for a user
php artisan qris:migrate-to-byok --rollback=123
```

#### Command Features:
- Identifies eligible users automatically
- Interactive credential input
- Real-time validation
- Transaction history migration
- Rollback capability
- Detailed progress reporting

## Eligibility Criteria

A user is eligible for migration if they meet ALL of the following:
1. Has a sub-merchant account
2. Has existing QRIS transactions with Midtrans
3. Does NOT have BYOK credentials configured yet

## Migration Process

### What Happens During Migration:

1. **Credential Validation**
   - Credentials are validated against Midtrans API
   - Invalid credentials are rejected before storage
   - User is notified of validation results

2. **Secure Storage**
   - Credentials are encrypted with AES-256
   - User-specific encryption key is generated
   - Encrypted data is stored with RLS protection

3. **Provider Activation**
   - Midtrans is set as the active provider
   - Connection status is updated to "valid"

4. **Transaction History**
   - Existing transactions are updated with provider information
   - `provider` field set to "midtrans"
   - `provider_transaction_id` populated from `midtrans_transaction_id`
   - All transaction data is preserved

5. **Audit Logging**
   - Migration event is logged
   - Credential access is tracked
   - User actions are recorded

### What is Preserved:

✅ All transaction history
✅ Transaction amounts and fees
✅ Settlement status
✅ QR code URLs
✅ Order IDs and references
✅ Sub-merchant balance

### What Changes:

- Users must provide their own Midtrans credentials
- Credentials are encrypted and stored securely
- Provider information is added to transactions
- Users gain access to multi-provider management

## API Endpoints

### Check Migration Status
```http
GET /api/sub-merchant/migration/status
Authorization: Bearer {token}
```

**Response:**
```json
{
  "needs_migration": true,
  "transaction_count": 15,
  "message": "You need to configure your own Midtrans credentials to continue using QRIS."
}
```

### Perform Migration
```http
POST /api/sub-merchant/migration/migrate
Authorization: Bearer {token}
Content-Type: application/json

{
  "server_key": "SB-Mid-server-xxxxxxxxxxxxxxxx",
  "client_key": "SB-Mid-client-xxxxxxxxxxxxxxxx"
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Migration completed successfully!",
  "transactions_updated": 15
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Credential validation failed: Invalid server key",
  "validation_error": "Invalid server key"
}
```

### Skip Migration
```http
POST /api/sub-merchant/migration/skip
Authorization: Bearer {token}
```

## Obtaining Midtrans Credentials

Users need to obtain their credentials from Midtrans Dashboard:

1. Log in to [Midtrans Dashboard](https://dashboard.midtrans.com/)
2. Navigate to **Settings → Access Keys**
3. Copy the **Server Key** and **Client Key**
4. Use these credentials during migration

**Note:** 
- Sandbox keys start with `SB-Mid-`
- Production keys start with `Mid-`
- Both environments are supported

## Rollback Process

If migration needs to be reversed:

```bash
php artisan qris:migrate-to-byok --rollback=123
```

**What happens during rollback:**
- BYOK credentials are deleted
- User encryption key is removed
- Transaction history is preserved (provider field remains)
- User must reconfigure to use QRIS again

**Note:** Rollback should only be used in case of errors. Transaction history is always preserved.

## Security Features

### Encryption
- **Algorithm**: AES-256-CBC
- **Key Management**: Per-user encryption keys
- **Storage**: Keys stored separately from encrypted data
- **Master Key**: Application-level master key for key encryption

### Row Level Security (RLS)
- Database-level access control
- Users can only access their own credentials
- Automatic user association on insert
- Query filtering at database level

### Audit Logging
- All credential operations logged
- Includes user, action, timestamp, IP address
- Failed attempts tracked
- RLS violations recorded

## Troubleshooting

### Common Issues

**Issue: "Credential validation failed"**
- Verify credentials are correct
- Check if using correct environment (sandbox vs production)
- Ensure Midtrans account is active
- Try copying credentials again

**Issue: "User not eligible for migration"**
- User must have sub-merchant account
- User must have existing transactions
- User must not have BYOK credentials already

**Issue: "Migration prompt keeps appearing"**
- Complete the migration process
- Or dismiss for 24 hours
- Check browser localStorage for `migration_prompt_dismissed`

**Issue: "Cannot generate QRIS after migration"**
- Verify credentials are valid
- Check active provider is set
- Revalidate credentials in provider settings
- Check audit logs for errors

## Testing Migration

### Test in Sandbox Environment

1. Create test user with sub-merchant account
2. Generate test QRIS transactions using old system
3. Run migration with sandbox credentials
4. Verify:
   - Credentials stored and encrypted
   - Transactions updated with provider info
   - Can generate new QRIS codes
   - Transaction history intact

### Validation Checklist

- [ ] User can complete migration wizard
- [ ] Invalid credentials are rejected
- [ ] Valid credentials are accepted
- [ ] Transaction count matches before/after
- [ ] Can generate QRIS after migration
- [ ] Provider settings show Midtrans as active
- [ ] Audit logs show migration event
- [ ] Skip functionality works
- [ ] Migration prompt appears for eligible users
- [ ] Prompt can be dismissed

## Support

For issues or questions:
1. Check audit logs: `credential_access_logs` table
2. Review Laravel logs: `storage/logs/laravel.log`
3. Verify database state: Check `payment_provider_credentials` table
4. Test credential validation manually via API

## Future Enhancements

The BYOK system supports multiple providers:
- ✅ Midtrans (available now)
- 🔜 Doku (coming soon)
- 🔜 Xendit (coming soon)
- 🔜 Duitku (coming soon)

Users can configure multiple providers and switch between them after migration.
