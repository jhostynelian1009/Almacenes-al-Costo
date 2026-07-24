<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\InventoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deduct physical stock and release reserved stock after a gateway payment is completed.
 * Queue: critical. Retries: 5 with exponential backoff.
 */
class UpdateInventoryOnPayment implements ShouldQueue
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
                Log::error('UpdateInventoryOnPayment: inventory not found for product', [
                    'order_reference' => $order->reference,
                    'product_id' => $item->product_id,
                ]);

                continue;
            }

            // Execute exit and release in a single atomic transaction.
            // Releasing the reservation first guarantees enough available stock for the physical exit.
            DB::transaction(function () use ($inventoryService, $inventory, $item, $order): void {
                $inventoryService->release(
                    $inventory,
                    $item->quantity,
                    reason: "Liberación reserva por pago confirmado {$order->reference}",
                    idempotencyKey: "payment-release:{$order->reference}:{$item->product_id}",
                    referenceType: 'order',
                    referenceId: $order->id,
                );

                $inventoryService->recordExit(
                    $inventory,
                    $item->quantity,
                    reason: "Deducción por pago confirmado {$order->reference}",
                    idempotencyKey: "payment-exit:{$order->reference}:{$item->product_id}",
                    referenceType: 'order',
                    referenceId: $order->id,
                );
            });
        }

        Log::info('Inventory updated after payment', [
            'order_reference' => $order->reference,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('UpdateInventoryOnPayment: permanent failure — manual intervention required', [
            'order_reference' => $this->order->reference,
            'error' => $exception->getMessage(),
        ]);
    }
}
