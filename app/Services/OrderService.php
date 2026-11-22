<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class OrderService
{
    public function create(array $data): Order
    {

            return Order::create([
                'customer_name' => $data['customer_name'],
                'size' => $data['size'],
                'notes' => $data['notes'] ?? null,
                'status' => Order::STATUS_NEW,
            ]);

    }

    public function updateStatus(Order $order, string $status, ?string $cancellationReason = null): array
    {
        if ($order->status === $status) {
            return [
                'ok' => false,
                'code' => 409,
                'message' => "The order is already in '{$status}' status.",
            ];
        }

        $allowed = [
            Order::STATUS_NEW => [Order::STATUS_PREPARING, Order::STATUS_CANCELLED],
            Order::STATUS_PREPARING => [Order::STATUS_READY, Order::STATUS_CANCELLED],
            Order::STATUS_READY => [],
            Order::STATUS_CANCELLED => [],
        ];

        if (! in_array($status, $allowed[$order->status] ?? [], true)) {
            return [
                'ok' => false,
                'code' => 409,
                'message' => "Transition from '{$order->status}' to '{$status}' is not allowed.",
            ];
        }

        DB::transaction(function () use ($order, $status, $cancellationReason): void {
            $order->update([
                'status' => $status,
                'cancellation_reason' => $status === Order::STATUS_CANCELLED ? $cancellationReason : null,
            ]);
        });

        $this->invalidateCache($order->id);

        return ['ok' => true, 'order' => $order->fresh()];
    }

    public function findWithCache(int $id): ?array
    {
        $key = $this->cacheKey($id);

        try {
            return Cache::remember($key, 60, function () use ($id): ?array {
                $order = Order::find($id);

                return $order ? $this->serializeOrder($order) : null;
            });
        } catch (Throwable) {
            $order = Order::find($id);

            return $order ? $this->serializeOrder($order) : null;
        }
    }

    public function invalidateCache(int $id): void
    {
        try {
            Cache::forget($this->cacheKey($id));
        } catch (Throwable) {

        }
    }

    public function serializeOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'customer_name' => $order->customer_name,
            'size' => $order->size,
            'notes' => $order->notes,
            'status' => $order->status,
            'cancellation_reason' => $order->cancellation_reason,
            'created_at' => $order->created_at?->toISOString(),
            'updated_at' => $order->updated_at?->toISOString(),
        ];
    }

    private function cacheKey(int $id): string
    {
        return "order_status_{$id}";
    }
}
