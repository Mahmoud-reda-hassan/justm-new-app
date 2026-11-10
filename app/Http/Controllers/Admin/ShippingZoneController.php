<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\ShippingZone;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShippingZoneController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission($request, 'shipping_zones.view');

        $zones = ShippingZone::query()
            ->with(['warehouse.branch.company', 'cities.governorate'])
            ->withCount('cities')
            ->whereHas('warehouse', fn (Builder $query) => $this->applyWarehouseScope($query, $request))
            ->orderBy('name_ar')
            ->paginate(20)
            ->withQueryString();

        $warehouses = Warehouse::query()
            ->with('branch.company', 'type')
            ->where('is_active', true)
            ->whereHas('type', fn (Builder $query) => $query->where('key', 'sales_dispatch')->where('is_active', true))
            ->whereHas('branch', fn (Builder $query) => $query->where('is_active', true)->whereHas('company', fn (Builder $company) => $company->where('is_active', true)))
            ->where(fn (Builder $query) => $this->applyWarehouseScope($query, $request))
            ->orderBy('name')
            ->get();

        $cities = City::query()
            ->with('governorate.country')
            ->where('is_active', true)
            ->orderBy('name_ar')
            ->get();

        return view('admin.shipping.zones', compact('zones', 'warehouses', 'cities'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'shipping_zones.create');
        $data = $this->validatedData($request);
        $warehouse = $this->validWarehouse($request, (int) $data['warehouse_id']);
        $cityIds = $data['city_ids'];
        $this->assertCitiesAvailable($cityIds);

        DB::transaction(function () use ($data, $cityIds, $warehouse): void {
            unset($data['city_ids']);
            $zone = ShippingZone::query()->create($data + ['is_active' => true]);
            City::query()->whereIn('id', $cityIds)->update(['shipping_zone_id' => $zone->id]);
        });

        return back()->with('status', 'تم إنشاء منطقة الشحن وربط المدن المحددة بها.');
    }

    public function update(Request $request, ShippingZone $zone): RedirectResponse
    {
        $this->authorizePermission($request, 'shipping_zones.update');
        $currentWarehouse = $zone->warehouse()->with('branch')->firstOrFail();
        $this->assertWarehouseScope($request, $currentWarehouse);

        $data = $this->validatedData($request, $zone);
        $warehouse = $this->validWarehouse($request, (int) $data['warehouse_id']);
        $cityIds = $data['city_ids'];
        $this->assertCitiesAvailable($cityIds, $zone);

        DB::transaction(function () use ($zone, $data, $cityIds): void {
            unset($data['city_ids']);
            $zone->update($data);
            City::query()->where('shipping_zone_id', $zone->id)->whereNotIn('id', $cityIds)->update(['shipping_zone_id' => null]);
            City::query()->whereIn('id', $cityIds)->update(['shipping_zone_id' => $zone->id]);
        });

        return back()->with('status', 'تم تحديث منطقة الشحن والمدن التابعة لها.');
    }

    public function setActive(Request $request, ShippingZone $zone): RedirectResponse
    {
        $this->authorizePermission($request, 'shipping_zones.activate');
        $warehouse = $zone->warehouse()->with('branch.company', 'type')->firstOrFail();
        $this->assertWarehouseScope($request, $warehouse);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        if ($data['is_active'] && (
            ! $warehouse->is_active
            || ! $warehouse->branch->is_active
            || ! $warehouse->branch->company->is_active
            || $warehouse->type->key !== 'sales_dispatch'
        )) {
            throw ValidationException::withMessages(['zone' => 'لا يمكن تفعيل منطقة شحن مرتبطة بمخزن أو فرع أو شركة غير نشطة، أو بمخزن ليس مخصصًا للبيع والتسليم.']);
        }

        $zone->update(['is_active' => (bool) $data['is_active']]);

        return back()->with('status', 'تم تحديث حالة منطقة الشحن.');
    }

    private function validatedData(Request $request, ?ShippingZone $zone = null): array
    {
        return $request->validate([
            'name_ar' => ['required', 'string', 'max:100', Rule::unique('shipping_zones', 'name_ar')->where('warehouse_id', $request->input('warehouse_id'))->ignore($zone?->id)],
            'name_en' => ['required', 'string', 'max:100', Rule::unique('shipping_zones', 'name_en')->where('warehouse_id', $request->input('warehouse_id'))->ignore($zone?->id)],
            'shipping_fee_min' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'shipping_fee_max' => ['required', 'numeric', 'gte:shipping_fee_min', 'max:9999999999.99'],
            'delivery_days' => ['required', 'integer', 'min:0', 'max:365'],
            'shipping_type' => ['required', Rule::in(['internal', 'external'])],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'city_ids' => ['required', 'array', 'min:1', 'max:500'],
            'city_ids.*' => ['required', 'integer', 'distinct', 'exists:cities,id'],
        ]);
    }

    private function validWarehouse(Request $request, int $warehouseId): Warehouse
    {
        $warehouse = Warehouse::query()->with('branch.company', 'type')->findOrFail($warehouseId);
        $this->assertWarehouseScope($request, $warehouse);

        if (
            ! $warehouse->is_active
            || ! $warehouse->branch->is_active
            || ! $warehouse->branch->company->is_active
            || $warehouse->type->key !== 'sales_dispatch'
        ) {
            throw ValidationException::withMessages(['warehouse_id' => 'اختر مخزن بيع مباشر نشطًا تابعًا لفرع وشركة نشطين.']);
        }

        return $warehouse;
    }

    private function assertCitiesAvailable(array $cityIds, ?ShippingZone $zone = null): void
    {
        $query = City::query()->whereIn('id', $cityIds)->where('is_active', true);
        if ($zone) {
            $query->where(fn (Builder $cities) => $cities->whereNull('shipping_zone_id')->orWhere('shipping_zone_id', $zone->id));
        } else {
            $query->whereNull('shipping_zone_id');
        }

        if ($query->count() !== count($cityIds)) {
            throw ValidationException::withMessages(['city_ids' => 'بعض المدن غير نشطة أو مرتبطة بالفعل بمنطقة شحن أخرى.']);
        }
    }

    private function applyWarehouseScope(Builder $query, Request $request): Builder
    {
        $user = $request->user();
        if ($user->hasPermission('structure.scope.manage_all')) {
            return $query;
        }

        $scopes = $user->dataScopes()->get();
        $companyIds = $scopes->where('scope_type', 'company')->pluck('scope_id');
        $branchIds = $scopes->where('scope_type', 'branch')->pluck('scope_id');
        $warehouseIds = $scopes->where('scope_type', 'warehouse')->pluck('scope_id');

        return $query->where(fn (Builder $visible) => $visible
            ->whereIn('id', $warehouseIds)
            ->orWhereIn('branch_id', $branchIds)
            ->orWhereHas('branch', fn (Builder $branch) => $branch->whereIn('company_id', $companyIds)));
    }

    private function assertWarehouseScope(Request $request, Warehouse $warehouse): void
    {
        abort_unless(
            $request->user()->hasPermission('structure.scope.manage_all')
            || $request->user()->hasDataScope('warehouse', (int) $warehouse->id)
            || $request->user()->hasDataScope('branch', (int) $warehouse->branch_id)
            || $request->user()->hasDataScope('company', (int) $warehouse->branch->company_id),
            403,
        );
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission), 403);
    }
}
