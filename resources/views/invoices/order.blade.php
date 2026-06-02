@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $statusLabel = [
        'paid' => 'LUNAS',
        'pending' => 'BELUM BAYAR',
        'cancelled' => 'DIBATALKAN',
    ][$order->status] ?? strtoupper($order->status);
    $deliveryLabel = $order->delivery_type === 'delivery' ? 'Pengantaran (Delivery)' : 'Ambil di Toko (Pickup)';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1f2937; font-size: 12px; margin: 0; }
        .wrap { padding: 24px 28px; }
        .head { width: 100%; border-bottom: 2px solid #059669; padding-bottom: 12px; }
        .head td { vertical-align: top; }
        .brand { font-size: 20px; font-weight: bold; color: #059669; }
        .muted { color: #6b7280; }
        .title { font-size: 22px; font-weight: bold; letter-spacing: 1px; text-align: right; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .badge-paid { background: #d1fae5; color: #065f46; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        .section { margin-top: 18px; }
        .info td { padding: 2px 0; vertical-align: top; }
        .info .label { color: #6b7280; width: 90px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items th { background: #f3f4f6; text-align: left; padding: 8px; font-size: 11px; text-transform: uppercase; color: #374151; }
        table.items td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        .right { text-align: right; }
        .totals { width: 45%; margin-left: 55%; margin-top: 12px; }
        .totals td { padding: 4px 0; }
        .totals .grand { border-top: 2px solid #1f2937; font-size: 15px; font-weight: bold; padding-top: 8px; }
        .foot { margin-top: 30px; text-align: center; color: #9ca3af; font-size: 11px; border-top: 1px solid #e5e7eb; padding-top: 12px; }
    </style>
</head>
<body>
<div class="wrap">
    <table class="head">
        <tr>
            <td>
                <div class="brand">{{ $order->store->name ?? 'Toko' }}</div>
                <div class="muted">{{ $order->store->address ?? '' }}</div>
            </td>
            <td>
                <div class="title">INVOICE</div>
                <div class="right muted">No. {{ $order->order_number }}</div>
                <div class="right" style="margin-top:6px;">
                    <span class="badge badge-{{ $order->status }}">{{ $statusLabel }}</span>
                </div>
            </td>
        </tr>
    </table>

    <div class="section">
        <table style="width:100%;">
            <tr>
                <td style="width:50%; vertical-align:top;">
                    <table class="info">
                        <tr><td class="label">Pelanggan</td><td>: {{ $order->customer_name ?: '-' }}</td></tr>
                        <tr><td class="label">WhatsApp</td><td>: {{ $order->customer_phone ?: '-' }}</td></tr>
                        <tr><td class="label">Metode</td><td>: {{ $deliveryLabel }}</td></tr>
                        @if($order->delivery_type === 'delivery' && $order->alamat)
                            <tr><td class="label">Alamat</td><td>: {{ $order->alamat }}</td></tr>
                        @endif
                    </table>
                </td>
                <td style="width:50%; vertical-align:top;">
                    <table class="info">
                        <tr><td class="label">Tanggal</td><td>: {{ ($order->confirmed_at ?? $order->created_at)?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</td></tr>
                        @if($order->courier_name)
                            <tr><td class="label">Kurir</td><td>: {{ $order->courier_name }}</td></tr>
                        @endif
                        @if($order->catatan)
                            <tr><td class="label">Catatan</td><td>: {{ $order->catatan }}</td></tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <table class="items">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th class="right" style="width:60px;">Qty</th>
                    <th class="right" style="width:110px;">Harga</th>
                    <th class="right" style="width:120px;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @forelse($order->items as $item)
                    <tr>
                        <td>{{ $item->product_name ?: ($item->product_retailer_id ?? 'Produk') }}</td>
                        <td class="right">{{ $item->quantity }}</td>
                        <td class="right">{{ $rp($item->unit_price) }}</td>
                        <td class="right">{{ $rp($item->subtotal) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">Tidak ada item.</td></tr>
                @endforelse
            </tbody>
        </table>

        <table class="totals">
            <tr><td class="muted">Subtotal</td><td class="right">{{ $rp($order->subtotal) }}</td></tr>
            <tr><td class="muted">Pajak</td><td class="right">{{ $rp($order->tax_amount) }}</td></tr>
            @if((float) $order->discount_amount > 0)
                <tr><td class="muted">Diskon</td><td class="right">- {{ $rp($order->discount_amount) }}</td></tr>
            @endif
            @if($order->delivery_type === 'delivery' && (float) $order->ongkir > 0)
                <tr><td class="muted">Ongkir</td><td class="right">{{ $rp($order->ongkir) }}</td></tr>
            @endif
            <tr><td class="grand">TOTAL</td><td class="right grand">{{ $rp($order->total) }}</td></tr>
        </table>
    </div>

    <div class="foot">
        Terima kasih telah berbelanja di {{ $order->store->name ?? 'toko kami' }}.<br>
        Invoice ini dibuat otomatis oleh QashierWise.
    </div>
</div>
</body>
</html>
