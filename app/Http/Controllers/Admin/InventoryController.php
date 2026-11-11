<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\InventoryOpeningBalanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function openingBalances(Request $request): View
    {
        abort_unless($request->user()->hasPermission('inventory.opening_balances.view'), 403);

        $warehouses = $this->visibleWarehouses($request)
            ->where('is_active', true)
            ->with('branch.company')
            ->orderBy('name')
            ->get();

        $variants = ProductVariant::query()
            ->with('product')
            ->where('is_active', true)
            ->whereHas('product', fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('sku')
            ->get();

        $movements = InventoryMovement::query()
            ->with('warehouse', 'creator')
            ->withCount('items')
            ->where('movement_type', 'opening_balance')
            ->when(! $request->user()->hasPermission('structure.scope.manage_all'), function (Builder $query) use ($request): void {
                $query->whereIn('warehouse_id', $this->visibleWarehouses($request)->select('warehouses.id'));
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.inventory.opening-balances', compact('warehouses', 'variants', 'movements'));
    }

    public function storeOpeningBalance(Request $request, InventoryOpeningBalanceService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('inventory.opening_balances.create'), 403);

        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.product_variant_id' => ['required', 'integer', 'distinct', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'gte:0', 'max:999999999999'],
        ]);

        $warehouse = $this->visibleWarehouses($request)
            ->whereKey($data['warehouse_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $movement = $service->create(
            (int) $warehouse->id,
            $data['items'],
            (int) $request->user()->id,
            $data['notes'] ?? null,
        );

        return redirect()
            ->route('admin.inventory.opening-balances')
            ->with('status', 'تم تسجيل الرصيد الافتتاحي وترحيله بنجاح. رقم المستند: '.$movement->document_no);
    }

    private function visibleWarehouses(Request $request): Builder
    {
        return Warehouse::query()->when(
            ! $request->user()->hasPermission('structure.scope.manage_all'),
            function (Builder $query) use ($request): void {
                $user = $request->user();
                $warehouseIds = $user->dataScopes()->where('scope_type', 'warehouse')->pluck('scope_id');
                $branchIds = $user->dataScopes()->where('scope_type', 'branch')->pluck('scope_id');
                $companyIds = $user->dataScopes()->where('scope_type', 'company')->pluck('scope_id');

                $query->where(function (Builder $scope) use ($warehouseIds, $branchIds, $companyIds): void {
                    $scope->whereIn('warehouses.id', $warehouseIds)
                        ->orWhereIn('warehouses.branch_id', $branchIds)
                        ->orWhereHas('branch', fn (Builder $branch) => $branch->whereIn('company_id', $companyIds));
                });
            },
        );
    }
}
