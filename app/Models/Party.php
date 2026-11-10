<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Party extends Model
{
    public const ROLES = [
        'customer' => 'عميل',
        'supplier' => 'مورد',
        'marketer' => 'مسوق',
        'sales_intermediary' => 'وسيط بيع',
        'delivery_agent' => 'مندوب داخلي',
        'shipping_company' => 'شركة شحن خارجية',
    ];

    protected $fillable = [
        'code', 'entity_type', 'name', 'contact_person', 'phone', 'mobile',
        'email', 'city_id', 'address', 'tax_number', 'commercial_register',
        'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function partyRoles(): HasMany
    {
        return $this->hasMany(PartyRole::class);
    }

    public function assignedRoles(): array
    {
        return $this->partyRoles()->pluck('role')->all();
    }
}
