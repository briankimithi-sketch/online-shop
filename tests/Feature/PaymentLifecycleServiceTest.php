<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\PaymentLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Product $headphones;

    private PaymentLifecycleService $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->customer = User::create([
            'name' => 'Lifecycle Customer',
            'email' => 'lifecycle@example.com',
            'phone' => '0712345678',
            'delivery_address' => 'Nairobi, Kenya',
            'password' => 'TestPassword123!',
            'role_id' => $roleId,
        ]);

        $this->headphones = $this->createProduct(
            'Wireless Headphones',
            4500,
            8
        );

        $this->lifecycle = app(PaymentLifecycleService::class);
    }

    public function test_pending_payment_can_be_marked_paid(): void
    {
        [$order, $payment] = $this->createCheckout();

        $result = $this->lifecycle->markPaid($payment, [
            'mpesa_receipt_number' => 'QWE1234567',
        ]);

        $this->assertTrue($result['transitioned']);
        $this->assertSame('paid', $result['status']);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_status' => 'paid',
            'mpesa_receipt_number' => 'QWE1234567',
        ]);

        $this->assertNotNull($payment->fresh()->paid_at);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);

        // Successful payment must not restore reserved stock.
        $this->assertDatabaseHas('products', [
            'id' => $this->headphones->id,
            'stock' => 6,
        ]);

        $this->assertNull($payment->fresh()->inventory_restored_at);
    }

    public function test_failed_multi_item_payment_restores_all_stock(): void
    {
        [$order, $payment] = $this->createCheckout();

        $shoes = $this->createProduct('Running Shoes', 17000, 5);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $shoes->id,
            'product_name' => $shoes->name,
            'quantity' => 2,
            'unit_price' => 17000,
        ]);

        $result = $this->lifecycle->markFailed($payment, [
            'mpesa_result_code' => '1032',
            'mpesa_result_desc' => 'Request cancelled by user',
        ]);

        $this->assertTrue($result['transitioned']);
        $this->assertSame('failed', $result['status']);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_status' => 'failed',
            'mpesa_result_code' => '1032',
        ]);

        $this->assertNotNull($payment->fresh()->inventory_restored_at);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $this->headphones->id,
            'stock' => 8,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $shoes->id,
            'stock' => 7,
        ]);
    }

    public function test_duplicate_failure_callback_does_not_restore_stock_twice(): void
    {
        [, $payment] = $this->createCheckout();

        $first = $this->lifecycle->markFailed($payment);
        $second = $this->lifecycle->markFailed($payment->fresh());

        $this->assertTrue($first['transitioned']);
        $this->assertFalse($second['transitioned']);
        $this->assertSame('failed', $second['status']);

        $this->assertDatabaseHas('products', [
            'id' => $this->headphones->id,
            'stock' => 8,
        ]);

        $this->assertNotNull($payment->fresh()->inventory_restored_at);
    }

    public function test_late_failure_cannot_reverse_a_paid_payment(): void
    {
        [$order, $payment] = $this->createCheckout();

        $this->lifecycle->markPaid($payment);

        $result = $this->lifecycle->markFailed($payment->fresh());

        $this->assertFalse($result['transitioned']);
        $this->assertSame('paid', $result['status']);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_status' => 'paid',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $this->headphones->id,
            'stock' => 6,
        ]);

        $this->assertNull($payment->fresh()->inventory_restored_at);
    }

    public function test_late_success_cannot_reverse_a_failed_payment(): void
    {
        [$order, $payment] = $this->createCheckout();

        $this->lifecycle->markFailed($payment);

        $result = $this->lifecycle->markPaid($payment->fresh());

        $this->assertFalse($result['transitioned']);
        $this->assertSame('failed', $result['status']);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_status' => 'failed',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $this->headphones->id,
            'stock' => 8,
        ]);
    }

    public function test_failed_legacy_order_restores_stock_without_order_items(): void
    {
        $order = Order::create([
            'user_id' => $this->customer->id,
            'product_id' => $this->headphones->id,
            'quantity' => 2,
            'status' => 'pending',
            'phone' => '0712345678',
            'delivery_address' => 'Nairobi, Kenya',
            'subtotal' => 9000,
            'shipping' => 0,
            'total' => 9000,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 9000,
            'payment_status' => 'pending',
        ]);

        $this->assertDatabaseCount('order_items', 0);

        // Simulate the stock reservation made when this legacy order was created.
        $this->headphones->decrement('stock', 2);

        $this->lifecycle->markFailed($payment);

        $this->assertDatabaseHas('products', [
            'id' => $this->headphones->id,
            'stock' => 8,
        ]);

        $this->assertNotNull($payment->fresh()->inventory_restored_at);
    }

    public function test_failed_payment_restores_stock_for_soft_deleted_product(): void
    {
        [, $payment] = $this->createCheckout();

        $this->headphones->delete();

        $this->lifecycle->markFailed($payment);

        $this->assertDatabaseHas('products', [
            'id' => $this->headphones->id,
            'stock' => 8,
        ]);

        $this->assertNotNull($this->headphones->fresh()->deleted_at);
    }

    private function createCheckout(): array
    {
        $order = Order::create([
            'user_id' => $this->customer->id,
            'product_id' => $this->headphones->id,
            'quantity' => 2,
            'status' => 'pending',
            'phone' => '0712345678',
            'delivery_address' => 'Nairobi, Kenya',
            'subtotal' => 9000,
            'shipping' => 0,
            'total' => 9000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->headphones->id,
            'product_name' => $this->headphones->name,
            'quantity' => 2,
            'unit_price' => 4500,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 9000,
            'payment_status' => 'pending',
        ]);

        // The two ordered units have already been reserved.
        $this->headphones->decrement('stock', 2);

        return [$order, $payment];
    }

    private function createProduct(
        string $name,
        int $price,
        int $stock
    ): Product {
        return Product::create([
            'name' => $name,
            'price' => $price,
            'description' => 'Lifecycle test product',
            'category' => 'Test',
            'image' => 'test.jpg',
            'stock' => $stock,
        ]);
    }
}
