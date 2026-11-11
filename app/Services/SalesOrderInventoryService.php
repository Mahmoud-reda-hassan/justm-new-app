<?php

namespace App\Services;

use App\Models\SalesOrderItem;
use App\Models\SalesOrderItemReservation;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrderInventoryService
{
    public function reserve(
        SalesOrderItem $item,
        Warehouse $warehouse,
        float $requestedQuantity,
        bool $allowPreorder,
        int $userId,
    ): SalesOrderItemReservation {
        DB::table('warehouse_product_stocks')->insertOrIgnore([
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $item->product_variant_id,
            'quantity_on_hand' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stock = DB::table('warehouse_product_stocks')
            ->where('warehouse_id', $warehouse->id)
            ->where('product_variant_id', $item->product_variant_id)
            ->lockForUpdate()
            ->first();

        $virtualStock = app(VirtualStockPolicyService::class);
        $reservedElsewhere = $virtualStock->reservedQuantity(
            (int) $warehouse->id,
            (int) $item->product_variant_id,
            $virtualStock->statusesFor('create_order')
        );

        $available = max(0, (float) $stock->quantity_on_hand - $reservedElsewhere);
        $reserved = min($requestedQuantity, $available);
        $backorder = max(0, $requestedQuantity - $reserved);

        if ($backorder > 0 && ! $allowPreorder) {
            throw ValidationException::withMessages([
                'items' => 'الكمية المطلوبة للصنف '.$item->variant_sku.' أكبر من المتاح في مخزن عنوان العميل (المتاح: '.number_format($available, 3).').',
            ]);
        }

        return SalesOrderItemReservation::query()->create([
            'sales_order_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $item->product_variant_id,
            'requested_quantity' => $requestedQuantity,
            'reserved_quantity' => $reserved,
            'backorder_quantity' => $backorder,
            'status' => 'active',
            'created_by' => $userId,
        ]);
    }
}
