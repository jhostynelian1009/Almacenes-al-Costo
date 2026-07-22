<?php

namespace App\Services;

use App\Exceptions\InventoryOperationException;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    private const MAX_UNSIGNED_INTEGER = 4_294_967_295;

    private const MAX_SIGNED_INTEGER = 2_147_483_647;

    private const MIN_SIGNED_INTEGER = -2_147_483_648;

    public function recordEntry(
        Inventory|int $inventory,
        mixed $quantity,
        ?string $reason = null,
        ?User $actor = null,
        ?string $idempotencyKey = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): InventoryMovement {
        return $this->execute(
            $inventory,
            InventoryMovement::TYPE_ENTRY,
            $quantity,
            $reason,
            $actor,
            $idempotencyKey,
            $referenceType,
            $referenceId,
        );
    }

    public function recordExit(
        Inventory|int $inventory,
        mixed $quantity,
        ?string $reason = null,
        ?User $actor = null,
        ?string $idempotencyKey = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): InventoryMovement {
        return $this->execute(
            $inventory,
            InventoryMovement::TYPE_EXIT,
            $quantity,
            $reason,
            $actor,
            $idempotencyKey,
            $referenceType,
            $referenceId,
        );
    }

    public function adjustStock(
        Inventory|int $inventory,
        mixed $newStock,
        ?string $reason,
        ?User $actor = null,
        ?string $idempotencyKey = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): InventoryMovement {
        return $this->execute(
            $inventory,
            InventoryMovement::TYPE_ADJUSTMENT,
            $newStock,
            $reason,
            $actor,
            $idempotencyKey,
            $referenceType,
            $referenceId,
        );
    }

    public function reserve(
        Inventory|int $inventory,
        mixed $quantity,
        ?string $reason = null,
        ?User $actor = null,
        ?string $idempotencyKey = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): InventoryMovement {
        return $this->execute(
            $inventory,
            InventoryMovement::TYPE_RESERVE,
            $quantity,
            $reason,
            $actor,
            $idempotencyKey,
            $referenceType,
            $referenceId,
        );
    }

    public function release(
        Inventory|int $inventory,
        mixed $quantity,
        ?string $reason = null,
        ?User $actor = null,
        ?string $idempotencyKey = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): InventoryMovement {
        return $this->execute(
            $inventory,
            InventoryMovement::TYPE_RELEASE,
            $quantity,
            $reason,
            $actor,
            $idempotencyKey,
            $referenceType,
            $referenceId,
        );
    }

    private function execute(
        Inventory|int $inventory,
        string $type,
        mixed $value,
        ?string $reason,
        ?User $actor,
        ?string $idempotencyKey,
        ?string $referenceType,
        ?int $referenceId,
    ): InventoryMovement {
        $inventoryId = (int) ($inventory instanceof Inventory ? $inventory->getKey() : $inventory);
        $normalizedKey = $this->normalizeIdempotencyKey($idempotencyKey);
        $normalizedReason = $this->normalizeReason($reason, $type);
        [$normalizedReferenceType, $normalizedReferenceId] = $this->normalizeReference(
            $referenceType,
            $referenceId,
        );
        $actorId = $actor?->getKey();

        try {
            return DB::transaction(function () use (
                $inventoryId,
                $type,
                $value,
                $normalizedReason,
                $actorId,
                $normalizedKey,
                $normalizedReferenceType,
                $normalizedReferenceId,
            ): InventoryMovement {
                $validatedValue = $this->validatedValue($type, $value);

                if ($normalizedKey !== null) {
                    $existingMovement = InventoryMovement::query()
                        ->where('idempotency_key', $normalizedKey)
                        ->first();

                    if ($existingMovement !== null) {
                        return $this->resolveIdempotentMovement(
                            $existingMovement,
                            $inventoryId,
                            $type,
                            $validatedValue,
                            $normalizedReason,
                            $actorId,
                            $normalizedReferenceType,
                            $normalizedReferenceId,
                        );
                    }
                }

                $lockedInventory = Inventory::query()
                    ->whereKey($inventoryId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedInventory->product()->firstOrFail()->trashed()) {
                    throw InventoryOperationException::deletedProduct();
                }

                $stockBefore = $lockedInventory->stock;
                $reservedBefore = $lockedInventory->reserved_stock;
                [$stockAfter, $reservedAfter] = $this->calculateBalances(
                    $type,
                    $validatedValue,
                    $stockBefore,
                    $reservedBefore,
                );
                $stockDelta = $stockAfter - $stockBefore;
                $reservedDelta = $reservedAfter - $reservedBefore;

                $this->ensureSignedDelta($stockDelta, $type);
                $this->ensureSignedDelta($reservedDelta, $type);

                $lockedInventory->update([
                    'stock' => $stockAfter,
                    'reserved_stock' => $reservedAfter,
                ]);

                return $lockedInventory->movements()->create([
                    'type' => $type,
                    'stock_delta' => $stockDelta,
                    'reserved_delta' => $reservedDelta,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'reserved_before' => $reservedBefore,
                    'reserved_after' => $reservedAfter,
                    'reason' => $normalizedReason,
                    'created_by' => $actorId,
                    'reference_type' => $normalizedReferenceType,
                    'reference_id' => $normalizedReferenceId,
                    'idempotency_key' => $normalizedKey,
                ]);
            });
        } catch (QueryException $exception) {
            if ($normalizedKey === null) {
                throw $exception;
            }

            $existingMovement = InventoryMovement::query()
                ->where('idempotency_key', $normalizedKey)
                ->first();

            if ($existingMovement === null) {
                throw $exception;
            }

            return $this->resolveIdempotentMovement(
                $existingMovement,
                $inventoryId,
                $type,
                $this->validatedValue($type, $value),
                $normalizedReason,
                $actorId,
                $normalizedReferenceType,
                $normalizedReferenceId,
                $exception,
            );
        }
    }

    /**
     * @return array{int, int}
     */
    private function calculateBalances(
        string $type,
        int $value,
        int $stock,
        int $reserved,
    ): array {
        return match ($type) {
            InventoryMovement::TYPE_ENTRY => $this->entryBalances($stock, $reserved, $value),
            InventoryMovement::TYPE_EXIT => $this->exitBalances($stock, $reserved, $value),
            InventoryMovement::TYPE_ADJUSTMENT => $this->adjustmentBalances($stock, $reserved, $value),
            InventoryMovement::TYPE_RESERVE => $this->reserveBalances($stock, $reserved, $value),
            InventoryMovement::TYPE_RELEASE => $this->releaseBalances($stock, $reserved, $value),
            default => throw InventoryOperationException::invalidQuantity(),
        };
    }

    /**
     * @return array{int, int}
     */
    private function entryBalances(int $stock, int $reserved, int $quantity): array
    {
        if ($stock > self::MAX_UNSIGNED_INTEGER - $quantity) {
            throw InventoryOperationException::invalidQuantity();
        }

        return [$stock + $quantity, $reserved];
    }

    /**
     * @return array{int, int}
     */
    private function exitBalances(int $stock, int $reserved, int $quantity): array
    {
        if ($quantity > $stock - $reserved) {
            throw InventoryOperationException::insufficientStock();
        }

        return [$stock - $quantity, $reserved];
    }

    /**
     * @return array{int, int}
     */
    private function adjustmentBalances(int $stock, int $reserved, int $newStock): array
    {
        if ($newStock === $stock || $newStock < $reserved) {
            throw InventoryOperationException::invalidAdjustment();
        }

        return [$newStock, $reserved];
    }

    /**
     * @return array{int, int}
     */
    private function reserveBalances(int $stock, int $reserved, int $quantity): array
    {
        if ($quantity > $stock - $reserved) {
            throw InventoryOperationException::insufficientStock();
        }

        return [$stock, $reserved + $quantity];
    }

    /**
     * @return array{int, int}
     */
    private function releaseBalances(int $stock, int $reserved, int $quantity): array
    {
        if ($quantity > $reserved) {
            throw InventoryOperationException::insufficientReservation();
        }

        return [$stock, $reserved - $quantity];
    }

    private function validatedValue(string $type, mixed $value): int
    {
        if (! is_int($value)) {
            throw $type === InventoryMovement::TYPE_ADJUSTMENT
                ? InventoryOperationException::invalidAdjustment()
                : InventoryOperationException::invalidQuantity();
        }

        if ($type === InventoryMovement::TYPE_ADJUSTMENT) {
            if ($value < 0 || $value > self::MAX_UNSIGNED_INTEGER) {
                throw InventoryOperationException::invalidAdjustment();
            }

            return $value;
        }

        if ($value <= 0 || $value > self::MAX_SIGNED_INTEGER) {
            throw InventoryOperationException::invalidQuantity();
        }

        return $value;
    }

    private function normalizeReason(?string $reason, string $type): ?string
    {
        $normalizedReason = $reason === null ? null : trim($reason);

        if ($normalizedReason === '') {
            $normalizedReason = null;
        }

        if ($type === InventoryMovement::TYPE_ADJUSTMENT && $normalizedReason === null) {
            throw InventoryOperationException::adjustmentReasonRequired();
        }

        if ($normalizedReason !== null && mb_strlen($normalizedReason) > 255) {
            throw InventoryOperationException::invalidAdjustment();
        }

        return $normalizedReason;
    }

    private function normalizeIdempotencyKey(?string $idempotencyKey): ?string
    {
        if ($idempotencyKey === null) {
            return null;
        }

        $normalizedKey = trim($idempotencyKey);

        if ($normalizedKey === '' || mb_strlen($normalizedKey) > 100) {
            throw InventoryOperationException::invalidIdempotencyKey();
        }

        return $normalizedKey;
    }

    /**
     * @return array{?string, ?int}
     */
    private function normalizeReference(?string $referenceType, ?int $referenceId): array
    {
        if ($referenceType === null && $referenceId === null) {
            return [null, null];
        }

        $normalizedType = $referenceType === null ? null : trim($referenceType);

        if (
            $normalizedType === null
            || $normalizedType === ''
            || mb_strlen($normalizedType) > 100
            || $referenceId === null
            || $referenceId <= 0
        ) {
            throw InventoryOperationException::incompleteReference();
        }

        return [$normalizedType, $referenceId];
    }

    private function ensureSignedDelta(int $delta, string $type): void
    {
        if ($delta < self::MIN_SIGNED_INTEGER || $delta > self::MAX_SIGNED_INTEGER) {
            throw $type === InventoryMovement::TYPE_ADJUSTMENT
                ? InventoryOperationException::invalidAdjustment()
                : InventoryOperationException::invalidQuantity();
        }
    }

    private function resolveIdempotentMovement(
        InventoryMovement $movement,
        int $inventoryId,
        string $type,
        int $value,
        ?string $reason,
        int|string|null $actorId,
        ?string $referenceType,
        ?int $referenceId,
        ?QueryException $previous = null,
    ): InventoryMovement {
        $expectedStockDelta = match ($type) {
            InventoryMovement::TYPE_ENTRY => $value,
            InventoryMovement::TYPE_EXIT => -$value,
            InventoryMovement::TYPE_ADJUSTMENT => $value - $movement->stock_before,
            default => 0,
        };
        $expectedReservedDelta = match ($type) {
            InventoryMovement::TYPE_RESERVE => $value,
            InventoryMovement::TYPE_RELEASE => -$value,
            default => 0,
        };

        $isSameOperation = $movement->inventory_id === $inventoryId
            && $movement->type === $type
            && $movement->stock_delta === $expectedStockDelta
            && $movement->reserved_delta === $expectedReservedDelta
            && $movement->reason === $reason
            && $movement->created_by === ($actorId === null ? null : (int) $actorId)
            && $movement->reference_type === $referenceType
            && $movement->reference_id === $referenceId;

        if (! $isSameOperation) {
            throw InventoryOperationException::reusedIdempotencyKey($previous);
        }

        return $movement;
    }
}
