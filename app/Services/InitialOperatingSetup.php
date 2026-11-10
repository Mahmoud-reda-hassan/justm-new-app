<?php

namespace App\\Services;

use App\\Models\\Branch;
use App\\Models\\City;
use App\\Models\\Company;
use App\\Models\\Country;
use App\\Models\\Governorate;
use App\\Models\\Region;
use App\\Models\\SystemSetting;
use App\\Models\\Warehouse;
use App\\Models\\WarehouseType;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Validation\\ValidationException;

class InitialOperatingSetup
{
    public function __construct(private readonly BranchManager $branchManager)
    {
    }

    public function configure(array $data): void
    {
        DB::transaction(function () use ($data): void {
            if (Company::query()->exists() || Branch::query()->exists() || Warehouse::query()->exists()) {
                throw ValidationException::withMessages([
                    'setup' => 'توجد بيانات شركة أو فروع أو مخازن بالفعل؛ لا يمكن تشغيل التأسيس الأول فوق بيانات موجودة.',
                ]);
            }

            $country = Country::query()
                ->where('name_ar', $data['country_name_ar'])
                ->orWhere('name_en', $data['country_name_en'])
                ->first();

            if ($country && ($country->name_ar !== $data['country_name_ar'] || $country->name_en !== $data['country_name_en'])) {
                throw ValidationException::withMessages([
                    'country_name_ar' => 'الدولة موجودة مسبقًا ببيانات مختلفة؛ راجع البيانات الجغرافية.',
                ]);
            }

            $country ??= Country::create([
                'name_ar' => $data['country_name_ar'],
                'name_en' => $data['country_name_en'],
                'is_active' => true,
            ]);

            $region = null;
            if (! empty($data['region_name'])) {
                $region = Region::query()->firstOrCreate(['name' => $data['region_name']]);
            }

            $governorate = Governorate::query()
                ->where('country_id', $country->id)
                ->where(function ($query) use ($data): void {
                    $query->where('name_ar', $data['governorate_name_ar'])
                        ->orWhere('name_en', $data['governorate_name_en']);
                })
                ->first();

            if ($governorate && ($governorate->name_ar !== $data['governorate_name_ar'] || $governorate->name_en !== $data['governorate_name_en'])) {
                throw ValidationException::withMessages([
                    'governorate_name_ar' => 'المحافظة موجودة مسبقًا ببيانات مختلفة.',
                ]);
            }

            $governorate ??= Governorate::create([
                'country_id' => $country->id,
                'region_id' => $region?->id,
                'name_ar' => $data['governorate_name_ar'],
                'name_en' => $data['governorate_name_en'],
                'is_active' => true,
            ]);

            $city = City::query()
                ->where('governorate_id', $governorate->id)
                ->where(function ($query) use ($data): void {
                    $query->where('name_ar', $data['city_name_ar'])
                        ->orWhere('name_en', $data['city_name_en']);
                })
                ->first();

            if ($city && ($city->name_ar !== $data['city_name_ar'] || $city->name_en !== $data['city_name_en'])) {
                throw ValidationException::withMessages([
                    'city_name_ar' => 'المدينة موجودة مسبقًا ببيانات مختلفة.',
                ]);
            }

            $city ??= City::create([
                'governorate_id' => $governorate->id,
                'name_ar' => $data['city_name_ar'],
                'name_en' => $data['city_name_en'],
                'is_active' => true,
            ]);

            $company = Company::create([
                'name' => $data['company_name'],
                'phone' => $data['company_phone'] ?? null,
                'mobile' => $data['company_mobile'] ?? null,
                'email' => $data['company_email'] ?? null,
                'city_id' => $city->id,
                'address' => $data['company_address'] ?? null,
                'is_active' => true,
            ]);

            $branch = $this->branchManager->create([
                'company_id' => $company->id,
                'city_id' => $city->id,
                'name' => $data['branch_name'],
                'is_main' => true,
                'address' => $data['branch_address'] ?? null,
                'is_active' => true,
            ]);

            $warehouseType = WarehouseType::query()
                ->where('key', $data['warehouse_type'])
                ->where('is_active', true)
                ->firstOrFail();

            Warehouse::create([
                'branch_id' => $branch->id,
                'warehouse_type_id' => $warehouseType->id,
                'name' => $data['warehouse_name'],
                'is_active' => true,
            ]);

            SystemSetting::query()->updateOrCreate(
                ['setting_key' => 'site_name'],
                [
                    'setting_value' => $data['site_name'],
                    'description' => 'الاسم الظاهر للنظام في الواجهات.',
                ],
            );
        });
    }
}
