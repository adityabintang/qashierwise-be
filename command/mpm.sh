#!/usr/bin/env bash
# command/mpm.sh
# ─────────────────────────────────────────────────────────────────────────────
# Interactive MPM-readiness checker for Meta product catalogs.
#   1. Resolves business_id from credentials
#   2. Lists every catalog accessible (owned + client + shared)
#   3. Prompts you to pick one
#   4. Fetches all products in the chosen catalog
#   5. Classifies each by image_fetch_status (FETCHED = MPM-ready, anything else = not)
#   6. Prints a per-product table + summary counts
#
# Requires `command/credential.json` with { token, waba_id }. Uses only curl + php.
# ─────────────────────────────────────────────────────────────────────────────

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CRED_FILE="${SCRIPT_DIR}/credential.json"
API_VERSION="v21.0"
GRAPH="https://graph.facebook.com/${API_VERSION}"

# ─── Style helpers ───────────────────────────────────────────────────────────
if [ -t 1 ]; then
  BOLD=$'\033[1m'; DIM=$'\033[2m'; RESET=$'\033[0m'
  GREEN=$'\033[32m'; RED=$'\033[31m'; YELLOW=$'\033[33m'; CYAN=$'\033[36m'; GRAY=$'\033[90m'
else
  BOLD=''; DIM=''; RESET=''; GREEN=''; RED=''; YELLOW=''; CYAN=''; GRAY=''
fi

bail() { echo "${RED}✗ $*${RESET}" >&2; exit 1; }
info() { echo "${CYAN}» $*${RESET}"; }
ok()   { echo "${GREEN}✓ $*${RESET}"; }
warn() { echo "${YELLOW}⚠ $*${RESET}"; }

# ─── Pre-flight ──────────────────────────────────────────────────────────────
[ -f "$CRED_FILE" ] || bail "Credential file not found: $CRED_FILE"
command -v curl >/dev/null || bail "curl not installed"
command -v php  >/dev/null || bail "php not installed"

TOKEN=$(php -r 'echo json_decode(file_get_contents("'"$CRED_FILE"'"))->token ?? "";')
WABA_ID=$(php -r 'echo json_decode(file_get_contents("'"$CRED_FILE"'"))->waba_id ?? "";')

[ -n "$TOKEN" ]   || bail "token kosong di credential.json"
[ -n "$WABA_ID" ] || bail "waba_id kosong di credential.json"

echo "${BOLD}MPM Readiness Checker${RESET}"
echo "${DIM}waba_id : ${WABA_ID}${RESET}"
echo ""

# ─── 1. Resolve business_id ──────────────────────────────────────────────────
info "Resolving business_id dari WABA..."
WABA_INFO=$(curl -sS "${GRAPH}/${WABA_ID}?fields=owner_business_info,name&access_token=${TOKEN}")
BUSINESS_ID=$(echo "$WABA_INFO" | php -r '$j=json_decode(file_get_contents("php://stdin"),true); echo $j["owner_business_info"]["id"] ?? "";')

if [ -z "$BUSINESS_ID" ]; then
  warn "owner_business_info kosong; coba /me/businesses..."
  ME_INFO=$(curl -sS "${GRAPH}/me/businesses?access_token=${TOKEN}")
  BUSINESS_ID=$(echo "$ME_INFO" | php -r '$j=json_decode(file_get_contents("php://stdin"),true); echo $j["data"][0]["id"] ?? "";')
fi

[ -n "$BUSINESS_ID" ] || bail "Tidak dapat resolve business_id. Token mungkin kurang scope business_management."
ok "business_id: ${BUSINESS_ID}"

# Check which catalog is currently bound to WABA (informational)
BOUND_JSON=$(curl -sS "${GRAPH}/${WABA_ID}/product_catalogs?access_token=${TOKEN}")
BOUND_ID=$(echo "$BOUND_JSON" | php -r '$j=json_decode(file_get_contents("php://stdin"),true); echo $j["data"][0]["id"] ?? "";')
BOUND_NAME=$(echo "$BOUND_JSON" | php -r '$j=json_decode(file_get_contents("php://stdin"),true); echo $j["data"][0]["name"] ?? "";')
if [ -n "$BOUND_ID" ]; then
  echo "${DIM}WABA bound ke katalog : ${BOUND_NAME} (${BOUND_ID})${RESET}"
else
  echo "${DIM}WABA tidak ter-bind ke katalog manapun${RESET}"
fi
echo ""

# ─── 2. List all catalogs across edges ──────────────────────────────────────
info "Menarik daftar katalog (owned + client + shared)..."
ALL_CATALOGS_JSON=$(php -r '
  $tok = "'"$TOKEN"'"; $biz = "'"$BUSINESS_ID"'"; $api = "'"$API_VERSION"'";
  $out = ["data"=>[]]; $seen = [];
  foreach (["owned_product_catalogs","client_product_catalogs","shared_product_catalogs"] as $edge) {
    $url = "https://graph.facebook.com/{$api}/{$biz}/{$edge}?fields=id,name,vertical,product_count&limit=100&access_token=".urlencode($tok);
    $ctx = stream_context_create(["http"=>["ignore_errors"=>true,"timeout"=>15]]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) continue;
    $j = json_decode($resp, true);
    foreach (($j["data"]??[]) as $c) {
      if (! isset($seen[$c["id"]])) { $seen[$c["id"]] = true; $out["data"][] = $c; }
    }
  }
  echo json_encode($out);
')

NUM_CATALOGS=$(echo "$ALL_CATALOGS_JSON" | php -r '$j=json_decode(file_get_contents("php://stdin"),true); echo count($j["data"]??[]);')
[ "$NUM_CATALOGS" -gt 0 ] || bail "Tidak ada katalog di business ${BUSINESS_ID}"

ok "Ditemukan ${NUM_CATALOGS} katalog:"
echo ""
echo "$ALL_CATALOGS_JSON" | BOUND_ID="$BOUND_ID" php -r '
$j=json_decode(file_get_contents("php://stdin"),true);
$boundId=getenv("BOUND_ID");
foreach ($j["data"]??[] as $i => $c) {
    $v = strtolower($c["vertical"] ?? "?");
    $pc = $c["product_count"] ?? "?";
    $name = $c["name"] ?? "(no name)";
    $id = $c["id"] ?? "?";
    $boundMarker = ($id === $boundId) ? " [BOUND]" : "";
    $verticalMarker = $v === "commerce" ? "✓" : "·";
    printf("  [%2d] %s %-30s  vertical=%-12s  produk=%-4s  id=%s%s\n",
        $i+1, $verticalMarker, mb_strimwidth($name, 0, 30, ""), $v, $pc, $id, $boundMarker);
}
'
echo ""
echo "  ${DIM}✓ = vertical commerce (eligible MPM); [BOUND] = catalog yang aktif di WABA${RESET}"
echo ""

# ─── 3. Prompt selection ─────────────────────────────────────────────────────
read -rp "Pilih nomor katalog (1-${NUM_CATALOGS}, q untuk keluar): " choice
[ "${choice:-q}" = "q" ] && { echo "Batal."; exit 0; }
case "$choice" in ''|*[!0-9]*) bail "Bukan angka." ;; esac
[ "$choice" -ge 1 ] && [ "$choice" -le "$NUM_CATALOGS" ] || bail "Nomor di luar rentang."

IDX=$((choice-1))
CATALOG_ID=$(echo "$ALL_CATALOGS_JSON" | IDX="$IDX" php -r '
$j=json_decode(file_get_contents("php://stdin"),true);
echo $j["data"][(int)getenv("IDX")]["id"] ?? "";')
CATALOG_NAME=$(echo "$ALL_CATALOGS_JSON" | IDX="$IDX" php -r '
$j=json_decode(file_get_contents("php://stdin"),true);
echo $j["data"][(int)getenv("IDX")]["name"] ?? "";')
CATALOG_VERTICAL=$(echo "$ALL_CATALOGS_JSON" | IDX="$IDX" php -r '
$j=json_decode(file_get_contents("php://stdin"),true);
echo $j["data"][(int)getenv("IDX")]["vertical"] ?? "";')

echo ""
echo "${BOLD}Selected:${RESET} ${CATALOG_NAME} (id=${CATALOG_ID}, vertical=${CATALOG_VERTICAL})"

if [ "$(echo "$CATALOG_VERTICAL" | tr '[:upper:]' '[:lower:]')" != "commerce" ]; then
  warn "Vertical bukan 'commerce' — tidak eligible untuk WhatsApp MPM."
fi
echo ""

# ─── 4. Fetch all products with paging ───────────────────────────────────────
info "Mengambil semua produk + image_fetch_status..."
PRODUCTS_JSON=$(php -r '
  $tok = "'"$TOKEN"'"; $cid = "'"$CATALOG_ID"'"; $api = "'"$API_VERSION"'";
  $url = "https://graph.facebook.com/{$api}/{$cid}/products?fields=id,retailer_id,name,image_fetch_status,review_status,availability,visibility&limit=100&access_token=".urlencode($tok);
  $all = [];
  $ctx = stream_context_create(["http"=>["ignore_errors"=>true,"timeout"=>30]]);
  while ($url) {
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) break;
    $j = json_decode($resp, true);
    foreach (($j["data"]??[]) as $p) $all[] = $p;
    $url = $j["paging"]["next"] ?? null;
    if (count($all) >= 1000) break; // safety cap
  }
  echo json_encode(["data" => $all]);
')

TOTAL=$(echo "$PRODUCTS_JSON" | php -r '$j=json_decode(file_get_contents("php://stdin"),true); echo count($j["data"]??[]);')
[ "$TOTAL" -gt 0 ] || { warn "Katalog ${CATALOG_NAME} kosong."; exit 0; }

# ─── 5. Render breakdown ─────────────────────────────────────────────────────
echo ""
echo "${BOLD}─── MPM-ready products (FETCHED) ───${RESET}"
echo "$PRODUCTS_JSON" | php -r '
$j=json_decode(file_get_contents("php://stdin"),true);
$n=0;
foreach ($j["data"]??[] as $p) {
    $s = $p["image_fetch_status"] ?? "(none)";
    if ($s !== "FETCHED") continue;
    $n++;
    printf("  ✓ %-32s  [%s]  sku=%s\n",
        mb_strimwidth($p["name"]??"(no name)", 0, 32, ""),
        $p["availability"]??"-",
        $p["retailer_id"]??"-");
}
if ($n === 0) echo "  (tidak ada)\n";
'

echo ""
echo "${BOLD}─── NOT MPM-ready ───${RESET}"
echo "$PRODUCTS_JSON" | php -r '
$j=json_decode(file_get_contents("php://stdin"),true);
$n=0;
foreach ($j["data"]??[] as $p) {
    $s = $p["image_fetch_status"] ?? "(none)";
    if ($s === "FETCHED") continue;
    $n++;
    $icon = match($s) {
        "FETCH_FAILED" => "✗",
        "OUTDATED", "PARTIAL_FETCH" => "↻",
        default => "·",
    };
    printf("  %s %-32s  [%s]  sku=%s\n",
        $icon,
        mb_strimwidth($p["name"]??"(no name)", 0, 32, ""),
        $s,
        $p["retailer_id"]??"-");
}
if ($n === 0) echo "  (semua produk sudah MPM-ready)\n";
'

# ─── 6. Summary ──────────────────────────────────────────────────────────────
echo ""
echo "${BOLD}═══ SUMMARY ═══${RESET}"
echo "$PRODUCTS_JSON" | CATALOG_ID="$CATALOG_ID" CATALOG_NAME="$CATALOG_NAME" BOUND_ID="$BOUND_ID" php -r '
$j=json_decode(file_get_contents("php://stdin"),true);
$total = count($j["data"]??[]);
$buckets = [];
foreach ($j["data"]??[] as $p) {
    $s = $p["image_fetch_status"] ?? "(none)";
    $buckets[$s] = ($buckets[$s] ?? 0) + 1;
}
$fetched = $buckets["FETCHED"] ?? 0;
$pct = $total > 0 ? round($fetched / $total * 100) : 0;
$bound = getenv("BOUND_ID") === getenv("CATALOG_ID");
printf("Katalog       : %s\n", getenv("CATALOG_NAME"));
printf("Bound ke WABA : %s\n", $bound ? "YA" : "TIDAK");
printf("Total produk  : %d\n", $total);
printf("MPM-ready     : %d / %d (%d%%)\n", $fetched, $total, $pct);
echo "Breakdown image_fetch_status:\n";
foreach ($buckets as $s => $c) printf("  %-15s %d\n", $s, $c);
'
echo ""
