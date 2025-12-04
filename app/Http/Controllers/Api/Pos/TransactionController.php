<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(
        protected TransactionService $transactionService
    ) {}

    /**
     * Display a listing of transactions with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $perPage = $validated['per_page'] ?? 20;
        $storeId = $validated['store_id'] ?? null;

        $transactions = $this->transactionService->list($perPage, $storeId);

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    /**
     * Display the specified transaction.
     */
    public function show(int $id): JsonResponse
    {
        $transaction = $this->transactionService->find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $transaction,
        ]);
    }

    /**
     * Search transactions by order number.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => 'required|string|min:1',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $storeId = $validated['store_id'] ?? null;

        $transactions = $this->transactionService->searchByOrderNumber(
            $validated['order_number'],
            $storeId
        );

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    /**
     * Filter transactions by date.
     */
    public function filterByDate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $date = Carbon::parse($validated['date']);
        $storeId = $validated['store_id'] ?? null;

        $transactions = $this->transactionService->filterByDate($date, $storeId);

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    /**
     * Filter transactions by date range.
     */
    public function filterByDateRange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $storeId = $validated['store_id'] ?? null;

        $transactions = $this->transactionService->filterByDateRange($startDate, $endDate, $storeId);

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }
}
