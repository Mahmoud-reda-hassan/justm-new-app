<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductAttributeGroup;
use App\Models\ProductAttributeValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductAttributeController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission($request, 'product_attributes.view');

        return view('admin.products.attributes', [
            'groups' => ProductAttributeGroup::query()->with(['values' => fn ($q) => $q->orderBy('value')])->withCount('products')->orderBy('name')->paginate(30),
        ]);
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'product_attributes.create');
        $data = $request->validate(['name' => ['required', 'string', 'max:120', 'unique:product_attribute_groups,name']]);
        ProductAttributeGroup::query()->create($data + ['is_active' => true]);

        return back()->with('status', 'تم إنشاء مجموعة الصفة.');
    }

    public function updateGroup(Request $request, ProductAttributeGroup $group): RedirectResponse
    {
        $this->authorizePermission($request, 'product_attributes.update');
        $data = $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('product_attribute_groups', 'name')->ignore($group->id)]]);
        $group->update($data);

        return back()->with('status', 'تم تحديث مجموعة الصفة.');
    }

    public function setGroupActive(Request $request, ProductAttributeGroup $group): RedirectResponse
    {
        $this->authorizePermission($request, 'product_attributes.activate');
        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        if (! $data['is_active'] && $group->products()->exists()) {
            throw ValidationException::withMessages(['group' => 'لا يمكن إيقاف مجموعة صفات مرتبطة بمنتجات.']);
        }

        $group->update(['is_active' => (bool) $data['is_active']]);

        return back()->with('status', 'تم تحديث حالة مجموعة الصفة.');
    }

    public function storeValue(Request $request, ProductAttributeGroup $group): RedirectResponse
    {
        $this->authorizePermission($request, 'product_attributes.create');
        abort_unless($group->is_active, 422, 'لا يمكن إضافة قيمة إلى مجموعة صفات موقوفة.');
        $data = $request->validate(['value' => ['required', 'string', 'max:120', Rule::unique('product_attribute_values', 'value')->where('group_id', $group->id)]]);
        $group->values()->create($data + ['is_active' => true]);

        return back()->with('status', 'تم إضافة قيمة الصفة.');
    }

    public function updateValue(Request $request, ProductAttributeValue $value): RedirectResponse
    {
        $this->authorizePermission($request, 'product_attributes.update');
        $data = $request->validate(['value' => ['required', 'string', 'max:120', Rule::unique('product_attribute_values', 'value')->where('group_id', $value->group_id)->ignore($value->id)]]);
        $value->update($data);

        return back()->with('status', 'تم تحديث قيمة الصفة.');
    }

    public function setValueActive(Request $request, ProductAttributeValue $value): RedirectResponse
    {
        $this->authorizePermission($request, 'product_attributes.activate');
        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        if (! $data['is_active'] && $value->variants()->exists()) {
            throw ValidationException::withMessages(['value' => 'لا يمكن إيقاف قيمة صفة مستخدمة في أكواد منتجات.']);
        }

        $value->update(['is_active' => (bool) $data['is_active']]);

        return back()->with('status', 'تم تحديث حالة قيمة الصفة.');
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission), 403);
    }
}
