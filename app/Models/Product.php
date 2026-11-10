<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'unit_id', 'name', 'description', 'image_path',
        'length_cm', 'width_cm', 'height_cm', 'weight_kg', 'is_visible', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'length_cm' => 'decimal:3',
            'width_cm' => 'decimal:3',
            'height_cm' => 'decimal:3',
            'weight_kg' => 'decimal:3',
            'is_visible' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function attributeGroups(): BelongsToMany
    {
        return $this->belongsToMany(ProductAttributeGroup::class, 'product_product_attribute_group', 'product_id', 'attribute_group_id');
    }
}
