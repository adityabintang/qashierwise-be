<?php

namespace App\Services;

use App\Models\ContactTag;
use App\Models\WhatsAppContact;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MlTaggingService
{
    private string $baseUrl;
    private float  $confidenceThreshold;

    public function __construct()
    {
        $this->baseUrl             = rtrim(config('services.ml_tagging.url', 'http://localhost:8000'), '/');
        $this->confidenceThreshold = (float) config('services.ml_tagging.confidence_threshold', 0.4);
    }

    /**
     * Call FastAPI ML service and return prediction result.
     *
     * @return array{label: string, confidence: float, probabilities: array}|null
     */
    public function predict(string $text): ?array
    {
        try {
            $response = Http::timeout(5)
                ->post("{$this->baseUrl}/api/v1/predict", ['text' => $text]);

            if (! $response->successful()) {
                Log::warning('ML tagging service returned non-200', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('ML tagging service unreachable', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Predict label from text and assign the matching system tag to the contact.
     * Replaces any previously assigned system tag on the contact.
     */
    public function applyTagToContact(WhatsAppContact $contact, string $messageText): void
    {
        $result = $this->predict($messageText);

        if (! $result || ($result['confidence'] ?? 0) < $this->confidenceThreshold) {
            Log::info('ML tagging skipped: low confidence or no result', [
                'contact_id' => $contact->id,
                'confidence' => $result['confidence'] ?? null,
            ]);
            return;
        }

        $label   = $result['label'];
        $systemTag = ContactTag::withoutGlobalScopes()
            ->where('is_system', true)
            ->where('name', $label)
            ->first();

        if (! $systemTag) {
            Log::warning('ML tag label has no matching system tag', ['label' => $label]);
            return;
        }

        // Get all system tag IDs to detach old ones
        $allSystemTagIds = ContactTag::withoutGlobalScopes()
            ->where('is_system', true)
            ->pluck('id');

        // Remove old system tags, keep merchant-assigned tags
        $contact->tags()->detach($allSystemTagIds);

        // Attach the new predicted system tag
        $contact->tags()->attach($systemTag->id);

        Log::info('ML system tag applied to contact', [
            'contact_id' => $contact->id,
            'label'      => $label,
            'confidence' => $result['confidence'],
        ]);
    }
}
