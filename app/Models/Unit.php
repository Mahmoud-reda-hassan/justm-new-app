<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $fillable = ['name', 'symbol', 'decimal_places', 'is_active'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected function casts(): array
    {
        return ['decimal_places' => 'integer', 'is_active' => 'boolean'];
    }
}
