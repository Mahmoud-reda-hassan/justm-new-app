<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\City;
use App\Models\Company;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Region;
use App\Models\SystemSetting;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
                ->whereKey($data['country_id'])
                ->where('is_active', true)
                ->first();

            $governorate = Governorate::query()
                ->whereKey($data['governorate_id'])
                ->where('country_id', $country?->id)
                ->where('is_active', true)
                ->first();

            $city = City::query()
                ->whereKey($data['city_id'])
                ->where('governorate_id', $governorate?->id)
                ->where('is_active', true)
                ->first();

            if (! $country || ! $governorate || ! $city) {
                throw ValidationException::withMessages([
                    'city_id' => 'اختر دولة ومحافظة ومدينة صحيحة ومتوافقة من القوائم.',
                ]);
            }

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
