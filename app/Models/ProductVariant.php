<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'sku', 'variant_name', 'list_price', 'minimum_sale_price', 'is_active'];

    protected function casts(): array
    {
        return [
            'list_price' => 'decimal:2',
            'minimum_sale_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(ProductAttributeValue::class, 'product_variant_attribute_values', 'product_variant_id', 'attribute_value_id')
            ->with('group');
    }
}
