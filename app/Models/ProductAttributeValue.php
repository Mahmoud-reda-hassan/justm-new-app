<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductAttributeValue extends Model
{
    protected $fillable = ['group_id', 'value', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductAttributeGroup::class, 'group_id');
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'product_variant_attribute_values', 'attribute_value_id', 'product_variant_id');
    }
}
