<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        return response()->json(Product::all());
    }

    public function create()
    {
        return response()->json(['message' => 'Use the product API to create a product.'], 405);
    }

    public function store(Request $request)
    {
        if ((int) $request->user()->role_id !== 1) {
            return response()->json(['message' => 'Administrator access required.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string',
            'price' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'description' => 'required|string',
            'category' => 'required|string',
            'image' => 'required|string',
            'stock' => 'sometimes|integer|min:0',
        ]);

        $validated['stock'] = $validated['stock'] ?? 0;

        $product = Product::create($validated);

        return response()->json([
            'message' => 'Product created successfully.',
            'product' => $product,
        ], 201);
    }

    public function show(Product $product)
    {
        return response()->json($product);
    }

    public function edit(Product $product)
    {
        return response()->json(['message' => 'Use the product API to update a product.'], 405);
    }

    public function update(Request $request, Product $product)
    {
        if ((int) $request->user()->role_id !== 1) {
            return response()->json(['message' => 'Administrator access required.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string',
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'description' => 'sometimes|required|string',
            'category' => 'sometimes|required|string',
            'image' => 'sometimes|required|string',
            'stock' => 'sometimes|required|integer|min:0',
        ]);

        $product->update($validated);

        return response()->json([
            'message' => 'Product updated successfully.',
            'product' => $product->fresh(),
        ]);
    }

    public function destroy(Request $request, Product $product)
    {
        if ((int) $request->user()->role_id !== 1) {
            return response()->json(['message' => 'Administrator access required.'], 403);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully.']);
    }
}
