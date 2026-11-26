<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $asOf = CarbonImmutable::now('Asia/Jakarta');
        $asOfUtc = $asOf->utc();
        $activeFrom = $asOf->subDays(30)->utc();
        $firstMonth = $asOf->startOfMonth()->subMonths(11);
        $merchant = fn () => User::query()
            ->where('is_master_admin', true)
            ->where('email', '<>', User::SUPER_ADMIN_EMAIL)
            ->whereDoesntHave('roles', fn ($roles) => $roles->where('name', 'author'));

        $registered = $merchant()->count();
        $active = $merchant()->where(function ($query) use ($activeFrom, $asOfUtc) {
            $query->whereExists(function ($messages) use ($activeFrom, $asOfUtc) {
                $messages->selectRaw('1')->from('whatsapp_messages')
                    ->whereColumn('whatsapp_messages.user_id', 'users.id')
                    ->whereBetween('whatsapp_messages.created_at', [$activeFrom, $asOfUtc]);
            })->orWhereExists(function ($orders) use ($activeFrom, $asOfUtc) {
                $orders->selectRaw('1')->from('orders')
                    ->join('stores', 'stores.id', '=', 'orders.store_id')
                    ->whereColumn('stores.user_id', 'users.id')
                    ->whereBetween('orders.created_at', [$activeFrom, $asOfUtc]);
            })->orWhereExists(function ($reservations) use ($activeFrom, $asOfUtc) {
                $reservations->selectRaw('1')->from('reservations')
                    ->whereColumn('reservations.user_id', 'users.id')
                    ->whereBetween('reservations.created_at', [$activeFrom, $asOfUtc]);
            });
        })->count();

        $validSubscription = function ($query) use ($asOfUtc) {
            $query->selectRaw('1')->from('subscriptions')
                ->whereColumn('subscriptions.user_id', 'users.id')
                ->where('subscriptions.status', 'active')
                ->where('subscriptions.current_period_start', '<=', $asOfUtc)
                ->where('subscriptions.current_period_end', '>', $asOfUtc);
        };
        $paid = $merchant()->whereExists(function ($subscriptions) use ($validSubscription, $asOfUtc) {
            $validSubscription($subscriptions);
            $subscriptions->whereExists(function ($payments) use ($asOfUtc) {
                $payments->selectRaw('1')->from('subscription_payments')
                    ->whereColumn('subscription_payments.subscription_id', 'subscriptions.id')
                    ->whereIn('subscription_payments.status', ['settlement', 'capture'])
                    ->where('subscription_payments.gross_amount', '>', 0)
                    ->where(function ($query) {
                        $query->whereNull('subscription_payments.transaction_id')
                            ->orWhereNull('subscriptions.xendit_subscription_id')
                            ->orWhereColumn('subscription_payments.transaction_id', '<>', 'subscriptions.xendit_subscription_id');
                    })
                    ->whereColumn('subscription_payments.transaction_time', '>=', 'subscriptions.current_period_start')
                    ->where('subscription_payments.transaction_time', '<=', $asOfUtc);
            });
        })->count();
        $unverified = $merchant()->whereExists($validSubscription)->count() - $paid;

        $messageCounts = DB::table('whatsapp_messages')
            ->join('users', 'users.id', '=', 'whatsapp_messages.user_id')
            ->where('users.is_master_admin', true)
            ->where('users.email', '<>', User::SUPER_ADMIN_EMAIL)
            ->whereNotExists(function ($roles) {
                $roles->selectRaw('1')->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', User::class)
                    ->where('roles.name', 'author');
            })
            ->selectRaw("SUM(CASE WHEN direction = 'incoming' THEN 1 ELSE 0 END) AS incoming, SUM(CASE WHEN direction = 'outgoing' THEN 1 ELSE 0 END) AS outgoing")
            ->first();
        $incoming = (int) ($messageCounts->incoming ?? 0);
        $outgoing = (int) ($messageCounts->outgoing ?? 0);

        $withWhatsApp = $merchant()->whereExists(function ($accounts) {
            $accounts->selectRaw('1')->from('whatsapp_accounts')
                ->whereColumn('whatsapp_accounts.user_id', 'users.id')
                ->where('whatsapp_accounts.is_active', true);
        })->count();

        $months = [];
        for ($offset = 0; $offset < 12; $offset++) {
            $month = $firstMonth->addMonths($offset);
            $end = $month->addMonth();
            $months[] = [
                'month' => $month->format('Y-m'),
                'count' => $merchant()->where('created_at', '>=', $month->utc())
                    ->where('created_at', '<', $end->utc())->count(),
                'partial' => $offset === 11,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'as_of' => $asOf->toIso8601String(),
                'activity_from' => $activeFrom->setTimezone('Asia/Jakarta')->toIso8601String(),
                'months' => $months,
                'stats' => [
                    'registered' => $registered,
                    'active' => $active,
                    'paid' => $paid,
                    'unpaid' => $registered - $paid - $unverified,
                    'unverified' => $unverified,
                    'messages_total' => $incoming + $outgoing,
                    'messages_incoming' => $incoming,
                    'messages_outgoing' => $outgoing,
                    'whatsapp_active' => $withWhatsApp,
                ],
            ],
        ])->header('Cache-Control', 'private, no-store');
    }
}
