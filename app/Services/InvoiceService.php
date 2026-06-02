<?php

namespace App\Services;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Renders an order invoice (resources/views/invoices/order.blade.php) to a PDF
 * and stores it on the configured media disk, returning a public URL that the
 * WhatsApp Cloud API can fetch to deliver as a document.
 */
class InvoiceService
{
    public function __construct(
        protected MediaStorageService $media,
    ) {}

    /**
     * Generate the invoice PDF for an order. Stores a copy on the media disk
     * (public URL, for record/fallback) AND writes a local temp file so the
     * caller can upload it to WhatsApp by media-id (more reliable than a link).
     *
     * @return array{url: string, tmp_path: string, filename: string}|null
     */
    public function generate(Order $order): ?array
    {
        try {
            $order->loadMissing(['items', 'store']);

            $pdf = Pdf::loadView('invoices.order', ['order' => $order])
                ->setPaper('a4');

            $bytes = $pdf->output();

            // Persist to the media disk for record + link fallback.
            $disk = $this->media->getDiskName();
            $name = 'invoice-'.preg_replace('/[^A-Za-z0-9\-]/', '', $order->order_number).'.pdf';
            $path = $this->media->generatePath('document', $name);
            Storage::disk($disk)->put($path, $bytes, ['ContentType' => 'application/pdf']);
            $url = $this->media->getPublicUrl($path);

            // Local temp file for the WhatsApp media upload endpoint.
            $tmp = tempnam(sys_get_temp_dir(), 'invoice_').'.pdf';
            file_put_contents($tmp, $bytes);

            Log::info('Invoice PDF generated', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'url' => $url,
            ]);

            return ['url' => $url, 'tmp_path' => $tmp, 'filename' => $this->filename($order)];
        } catch (\Throwable $e) {
            Log::error('Invoice PDF generation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Generate + store the invoice PDF and return only the public URL.
     */
    public function generatePdfUrl(Order $order): ?string
    {
        $result = $this->generate($order);
        if ($result) {
            @unlink($result['tmp_path']);
        }

        return $result['url'] ?? null;
    }

    /**
     * Filename used when sending the document over WhatsApp.
     */
    public function filename(Order $order): string
    {
        return 'Invoice-'.$order->order_number.'.pdf';
    }
}
