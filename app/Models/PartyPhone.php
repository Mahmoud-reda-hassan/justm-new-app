<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyPhone extends Model
{
    protected $fillable = [
        'number', 'type', 'is_whatsapp', 'is_telegram', 'is_primary', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_whatsapp' => 'boolean',
            'is_telegram' => 'boolean',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
