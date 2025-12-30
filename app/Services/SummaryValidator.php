<?php

namespace App\Services;

class SummaryValidator
{
    /**
     * Validation errors.
     *
     * @var array
     */
    private array $errors = [];

    /**
     * Valid intent values.
     *
     * @var array
     */
    private const VALID_INTENTS = [
        'browse_menu',
        'order_food',
        'reservation',
        'payment',
        'general_question',
        'unknown',
    ];

    /**
     * Required fields in summary.
     *
     * @var array
     */
    private const REQUIRED_FIELDS = [
        'summary',
        'intent',
        'key_data',
        'missing_information',
    ];

    /**
     * Validate summary structure and required fields.
     *
     * @param array $summary The summary array to validate
     * @return bool True if valid, false otherwise
     */
    public function validate(array $summary): bool
    {
        $this->errors = [];

        // Check required fields
        if (!$this->hasRequiredFields($summary)) {
            return false;
        }

        // Validate summary field is a string
        if (!is_string($summary['summary'])) {
            $this->errors[] = 'Field "summary" must be a string';
        }

        // Validate intent is a valid value
        if (!in_array($summary['intent'], self::VALID_INTENTS, true)) {
            $this->errors[] = sprintf(
                'Field "intent" must be one of: %s. Got: %s',
                implode(', ', self::VALID_INTENTS),
                $summary['intent'] ?? 'null'
            );
        }

        // Validate key_data is an array
        if (!is_array($summary['key_data'])) {
            $this->errors[] = 'Field "key_data" must be an array';
        }

        // Validate missing_information is an array
        if (!is_array($summary['missing_information'])) {
            $this->errors[] = 'Field "missing_information" must be an array';
        }

        // If this is a plain text fallback, be more lenient with validation
        if (isset($summary['source']) && $summary['source'] === 'plain_text_fallback') {
            // For fallback summaries, only require summary and intent fields to be valid
            // Other fields can be empty/default values
            $criticalErrors = array_filter($this->errors, function($error) {
                return str_contains($error, 'summary') || str_contains($error, 'intent');
            });
            
            if (empty($criticalErrors)) {
                // Clear non-critical errors for fallback summaries
                $this->errors = [];
                return true;
            }
        }

        return empty($this->errors);
    }

    /**
     * Check if summary has required fields.
     *
     * @param array $summary The summary array to check
     * @return bool True if all required fields are present
     */
    public function hasRequiredFields(array $summary): bool
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!array_key_exists($field, $summary)) {
                $this->errors[] = sprintf('Missing required field: %s', $field);
            }
        }

        return empty($this->errors);
    }

    /**
     * Get validation errors.
     *
     * @return array Array of error messages
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
