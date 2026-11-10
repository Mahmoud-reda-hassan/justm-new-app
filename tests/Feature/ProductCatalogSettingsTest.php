<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $keys, bool $globalScope = true): User
    {
        app(DatabaseSeeder::class)->run();
        $user = User::query()->create([
            'name' => 'Catalog Admin',
            'username' => 'catalog-admin-'.uniqid(),
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);
        $role = Role::query()->create(['name' => 'Catalog role', 'key' => 'catalog-role-'.uniqid(), 'is_active' => true]);
        $permissionKeys = $globalScope ? array_values(array_unique([...$keys, 'structure.scope.manage_all'])) : $keys;
        $role->permissions()->sync(Permission::query()->whereIn('key', $permissionKeys)->pluck('id'));
        $user->roles()->attach($role);
        return $user;
    }

    public function test_categories_can_be_nested_and_listed(): void
    {
        $user = $this->userWith(['product_categories.view', 'product_categories.create']);
        $this->actingAs($user)->post(route('admin.catalog.categories.store'), [
            'name' => 'ملابس',
            'code' => 'CLOTHES',
            'sort_order' => 1,
        ])->assertSessionHasNoErrors();
        $parent = ProductCategory::query()->where('name', 'ملابس')->firstOrFail();

        $this->post(route('admin.catalog.categories.store'), [
            'name' => 'قمصان',
            'parent_id' => $parent->id,
        ])->assertSessionHasNoErrors();

        $child = ProductCategory::query()->where('name', 'قمصان')->firstOrFail();
        $this->assertSame($parent->id, $child->parent_id);
        $this->get(route('admin.catalog.categories'))->assertOk()->assertSee('ملابس')->assertSee('قمصان');
    }

    public function test_category_cannot_be_moved_under_its_descendant(): void
    {
        $user = $this->userWith(['product_categories.update']);
        $parent = ProductCategory::query()->create(['name' => 'رئيسي']);
        $child = ProductCategory::query()->create(['name' => 'فرعي', 'parent_id' => $parent->id]);

        $this->actingAs($user)->put(route('admin.catalog.categories.update', $parent), [
            'name' => 'رئيسي',
            'parent_id' => $child->id,
        ])->assertSessionHasErrors('parent_id');

        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_category_with_active_children_cannot_be_deactivated(): void
    {
        $user = $this->userWith(['product_categories.activate']);
        $parent = ProductCategory::query()->create(['name' => 'رئيسي']);
        ProductCategory::query()->create(['name' => 'فرعي', 'parent_id' => $parent->id]);

        $this->actingAs($user)->patch(route('admin.catalog.categories.active', $parent), [
            'is_active' => 0,
        ])->assertSessionHasErrors('category');

        $this->assertTrue($parent->fresh()->is_active);
    }

    public function test_units_store_precision_and_symbol(): void
    {
        $user = $this->userWith(['units.view', 'units.create', 'units.update']);
        $this->actingAs($user)->post(route('admin.catalog.units.store'), [
            'name' => 'كيلوجرام',
            'symbol' => 'كجم',
            'decimal_places' => 3,
        ])->assertSessionHasNoErrors();

        $unit = Unit::query()->where('name', 'كيلوجرام')->firstOrFail();
        $this->assertSame(3, $unit->decimal_places);
        $this->put(route('admin.catalog.units.update', $unit), [
            'name' => 'كيلوجرام',
            'symbol' => 'kg',
            'decimal_places' => 4,
        ])->assertSessionHasNoErrors();

        $this->assertSame('kg', $unit->fresh()->symbol);
        $this->assertSame(4, $unit->fresh()->decimal_places);
    }


    public function test_dashboard_shows_catalog_links_only_when_permissions_are_assigned(): void
    {
        $user = $this->userWith(['product_categories.view', 'units.view', 'products.view', 'product_attributes.view']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('admin.catalog.categories'))
            ->assertSee(route('admin.catalog.units'))
            ->assertSee(route('admin.products.index'))
            ->assertSee(route('admin.product-attributes.index'));
    }

    public function test_active_category_with_active_product_cannot_be_deactivated(): void
    {
        $user = User::query()->create(['name' => 'Manager', 'username' => 'cat-manager-'.uniqid(), 'password' => 'secure-password', 'account_type' => 'admin', 'is_active' => true]);
        $role = Role::query()->create(['name' => 'Category role', 'key' => 'category-role-'.uniqid(), 'is_active' => true]);
        $role->permissions()->sync(Permission::query()->whereIn('key', ['product_categories.activate', 'structure.scope.manage_all'])->pluck('id'));
        $user->roles()->attach($role);
        $category = ProductCategory::query()->create(['name' => 'تصنيف مرتبط']);
        $unit = Unit::query()->create(['name' => 'وحدة مرتبطة']);
        Product::query()->create(['category_id' => $category->id, 'unit_id' => $unit->id, 'name' => 'منتج مرتبط', 'is_visible' => true, 'is_active' => true]);

        $this->actingAs($user)->patch(route('admin.catalog.categories.active', $category), ['is_active' => 0])->assertSessionHasErrors('category');
        $this->assertTrue($category->fresh()->is_active);
    }


    public function test_units_screen_renders_for_a_user_with_view_permission(): void
    {
        $user = $this->userWith(['units.view']);
        $this->actingAs($user)->get(route('admin.catalog.units'))->assertOk()->assertSee('وحدات القياس');
    }


    public function test_catalog_settings_are_not_visible_without_global_product_scope(): void
    {
        $user = $this->userWith(['product_categories.view', 'units.view'], false);
        $this->actingAs($user)->get(route('admin.catalog.categories'))->assertForbidden();
        $this->get(route('admin.catalog.units'))->assertForbidden();
    }

    public function test_catalog_settings_routes_require_specific_permissions(): void
    {
        $user = $this->userWith([]);
        $this->actingAs($user)->get(route('admin.catalog.categories'))->assertForbidden();
        $this->get(route('admin.catalog.units'))->assertForbidden();
    }
}
