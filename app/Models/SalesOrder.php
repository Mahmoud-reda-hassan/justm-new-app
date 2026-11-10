<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    protected $fillable = [
        'order_number', 'customer_id', 'created_by', 'created_by_party_id',
        'status', 'shipping_address', 'shipping_address_id', 'shipping_city_id', 'shipping_zone_id', 'shipping_warehouse_id',
        'shipping_zone_name', 'shipping_warehouse_name', 'shipping_fee', 'shipping_fee_min', 'shipping_fee_max',
        'shipping_days', 'shipping_type', 'shipping_date', 'subtotal',
        'discount_total', 'total', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'shipping_fee' => 'decimal:2',
            'shipping_fee_min' => 'decimal:2',
            'shipping_fee_max' => 'decimal:2',
            'shipping_days' => 'integer',
            'shipping_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(PartyAddress::class, 'shipping_address_id');
    }

    public function shippingCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'shipping_city_id');
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    public function shippingWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'shipping_warehouse_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function creatorParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'created_by_party_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }
}
