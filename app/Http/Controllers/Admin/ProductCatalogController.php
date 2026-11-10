<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductCatalogController extends Controller
{
    public function categories(Request $request): View
    {
        $this->authorizePermission($request, 'product_categories.view');

        $allCategories = ProductCategory::query()
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $childrenByParent = $allCategories->groupBy(fn (ProductCategory $category) => $category->parent_id ?? 'root');
        $orderedCategories = collect();
        $visited = [];

        $appendChildren = function ($parentId, int $depth) use (&$appendChildren, &$visited, $childrenByParent, $orderedCategories): void {
            $key = $parentId ?? 'root';
            foreach ($childrenByParent->get($key, collect()) as $category) {
                if (in_array($category->id, $visited, true)) {
                    continue;
                }
                $visited[] = $category->id;
                $category->setAttribute('tree_depth', $depth);
                $category->setAttribute('has_children', $childrenByParent->has($category->id));
                $orderedCategories->push($category);
                $appendChildren($category->id, $depth + 1);
            }
        };
        $appendChildren(null, 0);

        // Keep legacy/orphaned rows visible rather than silently hiding them.
        foreach ($allCategories as $category) {
            if (! in_array($category->id, $visited, true)) {
                $category->setAttribute('tree_depth', 0);
                $category->setAttribute('has_children', $childrenByParent->has($category->id));
                $orderedCategories->push($category);
            }
        }

        return view('admin.catalog.categories', [
            'categories' => $orderedCategories,
            'parentOptions' => $allCategories,
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'product_categories.create');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:product_categories,name'],
            'code' => ['nullable', 'string', 'max:50', 'unique:product_categories,code'],
            'parent_id' => ['nullable', 'integer', Rule::exists('product_categories', 'id')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $this->assertValidParent($data['parent_id'] ?? null);
        ProductCategory::query()->create($data + ['is_active' => true]);

        return back()->with('status', 'تم إنشاء التصنيف.');
    }

    public function updateCategory(Request $request, ProductCategory $category): RedirectResponse
    {
        $this->authorizePermission($request, 'product_categories.update');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('product_categories', 'name')->ignore($category->id)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('product_categories', 'code')->ignore($category->id)],
            'parent_id' => ['nullable', 'integer', Rule::exists('product_categories', 'id')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $this->assertValidParent($data['parent_id'] ?? null, $category);
        $category->update($data);

        return back()->with('status', 'تم تحديث التصنيف.');
    }

    public function setCategoryActive(Request $request, ProductCategory $category): RedirectResponse
    {
        $this->authorizePermission($request, 'product_categories.activate');
        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        if (! $data['is_active'] && ($category->children()->where('is_active', true)->exists() || $category->products()->where('is_active', true)->exists())) {
            throw ValidationException::withMessages(['category' => 'لا يمكن إيقاف تصنيف له تصنيفات فرعية أو منتجات نشطة.']);
        }

        $category->update(['is_active' => (bool) $data['is_active']]);

        return back()->with('status', 'تم تحديث حالة التصنيف.');
    }

    public function units(Request $request): View
    {
        $this->authorizePermission($request, 'units.view');

        return view('admin.catalog.units', [
            'units' => Unit::query()->orderBy('name')->paginate(40),
        ]);
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'units.create');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:units,name'],
            'symbol' => ['nullable', 'string', 'max:20', 'unique:units,symbol'],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:6'],
        ]);
        $data['decimal_places'] = (int) ($data['decimal_places'] ?? 0);
        Unit::query()->create($data + ['is_active' => true]);

        return back()->with('status', 'تم إنشاء وحدة القياس.');
    }

    public function updateUnit(Request $request, Unit $unit): RedirectResponse
    {
        $this->authorizePermission($request, 'units.update');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('units', 'name')->ignore($unit->id)],
            'symbol' => ['nullable', 'string', 'max:20', Rule::unique('units', 'symbol')->ignore($unit->id)],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:6'],
        ]);
        $unit->update($data);

        return back()->with('status', 'تم تحديث وحدة القياس.');
    }

    public function setUnitActive(Request $request, Unit $unit): RedirectResponse
    {
        $this->authorizePermission($request, 'units.activate');
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        if (! $data['is_active'] && $unit->products()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['unit' => 'لا يمكن إيقاف وحدة قياس مستخدمة في منتجات نشطة.']);
        }
        $unit->update(['is_active' => (bool) $data['is_active']]);

        return back()->with('status', 'تم تحديث حالة وحدة القياس.');
    }

    private function assertValidParent(?int $parentId, ?ProductCategory $category = null): void
    {
        if (! $parentId) {
            return;
        }

        $cursor = ProductCategory::query()->findOrFail($parentId);
        if (! $cursor->is_active && $cursor->id !== $category?->parent_id) {
            throw ValidationException::withMessages(['parent_id' => 'لا يمكن إسناد تصنيف جديد إلى تصنيف موقوف.']);
        }

        while ($cursor) {
            if ($category && $cursor->id === $category->id) {
                throw ValidationException::withMessages(['parent_id' => 'لا يمكن جعل التصنيف تابعًا لنفسه أو لأحد أحفاده.']);
            }
            $cursor = $cursor->parent;
        }
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission), 403);
        abort_unless($request->user()?->hasPermission('structure.scope.manage_all'), 403, 'إدارة التصنيفات والوحدات تتطلب النطاق الشامل حتى يُعتمد نطاق المنتجات.');
    }
}
