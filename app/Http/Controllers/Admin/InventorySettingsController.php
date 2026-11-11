<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\VirtualStockPolicyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class InventorySettingsController extends Controller
{
    public function index(Request $request, VirtualStockPolicyService $virtualStock): View
    {
        abort_unless($request->user()->hasPermission('inventory.settings.view'), 403);
        $setting = SystemSetting::query()->firstOrCreate(
            ['setting_key' => 'inventory.allow_negative_stock'],
            ['setting_value' => '0', 'description' => 'السماح بترحيل حركات مخزنية ينتج عنها رصيد فعلي سالب'],
        );

        return view('admin.inventory.settings', [
            'allowNegativeStock' => $setting->setting_value === '1',
            'updatedAt' => $setting->updated_at,
            'updatedBy' => $setting->updatedBy?->name,
            'virtualStock' => $virtualStock->settings(),
            'stockStatuses' => VirtualStockPolicyService::STATUSES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('inventory.settings.update'), 403);
        $data = $request->validate(['allow_negative_stock' => ['required', 'boolean']]);
        DB::transaction(function () use ($data, $request): void {
            SystemSetting::query()->updateOrCreate(
                ['setting_key' => 'inventory.allow_negative_stock'],
                ['setting_value' => $data['allow_negative_stock'] ? '1' : '0',
                 'description' => 'السماح بترحيل حركات مخزنية ينتج عنها رصيد فعلي سالب',
                 'updated_by' => $request->user()->id],
            );
        });
        return redirect()->route('admin.inventory.settings')->with('status', 'تم حفظ إعدادات المخازن العامة.');
    }

    public function updateVirtualStockPolicy(Request $request, VirtualStockPolicyService $virtualStock): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('inventory.settings.update'), 403);

        $allowed = array_keys(VirtualStockPolicyService::STATUSES);
        $data = $request->validate([
            'create_order_statuses' => ['sometimes', 'array'],
            'create_order_statuses.*' => ['string', Rule::in($allowed)],
            'customer_service_matrix' => ['sometimes', 'array'],
            'customer_service_matrix.*' => ['sometimes', 'array'],
            'customer_service_matrix.*.*' => ['string', Rule::in($allowed)],
            'warehouse_statuses' => ['sometimes', 'array'],
            'warehouse_statuses.*' => ['string', Rule::in($allowed)],
        ]);

        $matrixInput = $data['customer_service_matrix'] ?? [];
        $matrix = [];
        foreach ($allowed as $status) {
            $matrix[$status] = array_values(array_unique($matrixInput[$status] ?? []));
        }

        DB::transaction(function () use ($data, $matrix, $request, $virtualStock): void {
            $virtualStock->save([
                'create_order_statuses' => array_values(array_unique($data['create_order_statuses'] ?? [])),
                'customer_service_matrix' => $matrix,
                'warehouse_statuses' => array_values(array_unique($data['warehouse_statuses'] ?? [])),
            ], (int) $request->user()->id);
        });

        return redirect()->route('admin.inventory.settings')->with('status', 'تم حفظ إعدادات المخزون الافتراضي.');
    }
}
