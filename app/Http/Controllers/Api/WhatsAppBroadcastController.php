<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\WhatsAppBroadcastException;
use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppBroadcast;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppBroadcast;
use App\Services\WhatsAppAccountService;
use App\Services\WhatsAppBroadcastService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppBroadcastController extends Controller
{
    public function __construct(private WhatsAppAccountService $accounts, private WhatsAppBroadcastService $broadcasts) {}

    public function options(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => $this->broadcasts->options($this->account($request))]);
        } catch (WhatsAppBroadcastException $e) {
            return $this->error($e);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $input = $request->validate([
            'contact_ids' => ['required', 'array', 'list'],
            'contact_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'template_id' => ['required', 'string', 'regex:/^[0-9]{1,32}$/D'],
            'variables' => ['sometimes', 'array:header,body'],
            'variables.header' => ['sometimes', 'array'],
            'variables.body' => ['sometimes', 'array'],
            'variables.header.*' => ['required', 'string', 'max:60', 'not_regex:/[\r\n\t]| {5}/'],
            'variables.body.*' => ['required', 'string', 'max:1024', 'not_regex:/[\r\n\t]| {5}/'],
            'consent_confirmed' => ['required', 'accepted'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        try {
            $broadcast = $this->broadcasts->create($this->account($request), $input);
        } catch (WhatsAppBroadcastException $e) {
            return $this->error($e);
        }

        $progress = WhatsAppBroadcast::withProgress()->findOrFail($broadcast->id);
        if ($progress->queued_count > 0 && config('queue.connections.'.config('queue.default').'.driver') !== 'sync') {
            rescue(function () use ($broadcast) {
                SendWhatsAppBroadcast::dispatch($broadcast->id)->afterCommit();
            });
        }

        return response()->json(['data' => $progress], 202);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->history($request)->latest('id')->paginate(10));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $broadcast = $this->history($request)->findOrFail($id);

        return response()->json([
            'data' => $broadcast,
            'recipients' => $broadcast->recipients()->orderBy('id')->paginate(25),
        ]);
    }

    private function history(Request $request): Builder
    {
        $account = $this->accounts->getActiveAccount($request->user()->id);

        return WhatsAppBroadcast::withProgress()->where('user_id', $request->user()->id)
            ->where('whatsapp_account_id', $account?->id);
    }

    private function account(Request $request): WhatsAppAccount
    {
        return $this->accounts->getActiveAccount($request->user()->id)
            ?? throw new WhatsAppBroadcastException('BROADCAST_QUOTA_UNAVAILABLE', 'Hubungkan akun WhatsApp untuk memverifikasi kuota.');
    }

    private function error(WhatsAppBroadcastException $e): JsonResponse
    {
        $status = in_array($e->errorCode, ['BROADCAST_QUOTA_UNAVAILABLE', 'BROADCAST_TEMPLATES_UNAVAILABLE'], true) ? 503 : 409;

        return response()->json(['error_code' => $e->errorCode, 'message' => $e->getMessage()] + $e->details, $status);
    }
}
