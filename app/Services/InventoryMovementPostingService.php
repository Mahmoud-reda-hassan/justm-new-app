<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\InventoryMovementItem;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryMovementPostingService
{
    public function __construct(private readonly InventorySettingsService $settings) {}

    /**
     * Post an outbound movement. The caller must authorize the operation and
     * enforce the user's warehouse data scope before calling this service.
     */
    public function issue(
        int $warehouseId,
        array $items,
        int $userId,
        ?string $notes = null,
        ?string $idempotencyKey = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
    ): InventoryMovement {
        $items = $this->normalizeItems($items);

        return DB::transaction(function () use ($warehouseId, $items, $userId, $notes, $idempotencyKey, $sourceType, $sourceId): InventoryMovement {
            $negativeAllowed = $this->settings->allowsNegativeStock(lockForUpdate: true);
            $warehouse = Warehouse::query()->whereKey($warehouseId)->where('is_active', true)->lockForUpdate()->firstOrFail();
            $this->assertActiveVariants($items);
            $hash = $this->payloadHash('issue', $warehouseId, null, $items, $notes, $sourceType, $sourceId);

            if ($existing = $this->findIdempotent($idempotencyKey, $hash)) {
                return $existing;
            }

            $stocks = $this->lockStocks([['warehouse_id' => $warehouse->id, 'items' => $items]]);
            $movement = $this->createMovement(
                'issue', $warehouse->id, null, $userId, $notes, $negativeAllowed,
                $idempotencyKey, $hash, $sourceType, $sourceId,
            );

            $reserved = $this->reservedQuantities($warehouse->id, array_column($items, 'product_variant_id'));
            foreach ($items as $item) {
                $variantId = $item['product_variant_id'];
                $stock = $stocks[$warehouse->id.':'.$variantId];
                $before = (float) $stock->quantity_on_hand;
                $available = $before - (float) ($reserved[$variantId] ?? 0);
                if ($item['quantity'] > $available + 0.000001 && (! $negativeAllowed || (float) ($reserved[$variantId] ?? 0) > 0.000001)) {
                    throw ValidationException::withMessages([
                        'items' => 'الكمية المطلوب صرفها للصنف '.$variantId.' أكبر من الرصيد الحر بعد استبعاد الحجوزات (المتاح: '.number_format(max(0, $available), 3).').',
                    ]);
                }

                $after = round($before - $item['quantity'], 3);
                if ($after < 0 && blank($notes)) {
                    throw ValidationException::withMessages(['notes' => 'اذكر سبب الحركة عند السماح بترحيل رصيد سالب.']);
                }
                InventoryMovementItem::query()->create([
                    'inventory_movement_id' => $movement->id,
                    'product_variant_id' => $variantId,
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                ]);
                DB::table('warehouse_product_stocks')->where('warehouse_id', $warehouse->id)->where('product_variant_id', $variantId)->update(['quantity_on_hand' => $after, 'updated_at' => now()]);
            }

            return $movement->load('items.variant.product', 'warehouse');
        }, 3);
    }

    /**
     * Transfer stock atomically between two warehouses. The source warehouse
     * is checked against active reservations when negative stock is blocked.
     */
    public function transfer(
        int $sourceWarehouseId,
        int $destinationWarehouseId,
        array $items,
        int $userId,
        ?string $notes = null,
        ?string $idempotencyKey = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
    ): InventoryMovement {
        if ($sourceWarehouseId === $destinationWarehouseId) {
            throw ValidationException::withMessages(['destination_warehouse_id' => 'لا يمكن التحويل إلى نفس المخزن.']);
        }
        $items = $this->normalizeItems($items);

        return DB::transaction(function () use ($sourceWarehouseId, $destinationWarehouseId, $items, $userId, $notes, $idempotencyKey, $sourceType, $sourceId): InventoryMovement {
            $negativeAllowed = $this->settings->allowsNegativeStock(lockForUpdate: true);
            $warehouses = Warehouse::query()
                ->whereIn('id', [$sourceWarehouseId, $destinationWarehouseId])
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($warehouses->count() !== 2) {
                throw ValidationException::withMessages(['warehouse_id' => 'المخزن المصدر أو المستلم غير موجود أو غير نشط.']);
            }

            $this->assertActiveVariants($items);
            $hash = $this->payloadHash('transfer', $sourceWarehouseId, $destinationWarehouseId, $items, $notes, $sourceType, $sourceId);
            if ($existing = $this->findIdempotent($idempotencyKey, $hash)) {
                return $existing;
            }

            $stockRows = $this->lockStocks([
                ['warehouse_id' => $sourceWarehouseId, 'items' => $items],
                ['warehouse_id' => $destinationWarehouseId, 'items' => $items],
            ]);
            $movement = $this->createMovement(
                'transfer', $sourceWarehouseId, $destinationWarehouseId, $userId, $notes, $negativeAllowed,
                $idempotencyKey, $hash, $sourceType, $sourceId,
            );

            $reserved = $this->reservedQuantities($sourceWarehouseId, array_column($items, 'product_variant_id'));
            foreach ($items as $item) {
                $variantId = $item['product_variant_id'];
                $source = $stockRows[$sourceWarehouseId.':'.$variantId];
                $destination = $stockRows[$destinationWarehouseId.':'.$variantId];
                $sourceBefore = (float) $source->quantity_on_hand;
                $destinationBefore = (float) $destination->quantity_on_hand;
                $available = $sourceBefore - (float) ($reserved[$variantId] ?? 0);

                if (! $negativeAllowed && $item['quantity'] > $available + 0.000001) {
                    throw ValidationException::withMessages([
                        'items' => 'الكمية المطلوب تحويلها للصنف '.$variantId.' أكبر من الرصيد الحر في المخزن المصدر بعد استبعاد الحجوزات (المتاح: '.number_format(max(0, $available), 3).').',
                    ]);
                }

                $sourceAfter = round($sourceBefore - $item['quantity'], 3);
                if ($sourceAfter < 0 && blank($notes)) {
                    throw ValidationException::withMessages(['notes' => 'اذكر سبب التحويل عند السماح بترحيل رصيد سالب.']);
                }
                $destinationAfter = round($destinationBefore + $item['quantity'], 3);
                InventoryMovementItem::query()->create([
                    'inventory_movement_id' => $movement->id,
                    'product_variant_id' => $variantId,
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'quantity_before' => $sourceBefore,
                    'quantity_after' => $sourceAfter,
                    'destination_quantity_before' => $destinationBefore,
                    'destination_quantity_after' => $destinationAfter,
                ]);
                DB::table('warehouse_product_stocks')->where('warehouse_id', $sourceWarehouseId)->where('product_variant_id', $variantId)->update(['quantity_on_hand' => $sourceAfter, 'updated_at' => now()]);
                DB::table('warehouse_product_stocks')->where('warehouse_id', $destinationWarehouseId)->where('product_variant_id', $variantId)->update(['quantity_on_hand' => $destinationAfter, 'updated_at' => now()]);
            }

            return $movement->load('items.variant.product', 'warehouse', 'destinationWarehouse');
        }, 3);
    }

    private function assertActiveVariants(array $items): void
    {
        $ids = array_column($items, 'product_variant_id');
        $activeCount = ProductVariant::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->lockForUpdate()
            ->count();

        if ($activeCount !== count($ids)) {
            throw ValidationException::withMessages(['items' => 'يوجد صنف غير موجود أو غير نشط.']);
        }
    }

    private function normalizeItems(array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'أضف صنفًا واحدًا على الأقل.']);
        }

        $normalized = [];
        $seen = [];
        foreach ($items as $item) {
            $variantId = (int) ($item['product_variant_id'] ?? 0);
            $quantity = round((float) ($item['quantity'] ?? 0), 3);
            $unitCost = round((float) ($item['unit_cost'] ?? 0), 4);
            if ($variantId < 1 || $quantity <= 0 || $unitCost < 0) {
                throw ValidationException::withMessages(['items' => 'بيانات الصنف أو الكمية أو التكلفة غير صحيحة.']);
            }
            if (isset($seen[$variantId])) {
                throw ValidationException::withMessages(['items' => 'لا يمكن تكرار الصنف في المستند؛ اجمع الكمية في سطر واحد.']);
            }
            $seen[$variantId] = true;
            $normalized[] = ['product_variant_id' => $variantId, 'quantity' => $quantity, 'unit_cost' => $unitCost];
        }

        usort($normalized, static fn (array $a, array $b): int => $a['product_variant_id'] <=> $b['product_variant_id']);
        return $normalized;
    }

    private function lockStocks(array $warehouseItems): array
    {
        $pairs = [];
        foreach ($warehouseItems as $entry) {
            foreach ($entry['items'] as $item) {
                $pairs[] = ['warehouse_id' => (int) $entry['warehouse_id'], 'product_variant_id' => $item['product_variant_id']];
            }
        }
        usort($pairs, static fn (array $a, array $b): int => [$a['warehouse_id'], $a['product_variant_id']] <=> [$b['warehouse_id'], $b['product_variant_id']]);

        foreach ($pairs as $pair) {
            DB::table('warehouse_product_stocks')->insertOrIgnore([
                'warehouse_id' => $pair['warehouse_id'],
                'product_variant_id' => $pair['product_variant_id'],
                'quantity_on_hand' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $warehouseIds = array_values(array_unique(array_column($pairs, 'warehouse_id')));
        $variantIds = array_values(array_unique(array_column($pairs, 'product_variant_id')));
        $rows = DB::table('warehouse_product_stocks')
            ->whereIn('warehouse_id', $warehouseIds)
            ->whereIn('product_variant_id', $variantIds)
            ->orderBy('warehouse_id')
            ->orderBy('product_variant_id')
            ->lockForUpdate()
            ->get();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->warehouse_id.':'.$row->product_variant_id] = $row;
        }
        if (count($indexed) !== count($pairs)) {
            throw ValidationException::withMessages(['items' => 'تعذر قفل أرصدة جميع الأصناف المطلوبة.']);
        }
        return $indexed;
    }

    private function reservedQuantities(int $warehouseId, array $variantIds): array
    {
        return DB::table('sales_order_item_reservations')
            ->select('product_variant_id', DB::raw('SUM(reserved_quantity) as reserved_total'))
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_variant_id', $variantIds)
            ->where('status', 'active')
            ->groupBy('product_variant_id')
            ->pluck('reserved_total', 'product_variant_id')
            ->all();
    }

    private function findIdempotent(?string $key, string $hash): ?InventoryMovement
    {
        if ($key === null || $key === '') return null;
        if (strlen($key) > 100) throw ValidationException::withMessages(['idempotency_key' => 'مفتاح منع التكرار أطول من المسموح.']);
        $existing = InventoryMovement::query()->where('idempotency_key', $key)->first();
        if (! $existing) return null;
        if (! hash_equals((string) $existing->idempotency_hash, $hash)) {
            throw ValidationException::withMessages(['idempotency_key' => 'مفتاح التنفيذ مستخدم مسبقًا لبيانات حركة مختلفة.']);
        }
        return $existing->load('items.variant.product', 'warehouse', 'destinationWarehouse');
    }

    private function createMovement(
        string $type,
        int $warehouseId,
        ?int $destinationWarehouseId,
        int $userId,
        ?string $notes,
        bool $negativeAllowed,
        ?string $idempotencyKey,
        string $hash,
        ?string $sourceType,
        ?int $sourceId,
    ): InventoryMovement {
        return InventoryMovement::query()->create([
            'document_no' => strtoupper($type === 'transfer' ? 'TR-' : 'IS-').now()->format('YmdHis').'-'.Str::upper(Str::random(5)),
            'movement_type' => $type,
            'negative_stock_allowed' => $negativeAllowed,
            'warehouse_id' => $warehouseId,
            'destination_warehouse_id' => $destinationWarehouseId,
            'status' => 'posted',
            'movement_date' => now(),
            'notes' => $notes,
            'created_by' => $userId,
            'posted_by' => $userId,
            'posted_at' => now(),
            'idempotency_key' => $idempotencyKey ?: null,
            'idempotency_hash' => $idempotencyKey ? $hash : null,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
    }

    private function payloadHash(string $type, int $warehouseId, ?int $destinationId, array $items, ?string $notes, ?string $sourceType, ?int $sourceId): string
    {
        return hash('sha256', json_encode([
            'type' => $type, 'warehouse_id' => $warehouseId, 'destination_warehouse_id' => $destinationId,
            'items' => $items, 'notes' => $notes, 'source_type' => $sourceType, 'source_id' => $sourceId,
        ], JSON_THROW_ON_ERROR));
    }
}
