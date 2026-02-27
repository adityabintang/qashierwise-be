<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Models\ReservationConfig;
use App\Models\WhatsAppTemplate;
use App\Services\TemplateParameterService;
use App\Services\WhatsAppAccountService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Netflie\WhatsAppCloudApi\Message\Template\Component;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;

/**
 * Job for sending reservation reminder notifications via WhatsApp.
 *
 * This job is triggered by Qstash at the scheduled time.
 */
class SendReservationReminder implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [10, 30, 60];

    /**
     * The reservation ID.
     */
    protected int $reservationId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $reservationId)
    {
        $this->reservationId = $reservationId;
    }

    /**
     * Execute the job.
     */
    public function handle(
        WhatsAppAccountService $whatsappService,
        TemplateParameterService $parameterService
    ): void {
        $reservation = Reservation::with(['store', 'table'])->find($this->reservationId);

        if (! $reservation) {
            Log::warning('Reservation not found for reminder', [
                'reservation_id' => $this->reservationId,
            ]);

            return;
        }

        // Check if reservation is still valid
        if ($reservation->status === 'cancelled' || $reservation->status === 'completed') {
            Log::info('Skipping reminder - reservation is cancelled or completed', [
                'reservation_id' => $this->reservationId,
                'status' => $reservation->status,
            ]);

            return;
        }

        // Get the config for this reservation
        $config = ReservationConfig::where('user_id', $reservation->user_id)
            ->where('store_id', $reservation->store_id)
            ->first();

        if (! $config || ! $config->isReminderConfigured()) {
            Log::warning('Reminder not configured for this reservation', [
                'reservation_id' => $this->reservationId,
            ]);

            return;
        }

        // Get the template (bypass global auth scope — jobs run without an authenticated user)
        $template = WhatsAppTemplate::withoutGlobalScope('userTemplates')
            ->where('name', $config->reminder_template)
            ->where('whatsapp_account_id', function ($query) use ($reservation) {
                $query->select('id')
                    ->from('whatsapp_accounts')
                    ->where('user_id', $reservation->user_id)
                    ->where('is_active', true)
                    ->limit(1);
            })
            ->first();

        if (! $template) {
            Log::warning('Template not found for reminder', [
                'reservation_id' => $this->reservationId,
                'template_name' => $config->reminder_template,
            ]);

            return;
        }

        // Get the WhatsApp account
        $whatsappAccount = $whatsappService->getActiveAccount($reservation->user_id);

        if (! $whatsappAccount) {
            Log::warning('WhatsApp account not found for reminder', [
                'reservation_id' => $this->reservationId,
                'user_id' => $reservation->user_id,
            ]);

            return;
        }

        // Prepare reservation data for parameter mapping
        $statusLabels = [
            'pending_payment' => 'Menunggu Pembayaran',
            'confirmed' => 'Dikonfirmasi',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
        ];
        $paymentTypeLabels = [
            'dp' => 'DP',
            'full' => 'Lunas',
        ];
        $reservationData = [
            'customer_name' => $reservation->customer_name,
            'customer_phone' => $reservation->phone,
            'customer_email' => $reservation->email ?? '',
            'reservation_date' => \Carbon\Carbon::parse($reservation->reservation_date)->format('d/m/Y'),
            'reservation_time' => $reservation->reservation_time,
            'guest_count' => $reservation->guest_count,
            'store_name' => $reservation->store?->name ?? '',
            'store_address' => $reservation->store?->address ?? '',
            'reservation_notes' => $reservation->notes ?? '',
            'table_name' => $reservation->table?->number ?? '',
            'reservation_code' => $reservation->order_id ?? 'RSV-'.$reservation->id,
            'reservation_datetime' => \Carbon\Carbon::parse($reservation->reservation_date)->locale('id')->isoFormat('D MMM YYYY').' '.$reservation->reservation_time.' WIB',
            'status' => $statusLabels[$reservation->status] ?? $reservation->status,
            'payment_type' => $paymentTypeLabels[$reservation->payment_type] ?? $reservation->payment_type ?? '',
            'total_amount' => $reservation->total_amount ? number_format((float) $reservation->total_amount, 0, ',', '.') : '',
            'paid_amount' => $reservation->paid_amount ? number_format((float) $reservation->paid_amount, 0, ',', '.') : '',
            'remaining_amount' => $reservation->remaining_amount ? number_format((float) $reservation->remaining_amount, 0, ',', '.') : '',
        ];

        $paramMapping = $config->reminder_param_mapping ?? [];

        // Build the parameters based on mapping, ordered by template structure
        $bodyParams = $parameterService->buildParameters(
            $paramMapping,
            $reservationData,
            $template
        );

        // Validate parameters
        $validation = $parameterService->validateMapping($template, $paramMapping);
        if (! $validation['valid']) {
            Log::error('Template parameter validation failed', [
                'reservation_id' => $this->reservationId,
                'errors' => $validation['errors'],
            ]);

            return;
        }

        // Initialize WhatsApp API
        $whatsapp = new WhatsAppCloudApi([
            'from_phone_number_id' => $whatsappAccount->phone_number_id,
            'access_token' => $whatsappAccount->access_token,
        ]);

        // Get parameter structure from template to map correctly
        $templateStructure = $parameterService->getParameterStructure($template);

        // Build a mapping of parameter index to name
        $paramIndexToName = [];
        foreach ($templateStructure as $param) {
            if ($param['type'] === 'body' && isset($param['index']) && isset($param['name'])) {
                $paramIndexToName[$param['index']] = $param['name'];
            }
        }

        // Format body parameters as WhatsApp API expects objects with parameter names
        $formattedBodyParams = [];
        foreach ($bodyParams as $index => $param) {
            $paramIndex = $index + 1; // 0-based to 1-based
            $formattedBodyParams[] = [
                'type' => 'text',
                'text' => $param,
                'parameter_name' => $paramIndexToName[$paramIndex] ?? 'param'.$paramIndex,
            ];
        }

        try {
            // Create component with body parameters
            $component = new Component(
                [], // header_params
                $formattedBodyParams,  // body_params
                []  // button_params
            );

            $response = $whatsapp->sendTemplate(
                $reservation->phone,
                $config->reminder_template,
                $config->reminder_template_language,
                $component
            );

            Log::info('Reservation reminder sent successfully', [
                'reservation_id' => $this->reservationId,
                'customer_phone' => $reservation->phone,
                'template' => $config->reminder_template,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send reservation reminder', [
                'reservation_id' => $this->reservationId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
