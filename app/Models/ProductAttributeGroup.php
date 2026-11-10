<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductAttributeGroup extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class, 'group_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_product_attribute_group', 'attribute_group_id', 'product_id');
    }
}
