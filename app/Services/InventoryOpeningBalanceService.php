<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\InventoryMovementItem;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryOpeningBalanceService
{
    /**
     * Record and post one opening-balance document atomically.
     *
     * Opening balances are the first source of stock in the new system;
     * this service never imports or reads quantities from the legacy database.
     */
    public function create(int $warehouseId, array $items, int $userId, ?string $notes = null): InventoryMovement
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'أضف صنفًا واحدًا على الأقل.']);
        }

        return DB::transaction(function () use ($warehouseId, $items, $userId, $notes): InventoryMovement {
            $warehouse = Warehouse::query()
                ->whereKey($warehouseId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $variantIds = array_map(static fn (array $item): int => (int) $item['product_variant_id'], $items);
            if (count($variantIds) !== count(array_unique($variantIds))) {
                throw ValidationException::withMessages(['items' => 'لا يمكن تكرار نفس كود الصنف في المستند؛ اجمع الكمية في سطر واحد.']);
            }

            $variants = ProductVariant::query()
                ->with('product')
                ->whereIn('id', $variantIds)
                ->where('is_active', true)
                ->whereHas('product', fn ($query) => $query->where('is_active', true))
                ->get()
                ->keyBy('id');

            if ($variants->count() !== count($variantIds)) {
                throw ValidationException::withMessages(['items' => 'يوجد صنف غير موجود أو غير نشط.']);
            }

            $movement = InventoryMovement::query()->create([
                'document_no' => 'OB-'.$warehouse->id.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'movement_type' => 'opening_balance',
                'warehouse_id' => $warehouse->id,
                'status' => 'posted',
                'movement_date' => now(),
                'notes' => $notes,
                'created_by' => $userId,
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            foreach ($items as $item) {
                $variantId = (int) $item['product_variant_id'];
                $quantity = round((float) $item['quantity'], 3);
                $unitCost = round((float) ($item['unit_cost'] ?? 0), 4);

                if ($quantity <= 0 || $unitCost < 0) {
                    throw ValidationException::withMessages(['items' => 'الكمية يجب أن تكون أكبر من صفر والتكلفة لا يمكن أن تكون سالبة.']);
                }

                WarehouseProductStock::query()->firstOrCreate(
                    ['warehouse_id' => $warehouse->id, 'product_variant_id' => $variantId],
                    ['quantity_on_hand' => 0],
                );

                $stock = WarehouseProductStock::query()
                    ->where('warehouse_id', $warehouse->id)
                    ->where('product_variant_id', $variantId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $before = (float) $stock->quantity_on_hand;
                $after = round($before + $quantity, 3);

                InventoryMovementItem::query()->create([
                    'inventory_movement_id' => $movement->id,
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                ]);

                $stock->update(['quantity_on_hand' => $after]);
            }

            return $movement->load('items.variant.product', 'warehouse');
        }, 3);
    }
}
