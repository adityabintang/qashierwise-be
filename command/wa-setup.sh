#!/bin/bash
# Setup WhatsApp test account for admin@qashierwise.com
# Reads credentials from .env and upserts whatsapp_accounts + subscribes WABA webhook

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$ROOT_DIR/.env"

# Read values from .env
get_env() {
    grep "^$1=" "$ENV_FILE" | cut -d'=' -f2-
}

PHONE_NUMBER_ID=$(get_env WHATSAPP_PHONE_NUMBER_ID)
ACCESS_TOKEN=$(get_env WHATSAPP_ACCESS_TOKEN)
BUSINESS_ACCOUNT_ID=$(get_env WHATSAPP_BUSINESS_ACCOUNT_ID)
TEST_NUMBER=$(get_env WHATSAPP_BUSINESS_TEST_NUMBER)
API_VERSION=$(get_env WHATSAPP_API_VERSION)
API_VERSION=${API_VERSION:-v22.0}

if [[ -z "$PHONE_NUMBER_ID" || -z "$ACCESS_TOKEN" || -z "$BUSINESS_ACCOUNT_ID" ]]; then
    echo "ERROR: WHATSAPP_PHONE_NUMBER_ID, WHATSAPP_ACCESS_TOKEN, WHATSAPP_BUSINESS_ACCOUNT_ID must be set in .env"
    exit 1
fi

echo "=== WhatsApp Test Setup for admin@qashierwise.com ==="
echo "Phone Number ID : $PHONE_NUMBER_ID"
echo "Business Account: $BUSINESS_ACCOUNT_ID"
echo "Test Number     : $TEST_NUMBER"
echo ""

# Upsert whatsapp_accounts record via tinker.
# Uses encrypt() directly on raw DB to avoid DecryptException on stale tokens,
# and avoids DELETE to prevent CASCADE on ai_agents.
echo "[1/2] Upserting whatsapp_accounts record..."
php "$ROOT_DIR/artisan" tinker --execute="
use Illuminate\Support\Facades\DB;

\$encryptedToken = \Illuminate\Support\Facades\Crypt::encryptString('$ACCESS_TOKEN');

\$existing = DB::table('whatsapp_accounts')->where('user_id', 1)->first();
if (\$existing) {
    DB::table('whatsapp_accounts')->where('user_id', 1)->update([
        'phone_number_id'      => '$PHONE_NUMBER_ID',
        'business_account_id'  => '$BUSINESS_ACCOUNT_ID',
        'display_phone_number' => '$TEST_NUMBER',
        'name'                 => 'Test WA Account (Meta)',
        'access_token'         => \$encryptedToken,
        'is_active'            => true,
        'updated_at'           => now(),
    ]);
    echo 'Updated record id=' . \$existing->id . PHP_EOL;
} else {
    \$id = DB::table('whatsapp_accounts')->insertGetId([
        'user_id'              => 1,
        'phone_number_id'      => '$PHONE_NUMBER_ID',
        'business_account_id'  => '$BUSINESS_ACCOUNT_ID',
        'display_phone_number' => '$TEST_NUMBER',
        'name'                 => 'Test WA Account (Meta)',
        'access_token'         => \$encryptedToken,
        'is_active'            => true,
        'created_at'           => now(),
        'updated_at'           => now(),
    ]);
    echo 'Created record id=' . \$id . PHP_EOL;
}
"

# Subscribe WABA to app webhooks
echo "[2/2] Subscribing WABA to app webhooks..."
RESPONSE=$(curl -s -X POST \
    "https://graph.facebook.com/$API_VERSION/$BUSINESS_ACCOUNT_ID/subscribed_apps" \
    -H "Authorization: Bearer $ACCESS_TOKEN" \
    -H "Content-Type: application/json")

if echo "$RESPONSE" | grep -q '"success":true'; then
    echo "Webhook subscription: OK"
else
    echo "Webhook subscription response: $RESPONSE"
fi

echo ""
echo "=== Setup complete ==="
echo "Kirim pesan dari WA pribadi ke $TEST_NUMBER untuk test AI agent."
