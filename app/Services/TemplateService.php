<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TemplateService
{
    protected string $apiVersion;
    protected string $accessToken;
    protected string $businessAccountId;

    public function __construct()
    {
        $this->apiVersion = config('whatsapp.api_version', 'v22.0');
        $this->accessToken = config('whatsapp.access_token', '');
        $this->businessAccountId = config('whatsapp.business_account_id', '');
    }

    /**
     * Get the base URL for WhatsApp Business Management API
     */
    protected function getBaseUrl(): string
    {
        return "https://graph.facebook.com/{$this->apiVersion}";
    }

    /**
     * Validate template name - must be lowercase alphanumeric and underscores only
     *
     * @param string $name Template name to validate
     * @return array{valid: bool, error: string|null}
     */
    public function validateTemplateName(string $name): array
    {
        // Use strict empty string check instead of empty() to allow '0' as valid name
        if ($name === '') {
            return [
                'valid' => false,
                'error' => 'Template name is required'
            ];
        }

        if (preg_match('/^[a-z0-9_]+$/', $name) !== 1) {
            return [
                'valid' => false,
                'error' => 'Template name can only contain lowercase letters, numbers, and underscores'
            ];
        }

        return ['valid' => true, 'error' => null];
    }


    /**
     * Validate footer text - max 60 characters
     *
     * @param string|null $footer Footer text to validate
     * @return array{valid: bool, error: string|null}
     */
    public function validateFooter(?string $footer): array
    {
        if ($footer === null || $footer === '') {
            return ['valid' => true, 'error' => null];
        }

        if (mb_strlen($footer) > 60) {
            return [
                'valid' => false,
                'error' => 'Footer text cannot exceed 60 characters'
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate body text - max 1024 characters
     *
     * @param string $body Body text to validate
     * @return array{valid: bool, error: string|null}
     */
    public function validateBody(string $body): array
    {
        if (empty($body)) {
            return [
                'valid' => false,
                'error' => 'Body text is required'
            ];
        }

        if (mb_strlen($body) > 1024) {
            return [
                'valid' => false,
                'error' => 'Body text cannot exceed 1024 characters'
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate buttons - max 10 quick reply OR max 2 CTA buttons
     *
     * @param array|null $buttons Array of button configurations
     * @return array{valid: bool, error: string|null}
     */
    public function validateButtons(?array $buttons): array
    {
        if ($buttons === null || empty($buttons)) {
            return ['valid' => true, 'error' => null];
        }

        $quickReplyCount = 0;
        $ctaCount = 0;

        foreach ($buttons as $button) {
            $type = $button['type'] ?? '';
            
            if ($type === 'QUICK_REPLY') {
                $quickReplyCount++;
            } elseif (in_array($type, ['URL', 'PHONE_NUMBER'])) {
                $ctaCount++;
            }
        }

        // Cannot mix quick reply and CTA buttons
        if ($quickReplyCount > 0 && $ctaCount > 0) {
            return [
                'valid' => false,
                'error' => 'Cannot mix quick reply buttons with call-to-action buttons'
            ];
        }

        if ($quickReplyCount > 10) {
            return [
                'valid' => false,
                'error' => 'Maximum 10 quick reply buttons allowed'
            ];
        }

        if ($ctaCount > 2) {
            return [
                'valid' => false,
                'error' => 'Maximum 2 call-to-action buttons allowed'
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate required fields for template creation
     *
     * @param array $data Template data to validate
     * @return array{valid: bool, errors: array<string, string>}
     */
    public function validateRequiredFields(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = 'Template name is required';
        }

        if (empty($data['category'])) {
            $errors['category'] = 'Category is required';
        }

        if (empty($data['language'])) {
            $errors['language'] = 'Language is required';
        }

        if (empty($data['body'])) {
            $errors['body'] = 'Body text is required';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate variable sequence in body text
     * Variables should be sequential: {{1}}, {{2}}, {{3}}, etc.
     *
     * @param string $body Body text containing variables
     * @return array{valid: bool, warning: string|null}
     */
    public function validateVariableSequence(string $body): array
    {
        // Extract all variable numbers from the body
        preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);

        if (empty($matches[1])) {
            // No variables found, that's valid
            return ['valid' => true, 'warning' => null];
        }

        $variableNumbers = array_map('intval', $matches[1]);
        $variableNumbers = array_unique($variableNumbers);
        sort($variableNumbers);

        // Check if variables start from 1 and are sequential
        $expected = range(1, count($variableNumbers));

        if ($variableNumbers !== $expected) {
            return [
                'valid' => true, // Still valid, but with warning
                'warning' => 'Variable placeholders should be sequential starting from {{1}}. Found: {{' . implode('}}, {{', $variableNumbers) . '}}'
            ];
        }

        return ['valid' => true, 'warning' => null];
    }

    /**
     * Build components array for WhatsApp API from form data
     *
     * @param array $formData Form data from frontend
     * @return array Formatted components array for WhatsApp API
     */
    public function buildComponents(array $formData): array
    {
        $components = [];

        // Build HEADER component if present
        if (!empty($formData['header'])) {
            $header = $formData['header'];
            $headerComponent = [
                'type' => 'HEADER',
                'format' => $header['type'] ?? 'TEXT',
            ];

            if (($header['type'] ?? 'TEXT') === 'TEXT' && !empty($header['text'])) {
                $headerComponent['text'] = $header['text'];
            } elseif (in_array($header['type'] ?? '', ['IMAGE', 'VIDEO', 'DOCUMENT'])) {
                // For media headers, include example if provided
                if (!empty($header['example'])) {
                    $headerComponent['example'] = [
                        'header_handle' => [$header['example']]
                    ];
                }
            }

            $components[] = $headerComponent;
        }

        // Build BODY component (required)
        if (!empty($formData['body'])) {
            $bodyData = is_array($formData['body']) ? $formData['body'] : ['text' => $formData['body']];
            $bodyComponent = [
                'type' => 'BODY',
                'text' => $bodyData['text'] ?? $formData['body'],
            ];

            // Add example values for variables if provided
            if (!empty($bodyData['examples'])) {
                $bodyComponent['example'] = [
                    'body_text' => $bodyData['examples']
                ];
            }

            $components[] = $bodyComponent;
        }

        // Build FOOTER component if present
        if (!empty($formData['footer'])) {
            $footerText = is_array($formData['footer']) 
                ? ($formData['footer']['text'] ?? '') 
                : $formData['footer'];
            
            if (!empty($footerText)) {
                $components[] = [
                    'type' => 'FOOTER',
                    'text' => $footerText,
                ];
            }
        }

        // Build BUTTONS component if present
        if (!empty($formData['buttons'])) {
            $buttons = [];
            foreach ($formData['buttons'] as $button) {
                $buttonData = [
                    'type' => $button['type'],
                    'text' => $button['text'],
                ];

                // Add URL for URL type buttons
                if ($button['type'] === 'URL' && !empty($button['url'])) {
                    $buttonData['url'] = $button['url'];
                }

                // Add phone number for PHONE_NUMBER type buttons
                if ($button['type'] === 'PHONE_NUMBER' && !empty($button['phone_number'])) {
                    $buttonData['phone_number'] = $button['phone_number'];
                }

                $buttons[] = $buttonData;
            }

            if (!empty($buttons)) {
                $components[] = [
                    'type' => 'BUTTONS',
                    'buttons' => $buttons,
                ];
            }
        }

        return $components;
    }

    /**
     * Create a new template via WhatsApp Business Management API
     *
     * @param array $data Template data (name, category, language, components or form data)
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function createTemplate(array $data): array
    {
        // Validate required fields first
        $requiredValidation = $this->validateRequiredFields($data);
        if (!$requiredValidation['valid']) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Validation failed: ' . implode(', ', $requiredValidation['errors'])
            ];
        }

        // Validate template name
        $nameValidation = $this->validateTemplateName($data['name']);
        if (!$nameValidation['valid']) {
            return [
                'success' => false,
                'data' => null,
                'error' => $nameValidation['error']
            ];
        }

        // Validate body
        $bodyText = is_array($data['body']) ? ($data['body']['text'] ?? '') : $data['body'];
        $bodyValidation = $this->validateBody($bodyText);
        if (!$bodyValidation['valid']) {
            return [
                'success' => false,
                'data' => null,
                'error' => $bodyValidation['error']
            ];
        }

        // Validate footer if present
        $footerText = null;
        if (!empty($data['footer'])) {
            $footerText = is_array($data['footer']) ? ($data['footer']['text'] ?? '') : $data['footer'];
            $footerValidation = $this->validateFooter($footerText);
            if (!$footerValidation['valid']) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => $footerValidation['error']
                ];
            }
        }

        // Validate buttons if present
        if (!empty($data['buttons'])) {
            $buttonsValidation = $this->validateButtons($data['buttons']);
            if (!$buttonsValidation['valid']) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => $buttonsValidation['error']
                ];
            }
        }

        // Build components from form data
        $components = $this->buildComponents($data);

        // Build API request payload
        $payload = [
            'name' => $data['name'],
            'category' => $data['category'],
            'language' => $data['language'],
            'components' => $components,
        ];

        try {
            $url = $this->getBaseUrl() . "/{$this->businessAccountId}/message_templates";
            
            $response = Http::withToken($this->accessToken)
                ->post($url, $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info('Template created successfully', [
                    'template_id' => $responseData['id'] ?? null,
                    'name' => $data['name']
                ]);

                return [
                    'success' => true,
                    'data' => $responseData,
                    'error' => null
                ];
            }

            $errorData = $response->json();
            $errorMessage = $errorData['error']['message'] ?? 'Unknown error occurred';
            
            Log::error('Template creation failed', [
                'name' => $data['name'],
                'error' => $errorMessage,
                'response' => $errorData
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => $errorMessage
            ];
        } catch (\Exception $e) {
            Log::error('Template creation exception', [
                'name' => $data['name'],
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to connect to WhatsApp API: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update an existing template via WhatsApp Business Management API
     *
     * @param string $templateId Template ID from WhatsApp
     * @param array $data Updated template data
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function updateTemplate(string $templateId, array $data): array
    {
        // Validate body if present
        if (!empty($data['body'])) {
            $bodyText = is_array($data['body']) ? ($data['body']['text'] ?? '') : $data['body'];
            $bodyValidation = $this->validateBody($bodyText);
            if (!$bodyValidation['valid']) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => $bodyValidation['error']
                ];
            }
        }

        // Validate footer if present
        if (!empty($data['footer'])) {
            $footerText = is_array($data['footer']) ? ($data['footer']['text'] ?? '') : $data['footer'];
            $footerValidation = $this->validateFooter($footerText);
            if (!$footerValidation['valid']) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => $footerValidation['error']
                ];
            }
        }

        // Validate buttons if present
        if (!empty($data['buttons'])) {
            $buttonsValidation = $this->validateButtons($data['buttons']);
            if (!$buttonsValidation['valid']) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => $buttonsValidation['error']
                ];
            }
        }

        // Build components from form data
        $components = $this->buildComponents($data);

        // Build API request payload for update
        $payload = [
            'components' => $components,
        ];

        try {
            // WhatsApp API uses POST to /{template-id} for updates
            $url = $this->getBaseUrl() . "/{$templateId}";
            
            $response = Http::withToken($this->accessToken)
                ->post($url, $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info('Template updated successfully', [
                    'template_id' => $templateId
                ]);

                return [
                    'success' => true,
                    'data' => $responseData,
                    'error' => null
                ];
            }

            $errorData = $response->json();
            $errorMessage = $errorData['error']['message'] ?? 'Unknown error occurred';
            
            Log::error('Template update failed', [
                'template_id' => $templateId,
                'error' => $errorMessage,
                'response' => $errorData
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => $errorMessage
            ];
        } catch (\Exception $e) {
            Log::error('Template update exception', [
                'template_id' => $templateId,
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to connect to WhatsApp API: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete a template via WhatsApp Business Management API
     *
     * @param string $templateName Template name to delete
     * @return array{success: bool, data: array|null, error: string|null}
     */
    public function deleteTemplate(string $templateName): array
    {
        if (empty($templateName)) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Template name is required for deletion'
            ];
        }

        try {
            // WhatsApp API uses DELETE to /{waba-id}/message_templates?name={template-name}
            $url = $this->getBaseUrl() . "/{$this->businessAccountId}/message_templates";
            
            $response = Http::withToken($this->accessToken)
                ->delete($url, [
                    'name' => $templateName
                ]);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info('Template deleted successfully', [
                    'template_name' => $templateName
                ]);

                return [
                    'success' => true,
                    'data' => $responseData,
                    'error' => null
                ];
            }

            $errorData = $response->json();
            $errorMessage = $errorData['error']['message'] ?? 'Unknown error occurred';
            
            Log::error('Template deletion failed', [
                'template_name' => $templateName,
                'error' => $errorMessage,
                'response' => $errorData
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => $errorMessage
            ];
        } catch (\Exception $e) {
            Log::error('Template deletion exception', [
                'template_name' => $templateName,
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to connect to WhatsApp API: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Serialize template data for storage
     * Converts component arrays to JSON format
     *
     * @param array $templateData Template data to serialize
     * @return array Serialized template data with JSON-encoded components
     */
    public function serializeTemplate(array $templateData): array
    {
        $serialized = $templateData;

        // Serialize components array to JSON
        if (isset($serialized['components']) && is_array($serialized['components'])) {
            $serialized['components'] = json_encode($serialized['components'], JSON_UNESCAPED_UNICODE);
        }

        // Serialize buttons array to JSON
        if (isset($serialized['buttons']) && is_array($serialized['buttons'])) {
            $serialized['buttons'] = json_encode($serialized['buttons'], JSON_UNESCAPED_UNICODE);
        }

        return $serialized;
    }

    /**
     * Deserialize template data from storage
     * Converts JSON strings back to component arrays
     *
     * @param array $templateData Template data to deserialize
     * @return array Deserialized template data with array components
     */
    public function deserializeTemplate(array $templateData): array
    {
        $deserialized = $templateData;

        // Deserialize components JSON to array
        if (isset($deserialized['components'])) {
            if (is_string($deserialized['components'])) {
                $decoded = json_decode($deserialized['components'], true);
                $deserialized['components'] = $decoded !== null ? $decoded : [];
            }
        }

        // Deserialize buttons JSON to array
        if (isset($deserialized['buttons'])) {
            if (is_string($deserialized['buttons'])) {
                $decoded = json_decode($deserialized['buttons'], true);
                $deserialized['buttons'] = $decoded !== null ? $decoded : [];
            }
        }

        return $deserialized;
    }

    /**
     * Render preview body text with variable placeholders replaced by indicators
     * Replaces {{N}} with [Variable N] for preview display
     *
     * @param string $body Body text containing variable placeholders
     * @return string Body text with variables replaced by placeholder indicators
     */
    public function renderPreviewBody(string $body): string
    {
        if (empty($body)) {
            return '';
        }

        // Replace {{N}} with [Variable N]
        return preg_replace('/\{\{(\d+)\}\}/', '[Variable $1]', $body);
    }

    /**
     * Count the number of unique variable placeholders in body text
     *
     * @param string $body Body text containing variable placeholders
     * @return int Number of unique variable placeholders
     */
    public function countVariables(string $body): int
    {
        if (empty($body)) {
            return 0;
        }

        preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);

        if (empty($matches[1])) {
            return 0;
        }

        // Return count of unique variable numbers
        return count(array_unique($matches[1]));
    }
}
