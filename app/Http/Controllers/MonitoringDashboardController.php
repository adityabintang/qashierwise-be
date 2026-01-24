<?php

namespace App\Http\Controllers;

use App\Services\SubscriptionMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller for subscription monitoring dashboard.
 * 
 * Provides endpoints for viewing metrics, health status, and alerts.
 */
class MonitoringDashboardController extends Controller
{
    public function __construct(
        private SubscriptionMonitoringService $monitoring
    ) {}

    /**
     * Show monitoring dashboard.
     *
     * @return View
     */
    public function index(): View
    {
        $metrics = $this->monitoring->getMetrics();
        $health = $this->monitoring->checkHealth();

        return view('monitoring.dashboard', [
            'metrics' => $metrics,
            'health' => $health,
        ]);
    }

    /**
     * Get metrics as JSON (for AJAX updates).
     *
     * @return JsonResponse
     */
    public function metrics(): JsonResponse
    {
        $metrics = $this->monitoring->getMetrics();

        return response()->json([
            'success' => true,
            'data' => $metrics,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get health status as JSON.
     *
     * @return JsonResponse
     */
    public function health(): JsonResponse
    {
        $health = $this->monitoring->checkHealth();

        $statusCode = $health['status'] === 'healthy' ? 200 : 503;

        return response()->json($health, $statusCode);
    }

    /**
     * Get subscription system status (simple endpoint for uptime monitoring).
     *
     * @return JsonResponse
     */
    public function status(): JsonResponse
    {
        $health = $this->monitoring->checkHealth();

        return response()->json([
            'status' => $health['status'],
            'timestamp' => now()->toIso8601String(),
        ], $health['status'] === 'healthy' ? 200 : 503);
    }
}
