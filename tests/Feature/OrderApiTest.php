<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_order(): void
    {
        $response = $this->postJson('/api/orders', [
            'customer_name' => 'Ahmed',
            'size' => 'medium',
            'notes' => 'No sugar',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.customer_name', 'Ahmed')
            ->assertJsonPath('data.status', 'new');

        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Ahmed',
            'size' => 'medium',
            'notes' => 'No sugar',
            'status' => 'new',
        ]);
    }

    public function test_update_status_valid_and_invalid_transitions(): void
    {
        $order = Order::create([
            'customer_name' => 'Sara',
            'size' => 'small',
            'status' => 'new',
        ]);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'preparing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'preparing');

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'preparing'])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'new'])
            ->assertStatus(409);
    }

    public function test_show_existing_and_missing_orders(): void
    {
        $order = Order::create([
            'customer_name' => 'Omar',
            'size' => 'large',
            'status' => 'new',
        ]);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.status', 'new');

        $this->getJson('/api/orders/999999')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_validation_rejects_invalid_create_data(): void
    {
        $this->postJson('/api/orders', [
            'customer_name' => '',
            'size' => 'extra-large',
            'notes' => str_repeat('a', 501),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_name', 'size', 'notes']);
    }

    public function test_order_can_be_cancelled_with_reason_and_ready_cannot_be_cancelled(): void
    {
        $order = Order::create([
            'customer_name' => 'Cancel Test',
            'size' => 'medium',
            'status' => 'new',
        ]);

        $this->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'cancelled',
            'cancellation_reason' => 'Customer changed plans',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Customer changed plans');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
            'cancellation_reason' => 'Customer changed plans',
        ]);

        $readyOrder = Order::create([
            'customer_name' => 'Ready Test',
            'size' => 'large',
            'status' => 'ready',
        ]);

        $this->patchJson("/api/orders/{$readyOrder->id}/status", [
            'status' => 'cancelled',
            'cancellation_reason' => 'Too late',
        ])
            ->assertStatus(409);
    }

    public function test_cancellation_requires_reason(): void
    {
        $order = Order::create([
            'customer_name' => 'Reason Test',
            'size' => 'small',
            'status' => 'new',
        ]);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cancellation_reason']);
    }

    public function test_validation_rejects_invalid_status(): void
    {
        $order = Order::create([
            'customer_name' => 'Lina',
            'size' => 'medium',
            'status' => 'new',
        ]);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'voided'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_redis_cache_is_used_and_invalidated(): void
    {
        $redis = @new \Redis();

        if (! $redis || ! @$redis->connect(config('database.redis.cache.host'), (int) config('database.redis.cache.port'), 0.2)) {
            $this->markTestSkipped('Redis server is unavailable.');
        }

        config()->set('cache.default', 'redis');
        config()->set('database.redis.cache.database', 15);
        config()->set('cache.prefix', 'b17-test-');
        Cache::forget("order_status_9999991");

        $order = Order::create([
            'customer_name' => 'Redis Test',
            'size' => 'medium',
            'status' => 'new',
        ]);
        $key = "order_status_{$order->id}";

        Cache::forget($key);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'new');

        $this->assertTrue(Cache::has($key));

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'preparing'])
            ->assertOk();

        $this->assertFalse(Cache::has($key));

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'preparing');

        Cache::forget($key);
    }

    public function test_api_falls_back_to_mysql_when_cache_is_unavailable(): void
    {
        $order = Order::create([
            'customer_name' => 'Fallback Test',
            'size' => 'small',
            'status' => 'new',
        ]);

        config()->set('cache.default', 'redis');
        config()->set('database.redis.cache.host', '127.0.0.1');
        config()->set('database.redis.cache.port', 1);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'new');
    }
}
