<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\City;
use App\Models\Company;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use App\Services\BranchManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OperatingStructureController extends Controller
{
    public function companies(Request $request): View
    {
        $this->authorizePermission($request, 'companies.view');

        $companies = Company::query()
            ->withCount(['branches', 'branches as active_branches_count' => fn ($q) => $q->where('is_active', true)])
            ->when(! $request->user()->hasPermission('structure.scope.manage_all'), fn (Builder $q) => $q->whereIn('id', $this->scopeIds($request, 'company')))
            ->orderBy('name')->paginate(20);

        return view('admin.structure.companies', ['companies' => $companies, 'cities' => City::query()->where('is_active', true)->orderBy('name_ar')->get()]);
    }

    public function storeCompany(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'companies.create');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:companies,name'],
            'phone' => ['nullable', 'string', 'max:40'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'fax' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')->where('is_active', true)],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);
        $company = Company::create($data + ['is_active' => true]);
        if (! $request->user()->hasPermission('structure.scope.manage_all')) {
            $request->user()->dataScopes()->create(['scope_type' => 'company', 'scope_id' => $company->id]);
        }

        return back()->with('status', 'تم إنشاء الشركة.');
    }

    public function updateCompany(Request $request, Company $company): RedirectResponse
    {
        $this->authorizePermission($request, 'companies.update');
        $this->assertCompanyScope($request, $company);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('companies', 'name')->ignore($company->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'fax' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);
        $company->update($data);

        return back()->with('status', 'تم تحديث بيانات الشركة.');
    }

    public function setCompanyActive(Request $request, Company $company): RedirectResponse
    {
        $this->authorizePermission($request, 'companies.activate');
        $this->assertCompanyScope($request, $company);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        if (! $data['is_active'] && $company->branches()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['company' => 'لا يمكن إيقاف شركة لديها فروع نشطة؛ أوقف الفروع أولًا.']);
        }
        $company->update(['is_active' => (bool) $data['is_active']]);

        return back()->with('status', 'تم تحديث حالة الشركة.');
    }

    public function branches(Request $request): View
    {
        $this->authorizePermission($request, 'branches.view');
        $companies = $this->visibleCompanies($request)->where('is_active', true)->orderBy('name')->get();
        $branches = Branch::query()->with('company', 'city')->withCount('warehouses')
            ->when(! $request->user()->hasPermission('structure.scope.manage_all'), function (Builder $q) use ($request): void {
                $companyIds = $this->scopeIds($request, 'company');
                $branchIds = $this->scopeIds($request, 'branch');
                $q->where(fn (Builder $inner) => $inner->whereIn('company_id', $companyIds)->orWhereIn('id', $branchIds));
            })->orderBy('company_id')->orderBy('name')->paginate(20);

        return view('admin.structure.branches', ['branches' => $branches, 'companies' => $companies, 'cities' => City::query()->where('is_active', true)->orderBy('name_ar')->get()]);
    }

    public function storeBranch(Request $request, BranchManager $manager): RedirectResponse
    {
        $this->authorizePermission($request, 'branches.create');
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'name' => ['required', 'string', 'max:120', Rule::unique('branches', 'name')->where('company_id', $request->input('company_id'))],
            'address' => ['nullable', 'string', 'max:255'],
            'is_main' => ['sometimes', 'boolean'],
        ]);
        $company = Company::query()->findOrFail($data['company_id']);
        $this->assertCompanyScope($request, $company);
        $manager->create($data + ['is_main' => false, 'is_active' => true]);

        return back()->with('status', 'تم إنشاء الفرع.');
    }

    public function updateBranch(Request $request, Branch $branch, BranchManager $manager): RedirectResponse
    {
        $this->authorizePermission($request, 'branches.update');
        $this->assertBranchScope($request, $branch);
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'name' => ['required', 'string', 'max:120', Rule::unique('branches', 'name')->where('company_id', $request->input('company_id'))->ignore($branch->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'is_main' => ['sometimes', 'boolean'],
        ]);
        $this->assertCompanyScope($request, Company::query()->findOrFail($data['company_id']), $branch);
        try {
            $manager->update($branch, $data);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages(['company_id' => $exception->getMessage()]);
        }

        return back()->with('status', 'تم تحديث بيانات الفرع.');
    }

    public function setBranchActive(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizePermission($request, 'branches.activate');
        $this->assertBranchScope($request, $branch);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
                if ($data['is_active'] && ! $branch->company->is_active) {
            throw ValidationException::withMessages(['branch' => 'لا يمكن تفعيل فرع تابع لشركة موقوفة.']);
        }
        if (! $data['is_active'] && $branch->warehouses()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['branch' => 'لا يمكن إيقاف فرع لديه مخازن نشطة؛ أوقف المخازن أولًا.']);
        }
        $branch->update(['is_active' => (bool) $data['is_active']]);

        return back()->with('status', 'تم تحديث حالة الفرع.');
    }

    public function warehouses(Request $request): View
    {
        $this->authorizePermission($request, 'warehouses.view');
        $warehouses = Warehouse::query()->with('branch.company', 'type')
            ->when(! $request->user()->hasPermission('structure.scope.manage_all'), function (Builder $q) use ($request): void {
                $companyIds = $this->scopeIds($request, 'company');
                $branchIds = $this->scopeIds($request, 'branch');
                $warehouseIds = $this->scopeIds($request, 'warehouse');
                $q->where(fn (Builder $inner) => $inner
                    ->whereIn('id', $warehouseIds)
                    ->orWhereIn('branch_id', $branchIds)
                    ->orWhereHas('branch', fn (Builder $branch) => $branch->whereIn('company_id', $companyIds)));
            })->orderBy('branch_id')->orderBy('name')->paginate(20);

        $branches = Branch::query()->where('is_active', true)
            ->whereHas('company', fn (Builder $q) => $q->where('is_active', true))
            ->when(! $request->user()->hasPermission('structure.scope.manage_all'), function (Builder $q) use ($request): void {
                $companyIds = $this->scopeIds($request, 'company');
                $branchIds = $this->scopeIds($request, 'branch');
                $q->where(fn (Builder $inner) => $inner->whereIn('id', $branchIds)->orWhereIn('company_id', $companyIds));
            })->orderBy('name')->get();

        return view('admin.structure.warehouses', [
            'warehouses' => $warehouses,
            'branches' => $branches,
            'types' => WarehouseType::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeWarehouse(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'warehouses.create');
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'warehouse_type_id' => ['required', 'integer', 'exists:warehouse_types,id'],
            'name' => ['required', 'string', 'max:150', Rule::unique('warehouses', 'name')->where('branch_id', $request->input('branch_id'))],
        ]);
        $branch = Branch::query()->findOrFail($data['branch_id']);
        $this->assertBranchScope($request, $branch);
        abort_unless($branch->is_active && $branch->company()->where('is_active', true)->exists(), 422, 'الفرع أو الشركة موقوفة.');
        abort_unless(WarehouseType::query()->whereKey($data['warehouse_type_id'])->where('is_active', true)->exists(), 422);
        Warehouse::create($data + ['is_active' => true]);

        return back()->with('status', 'تم إنشاء المخزن.');
    }

    public function updateWarehouse(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $this->authorizePermission($request, 'warehouses.update');
        $this->assertWarehouseScope($request, $warehouse);
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'warehouse_type_id' => ['required', 'integer', 'exists:warehouse_types,id'],
            'name' => ['required', 'string', 'max:150', Rule::unique('warehouses', 'name')->where('branch_id', $request->input('branch_id'))->ignore($warehouse->id)],
        ]);
        $branch = Branch::query()->findOrFail($data['branch_id']);
        $this->assertBranchScope($request, $branch);
        abort_unless($branch->is_active && $branch->company()->where('is_active', true)->exists(), 422, 'الفرع أو الشركة موقوفة.');
        abort_unless(WarehouseType::query()->whereKey($data['warehouse_type_id'])->where('is_active', true)->exists(), 422);
        $warehouse->update($data);

        return back()->with('status', 'تم تحديث بيانات المخزن.');
    }

    public function setWarehouseActive(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $this->authorizePermission($request, 'warehouses.activate');
        $this->assertWarehouseScope($request, $warehouse);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        if ($data['is_active'] && (! $warehouse->branch->is_active || ! $warehouse->branch->company->is_active)) {
            throw ValidationException::withMessages(['warehouse' => 'لا يمكن تفعيل مخزن تابع لفرع أو شركة موقوفة.']);
        }
        $warehouse->update(['is_active' => (bool) $data['is_active']]);

        return back()->with('status', 'تم تحديث حالة المخزن.');
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission), 403);
    }

    private function scopeIds(Request $request, string $type): array
    {
        return $request->user()->dataScopes()->where('scope_type', $type)->pluck('scope_id')->map(fn ($id) => (int) $id)->all();
    }

    private function visibleCompanies(Request $request): Builder
    {
        return Company::query()->when(
            ! $request->user()->hasPermission('structure.scope.manage_all'),
            function (Builder $q) use ($request): void {
                $q->where(fn (Builder $inner) => $inner
                    ->whereIn('id', $this->scopeIds($request, 'company'))
                    ->orWhereHas('branches', fn (Builder $branches) => $branches->whereIn('id', $this->scopeIds($request, 'branch'))));
            },
        );
    }

    private function assertCompanyScope(Request $request, Company $company, ?Branch $currentBranch = null): void
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermission('structure.scope.manage_all')
            || $user->hasDataScope('company', (int) $company->id)
            || ($currentBranch && (int) $currentBranch->company_id === (int) $company->id && $user->hasDataScope('branch', (int) $currentBranch->id)),
            403,
        );
    }

    private function assertBranchScope(Request $request, Branch $branch): void
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermission('structure.scope.manage_all')
            || $user->hasDataScope('branch', (int) $branch->id)
            || $user->hasDataScope('company', (int) $branch->company_id),
            403,
        );
    }

    private function assertWarehouseScope(Request $request, Warehouse $warehouse): void
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermission('structure.scope.manage_all')
            || $user->hasDataScope('warehouse', (int) $warehouse->id)
            || $user->hasDataScope('branch', (int) $warehouse->branch_id)
            || $user->hasDataScope('company', (int) $warehouse->branch->company_id),
            403,
        );
    }
}
