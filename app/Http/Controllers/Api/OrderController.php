<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Throwable;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orders->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully.',
                'data' => $this->orders->serializeOrder($order),
            ], 201);
        } catch (Throwable $exception) {
            report($exception);
            return $this->serverError();
        }
    }

    public function updateStatus(UpdateOrderStatusRequest $request, int $id): JsonResponse
    {
        $order = Order::find($id);

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
                'errors' => null,
            ], 404);
        }

        try {
            $validated = $request->validated();
            $result = $this->orders->updateStatus(
                $order,
                $validated['status'],
                $validated['cancellation_reason'] ?? null,
            );

            if (! $result['ok']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => null,
                ], $result['code']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully.',
                'data' => $this->orders->serializeOrder($result['order']),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            return $this->serverError();
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $order = $this->orders->findWithCache($id);

            if (! $order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found.',
                    'errors' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Order retrieved successfully.',
                'data' => $order,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            return $this->serverError();
        }
    }

    private function serverError(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'An internal server error occurred.',
            'errors' => null,
        ], 500);
    }
}
