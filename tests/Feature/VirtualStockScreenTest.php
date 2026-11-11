<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VirtualStockScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_open_the_three_virtual_stock_views(): void
    {
        app(DatabaseSeeder::class)->run();

        $user = User::query()->create([
            'name' => 'Inventory Viewer',
            'username' => 'inventory-viewer-'.uniqid(),
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);

        $role = Role::query()->create([
            'name' => 'Virtual stock viewer',
            'key' => 'virtual-stock-viewer-'.uniqid(),
            'is_active' => true,
        ]);
        $permission = Permission::query()->where('key', 'inventory.virtual_stock.view')->firstOrFail();
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        foreach (['create_order', 'customer_service', 'warehouse'] as $type) {
            $this->actingAs($user)
                ->get(route('admin.inventory.virtual-stock', ['type' => $type]))
                ->assertOk()
                ->assertSee('المخازن الوهمية')
                ->assertSee('مخزن منشئ أمر البيع')
                ->assertSee('مخزن خدمة العملاء')
                ->assertSee('مخزن أمين المخزن والتجهيز');
        }
    }

    public function test_virtual_stock_screen_requires_its_view_permission(): void
    {
        app(DatabaseSeeder::class)->run();

        $user = User::query()->create([
            'name' => 'No Inventory Access',
            'username' => 'no-inventory-'.uniqid(),
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.inventory.virtual-stock'))
            ->assertForbidden();
    }
}
