<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\City;
use App\Models\Company;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Region;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use App\Services\BranchManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_entities_are_related_and_can_be_created_as_one_hierarchy(): void
    {
        $country = Country::create(['name_ar' => 'مصر', 'name_en' => 'Egypt']);
        $region = Region::create(['name' => 'القاهرة الكبرى']);
        $governorate = Governorate::create([
            'country_id' => $country->id,
            'region_id' => $region->id,
            'name_ar' => 'القاهرة',
            'name_en' => 'Cairo',
        ]);
        $city = City::create([
            'governorate_id' => $governorate->id,
            'name_ar' => 'مدينة نصر',
            'name_en' => 'Nasr City',
        ]);
        $company = Company::create([
            'name' => 'Just-M',
            'city_id' => $city->id,
            'address' => 'عنوان الشركة',
        ]);
        $branch = Branch::create([
            'company_id' => $company->id,
            'city_id' => $city->id,
            'name' => 'المقر الرئيسي',
            'is_main' => true,
        ]);

        app(DatabaseSeeder::class)->run();
        $warehouseType = WarehouseType::query()->where('key', 'sales_dispatch')->firstOrFail();
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'warehouse_type_id' => $warehouseType->id,
            'name' => 'مخزن البيع',
        ]);

        $this->assertSame('Egypt', $governorate->fresh()->country->name_en);
        $this->assertSame('إقليم القاهرة الكبرى', $city->fresh()->governorate->region->name);
        $this->assertSame('Just-M', $branch->fresh()->company->name);
        $this->assertSame('المقر الرئيسي', $warehouse->fresh()->branch->name);
        $this->assertSame('sales_dispatch', $warehouse->fresh()->type->key);
    }

    public function test_branch_names_are_unique_inside_a_company_not_globally(): void
    {
        $first = Company::create(['name' => 'Company One']);
        $second = Company::create(['name' => 'Company Two']);

        Branch::create(['company_id' => $first->id, 'name' => 'المقر الرئيسي']);
        Branch::create(['company_id' => $second->id, 'name' => 'المقر الرئيسي']);

        $this->assertDatabaseCount('branches', 2);
    }

    public function test_branch_manager_keeps_only_one_main_branch_per_company(): void
    {
        $company = Company::create(['name' => 'Main Company']);
        $manager = app(BranchManager::class);

        $first = $manager->create([
            'company_id' => $company->id,
            'name' => 'First branch',
            'is_main' => true,
        ]);
        $second = $manager->create([
            'company_id' => $company->id,
            'name' => 'Second branch',
            'is_main' => true,
        ]);

        $this->assertFalse($first->fresh()->is_main);
        $this->assertTrue($second->fresh()->is_main);
        $this->assertSame(1, Branch::query()->where('company_id', $company->id)->where('is_main', true)->count());
    }

    public function test_branch_with_warehouses_cannot_be_moved_to_another_company(): void
    {
        $firstCompany = Company::create(['name' => 'First Company']);
        $secondCompany = Company::create(['name' => 'Second Company']);
        $branch = Branch::create(['company_id' => $firstCompany->id, 'name' => 'Branch']);
        $type = WarehouseType::create(['key' => 'branch-move-test', 'name' => 'Test']);
        Warehouse::create([
            'branch_id' => $branch->id,
            'warehouse_type_id' => $type->id,
            'name' => 'Warehouse',
        ]);

        $this->expectException(\RuntimeException::class);
        app(BranchManager::class)->update($branch, ['company_id' => $secondCompany->id]);
    }

    public function test_warehouse_name_is_unique_inside_its_branch(): void
    {
        $company = Company::create(['name' => 'Company']);
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'Main']);
        $type = WarehouseType::create(['key' => 'test-type', 'name' => 'Test']);

        Warehouse::create([
            'branch_id' => $branch->id,
            'warehouse_type_id' => $type->id,
            'name' => 'Main warehouse',
        ]);

        $this->expectException(QueryException::class);
        Warehouse::create([
            'branch_id' => $branch->id,
            'warehouse_type_id' => $type->id,
            'name' => 'Main warehouse',
        ]);
    }

    public function test_branch_cannot_reference_a_missing_company(): void
    {
        $this->expectException(QueryException::class);

        Branch::create(['company_id' => 999999, 'name' => 'Orphan branch']);
    }

    public function test_setup_seeds_egypt_geography_and_two_legacy_warehouse_types_without_sample_companies(): void
    {
        app(DatabaseSeeder::class)->run();

        $this->assertDatabaseHas('warehouse_types', ['key' => 'internal', 'name' => 'مخزن عادي']);
        $this->assertDatabaseHas('warehouse_types', ['key' => 'sales_dispatch', 'name' => 'مخزن بيع مباشر']);
        $this->assertDatabaseCount('countries', 1);
        $this->assertDatabaseCount('regions', 7);
        $this->assertDatabaseCount('governorates', 27);
        $this->assertDatabaseCount('cities', 516);
        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('branches', 0);
        $this->assertDatabaseCount('warehouses', 0);
    }
}
