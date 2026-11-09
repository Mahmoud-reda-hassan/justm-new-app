<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'username', 'email', 'password', 'account_type', 'is_active'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function dataScopes(): HasMany
    {
        return $this->hasMany(UserDataScope::class);
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->exists || ! $this->is_active) {
            return false;
        }

        return $this->roles()
            ->where('roles.is_active', true)
            ->whereHas('permissions', fn ($query) => $query->where('permissions.key', $permission))
            ->exists();
    }

    public function hasDataScope(string $type, int $id): bool
    {
        if (! in_array($type, ['company', 'branch', 'warehouse', 'delivery_agent', 'shipping_company'], true)) {
            return false;
        }

        return $this->dataScopes()
            ->where('scope_type', $type)
            ->where('scope_id', $id)
            ->exists();
    }
}
