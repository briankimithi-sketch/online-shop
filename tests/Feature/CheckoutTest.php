<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Mockery;
use FelixMuhoro\Mpesa\Mpesa;
use FelixMuhoro\Mpesa\DTOs\StkPushResponse;
use App\Services\MpesaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Product $headphones;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->customer = User::create([
            'name' => 'Checkout Customer',
            'email' => 'checkout@example.com',
            'phone' => '0712345678',
            'delivery_address' => 'Nairobi, Kenya',
            'password' => 'TestPassword123!',
            'role_id' => $roleId,
        ]);

        $this->headphones = $this->createProduct(
            'Wireless Headphones',
            4500,
            10
        );
    }

    public function test_customer_can_checkout_multiple_products_with_one_order_and_payment(): void
    {
        $shoes = $this->createProduct('Running Shoes', 17000, 8);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [
                    [
                        'product_id' => $this->headphones->id,
                        'quantity' => 2,
                        'price' => 1,
                    ],
                    [
                        'product_id' => $shoes->id,
                        'quantity' => 1,
                        'price' => 1,
                    ],
                ],
                'phone' => '0722000000',
                'delivery_address' => 'Westlands, Nairobi',
                'total' => 1,
            ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Order created successfully.')
            ->assertJsonPath('order.subtotal', 26000)
            ->assertJsonPath('order.shipping', 0)
            ->assertJsonPath('order.total', 26000)
            ->assertJsonPath('order.phone', '0712345678')
            ->assertJsonPath('order.delivery_address', 'Westlands, Nairobi')
            ->assertJsonPath('payment.amount', 26000)
            ->assertJsonPath('payment.payment_status', 'pending')
            ->assertJsonCount(2, 'order.items')
            ->assertJsonCount(1, 'orders');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseCount('payments', 1);

        $this->assertDatabaseHas('products', [
            'id' => $this->headphones->id,
            'stock' => 8,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $shoes->id,
            'stock' => 7,
        ]);

        $order = Order::with('items')->firstOrFail();

        $this->assertCount(2, $order->items);
        $this->assertSame(2, $order->items->firstWhere(
            'product_id',
            $this->headphones->id
        )->quantity);

        $this->assertSame(1, $order->items->firstWhere(
            'product_id',
            $shoes->id
        )->quantity);

        $this->assertSame($order->id, Payment::firstOrFail()->order_id);
    }

    public function test_duplicate_product_lines_are_combined_before_stock_deduction(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [
                    [
                        'product_id' => $this->headphones->id,
                        'quantity' => 2,
                    ],
                    [
                        'product_id' => $this->headphones->id,
                        'quantity' => 3,
                    ],
                ],
                'phone' => '0712345678',
                'delivery_address' => 'Kahawa Sukari, Nairobi',
            ]);

        $response->assertCreated()
            ->assertJsonPath('order.subtotal', 22500)
            ->assertJsonPath('order.shipping', 0)
            ->assertJsonPath('order.total', 22500)
            ->assertJsonCount(1, 'order.items');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('payments', 1);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $this->headphones->id,
            'quantity' => 5,
            'unit_price' => 4500,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $this->headphones->id,
            'stock' => 5,
        ]);
    }

    public function test_shipping_is_added_when_subtotal_is_not_above_five_thousand(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [
                    [
                        'product_id' => $this->headphones->id,
                        'quantity' => 1,
                    ],
                ],
                'phone' => '0712345678',
                'delivery_address' => 'Nairobi, Kenya',
            ]);

        $response->assertCreated()
            ->assertJsonPath('order.subtotal', 4500)
            ->assertJsonPath('order.shipping', 500)
            ->assertJsonPath('order.total', 5000)
            ->assertJsonPath('payment.amount', 5000);
    }

    public function test_insufficient_stock_rejects_entire_checkout_without_partial_deduction(): void
    {
        $shoes = $this->createProduct('Running Shoes', 17000, 1);

        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [
                    [
                        'product_id' => $this->headphones->id,
                        'quantity' => 2,
                    ],
                    [
                        'product_id' => $shoes->id,
                        'quantity' => 2,
                    ],
                ],
                'phone' => '0712345678',
                'delivery_address' => 'Nairobi, Kenya',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('payments', 0);

        $this->assertDatabaseHas('products', [
            'id' => $this->headphones->id,
            'stock' => 10,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $shoes->id,
            'stock' => 1,
        ]);
    }

    public function test_checkout_requires_delivery_address_but_not_a_submitted_phone(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [
                    [
                        'product_id' => $this->headphones->id,
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['delivery_address']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);

        $this->assertDatabaseHas('products', [
            'id' => $this->headphones->id,
            'stock' => 10,
        ]);
    }

    public function test_checkout_rejects_customer_without_registered_phone_even_if_request_supplies_one(): void
    {
        $this->customer->update(['phone' => '']);

        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [
                    [
                        'product_id' => $this->headphones->id,
                        'quantity' => 1,
                    ],
                ],
                'phone' => '0722000000',
                'delivery_address' => 'Westlands, Nairobi',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);

        $this->assertDatabaseHas('products', [
            'id' => $this->headphones->id,
            'stock' => 10,
        ]);
    }


    public function test_mpesa_stk_push_uses_registered_account_phone_not_order_phone(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [
                    [
                        'product_id' => $this->headphones->id,
                        'quantity' => 1,
                    ],
                ],
                'delivery_address' => 'Westlands, Nairobi',
            ])
            ->assertCreated();

        $payment = Payment::firstOrFail();
        $order = $payment->order;

        // Simulate an old or stale phone stored on the order.
        $order->update(['phone' => '0722000000']);

        $mpesa = Mockery::mock(Mpesa::class);

        $mpesa->shouldReceive('stkPush')
            ->once()
            ->withArgs(function (
                $phone,
                $amount,
                $reference,
                $description,
                $callbackUrl
            ) use ($order) {
                return $phone === '0712345678'
                    && $amount === 5000
                    && $reference === "Order_{$order->id}";
            })
            ->andReturn(new StkPushResponse(
                merchantRequestId: 'merchant-test-123',
                checkoutRequestId: 'checkout-test-123',
                responseCode: '0',
                responseDescription: 'Accepted',
                customerMessage: 'Success'
            ));

        $result = (new MpesaService($mpesa))->initiateStkPush($payment);

        $this->assertTrue($result['success']);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'mpesa_phone_number' => '0712345678',
            'mpesa_checkout_request_id' => 'checkout-test-123',
        ]);
    }

    public function test_mpesa_stk_push_is_rejected_when_registered_phone_is_missing(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [
                    [
                        'product_id' => $this->headphones->id,
                        'quantity' => 1,
                    ],
                ],
                'delivery_address' => 'Westlands, Nairobi',
            ])
            ->assertCreated();

        $payment = Payment::firstOrFail();

        // Remove the phone after order creation to exercise the service guard.
        $this->customer->update(['phone' => '']);

        $mpesa = Mockery::mock(Mpesa::class);
        $mpesa->shouldNotReceive('stkPush');

        $result = (new MpesaService($mpesa))->initiateStkPush($payment);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString(
            'registered phone number',
            $result['message']
        );

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'mpesa_checkout_request_id' => null,
            'mpesa_phone_number' => null,
        ]);
    }


    public function test_inline_checkout_data_uses_registered_account_phone(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [
                    [
                        'product_id' => $this->headphones->id,
                        'quantity' => 1,
                    ],
                ],
                'delivery_address' => 'Westlands, Nairobi',
            ])
            ->assertCreated();

        $payment = Payment::firstOrFail();
        $order = $payment->order;

        $order->update(['phone' => '0722000000']);

        $data = app(MpesaService::class)
            ->getInlineCheckoutData($payment);

        $this->assertSame('0712345678', $data['phone_number']);
        $this->assertSame(5000, $data['amount']);
        $this->assertSame("Order_{$order->id}", $data['reference']);
    }

    private function createProduct(
        string $name,
        int $price,
        int $stock
    ): Product {
        return Product::create([
            'name' => $name,
            'price' => $price,
            'description' => 'Checkout test product',
            'category' => 'Test',
            'image' => 'test.jpg',
            'stock' => $stock,
        ]);
    }
}
