<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Party extends Model
{
    // Customer accounts can enter the storefront; supplier and shipping company remain external records.
    public const LOGIN_ROLES = ['customer', 'marketer', 'sales_intermediary', 'delivery_agent'];

    // Only these roles open a dedicated operational workspace.
    public const WORKSPACE_ROLES = ['marketer', 'sales_intermediary', 'delivery_agent'];

    // sales_intermediary is retained only as a legacy database alias for marketer.
    public const ROLE_ALIASES = ['sales_intermediary' => 'marketer'];

    public const ROLES = [
        'customer' => 'عميل',
        'supplier' => 'مورد',
        'marketer' => 'مسوق / وسيط',
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

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public static function canonicalRole(string $role): string
    {
        return self::ROLE_ALIASES[$role] ?? $role;
    }

    public function assignedRoles(): array
    {
        return $this->partyRoles()
            ->pluck('role')
            ->map(fn (string $role): string => self::canonicalRole($role))
            ->unique()
            ->values()
            ->all();
    }
}
