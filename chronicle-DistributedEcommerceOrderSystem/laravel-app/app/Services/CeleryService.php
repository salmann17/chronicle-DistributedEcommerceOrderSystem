<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CeleryService
{
    /**
     * Dispatch order processed task to Celery service.
     *
     * @param int $orderId
     * @return void
     */
    public static function dispatchOrderProcessed(int $orderId): void
    {
        try {
            Http::timeout(2)
                ->post('http://celery-service:5000/tasks/order-processed', [
                    'order_id' => $orderId,
                ]);

            Log::info("Order {$orderId} dispatched to Celery service");
        } catch (\Exception $e) {
            Log::warning("Failed to dispatch order {$orderId} to Celery service: " . $e->getMessage());
        }
    }
}
