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
     * Params are returned ordered by their position in the template.
     *
     * @param  array  $paramMapping  Mapping from param name/index to reservation field
     * @param  array  $reservationData  Reservation data array
     * @param  WhatsAppTemplate|null  $template  Template to derive parameter order from
     * @return array Array of parameter values in template order
     */
    public function buildParameters(array $paramMapping, array $reservationData, ?WhatsAppTemplate $template = null): array
    {
        if ($template !== null) {
            // Order parameters by their position in the template body
            $structure = $this->getParameterStructure($template);
            $bodyParams = array_filter($structure, fn ($p) => $p['type'] === 'body');
            usort($bodyParams, fn ($a, $b) => $a['index'] - $b['index']);

            $params = [];
            foreach ($bodyParams as $param) {
                $key = $param['name'];
                $field = $paramMapping[$key] ?? $paramMapping[(string) $param['index']] ?? '';
                $params[] = $this->getFieldValue($field, $reservationData);
            }

            return $params;
        }

        // Legacy path: sort by key and return ordered values
        ksort($paramMapping);
        $params = [];
        foreach ($paramMapping as $index => $field) {
            $params[$index] = $this->getFieldValue($field, $reservationData);
        }

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
        $structure = $this->getParameterStructure($template);
        $bodyParams = array_filter($structure, fn ($p) => $p['type'] === 'body');
        $requiredParams = array_values(array_column($bodyParams, 'name')); // e.g. ['nama', 'order_id'] or ['1', '2']

        $requiredCount = count($requiredParams);
        $providedCount = count($paramMapping);
        $providedKeys = array_map('strval', array_keys($paramMapping));

        $errors = [];

        if ($requiredCount !== $providedCount) {
            $errors[] = "Template membutuhkan {$requiredCount} parameter, tapi mapping menyediakan {$providedCount}";
        }

        foreach ($requiredParams as $paramName) {
            // Accept both the exact name and numeric fallback (e.g. '1', '2')
            if (! in_array((string) $paramName, $providedKeys, true)) {
                $errors[] = "Parameter '{$paramName}' tidak ada dalam mapping";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
