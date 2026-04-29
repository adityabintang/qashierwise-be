<?php

namespace App\Jobs;

use App\Services\MetaConversionsApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;

/**
 * Queued delivery of a single Meta Conversions API event so the user-facing
 * response isn't blocked by the outbound HTTP call to graph.facebook.com.
 *
 * The Request object can't be serialized, so callers pass the bits CAPI needs
 * (ip, user agent, fbp/fbc cookies, source url) directly.
 */
class SendMetaCapiEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        protected string $eventName,
        protected array $userData = [],
        protected array $customData = [],
        protected ?string $eventId = null,
        protected ?string $eventSourceUrl = null,
        protected ?string $clientIp = null,
        protected ?string $clientUserAgent = null,
        protected ?string $fbp = null,
        protected ?string $fbc = null,
    ) {}

    public function handle(MetaConversionsApiService $capi): void
    {
        if (! $capi->isConfigured()) {
            return;
        }

        $userData = $this->userData;
        if (! empty($this->fbp) && empty($userData['fbp'])) {
            $userData['fbp'] = $this->fbp;
        }
        if (! empty($this->fbc) && empty($userData['fbc'])) {
            $userData['fbc'] = $this->fbc;
        }

        $request = $this->reconstructRequest();

        $capi->sendEvent(
            eventName: $this->eventName,
            request: $request,
            userData: $userData,
            customData: $this->customData,
            eventId: $this->eventId,
            eventSourceUrl: $this->eventSourceUrl,
        );
    }

    /**
     * Build a minimal Request the service can read ip / user agent / cookies
     * from, since the original Request can't cross a queue boundary.
     */
    protected function reconstructRequest(): ?Request
    {
        if (empty($this->clientIp) && empty($this->clientUserAgent)
            && empty($this->fbp) && empty($this->fbc)
            && empty($this->eventSourceUrl)) {
            return null;
        }

        $server = [];
        if (! empty($this->clientIp)) {
            $server['REMOTE_ADDR'] = $this->clientIp;
        }
        if (! empty($this->clientUserAgent)) {
            $server['HTTP_USER_AGENT'] = $this->clientUserAgent;
        }

        $cookies = [];
        if (! empty($this->fbp)) {
            $cookies['_fbp'] = $this->fbp;
        }
        if (! empty($this->fbc)) {
            $cookies['_fbc'] = $this->fbc;
        }

        return Request::create(
            uri: $this->eventSourceUrl ?? 'http://localhost',
            method: 'GET',
            parameters: [],
            cookies: $cookies,
            files: [],
            server: $server,
        );
    }
}
