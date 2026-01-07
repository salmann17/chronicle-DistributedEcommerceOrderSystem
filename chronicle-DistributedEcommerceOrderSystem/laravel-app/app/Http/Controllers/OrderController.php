<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Http\Requests\PurchaseRequest;
use App\Services\CeleryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Purchase a product and create an order.
     */
    public function purchase(PurchaseRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $productId = $validated['product_id'];
        $quantity = $validated['quantity'];

        try {
            DB::beginTransaction();

            $affectedRows = DB::update(
                'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?',
                [$quantity, $productId, $quantity]
            );

            if ($affectedRows === 0) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Out of stock',
                ], 409);
            }

            $product = Product::findOrFail($productId);
            $totalPrice = $product->price * $quantity;

            $order = Order::create([
                'product_id' => $productId,
                'quantity' => $quantity,
                'total_price' => $totalPrice,
                'status' => 'created',
            ]);

            DB::commit();

            $this->dispatchBackgroundOrderProcessed($order->id);

            return response()->json([
                'data' => $order->load('product'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Purchase failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Purchase failed',
            ], 500);
        }
    }

    /**
     * Dispatch background order processing.
     */
    private function dispatchBackgroundOrderProcessed(int $orderId): void
    {
        CeleryService::dispatchOrderProcessed($orderId);
    }
}
