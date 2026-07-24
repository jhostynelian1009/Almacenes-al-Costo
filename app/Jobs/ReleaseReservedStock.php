<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\InventoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Release reserved stock when a payment fails or expires.
 * Queue: critical. Retries: 5.
 */
class ReleaseReservedStock implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function __construct(
        private readonly Order $order,
    ) {
        $this->onQueue('critical');
    }

    public function handle(InventoryService $inventoryService): void
    {
        $order = $this->order->loadMissing(['items.product.inventory']);

        foreach ($order->items as $item) {
            $inventory = $item->product?->inventory;
            if ($inventory === null) {
                continue;
            }

            $inventoryService->release(
                $inventory,
                $item->quantity,
                reason: "Liberación de reserva por fallo/expiración {$order->reference}",
                idempotencyKey: "release-failed:{$order->reference}:{$item->product_id}",
                referenceType: 'order',
                referenceId: $order->id,
            );
        }

        Log::info('Reserved stock released after payment failure', [
            'order_reference' => $order->reference,
        ]);
    }
}
