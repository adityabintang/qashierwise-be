#!/bin/bash

# ============================================================
# Simulate QRIS Payment - Xendit Test Mode
# Ambil data QRIS terbaru dari Laravel log, pilih order,
# lalu simulate pembayaran ke Xendit test API
# ============================================================

ENV_FILE="$(dirname "$0")/../.env"
LOG_FILE="$(dirname "$0")/../storage/logs/laravel.log"

# Load credentials dari .env
XENDIT_API_KEY=$(grep -E '^XENDIT_API_KEY=' "$ENV_FILE" | cut -d'=' -f2 | tr -d '\r')
XENDIT_BASE_URL=$(grep -E '^XENDIT_BASE_URL=' "$ENV_FILE" | cut -d'=' -f2 | tr -d '\r')
XENDIT_BASE_URL="${XENDIT_BASE_URL:-https://api.xendit.co}"

if [ -z "$XENDIT_API_KEY" ]; then
    echo "❌ XENDIT_API_KEY tidak ditemukan di .env"
    exit 1
fi

echo ""
echo "══════════════════════════════════════════════════"
echo "  Simulate QRIS Payment - Xendit Test"
echo "══════════════════════════════════════════════════"
echo ""

# Ambil baris log dengan format lengkap: order_id + xendit_account_id + amount
# Gunakan baris "QRIS generated via XenPlatform" yang paling lengkap
# Ambil 10 transaksi terakhir, deduplicate by order_id
declare -A SEEN
declare -a ORDERS
declare -a ACCOUNT_IDS
declare -a AMOUNTS

while IFS= read -r line; do
    order_id=$(echo "$line" | grep -o '"order_id":"[^"]*"' | cut -d'"' -f4)
    account_id=$(echo "$line" | grep -o '"xendit_account_id":"[^"]*"' | cut -d'"' -f4)
    # Amount bisa berformat 12222 atau 12222.0
    amount=$(echo "$line" | grep -o '"amount":[0-9.]*' | head -1 | cut -d':' -f2 | cut -d'.' -f1)

    # Skip jika order_id kosong atau amount 0 atau sudah ada
    [ -z "$order_id" ] && continue
    [ -z "$amount" ] || [ "$amount" -eq 0 ] 2>/dev/null && continue
    [ "${SEEN[$order_id]+isset}" ] && continue

    SEEN[$order_id]=1
    ORDERS+=("$order_id")
    ACCOUNT_IDS+=("$account_id")
    AMOUNTS+=("$amount")
done < <(grep "QRIS generated via XenPlatform\|QRIS generated via API" "$LOG_FILE" 2>/dev/null | tail -20)

# Fallback: coba format log lain jika kosong
if [ ${#ORDERS[@]} -eq 0 ]; then
    while IFS= read -r line; do
        order_id=$(echo "$line" | grep -o '"order_id":"[^"]*"' | cut -d'"' -f4)
        account_id=$(echo "$line" | grep -o '"xendit_account_id":"[^"]*"' | cut -d'"' -f4)
        amount=$(echo "$line" | grep -o '"amount":[0-9.]*' | head -1 | cut -d':' -f2 | cut -d'.' -f1)

        [ -z "$order_id" ] && continue
        [ -z "$amount" ] || [ "$amount" -eq 0 ] 2>/dev/null && continue
        [ "${SEEN[$order_id]+isset}" ] && continue

        SEEN[$order_id]=1
        ORDERS+=("$order_id")
        ACCOUNT_IDS+=("$account_id")
        AMOUNTS+=("$amount")
    done < <(grep "QRIS generated\|XenPlatform: QRIS generated" "$LOG_FILE" 2>/dev/null | tail -20)
fi

if [ ${#ORDERS[@]} -eq 0 ]; then
    echo "❌ Tidak ada data QRIS ditemukan di laravel.log"
    echo "   Pastikan sudah ada transaksi QRIS yang di-generate."
    exit 1
fi

# Format angka ribuan tanpa locale dependency
format_rupiah() {
    local num=$1
    printf "%d" "$num" | sed ':a;s/\B[0-9]\{3\}\>/.&/;ta'
}

# Tampilkan daftar QRIS (terbaru di bawah)
echo "📋 Daftar QRIS yang tersedia:"
echo ""
for i in "${!ORDERS[@]}"; do
    echo "  [$((i+1))] Order ID : ${ORDERS[$i]}"
    echo "       Amount    : Rp $(format_rupiah "${AMOUNTS[$i]}")"
    if [ -n "${ACCOUNT_IDS[$i]}" ]; then
        echo "       Sub-acct  : ${ACCOUNT_IDS[$i]}"
    fi
    echo ""
done

# Pilih order
if [ ${#ORDERS[@]} -eq 1 ]; then
    SELECTED=0
    echo "✔ Satu order tersedia: ${ORDERS[$SELECTED]}"
    echo ""
    read -r -p "Tekan Enter untuk simulate pembayaran, atau Ctrl+C untuk batal... "
else
    while true; do
        read -r -p "Pilih nomor order [1-${#ORDERS[@]}]: " CHOICE
        if [[ "$CHOICE" =~ ^[0-9]+$ ]] && [ "$CHOICE" -ge 1 ] && [ "$CHOICE" -le "${#ORDERS[@]}" ]; then
            SELECTED=$((CHOICE - 1))
            break
        fi
        echo "  ⚠ Masukkan angka antara 1 dan ${#ORDERS[@]}"
    done
    echo ""
    read -r -p "Tekan Enter untuk simulate pembayaran '${ORDERS[$SELECTED]}'... "
fi

ORDER_ID="${ORDERS[$SELECTED]}"
ACCOUNT_ID="${ACCOUNT_IDS[$SELECTED]}"
AMOUNT="${AMOUNTS[$SELECTED]}"

echo ""
echo "🚀 Mengirim simulate payment ke Xendit..."
echo "   Order ID : $ORDER_ID"
echo "   Amount   : Rp $(format_rupiah "$AMOUNT")"
echo ""

# Build curl args
CURL_ARGS=(
    -s
    -X POST
    "${XENDIT_BASE_URL}/qr_codes/${ORDER_ID}/payments/simulate"
    -u "${XENDIT_API_KEY}:"
    -H "Content-Type: application/json"
    -d "{\"amount\": ${AMOUNT}}"
)

if [ -n "$ACCOUNT_ID" ]; then
    CURL_ARGS+=(-H "for-user-id: ${ACCOUNT_ID}")
fi

RESPONSE=$(curl "${CURL_ARGS[@]}" 2>/dev/null)

# Tampilkan hasil
if echo "$RESPONSE" | grep -q '"status":"COMPLETED"'; then
    PAYMENT_ID=$(echo "$RESPONSE" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)
    SOURCE=$(echo "$RESPONSE" | grep -o '"source":"[^"]*"' | cut -d'"' -f4)
    echo "✅ Simulate BERHASIL!"
    echo "   Payment ID : $PAYMENT_ID"
    echo "   Source     : ${SOURCE:-DANA}"
    echo ""
    echo "💡 Cek laravel.log untuk konfirmasi webhook diterima."
elif echo "$RESPONSE" | grep -q '"error_code"'; then
    ERROR_CODE=$(echo "$RESPONSE" | grep -o '"error_code":"[^"]*"' | cut -d'"' -f4)
    ERROR_MSG=$(echo "$RESPONSE" | grep -o '"message":"[^"]*"' | cut -d'"' -f4)
    echo "❌ Simulate GAGAL!"
    echo "   Error : $ERROR_CODE"
    echo "   Pesan : $ERROR_MSG"
else
    echo "⚠ Response tidak dikenali:"
    echo "$RESPONSE"
fi

echo ""
