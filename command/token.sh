#!/bin/bash
# Inject WhatsApp DEV credentials (Graph Explorer / personal Meta app) into
# the database. Use this when the short-lived token from your personal dev app
# expires and you need to re-inject.
#
# Reads credentials from command/credential.json:
#   {
#     "token": "EAA...",
#     "display_number": "+1 (555) 641-8500",
#     "phone_number_id": "1143578858838092",
#     "waba_id": "1529086785239495",
#     "target_email": "admin@qashierwise.com"
#   }
#
# What this script does:
#   1. Parses credential.json (jq required)
#   2. Validates token via Graph API debug_token
#   3. Validates phone_number_id + waba_id accessibility
#   4. Upserts whatsapp_accounts row for target_email (user_id resolved by email)
#   5. Resets catalog_business_id so it gets re-resolved on next catalog call
#   6. Subscribes the WABA to webhook events
#
# Re-run safely any time the token expires.

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
CRED_FILE="$SCRIPT_DIR/credential.json"
ENV_FILE="$ROOT_DIR/.env"

if [[ ! -f "$CRED_FILE" ]]; then
    echo "ERROR: $CRED_FILE not found."
    echo "Create it with the structure shown in the header of this script."
    exit 1
fi

if ! command -v jq >/dev/null 2>&1; then
    echo "ERROR: jq is not installed. Install with: sudo apt install jq (Debian/Ubuntu)"
    echo "                                          sudo dnf install jq  (Fedora)"
    exit 1
fi

# ---- Parse credential.json -------------------------------------------------
TOKEN=$(jq -r '.token // empty' "$CRED_FILE")
DISPLAY_NUMBER=$(jq -r '.display_number // empty' "$CRED_FILE")
PHONE_NUMBER_ID=$(jq -r '.phone_number_id // empty' "$CRED_FILE")
WABA_ID=$(jq -r '.waba_id // empty' "$CRED_FILE")
TARGET_EMAIL=$(jq -r '.target_email // "admin@qashierwise.com"' "$CRED_FILE")

if [[ -z "$TOKEN" || -z "$PHONE_NUMBER_ID" || -z "$WABA_ID" ]]; then
    echo "ERROR: credential.json missing required keys."
    echo "  Required: token, phone_number_id, waba_id"
    echo "  Parsed:"
    echo "    token           : ${TOKEN:0:20}... (length=${#TOKEN})"
    echo "    display_number  : $DISPLAY_NUMBER"
    echo "    phone_number_id : $PHONE_NUMBER_ID"
    echo "    waba_id         : $WABA_ID"
    echo "    target_email    : $TARGET_EMAIL"
    exit 1
fi

API_VERSION="v25.0"
if [[ -f "$ENV_FILE" ]]; then
    ENV_API_VERSION=$(grep "^WHATSAPP_API_VERSION=" "$ENV_FILE" | cut -d'=' -f2- || true)
    [[ -n "$ENV_API_VERSION" ]] && API_VERSION="$ENV_API_VERSION"
fi

echo "=== WhatsApp DEV Token Injection ==="
echo "Target user (email) : $TARGET_EMAIL"
echo "Phone Number ID     : $PHONE_NUMBER_ID"
echo "WABA ID             : $WABA_ID"
echo "Display number      : ${DISPLAY_NUMBER:-(not specified)}"
echo "Graph API version   : $API_VERSION"
echo "Token preview       : ${TOKEN:0:20}...${TOKEN: -10}"
echo ""

# ---- Validate token via Graph API ------------------------------------------
echo "[1/5] Validating token (debug_token)..."
DEBUG_RESPONSE=$(curl -s "https://graph.facebook.com/${API_VERSION}/debug_token?input_token=${TOKEN}&access_token=${TOKEN}")
IS_VALID=$(echo "$DEBUG_RESPONSE" | jq -r '.data.is_valid // false')

if [[ "$IS_VALID" != "true" ]]; then
    echo "  ERROR: Token is not valid. Response:"
    echo "  $DEBUG_RESPONSE"
    exit 1
fi

EXPIRES_AT=$(echo "$DEBUG_RESPONSE" | jq -r '.data.expires_at // empty')
SCOPES=$(echo "$DEBUG_RESPONSE" | jq -r '.data.scopes // [] | join(", ")')

if [[ -n "$EXPIRES_AT" && "$EXPIRES_AT" != "0" ]]; then
    echo "  Token valid. Expires: $(date -d @"$EXPIRES_AT" 2>/dev/null || echo "$EXPIRES_AT")"
else
    echo "  Token valid. (No expiry / never expires)"
fi
echo "  Scopes : $SCOPES"

# Warn if critical scopes are missing
for scope in catalog_management business_management whatsapp_business_messaging; do
    if ! echo "$SCOPES" | grep -q "$scope"; then
        echo "  WARN: scope '$scope' missing — some features may fail."
    fi
done

# ---- Validate phone number resource ----------------------------------------
echo ""
echo "[2/5] Validating phone_number_id..."
PHONE_RESP=$(curl -s "https://graph.facebook.com/${API_VERSION}/${PHONE_NUMBER_ID}?fields=display_phone_number,verified_name,id&access_token=${TOKEN}")
RESP_ID=$(echo "$PHONE_RESP" | jq -r '.id // empty')
if [[ "$RESP_ID" != "$PHONE_NUMBER_ID" ]]; then
    echo "  ERROR: phone_number_id not accessible. Response:"
    echo "  $PHONE_RESP"
    exit 1
fi
echo "  $PHONE_RESP"

# ---- Validate WABA resource ------------------------------------------------
echo ""
echo "[3/5] Validating WABA..."
WABA_RESP=$(curl -s "https://graph.facebook.com/${API_VERSION}/${WABA_ID}?fields=id,name&access_token=${TOKEN}")
RESP_ID=$(echo "$WABA_RESP" | jq -r '.id // empty')
if [[ "$RESP_ID" != "$WABA_ID" ]]; then
    echo "  ERROR: WABA id not accessible. Response:"
    echo "  $WABA_RESP"
    exit 1
fi
echo "  $WABA_RESP"

# ---- Upsert whatsapp_accounts row ------------------------------------------
echo ""
echo "[4/5] Upserting whatsapp_accounts row for $TARGET_EMAIL..."

# We resolve user_id from the email at runtime so this script is portable and
# never silently writes to the wrong user. Encryption uses Crypt::encryptString
# (matching the 'encrypted' cast on WhatsAppAccount::access_token).
php "$ROOT_DIR/artisan" tinker --execute="
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

\$user = DB::table('users')->where('email', '$TARGET_EMAIL')->first();
if (! \$user) {
    fwrite(STDERR, 'ERROR: user with email $TARGET_EMAIL not found' . PHP_EOL);
    exit(1);
}

\$encryptedToken = Crypt::encryptString('$TOKEN');
\$expiresAt = '$EXPIRES_AT' ? date('Y-m-d H:i:s', (int) '$EXPIRES_AT') : null;

\$existing = DB::table('whatsapp_accounts')->where('user_id', \$user->id)->first();
\$payload = [
    'phone_number_id'        => '$PHONE_NUMBER_ID',
    'business_account_id'    => '$WABA_ID',
    'waba_id'                => '$WABA_ID',
    'display_phone_number'   => '$DISPLAY_NUMBER',
    'name'                   => 'DEV: Meta Dev App (re-injected)',
    'access_token'           => \$encryptedToken,
    'token_expires_at'       => \$expiresAt,
    'is_active'              => true,
    'catalog_business_id'    => null,
    'updated_at'             => now(),
];

if (\$existing) {
    DB::table('whatsapp_accounts')->where('id', \$existing->id)->update(\$payload);
    echo 'Updated whatsapp_accounts id=' . \$existing->id . ' for user_id=' . \$user->id . PHP_EOL;
} else {
    \$payload['user_id'] = \$user->id;
    \$payload['coexistence_enabled'] = false;
    \$payload['connection_method'] = 'manual_dev_inject';
    \$payload['created_at'] = now();
    \$id = DB::table('whatsapp_accounts')->insertGetId(\$payload);
    echo 'Created whatsapp_accounts id=' . \$id . ' for user_id=' . \$user->id . PHP_EOL;
}
" 2>&1

# ---- Subscribe WABA to app webhook ----------------------------------------
echo ""
echo "[5/5] Subscribing WABA to app webhook..."
SUB_RESP=$(curl -s -X POST \
    "https://graph.facebook.com/${API_VERSION}/${WABA_ID}/subscribed_apps" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "Content-Type: application/json")

if echo "$SUB_RESP" | jq -e '.success == true' >/dev/null 2>&1; then
    echo "  Webhook subscription: OK"
else
    echo "  Webhook subscription response: $SUB_RESP"
fi

echo ""
echo "=== Injection complete ==="
echo ""
echo "Next steps:"
echo "  1. Login as $TARGET_EMAIL"
echo "  2. Open /dashboard/meta-catalog — daftar katalog Anda akan muncul"
echo "  3. Open /dashboard/ai-agent — pilih katalog yang akan dipakai bot"
echo "  4. Tambah produk di /dashboard/meta-catalog/<id-catalog>"
echo "  5. WA pribadi Anda harus di-whitelist di Meta dev app dulu, lalu kirim 'menu' ke $DISPLAY_NUMBER"
