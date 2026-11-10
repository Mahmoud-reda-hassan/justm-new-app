<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyRole extends Model
{
    protected $fillable = ['role'];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
