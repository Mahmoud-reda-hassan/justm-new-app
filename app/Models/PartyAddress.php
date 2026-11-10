<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyAddress extends Model
{
    protected $fillable = [
        'city_id', 'title', 'address', 'is_primary', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
