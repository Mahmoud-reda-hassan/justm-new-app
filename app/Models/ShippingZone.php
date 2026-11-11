<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends Model
{
    protected $fillable = [
        'name_ar', 'name_en', 'zone_type', 'shipping_fee_min', 'shipping_fee_max',
        'delivery_days', 'shipping_type', 'warehouse_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'shipping_fee_min' => 'decimal:2',
            'shipping_fee_max' => 'decimal:2',
            'delivery_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'shipping_zone_countries')->withTimestamps();
    }
}
