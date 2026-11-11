<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderItemReservation extends Model
{
    protected $fillable = [
        'sales_order_item_id', 'warehouse_id', 'product_variant_id',
        'requested_quantity', 'reserved_quantity', 'backorder_quantity', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'decimal:3',
            'reserved_quantity' => 'decimal:3',
            'backorder_quantity' => 'decimal:3',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
