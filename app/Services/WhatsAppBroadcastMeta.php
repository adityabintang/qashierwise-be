<?php

namespace App\Services;

use App\Exceptions\WhatsAppBroadcastException;
use App\Models\WhatsAppAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class WhatsAppBroadcastMeta
{
    private const VARIABLE = '/\{\{\s*([a-z][a-z0-9_]*|[1-9][0-9]*)\s*\}\}/';

    public function client(WhatsAppAccount $account): PendingRequest
    {
        return Http::baseUrl('https://graph.facebook.com/'.config('whatsapp.api_version'))
            ->withToken($account->access_token)
            ->acceptJson()
            ->connectTimeout(4)
            ->timeout(8);
    }

    public function wabaId(WhatsAppAccount $account): string
    {
        return $account->waba_id ?: $account->business_account_id;
    }

    public function quota(WhatsAppAccount $account): array
    {
        try {
            $response = $this->client($account)->get($this->wabaId($account), [
                'fields' => 'owner_business_info,whatsapp_business_manager_messaging_limit,health_status',
            ]);
        } catch (ConnectionException) {
            throw $this->quotaUnavailable();
        }

        $portfolio = (string) $response->json('owner_business_info.id');
        $tier = $response->json('whatsapp_business_manager_messaging_limit');
        $limits = [
            'UNTIERED' => 0,
            'TIER_50' => 50,
            'TIER_250' => 250,
            'TIER_1K' => 1000,
            'TIER_2K' => 2000,
            'TIER_10K' => 10000,
            'TIER_100K' => 100000,
            'TIER_UNLIMITED' => null,
        ];

        $health = $response->json('health_status.can_send_message');
        if (in_array($health, ['BLOCKED', 'LIMITED'], true) || in_array((int) $response->json('error.code'), [368, 131031, 131048, 131064], true)) {
            throw new WhatsAppBroadcastException('BROADCAST_ACCOUNT_RESTRICTED', 'Meta membatasi pengiriman dari akun WhatsApp ini.');
        }

        if (! $response->successful() || ! preg_match('/^[0-9]{1,32}$/D', $portfolio)
            || ! is_string($tier) || ! array_key_exists($tier, $limits)) {
            throw $this->quotaUnavailable();
        }

        return ['portfolio' => $portfolio, 'tier' => $tier, 'limit' => $limits[$tier]];
    }

    public function ownedWabas(WhatsAppAccount $account, string $portfolio): array
    {
        try {
            return array_values(array_filter(array_column(
                $this->pages($account, "$portfolio/owned_whatsapp_business_accounts", 'id'),
                'id',
            ), fn ($id) => preg_match('/^[0-9]{1,32}$/D', (string) $id)));
        } catch (WhatsAppBroadcastException) {
            // Some tokens can verify the current WABA without permission to list the portfolio.
            return [];
        }
    }

    public function templates(WhatsAppAccount $account): array
    {
        $templates = $this->pages($account, $this->wabaId($account).'/message_templates', 'id,name,language,category,status,components');

        return array_values(array_filter(array_map($this->describe(...), $templates)));
    }

    public function template(WhatsAppAccount $account, string $id): ?array
    {
        try {
            $response = $this->client($account)->get($id, [
                'fields' => 'id,name,language,category,status,components',
            ]);
        } catch (ConnectionException) {
            throw $this->templatesUnavailable();
        }

        if ($response->serverError() || $response->status() === 429) {
            throw $this->templatesUnavailable();
        }

        return $response->successful() ? $this->describe($response->json()) : null;
    }

    public function payload(array $template, array $values): array
    {
        $components = [];
        $preview = [];

        foreach ($template['sections'] as $section) {
            $type = $section['type'];
            $inputs = $values[$type] ?? [];
            if (array_diff(array_keys($inputs), $section['variables']) || array_diff($section['variables'], array_keys($inputs))) {
                throw ValidationException::withMessages(['variables' => 'Isi semua variabel yang diperlukan template.']);
            }

            $parameters = [];
            foreach ($section['variables'] as $key) {
                $parameter = ['type' => 'text', 'text' => $inputs[$key]];
                if (! ctype_digit($key)) {
                    $parameter['parameter_name'] = $key;
                }
                $parameters[] = $parameter;
            }

            if ($parameters !== []) {
                $components[] = ['type' => $type, 'parameters' => $parameters];
            }

            $preview[$type] = preg_replace_callback(
                self::VARIABLE,
                fn ($match) => $inputs[$match[1]] ?? $match[0],
                $section['text'],
            );
        }

        if (array_diff(array_keys($values), array_column($template['sections'], 'type'))) {
            throw ValidationException::withMessages(['variables' => 'Variabel tidak cocok dengan template.']);
        }

        return [
            'template_payload' => ['name' => $template['name'], 'language' => ['code' => $template['language']]]
                + ($components === [] ? [] : ['components' => $components]),
            'preview' => $preview,
        ];
    }

    private function describe(mixed $template): ?array
    {
        if (! is_array($template) || ! preg_match('/^[0-9]{1,32}$/D', (string) ($template['id'] ?? ''))
            || ($template['status'] ?? null) !== 'APPROVED'
            || ! in_array($template['category'] ?? null, ['MARKETING', 'UTILITY'], true)
            || empty($template['name']) || empty($template['language'])) {
            return null;
        }

        $sections = [];
        foreach ($template['components'] ?? [] as $component) {
            $type = strtolower($component['type'] ?? '');
            if (! in_array($type, ['header', 'body', 'footer'], true)
                || ($type === 'header' && ($component['format'] ?? null) !== 'TEXT')
                || ! is_string($component['text'] ?? null) || isset($sections[$type])) {
                return null;
            }

            preg_match_all(self::VARIABLE, $component['text'], $matches);
            $variables = array_values(array_unique($matches[1]));
            if ($type === 'footer' && $variables !== []) {
                return null;
            }
            if ($variables !== [] && ctype_digit($variables[0])) {
                sort($variables, SORT_NUMERIC);
            }
            $sections[$type] = ['type' => $type, 'text' => $component['text'], 'variables' => $variables];
        }

        if (! isset($sections['body'])) {
            return null;
        }

        return [
            'id' => (string) $template['id'],
            'name' => $template['name'],
            'language' => $template['language'],
            'category' => $template['category'],
            'sections' => array_values(array_filter([$sections['header'] ?? null, $sections['body'], $sections['footer'] ?? null])),
        ];
    }

    private function pages(WhatsAppAccount $account, string $path, string $fields): array
    {
        $items = [];
        $after = null;
        do {
            try {
                $response = $this->client($account)->get($path, array_filter([
                    'fields' => $fields, 'limit' => 100, 'after' => $after,
                ]));
            } catch (ConnectionException) {
                throw $this->templatesUnavailable();
            }

            if (! $response->successful() || ! is_array($response->json('data'))) {
                throw $this->templatesUnavailable();
            }

            array_push($items, ...$response->json('data'));
            $next = $response->json('paging.next') ? $response->json('paging.cursors.after') : null;
            if ($response->json('paging.next') && (! is_string($next) || $next === '' || $next === $after)) {
                throw $this->templatesUnavailable();
            }
            $after = $next;
        } while ($after);

        return $items;
    }

    private function quotaUnavailable(): WhatsAppBroadcastException
    {
        return new WhatsAppBroadcastException('BROADCAST_QUOTA_UNAVAILABLE', 'Limit atau portofolio WhatsApp belum dapat diverifikasi.');
    }

    private function templatesUnavailable(): WhatsAppBroadcastException
    {
        return new WhatsAppBroadcastException('BROADCAST_TEMPLATES_UNAVAILABLE', 'Template WhatsApp belum dapat diverifikasi.');
    }
}
