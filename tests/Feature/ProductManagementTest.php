<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductAttributeGroup;
use App\Models\ProductAttributeValue;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $keys): User
    {
        app(DatabaseSeeder::class)->run();
        $user = User::query()->create([
            'name' => 'Product Admin',
            'username' => 'product-admin-'.uniqid(),
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);
        $role = Role::query()->create(['name' => 'Product role', 'key' => 'product-role-'.uniqid(), 'is_active' => true]);
        $role->permissions()->sync(Permission::query()->whereIn('key', $keys)->pluck('id'));
        $user->roles()->attach($role);
        return $user;
    }

    private function category(): ProductCategory
    {
        return ProductCategory::query()->create(['name' => 'تصنيف المنتج']);
    }

    private function unit(): Unit
    {
        return Unit::query()->create(['name' => 'قطعة', 'symbol' => 'قطعة', 'decimal_places' => 0]);
    }

    private function createProduct(User $user, array $extra = []): Product
    {
        $category = $this->category();
        $unit = $this->unit();
        $payload = array_merge([
            'name' => 'منتج تجريبي',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'is_visible' => 1,
            'initial_variant' => [
                'sku' => 'SKU-'.uniqid(),
                'list_price' => 100,
                'minimum_sale_price' => 70,
                'attribute_value_ids' => [],
            ],
        ], $extra);

        $this->actingAs($user)->post(route('admin.products.store'), $payload)->assertSessionHasNoErrors();
        return Product::query()->where('name', $payload['name'])->firstOrFail();
    }

    public function test_product_is_created_with_its_first_sku_and_sale_price_range(): void
    {
        $user = $this->userWith(['products.view', 'products.create']);
        $product = $this->createProduct($user);

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'list_price' => '100.00',
            'minimum_sale_price' => '70.00',
        ]);
        $this->actingAs($user)->get(route('admin.products.index'))->assertOk()->assertSee('منتج تجريبي');
    }

    public function test_product_variant_must_select_one_value_from_each_assigned_attribute_group(): void
    {
        $user = $this->userWith(['products.create', 'products.view']);
        $group = ProductAttributeGroup::query()->create(['name' => 'اللون']);
        $red = $group->values()->create(['value' => 'أحمر']);
        $blue = $group->values()->create(['value' => 'أزرق']);

        $product = $this->createProduct($user, [
            'attribute_group_ids' => [$group->id],
            'initial_variant' => [
                'sku' => 'COLOR-RED',
                'list_price' => 120,
                'minimum_sale_price' => 80,
                'attribute_value_ids' => [$red->id],
            ],
        ]);
        $this->assertTrue($product->attributeGroups()->whereKey($group->id)->exists());
        $this->assertDatabaseHas('product_variant_attribute_values', [
            'attribute_value_id' => $red->id,
        ]);

        $this->post(route('admin.products.variants.store', $product), [
            'sku' => 'COLOR-BLUE',
            'list_price' => 120,
            'minimum_sale_price' => 80,
            'attribute_value_ids' => [$blue->id],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_variants', ['product_id' => $product->id, 'sku' => 'COLOR-BLUE']);
    }

    public function test_product_rejects_a_variant_value_from_an_unassigned_group(): void
    {
        $user = $this->userWith(['products.create']);
        $color = ProductAttributeGroup::query()->create(['name' => 'اللون']);
        $color->values()->create(['value' => 'أحمر']);
        $size = ProductAttributeGroup::query()->create(['name' => 'المقاس']);
        $xl = $size->values()->create(['value' => 'XL']);
        $product = $this->createProduct($user, [
            'attribute_group_ids' => [$color->id],
            'initial_variant' => [
                'sku' => 'ONLY-COLOR',
                'list_price' => 100,
                'minimum_sale_price' => 70,
                'attribute_value_ids' => [$color->values()->first()->id],
            ],
        ]);

        $this->post(route('admin.products.variants.store', $product), [
            'sku' => 'WRONG-GROUP',
            'list_price' => 100,
            'minimum_sale_price' => 70,
            'attribute_value_ids' => [$xl->id],
        ])->assertSessionHasErrors('attribute_value_ids');

        $this->assertDatabaseMissing('product_variants', ['sku' => 'WRONG-GROUP']);
    }

    public function test_minimum_sale_price_cannot_exceed_list_price(): void
    {
        $user = $this->userWith(['products.create']);
        $category = $this->category();
        $unit = $this->unit();

        $this->actingAs($user)->post(route('admin.products.store'), [
            'name' => 'منتج بسعر غير صحيح',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'initial_variant' => [
                'sku' => 'BAD-PRICE',
                'list_price' => 80,
                'minimum_sale_price' => 100,
            ],
        ])->assertSessionHasErrors('minimum_sale_price');

        $this->assertDatabaseMissing('products', ['name' => 'منتج بسعر غير صحيح']);
    }

    public function test_used_category_and_unit_cannot_be_deactivated(): void
    {
        $user = $this->userWith(['products.create', 'product_categories.activate', 'units.activate']);
        $product = $this->createProduct($user);

        $this->actingAs($user)->patch(route('admin.catalog.categories.active', $product->category), [
            'is_active' => 0,
        ])->assertSessionHasErrors('category');

        $this->patch(route('admin.catalog.units.active', $product->unit), [
            'is_active' => 0,
        ])->assertSessionHasErrors('unit');

        $this->assertTrue($product->category->fresh()->is_active);
        $this->assertTrue($product->unit->fresh()->is_active);
    }

    public function test_attribute_group_and_value_cannot_be_deactivated_when_product_uses_them(): void
    {
        $user = $this->userWith(['products.create', 'product_attributes.activate']);
        $group = ProductAttributeGroup::query()->create(['name' => 'الخامة']);
        $value = $group->values()->create(['value' => 'قطن']);
        $product = $this->createProduct($user, [
            'attribute_group_ids' => [$group->id],
            'initial_variant' => [
                'sku' => 'COTTON-01',
                'list_price' => 90,
                'minimum_sale_price' => 60,
                'attribute_value_ids' => [$value->id],
            ],
        ]);

        $this->actingAs($user)->patch(route('admin.product-attributes.groups.active', $group), [
            'is_active' => 0,
        ])->assertSessionHasErrors('group');

        $this->patch(route('admin.product-attributes.values.active', $value), [
            'is_active' => 0,
        ])->assertSessionHasErrors('value');

        $this->assertTrue($group->fresh()->is_active);
        $this->assertTrue($value->fresh()->is_active);
        $this->assertSame($product->id, $value->variants()->firstOrFail()->product_id);
    }

    public function test_product_and_attribute_screens_require_specific_permissions(): void
    {
        $user = $this->userWith([]);
        $this->actingAs($user)->get(route('admin.products.index'))->assertForbidden();
        $this->get(route('admin.product-attributes.index'))->assertForbidden();
    }
}
