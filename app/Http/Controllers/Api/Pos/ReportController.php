<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    /**
     * Get daily sales report.
     */
    public function dailySales(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $date = isset($validated['date']) 
            ? Carbon::parse($validated['date']) 
            : Carbon::today();
        
        $storeId = $validated['store_id'] ?? null;

        $report = $this->reportService->dailySales($date, $storeId);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Get sales report by date range.
     */
    public function salesByRange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);
        $storeId = $validated['store_id'] ?? null;

        $report = $this->reportService->salesByRange($start, $end, $storeId);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Get top selling products.
     */
    public function topProducts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'limit' => 'nullable|integer|min:1|max:100',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $start = isset($validated['start_date']) 
            ? Carbon::parse($validated['start_date']) 
            : Carbon::today()->subDays(30);
        
        $end = isset($validated['end_date']) 
            ? Carbon::parse($validated['end_date']) 
            : Carbon::today();
        
        $limit = $validated['limit'] ?? 10;
        $storeId = $validated['store_id'] ?? null;

        $products = $this->reportService->topProducts($start, $end, $limit, $storeId);

        return response()->json([
            'success' => true,
            'data' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'store_id' => $storeId,
                'products' => $products,
            ],
        ]);
    }

    /**
     * Get sales by payment method.
     */
    public function salesByPaymentMethod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $start = isset($validated['start_date']) 
            ? Carbon::parse($validated['start_date']) 
            : Carbon::today()->subDays(30);
        
        $end = isset($validated['end_date']) 
            ? Carbon::parse($validated['end_date']) 
            : Carbon::today();
        
        $storeId = $validated['store_id'] ?? null;

        $report = $this->reportService->salesByPaymentMethod($start, $end, $storeId);

        return response()->json([
            'success' => true,
            'data' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'store_id' => $storeId,
                'payment_methods' => $report,
            ],
        ]);
    }

    /**
     * Get hourly sales distribution.
     */
    public function hourlySales(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $date = isset($validated['date']) 
            ? Carbon::parse($validated['date']) 
            : Carbon::today();
        
        $storeId = $validated['store_id'] ?? null;

        $report = $this->reportService->hourlySales($date, $storeId);

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date->toDateString(),
                'store_id' => $storeId,
                'hourly_data' => $report,
            ],
        ]);
    }
}
