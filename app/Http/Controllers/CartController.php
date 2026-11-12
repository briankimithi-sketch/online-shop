<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->cartResponse($request);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($request, $validated) {
            $product = Product::query()
                ->whereKey($validated['product_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $item = CartItem::query()
                ->where('user_id', $request->user()->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            $newQuantity = ($item?->quantity ?? 0)
                + $validated['quantity'];

            if ($newQuantity > (int) $product->stock) {
                throw ValidationException::withMessages([
                    'quantity' => [
                        "Only {$product->stock} units of {$product->name} are available.",
                    ],
                ]);
            }

            if ($item) {
                $item->update(['quantity' => $newQuantity]);
            } else {
                CartItem::create([
                    'user_id' => $request->user()->id,
                    'product_id' => $product->id,
                    'quantity' => $newQuantity,
                ]);
            }
        });

        return $this->cartResponse($request, 'Item added to cart.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($request, $id, $validated) {
            $item = CartItem::query()
                ->where('user_id', $request->user()->id)
                ->whereKey($id)
                ->firstOrFail();

            $product = Product::query()
                ->whereKey($item->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($validated['quantity'] > (int) $product->stock) {
                throw ValidationException::withMessages([
                    'quantity' => [
                        "Only {$product->stock} units of {$product->name} are available.",
                    ],
                ]);
            }

            $item->update([
                'quantity' => $validated['quantity'],
            ]);
        });

        return $this->cartResponse($request, 'Cart quantity updated.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $item = CartItem::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $item->delete();

        return $this->cartResponse($request, 'Item removed from cart.');
    }

    private function cartResponse(
        Request $request,
        string $message = 'Cart retrieved successfully.'
    ): JsonResponse {
        $items = CartItem::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('product')
            ->with('product')
            ->orderBy('id')
            ->get()
            ->map(function (CartItem $item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->product->name,
                    'price' => (float) $item->product->price,
                    'quantity' => $item->quantity,
                    'stock' => (int) $item->product->stock,
                ];
            })
            ->values();

        return response()->json([
            'message' => $message,
            'items' => $items,
        ]);
    }
}
