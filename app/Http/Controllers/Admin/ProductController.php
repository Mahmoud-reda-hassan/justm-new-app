<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductAttributeGroup;
use App\Models\ProductAttributeValue;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission($request, 'products.view');

        $query = Product::query()->with([
            'category',
            'unit',
            'attributeGroups.values',
            'variants.attributeValues.group',
        ])->orderByDesc('id');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhereHas('variants', fn (Builder $variants) => $variants->where('sku', 'like', '%'.$search.'%'));
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->input('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->input('status') === 'inactive') {
            $query->where('is_active', false);
        }

        return view('admin.products.index', [
            'products' => $query->paginate(20)->withQueryString(),
            'categories' => ProductCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(),
            'attributeGroups' => ProductAttributeGroup::query()->with(['values' => fn ($q) => $q->where('is_active', true)->orderBy('value')])->where('is_active', true)->orderBy('name')->get(),
            'stats' => [
                'total' => Product::query()->count(),
                'active' => Product::query()->where('is_active', true)->count(),
                'visible' => Product::query()->where('is_visible', true)->count(),
                'variants' => ProductVariant::query()->where('is_active', true)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'products.create');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180', 'unique:products,name'],
            'category_id' => ['required', 'integer', Rule::exists('product_categories', 'id')->where('is_active', true)],
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'description' => ['nullable', 'string', 'max:20000'],
            'length_cm' => ['nullable', 'numeric', 'min:0'],
            'width_cm' => ['nullable', 'numeric', 'min:0'],
            'height_cm' => ['nullable', 'numeric', 'min:0'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'is_visible' => ['nullable', 'boolean'],
            'attribute_group_ids' => ['nullable', 'array', 'max:12'],
            'attribute_group_ids.*' => ['integer', 'distinct', Rule::exists('product_attribute_groups', 'id')->where('is_active', true)],
            'initial_variant.sku' => ['required', 'string', 'max:50', 'unique:product_variants,sku'],
            'initial_variant.variant_name' => ['nullable', 'string', 'max:180'],
            'initial_variant.list_price' => ['required', 'numeric', 'min:0'],
            'initial_variant.minimum_sale_price' => ['required', 'numeric', 'min:0'],
            'initial_variant.attribute_value_ids' => ['nullable', 'array', 'max:12'],
            'initial_variant.attribute_value_ids.*' => ['integer', 'distinct', Rule::exists('product_attribute_values', 'id')->where('is_active', true)],
        ]);

        $groupIds = array_map('intval', $data['attribute_group_ids'] ?? []);
        $variantData = $data['initial_variant'];
        $this->assertPriceRange($variantData['minimum_sale_price'], $variantData['list_price']);
        $valueIds = $this->validatedAttributeValues($groupIds, $variantData['attribute_value_ids'] ?? [], 'initial_variant.attribute_value_ids');
        unset($data['attribute_group_ids'], $data['initial_variant']);
        $data['is_visible'] = $request->boolean('is_visible');

        DB::transaction(function () use ($data, $groupIds, $variantData, $valueIds): void {
            $product = Product::query()->create($data + ['is_active' => true]);
            $product->attributeGroups()->sync($groupIds);
            $variant = $product->variants()->create([
                'sku' => trim($variantData['sku']),
                'variant_name' => $variantData['variant_name'] ?? null,
                'list_price' => $variantData['list_price'],
                'minimum_sale_price' => $variantData['minimum_sale_price'],
                'is_active' => true,
            ]);
            $variant->attributeValues()->sync($valueIds);
        });

        return back()->with('status', 'تم إنشاء المنتج والكود الأول.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorizePermission($request, 'products.update');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180', Rule::unique('products', 'name')->ignore($product->id)],
            'category_id' => ['required', 'integer', Rule::exists('product_categories', 'id')->where('is_active', true)],
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'description' => ['nullable', 'string', 'max:20000'],
            'length_cm' => ['nullable', 'numeric', 'min:0'],
            'width_cm' => ['nullable', 'numeric', 'min:0'],
            'height_cm' => ['nullable', 'numeric', 'min:0'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'is_visible' => ['nullable', 'boolean'],
        ]);
        $data['is_visible'] = $request->boolean('is_visible');
        $product->update($data);

        return back()->with('status', 'تم تحديث بيانات المنتج.');
    }

    public function setActive(Request $request, Product $product): RedirectResponse
    {
        $this->authorizePermission($request, 'products.activate');
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        if ($data['is_active'] && (! $product->category->is_active || ! $product->unit->is_active)) {
            throw ValidationException::withMessages(['product' => 'لا يمكن تفعيل المنتج قبل تفعيل تصنيفه ووحدة قياسه.']);
        }
        $product->update(['is_active' => (bool) $data['is_active']]);

        return back()->with('status', 'تم تحديث حالة المنتج.');
    }

    public function setVisible(Request $request, Product $product): RedirectResponse
    {
        $this->authorizePermission($request, 'products.update');
        $data = $request->validate(['is_visible' => ['required', 'boolean']]);
        if ($data['is_visible'] && (! $product->is_active || ! $product->category->is_active || ! $product->unit->is_active)) {
            throw ValidationException::withMessages(['product' => 'لا يمكن إتاحة منتج موقوف أو تابع لتصنيف/وحدة قياس موقوفة.']);
        }
        $product->update(['is_visible' => (bool) $data['is_visible']]);

        return back()->with('status', 'تم تحديث إتاحة المنتج للبيع.');
    }

    public function storeVariant(Request $request, Product $product): RedirectResponse
    {
        $this->authorizePermission($request, 'products.create');
        abort_unless($product->is_active, 422, 'لا يمكن إضافة كود لمنتج موقوف.');
        $data = $this->validateVariant($request);
        $groupIds = $product->attributeGroups()->pluck('product_attribute_groups.id')->map(fn ($id) => (int) $id)->all();
        $valueIds = $this->validatedAttributeValues($groupIds, $data['attribute_value_ids'] ?? [], 'attribute_value_ids');
        unset($data['attribute_value_ids']);
        $product->variants()->create($data + ['is_active' => true])->attributeValues()->sync($valueIds);

        return back()->with('status', 'تم إضافة كود المنتج.');
    }

    public function updateVariant(Request $request, Product $product, ProductVariant $variant): RedirectResponse
    {
        $this->authorizePermission($request, 'products.update');
        abort_unless($variant->product_id === $product->id, 404);
        $data = $this->validateVariant($request, $variant);
        $groupIds = $product->attributeGroups()->pluck('product_attribute_groups.id')->map(fn ($id) => (int) $id)->all();
        $valueIds = $this->validatedAttributeValues($groupIds, $data['attribute_value_ids'] ?? [], 'attribute_value_ids');
        unset($data['attribute_value_ids']);
        $variant->update($data);
        $variant->attributeValues()->sync($valueIds);

        return back()->with('status', 'تم تحديث كود المنتج.');
    }

    public function setVariantActive(Request $request, Product $product, ProductVariant $variant): RedirectResponse
    {
        $this->authorizePermission($request, 'products.activate');
        abort_unless($variant->product_id === $product->id, 404);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $variant->update(['is_active' => (bool) $data['is_active']]);

        return back()->with('status', 'تم تحديث حالة كود المنتج.');
    }

    private function validateVariant(Request $request, ?ProductVariant $variant = null): array
    {
        $skuRule = Rule::unique('product_variants', 'sku');
        if ($variant) {
            $skuRule->ignore($variant->id);
        }
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:50', $skuRule],
            'variant_name' => ['nullable', 'string', 'max:180'],
            'list_price' => ['required', 'numeric', 'min:0'],
            'minimum_sale_price' => ['required', 'numeric', 'min:0'],
            'attribute_value_ids' => ['nullable', 'array', 'max:12'],
            'attribute_value_ids.*' => ['integer', 'distinct', Rule::exists('product_attribute_values', 'id')->where('is_active', true)],
        ]);
        $this->assertPriceRange($data['minimum_sale_price'], $data['list_price']);

        return $data;
    }

    private function assertPriceRange(string|int|float $minimum, string|int|float $list): void
    {
        if ((float) $minimum > (float) $list) {
            throw ValidationException::withMessages(['minimum_sale_price' => 'الحد الأدنى لسعر البيع لا يمكن أن يتجاوز سعر القائمة.']);
        }
    }

    private function validatedAttributeValues(array $groupIds, array $valueIds, string $field): array
    {
        $groupIds = array_values(array_unique(array_map('intval', $groupIds)));
        $valueIds = array_values(array_unique(array_map('intval', $valueIds)));

        if (count($groupIds) !== count($valueIds)) {
            if ($groupIds !== [] || $valueIds !== []) {
                throw ValidationException::withMessages([$field => 'يجب اختيار قيمة واحدة فقط من كل مجموعة صفات مرتبطة بالمنتج.']);
            }
            return [];
        }

        if ($valueIds === []) {
            return [];
        }

        $values = ProductAttributeValue::query()->whereIn('id', $valueIds)->where('is_active', true)->get();
        $selectedGroupIds = $values->pluck('group_id')->map(fn ($id) => (int) $id)->all();

        if ($values->count() !== count($valueIds)
            || count(array_unique($selectedGroupIds)) !== count($selectedGroupIds)
            || array_diff($groupIds, $selectedGroupIds) !== []
            || array_diff($selectedGroupIds, $groupIds) !== []) {
            throw ValidationException::withMessages([$field => 'قيم الصفات المختارة لا تطابق مجموعات الصفات المحددة للمنتج.']);
        }

        return $valueIds;
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission), 403);
    }
}
