<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(): JsonResponse
    {
        $products = Product::all();

        return response()->json([
            'data' => $products,
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        Cache::forget("product:{$product->id}");

        return response()->json([
            'data' => $product,
        ], 201);
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product): JsonResponse
    {
        if (!$product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        $cachedProduct = Cache::remember("product:{$product->id}", 60, function () use ($product) {
            return $product;
        });

        return response()->json([
            'data' => $cachedProduct,
        ]);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        if (!$product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        $product->update($request->validated());

        Cache::forget("product:{$product->id}");

        return response()->json([
            'data' => $product,
        ]);
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product): JsonResponse
    {
        if (!$product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        Cache::forget("product:{$product->id}");

        $product->delete();

        return response()->json(null, 204);
    }
}
