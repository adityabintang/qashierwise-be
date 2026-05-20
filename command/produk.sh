#!/usr/bin/env bash
# command/produk.sh
# ─────────────────────────────────────────────────────────────────────────────
# Interactive WhatsApp catalog cache health checker.
#   1. Lists all catalogs accessible to the admin's Meta business
#   2. Prompts you to pick one
#   3. Picks the first product in that catalog and tries to send an interactive
#      single-product message to +62 822-8418-4525 (Aan, allowlisted recipient)
#   4. Reports whether WhatsApp's internal catalog cache is in sync
#
# Requires `command/credential.json` with { token, phone_number_id, waba_id }.
# Uses only curl + php (CLI) — no Laravel boot.
# ─────────────────────────────────────────────────────────────────────────────

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CRED_FILE="${SCRIPT_DIR}/credential.json"
TARGET_PHONE="6282284184525"
API_VERSION="v22.0"
GRAPH="https://graph.facebook.com/${API_VERSION}"

# ─── Style helpers ───────────────────────────────────────────────────────────
if [ -t 1 ]; then
  BOLD=$'\033[1m'; DIM=$'\033[2m'; RESET=$'\033[0m'
  GREEN=$'\033[32m'; RED=$'\033[31m'; YELLOW=$'\033[33m'; CYAN=$'\033[36m'
else
  BOLD=''; DIM=''; RESET=''; GREEN=''; RED=''; YELLOW=''; CYAN=''
fi

bail() { echo "${RED}✗ $*${RESET}" >&2; exit 1; }
info() { echo "${CYAN}» $*${RESET}"; }
ok()   { echo "${GREEN}✓ $*${RESET}"; }
warn() { echo "${YELLOW}⚠ $*${RESET}"; }

json_get() { php -r '$j=json_decode(file_get_contents("php://stdin"),true); $p='"$1"'; foreach($p as $k){$j=is_array($j)?($j[$k]??null):null;} echo is_scalar($j)?$j:""; '; }

# ─── Pre-flight ──────────────────────────────────────────────────────────────
[ -f "$CRED_FILE" ] || bail "Credential file not found: $CRED_FILE"
command -v curl >/dev/null || bail "curl not installed"
command -v php  >/dev/null || bail "php not installed"

TOKEN=$(php -r 'echo json_decode(file_get_contents("'"$CRED_FILE"'"))->token;')
PHONE_ID=$(php -r 'echo json_decode(file_get_contents("'"$CRED_FILE"'"))->phone_number_id;')
WABA_ID=$(php -r 'echo json_decode(file_get_contents("'"$CRED_FILE"'"))->waba_id;')

[ -n "$TOKEN" ]    || bail "token kosong di credential.json"
[ -n "$PHONE_ID" ] || bail "phone_number_id kosong di credential.json"
[ -n "$WABA_ID" ]  || bail "waba_id kosong di credential.json"

echo "${BOLD}WhatsApp Catalog Cache Tester${RESET}"
echo "${DIM}target nomor : +${TARGET_PHONE}"
echo "phone_number_id : ${PHONE_ID}"
echo "waba_id         : ${WABA_ID}${RESET}"
echo ""

# ─── 1. Resolve business_id ──────────────────────────────────────────────────
info "Resolving business_id from WABA..."
WABA_INFO=$(curl -sS "${GRAPH}/${WABA_ID}?fields=owner_business_info,name&access_token=${TOKEN}")
BUSINESS_ID=$(echo "$WABA_INFO" | json_get '["owner_business_info","id"]')

if [ -z "$BUSINESS_ID" ]; then
  warn "owner_business_info kosong; coba /me/businesses..."
  ME_INFO=$(curl -sS "${GRAPH}/me/businesses?access_token=${TOKEN}")
  BUSINESS_ID=$(echo "$ME_INFO" | json_get '["data",0,"id"]')
fi

[ -n "$BUSINESS_ID" ] || bail "Tidak dapat resolve business_id. Token mungkin kurang scope business_management."
ok "business_id: ${BUSINESS_ID}"
echo ""

# ─── 2. List catalogs ────────────────────────────────────────────────────────
info "Listing catalogs (owned + client + shared)..."
ALL_CATALOGS_JSON=$(php -r '
  $tok = "'"$TOKEN"'"; $biz = "'"$BUSINESS_ID"'"; $api = "'"$API_VERSION"'";
  $out = ["data"=>[]];
  $seen = [];
  foreach (["owned_product_catalogs","client_product_catalogs","shared_product_catalogs"] as $edge) {
    $url = "https://graph.facebook.com/{$api}/{$biz}/{$edge}?fields=id,name,vertical,product_count&limit=100&access_token=".urlencode($tok);
    $ctx = stream_context_create(["http"=>["ignore_errors"=>true,"timeout"=>15]]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) continue;
    $j = json_decode($resp, true);
    foreach (($j["data"]??[]) as $c) {
      if (! isset($seen[$c["id"]])) { $seen[$c["id"]] = true; $c["_edge"]=$edge; $out["data"][] = $c; }
    }
  }
  echo json_encode($out);
')

NUM_CATALOGS=$(echo "$ALL_CATALOGS_JSON" | php -r '$j=json_decode(file_get_contents("php://stdin"),true); echo count($j["data"]??[]);')
[ "$NUM_CATALOGS" -gt 0 ] || bail "Tidak ada katalog ditemukan di business ${BUSINESS_ID}"

ok "Ditemukan ${NUM_CATALOGS} katalog:"
echo ""
echo "$ALL_CATALOGS_JSON" | php -r '
$j=json_decode(file_get_contents("php://stdin"),true);
foreach ($j["data"]??[] as $i => $c) {
    $v = $c["vertical"] ?? "—";
    $pc = $c["product_count"] ?? "?";
    $isCommerce = strtolower($v) === "commerce";
    $marker = $isCommerce ? "✓" : "·";
    printf("  [%d] %s %-30s  vertical=%-12s  produk=%s  (id=%s)\n",
        $i+1, $marker, $c["name"]??"(no name)", $v, $pc, $c["id"]);
}
echo "\n  ", "Hanya katalog vertical=commerce yang bisa dipakai WhatsApp Messaging.\n";
'

# ─── 3. Prompt selection ─────────────────────────────────────────────────────
echo ""
read -rp "Pilih nomor katalog (1-${NUM_CATALOGS}, atau q untuk keluar): " choice
[ "${choice:-q}" = "q" ] && { echo "Batal."; exit 0; }
case "$choice" in ''|*[!0-9]*) bail "Bukan angka." ;; esac
[ "$choice" -ge 1 ] && [ "$choice" -le "$NUM_CATALOGS" ] || bail "Nomor di luar rentang."

CATALOG_ID=$(echo "$ALL_CATALOGS_JSON" | php -r '
$j=json_decode(file_get_contents("php://stdin"),true);
echo $j["data"]['"$((choice-1))"']["id"] ?? "";')
CATALOG_NAME=$(echo "$ALL_CATALOGS_JSON" | php -r '
$j=json_decode(file_get_contents("php://stdin"),true);
echo $j["data"]['"$((choice-1))"']["name"] ?? "";')
CATALOG_VERTICAL=$(echo "$ALL_CATALOGS_JSON" | php -r '
$j=json_decode(file_get_contents("php://stdin"),true);
echo $j["data"]['"$((choice-1))"']["vertical"] ?? "";')

echo ""
echo "${BOLD}Selected:${RESET} ${CATALOG_NAME} (id=${CATALOG_ID}, vertical=${CATALOG_VERTICAL})"

if [ "${CATALOG_VERTICAL,,}" != "commerce" ] 2>/dev/null && [ "$(echo "$CATALOG_VERTICAL" | tr '[:upper:]' '[:lower:]')" != "commerce" ]; then
  warn "Vertical bukan 'commerce' — WhatsApp Messaging tidak support katalog ini. Test akan gagal."
fi
echo ""

# ─── 4. Pick first product ───────────────────────────────────────────────────
info "Mengambil produk pertama dari katalog..."
PRODUCTS_JSON=$(curl -sS "${GRAPH}/${CATALOG_ID}/products?fields=retailer_id,name,price&limit=1&access_token=${TOKEN}")
SKU=$(echo "$PRODUCTS_JSON" | json_get '["data",0,"retailer_id"]')
PRODUCT_NAME=$(echo "$PRODUCTS_JSON" | json_get '["data",0,"name"]')
PRODUCT_PRICE=$(echo "$PRODUCTS_JSON" | json_get '["data",0,"price"]')

[ -n "$SKU" ] || bail "Katalog kosong / tidak ada produk untuk dijadikan probe."

echo "  produk : ${PRODUCT_NAME}"
echo "  sku    : ${SKU}"
echo "  harga  : ${PRODUCT_PRICE}"
echo ""

# ─── 5. Send probe (interactive single product) ──────────────────────────────
info "Mengirim probe sendSingleProduct ke +${TARGET_PHONE}..."
PROBE_PAYLOAD=$(cat <<JSON
{
  "messaging_product": "whatsapp",
  "to": "${TARGET_PHONE}",
  "type": "interactive",
  "interactive": {
    "type": "product",
    "body": { "text": "🔍 Cache test — ${PRODUCT_NAME}" },
    "footer": { "text": "produk.sh probe" },
    "action": {
      "catalog_id": "${CATALOG_ID}",
      "product_retailer_id": "${SKU}"
    }
  }
}
JSON
)

RESP=$(curl -sS -X POST "${GRAPH}/${PHONE_ID}/messages" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -d "$PROBE_PAYLOAD")

WAMID=$(echo "$RESP" | json_get '["messages",0,"id"]')

echo ""
if [ -n "$WAMID" ]; then
  echo "${BOLD}${GREEN}╭──────────────────────────────────────────────╮${RESET}"
  echo "${BOLD}${GREEN}│  ✅ CACHE VALID — sendSingleProduct sukses   │${RESET}"
  echo "${BOLD}${GREEN}╰──────────────────────────────────────────────╯${RESET}"
  echo "  message_id : ${WAMID}"
  echo "  Cek nomor +${TARGET_PHONE} — harusnya menerima Single Product card."
  echo ""
  echo "  ${DIM}→ Cache WhatsApp sudah sinkron dengan Meta Catalog."
  echo "  → MPM (sendMultiProduct) & flow catalog di chatbot juga akan jalan.${RESET}"
  exit 0
fi

# Failure path — decode error
ERR_CODE=$(echo "$RESP" | json_get '["error","code"]')
ERR_SUB=$(echo "$RESP"  | json_get '["error","error_subcode"]')
ERR_MSG=$(echo "$RESP"  | json_get '["error","message"]')
ERR_DET=$(echo "$RESP"  | json_get '["error","error_data","details"]')
FB_TRACE=$(echo "$RESP" | json_get '["error","fbtrace_id"]')

echo "${BOLD}${RED}╭──────────────────────────────────────────────╮${RESET}"
echo "${BOLD}${RED}│  ❌ CACHE BELUM VALID                        │${RESET}"
echo "${BOLD}${RED}╰──────────────────────────────────────────────╯${RESET}"
printf "  %-14s : %s\n" "code"        "${ERR_CODE:-?}"
printf "  %-14s : %s\n" "subcode"     "${ERR_SUB:-—}"
printf "  %-14s : %s\n" "message"     "${ERR_MSG:-?}"
printf "  %-14s : %s\n" "details"     "${ERR_DET:-—}"
printf "  %-14s : %s\n" "fbtrace_id"  "${FB_TRACE:-—}"
echo ""

case "$ERR_CODE" in
  131009)
    echo "  ${YELLOW}Diagnosis:${RESET} WhatsApp internal cache untuk catalog ini belum sync."
    echo "  ${YELLOW}Solusi:${RESET}    Tunggu — biasanya:"
    echo "             • catalog baru atau setelah mass update: 14-24 jam"
    echo "             • update kecil (price/image single product): 30 menit – 4 jam"
    echo "             Test number Meta cenderung lebih lambat."
    echo "             Tidak ada force-resync trigger; tidak ada API status."
    ;;
  131030)
    echo "  ${YELLOW}Diagnosis:${RESET} Nomor recipient (+${TARGET_PHONE}) belum di-allowlist test number."
    echo "  ${YELLOW}Solusi:${RESET}    Tambahkan di Meta Developer Console → WhatsApp → API Setup → 'To' field."
    ;;
  190|0)
    echo "  ${YELLOW}Diagnosis:${RESET} Token expired atau invalid."
    echo "  ${YELLOW}Solusi:${RESET}    Generate ulang token di Meta Developer Console → WhatsApp → API Setup."
    echo "             Update field 'token' di command/credential.json."
    ;;
  100)
    echo "  ${YELLOW}Diagnosis:${RESET} Parameter invalid — kemungkinan catalog_id atau retailer_id salah format."
    ;;
  *)
    echo "  ${YELLOW}Diagnosis:${RESET} Error tidak dikenali. Cek detail di atas."
    ;;
esac

echo ""
echo "${DIM}Raw response:${RESET}"
echo "$RESP" | php -r 'echo json_encode(json_decode(file_get_contents("php://stdin")), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);'
echo ""
exit 1
