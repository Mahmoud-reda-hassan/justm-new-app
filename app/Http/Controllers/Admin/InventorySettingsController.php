<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventorySettingsController extends Controller
{
    public function index(Request $request): View
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
}
