<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CartControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->customer = User::create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone' => '0712345678',
            'delivery_address' => 'Nairobi, Kenya',
            'password' => 'TestPassword123!',
            'role_id' => $roleId,
        ]);

        $this->product = Product::create([
            'name' => 'Wireless Headphones',
            'price' => '4500',
            'description' => 'Test product',
            'category' => 'Electronics',
            'image' => 'headphones.jpg',
            'stock' => 10,
        ]);
    }

    public function test_guest_cannot_access_cart(): void
    {
        $this->getJson('/api/cart')->assertUnauthorized();

        $this->postJson('/api/cart', [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ])->assertUnauthorized();
    }

    public function test_customer_can_view_an_empty_cart(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'items',
            ])
            ->assertJsonCount(0, 'items');
    }

    public function test_customer_can_add_a_product_to_the_cart(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/cart', [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('items.0.product_id', $this->product->id)
            ->assertJsonPath('items.0.name', 'Wireless Headphones')
            ->assertJsonPath('items.0.quantity', 2)
            ->assertJsonPath('items.0.stock', 10);

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);
    }

    public function test_adding_the_same_product_increases_its_quantity(): void
    {
        $this->actingAs($this->customer, 'sanctum');

        $this->postJson('/api/cart', [
            'product_id' => $this->product->id,
            'quantity' => 2,
        ])->assertOk();

        $this->postJson('/api/cart', [
            'product_id' => $this->product->id,
            'quantity' => 3,
        ])->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.quantity', 5);

        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_customer_can_update_cart_quantity(): void
    {
        $item = CartItem::create([
            'user_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $this->actingAs($this->customer, 'sanctum')
            ->putJson("/api/cart/{$item->id}", [
                'quantity' => 4,
            ])
            ->assertOk()
            ->assertJsonPath('items.0.id', $item->id)
            ->assertJsonPath('items.0.quantity', 4);

        $this->assertDatabaseHas('cart_items', [
            'id' => $item->id,
            'quantity' => 4,
        ]);
    }

    public function test_customer_can_remove_a_cart_item(): void
    {
        $item = CartItem::create([
            'user_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $this->actingAs($this->customer, 'sanctum')
            ->deleteJson("/api/cart/{$item->id}")
            ->assertOk()
            ->assertJsonCount(0, 'items');

        $this->assertDatabaseMissing('cart_items', [
            'id' => $item->id,
        ]);
    }

    public function test_customer_cannot_add_more_than_available_stock(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/cart', [
                'product_id' => $this->product->id,
                'quantity' => 11,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_customer_cannot_set_quantity_above_available_stock(): void
    {
        $item = CartItem::create([
            'user_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $this->actingAs($this->customer, 'sanctum')
            ->putJson("/api/cart/{$item->id}", [
                'quantity' => 11,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertDatabaseHas('cart_items', [
            'id' => $item->id,
            'quantity' => 2,
        ]);
    }

    public function test_customer_cannot_add_zero_or_negative_quantity(): void
    {
        $this->actingAs($this->customer, 'sanctum');

        foreach ([0, -1] as $quantity) {
            $this->postJson('/api/cart', [
                'product_id' => $this->product->id,
                'quantity' => $quantity,
            ])->assertUnprocessable()
                ->assertJsonValidationErrors('quantity');
        }

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_customer_cannot_change_another_customers_cart_item(): void
    {
        $otherCustomer = $this->createAnotherCustomer();

        $otherItem = CartItem::create([
            'user_id' => $otherCustomer->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $this->actingAs($this->customer, 'sanctum');

        $this->getJson('/api/cart')
            ->assertOk()
            ->assertJsonCount(0, 'items');

        $this->putJson("/api/cart/{$otherItem->id}", [
            'quantity' => 5,
        ])->assertNotFound();

        $this->deleteJson("/api/cart/{$otherItem->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('cart_items', [
            'id' => $otherItem->id,
            'user_id' => $otherCustomer->id,
            'quantity' => 2,
        ]);
    }

    public function test_cart_ignores_items_for_soft_deleted_products(): void
    {
        CartItem::create([
            'user_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $this->product->delete();

        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonCount(0, 'items');
    }

    private function createAnotherCustomer(): User
    {
        return User::create([
            'name' => 'Another Customer',
            'email' => 'another@example.com',
            'phone' => '0798765432',
            'delivery_address' => 'Westlands, Nairobi',
            'password' => 'TestPassword123!',
            'role_id' => $this->customer->role_id,
        ]);
    }
}
