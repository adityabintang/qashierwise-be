<?php

namespace App\Services;

use App\Models\WhatsAppTemplate;

/**
 * Service for extracting and handling WhatsApp template parameters.
 */
class TemplateParameterService
{
    /**
     * Extract the number of parameters required by a template.
     */
    public function getParameterCount(WhatsAppTemplate $template): int
    {
        $components = $template->components ?? [];
        $paramCount = 0;

        foreach ($components as $component) {
            if ($component['type'] === 'HEADER' && isset($component['format'])) {
                // Header can have parameters too (for IMAGE, VIDEO, etc.)
                if (in_array($component['format'], ['IMAGE', 'VIDEO', 'DOCUMENT'])) {
                    $paramCount++;
                }
            } elseif ($component['type'] === 'BODY' && isset($component['text'])) {
                // Count {{number}} or {{name}} patterns in body text
                preg_match_all('/\{\{([^}]+)\}\}/', $component['text'], $matches);
                $paramCount += count($matches[0]);
            }
        }

        return $paramCount;
    }

    /**
     * Get the parameter structure of a template.
     */
    public function getParameterStructure(WhatsAppTemplate $template): array
    {
        $components = $template->components ?? [];
        $parameters = [];

        foreach ($components as $component) {
            if ($component['type'] === 'HEADER') {
                if (isset($component['format']) && in_array($component['format'], ['IMAGE', 'VIDEO', 'DOCUMENT'])) {
                    $parameters[] = [
                        'type' => 'header',
                        'format' => $component['format'],
                        'index' => 1,
                    ];
                }
            } elseif ($component['type'] === 'BODY' && isset($component['text'])) {
                preg_match_all('/\{\{([^}]+)\}\}/', $component['text'], $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

                $index = 1;
                foreach ($matches as $match) {
                    $paramName = $match[1][0];
                    $parameters[] = [
                        'type' => 'body',
                        'index' => $index,
                        'name' => $paramName,
                        'position' => $match[0][1],
                    ];
                    $index++;
                }
            }
        }

        return $parameters;
    }

    /**
     * Build body parameters from reservation data based on mapping.
     *
     * @param  array  $paramMapping  Mapping from template index to reservation field
     * @param  array  $reservationData  Reservation data array
     * @return array Array of parameter values in correct order
     */
    public function buildParameters(array $paramMapping, array $reservationData): array
    {
        $params = [];

        // Sort by index to maintain order
        ksort($paramMapping);

        foreach ($paramMapping as $index => $field) {
            $value = $this->getFieldValue($field, $reservationData);
            $params[$index] = $value;
        }

        // Return values in order (1, 2, 3, ...)
        return array_values($params);
    }

    /**
     * Get value from reservation data based on field name.
     */
    protected function getFieldValue(string $field, array $data): string
    {
        // Handle nested fields like store.name
        if (str_contains($field, '.')) {
            $parts = explode('.', $field);
            $value = $data;

            foreach ($parts as $part) {
                if (is_array($value) && isset($value[$part])) {
                    $value = $value[$part];
                } else {
                    return '';
                }
            }

            return $value ?? '';
        }

        return $data[$field] ?? '';
    }

    /**
     * Validate that the mapping matches the template parameters.
     *
     * @return array{valid: bool, errors: array}
     */
    public function validateMapping(WhatsAppTemplate $template, array $paramMapping): array
    {
        $requiredCount = $this->getParameterCount($template);
        $providedCount = count($paramMapping);

        $errors = [];

        if ($requiredCount !== $providedCount) {
            $errors[] = "Template membutuhkan {$requiredCount} parameter, tapi mapping menyediakan {$providedCount}";
        }

        // Check that all indices are present
        $indices = array_keys($paramMapping);
        for ($i = 1; $i <= $requiredCount; $i++) {
            if (! in_array((string) $i, $indices) && ! in_array($i, $indices)) {
                $errors[] = "Parameter ke-{$i} tidak ada dalam mapping";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
