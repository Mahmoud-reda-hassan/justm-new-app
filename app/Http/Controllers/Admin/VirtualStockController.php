<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\VirtualStockPolicyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VirtualStockController extends Controller
{
    public function index(Request $request, VirtualStockPolicyService $policy): View
    {
        abort_unless($request->user()->hasPermission('inventory.virtual_stock.view'), 403);

        $types = [
            'create_order' => 'مخزن منشئ أمر البيع',
            'customer_service' => 'مخزن خدمة العملاء',
            'warehouse' => 'مخزن أمين المخزن والتجهيز',
        ];
        $type = $request->query('type', 'create_order');
        if (! array_key_exists($type, $types)) {
            $type = 'create_order';
        }

        $statusOptions = VirtualStockPolicyService::STATUSES;
        $status = $request->query('status', array_key_first($statusOptions));
        if (! array_key_exists($status, $statusOptions)) {
            $status = array_key_first($statusOptions);
        }

        $warehousesQuery = Warehouse::query()
            ->with('branch.company', 'type')
            ->where('is_active', true)
            ->when(
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
            )
            ->orderBy('name');

        $warehouses = $warehousesQuery->get();
        $warehouse = $warehouses->firstWhere('id', (int) $request->query('warehouse_id'))
            ?? $warehouses->first();

        $countedStatuses = $policy->statusesFor(
            $type,
            $type === 'customer_service' ? $status : null,
        );

        $variantQuery = ProductVariant::query()
            ->with(['product.unit', 'product.category'])
            ->where('is_active', true)
            ->whereHas('product', fn (Builder $query) => $query->where('is_active', true))
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = '%'.trim((string) $request->query('q')).'%';
                $query->where(function (Builder $search) use ($term): void {
                    $search->where('sku', 'like', $term)
                        ->orWhere('variant_name', 'like', $term)
                        ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'like', $term));
                });
            })
            ->orderBy('sku');

        $variants = $variantQuery->get();
        $stockByVariant = collect();
        $reservedByVariant = collect();

        if ($warehouse) {
            $stockByVariant = DB::table('warehouse_product_stocks')
                ->where('warehouse_id', $warehouse->id)
                ->pluck('quantity_on_hand', 'product_variant_id');

            if ($countedStatuses !== []) {
                $reservedByVariant = DB::table('sales_order_item_reservations as reservations')
                    ->join('sales_order_items as items', 'items.id', '=', 'reservations.sales_order_item_id')
                    ->join('sales_orders as orders', 'orders.id', '=', 'items.sales_order_id')
                    ->where('reservations.warehouse_id', $warehouse->id)
                    ->where('reservations.status', 'active')
                    ->whereIn('orders.status', $countedStatuses)
                    ->select([
                        'reservations.product_variant_id',
                        'orders.status',
                    ])
                    ->selectRaw('SUM(reservations.reserved_quantity) as reserved_quantity')
                    ->groupBy('reservations.product_variant_id', 'orders.status')
                    ->get()
                    ->groupBy('product_variant_id');
            }
        }

        $rows = $variants->map(function (ProductVariant $variant) use ($stockByVariant, $reservedByVariant): array {
            $breakdown = ($reservedByVariant->get($variant->id) ?? collect())
                ->mapWithKeys(fn ($row): array => [$row->status => (float) $row->reserved_quantity]);
            $onHand = (float) ($stockByVariant->get($variant->id) ?? 0);
            $reserved = (float) $breakdown->sum();

            return [
                'variant' => $variant,
                'on_hand' => $onHand,
                'reserved' => $reserved,
                'available' => max(0, $onHand - $reserved),
                'breakdown' => $breakdown,
            ];
        });

        return view('admin.inventory.virtual-stock', [
            'types' => $types,
            'type' => $type,
            'status' => $status,
            'statusOptions' => $statusOptions,
            'countedStatuses' => $countedStatuses,
            'warehouses' => $warehouses,
            'warehouse' => $warehouse,
            'rows' => $rows,
            'totals' => [
                'on_hand' => $rows->sum('on_hand'),
                'reserved' => $rows->sum('reserved'),
                'available' => $rows->sum('available'),
            ],
            'search' => trim((string) $request->query('q', '')),
        ]);
    }
}
