<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index()
    {
        $user = request()->user();

        $query = Order::with([
            'items.product',
            'product',
            'user',
            'payment',
        ])->latest();

        if ((int) $user->role_id !== 1) {
            $query->where('user_id', $user->id);
        }

        return response()->json($query->get());
    }

    public function create()
    {
        return response()->json([
            'message' => 'Use the order API to create an order.',
        ], 405);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'product_id' => 'required_without:items|integer|exists:products,id',
            'quantity' => 'required_without:items|integer|min:1',
            'items' => 'required_without:product_id|array|min:1',
            'items.*.product_id' => 'required_with:items|integer|exists:products,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'delivery_address' => 'required|string|max:2000',
        ]);

        if (! is_string($user->phone) || trim($user->phone) === '') {
            throw ValidationException::withMessages([
                'phone' => [
                    'Your account has no registered phone number. Please update your profile before checkout.',
                ],
            ]);
        }

        $items = $validated['items'] ?? [[
            'product_id' => $validated['product_id'],
            'quantity' => $validated['quantity'],
        ]];

        // Aggregate duplicate product IDs to prevent stock-check bypasses.
        $quantities = [];

        foreach ($items as $item) {
            $id = (int) $item['product_id'];
            $quantity = (int) $item['quantity'];

            if (($quantities[$id] ?? 0) > PHP_INT_MAX - $quantity) {
                throw ValidationException::withMessages([
                    'items' => ['The requested quantity is too large.'],
                ]);
            }

            $quantities[$id] = ($quantities[$id] ?? 0) + $quantity;
        }

        // Consistent lock ordering helps reduce deadlock risk.
        ksort($quantities);

        $order = DB::transaction(function () use (
            $user,
            $validated,
            $quantities
        ) {
            $products = Product::query()
                ->whereIn('id', array_keys($quantities))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== count($quantities)) {
                throw ValidationException::withMessages([
                    'items' => ['One or more products are no longer available.'],
                ]);
            }

            $subtotal = 0;

            // Validate all products and calculate prices before changing stock.
            foreach ($quantities as $productId => $quantity) {
                $product = $products->get($productId);

                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => [
                            "Insufficient stock for {$product->name}. Available: {$product->stock}.",
                        ],
                    ]);
                }

                if (! is_numeric($product->price) || (float) $product->price < 0) {
                    throw ValidationException::withMessages([
                        'items' => ["Product {$product->name} has an invalid price."],
                    ]);
                }

                $unitPrice = (int) round((float) $product->price);

                if (
                    $unitPrice > intdiv(PHP_INT_MAX, $quantity) ||
                    $subtotal > PHP_INT_MAX - ($unitPrice * $quantity)
                ) {
                    throw ValidationException::withMessages([
                        'items' => ['The checkout total is too large.'],
                    ]);
                }

                $subtotal += $unitPrice * $quantity;
            }

            // Shipping: free above KSh 5,000; otherwise KSh 500.
            $shipping = $subtotal > 5000 ? 0 : 500;
            $total = $subtotal + $shipping;

            // The database columns are unsigned integers.
            $maxDatabaseInteger = 4294967295;

            if (
                $subtotal > $maxDatabaseInteger ||
                $shipping > $maxDatabaseInteger ||
                $total > $maxDatabaseInteger
            ) {
                throw ValidationException::withMessages([
                    'items' => ['The checkout total exceeds the supported amount.'],
                ]);
            }

            // Deduct each product's aggregated quantity once.
            foreach ($quantities as $productId => $quantity) {
                $products->get($productId)->decrement('stock', $quantity);
            }

            // Preserve legacy columns using the first product.
            $firstProductId = array_key_first($quantities);
            $firstQuantity = $quantities[$firstProductId];

            $order = Order::create([
                'user_id' => $user->id,
                'product_id' => $firstProductId,
                'quantity' => $firstQuantity,
                'status' => 'pending',
                'phone' => trim($user->phone),
                'delivery_address' => $validated['delivery_address'],
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'total' => $total,
            ]);

            // One order can now contain multiple product lines.
            foreach ($quantities as $productId => $quantity) {
                $product = $products->get($productId);

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => (int) round((float) $product->price),
                ]);
            }

            // Exactly one payment for the entire checkout.
            $payment = Payment::create([
                'order_id' => $order->id,
                'amount' => $total,
                'payment_status' => 'pending',
            ]);

            return $order->load([
                'items.product',
                'product',
                'user',
                'payment',
            ]);
        }, 3);

        return response()->json([
            'message' => 'Order created successfully.',
            'order' => $order,
            'orders' => [$order],
            'payment' => $order->payment,
        ], 201);
    }

    public function show(Order $order)
    {
        $user = request()->user();

        if (
            (int) $user->role_id !== 1 &&
            (int) $order->user_id !== (int) $user->id
        ) {
            return response()->json([
                'message' => 'You are not authorized to view this order.',
            ], 403);
        }

        return response()->json(
            $order->load([
                'items.product',
                'product',
                'user',
                'payment',
            ])
        );
    }

    public function edit(Order $order)
    {
        return response()->json([
            'message' => 'Order editing is not supported.',
        ], 405);
    }

    public function update(Request $request, Order $order)
    {
        return response()->json([
            'message' => 'Orders cannot be edited after checkout. Contact support if a correction is needed.',
        ], 409);
    }

    public function destroy(Order $order)
    {
        return response()->json([
            'message' => 'Orders cannot be deleted through this endpoint because inventory and payment records must remain consistent.',
        ], 409);
    }
}
