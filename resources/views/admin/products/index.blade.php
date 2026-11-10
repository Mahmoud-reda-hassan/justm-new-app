@extends('layouts.app')

@section('title', 'المنتجات')
@section('page-title', 'المنتجات')
@section('breadcrumb', 'المنتجات')

@section('content')
<header class="head"><div><span class="eyebrow">JUST-M · كتالوج المنتجات</span><h1>المنتجات والأكواد</h1><p>تعريف المنتج ووحدة قياسه وصفاته وأكواد البيع وأسعارها.</p></div>
<nav class="nav"><a href="{{ route('dashboard') }}">الرئيسية</a>@if(auth()->user()->hasPermission('product_categories.view'))<a href="{{ route('admin.catalog.categories') }}">التصنيفات</a>@endif @if(auth()->user()->hasPermission('units.view'))<a href="{{ route('admin.catalog.units') }}">الوحدات</a>@endif @if(auth()->user()->hasPermission('product_attributes.view'))<a href="{{ route('admin.product-attributes.index') }}">صفات المنتجات</a>@endif</nav></header>
@if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert errors"><strong>راجع البيانات:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="stats"><div class="stat"><small>كل المنتجات</small><strong>{{ number_format($stats['total']) }}</strong></div><div class="stat"><small>منتجات نشطة</small><strong>{{ number_format($stats['active']) }}</strong></div><div class="stat"><small>متاحة للبيع</small><strong>{{ number_format($stats['visible']) }}</strong></div><div class="stat"><small>أكواد نشطة</small><strong>{{ number_format($stats['variants']) }}</strong></div></section>

@if(auth()->user()->hasPermission('products.create'))
<section class="panel"><h2>إضافة منتج</h2><p class="sub">كل منتج يحتاج كود بيع واحدًا على الأقل. يمكن إضافة أكواد أخرى بعد الحفظ.</p>
<form method="post" enctype="multipart/form-data" action="{{ route('admin.products.store') }}">@csrf
<div class="fields">
<div class="field span2"><label>اسم المنتج</label><input name="name" value="{{ old('name') }}" required maxlength="180"></div>
<div class="field"><label>التصنيف</label><select name="category_id" required><option value="">اختر التصنيف</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('category_id')===(string)$category->id)>{{ $category->name }}</option>@endforeach</select></div>
<div class="field"><label>وحدة القياس</label><select name="unit_id" required><option value="">اختر الوحدة</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected((string)old('unit_id')===(string)$unit->id)>{{ $unit->name }} {{ $unit->symbol }}</option>@endforeach</select></div>
<div class="field span4"><label>وصف المنتج</label><textarea name="description">{{ old('description') }}</textarea></div><div class="field span4"><label>صورة المنتج</label><input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"></div>
<div class="field"><label>الطول (سم)</label><input name="length_cm" type="number" step="0.001" min="0" value="{{ old('length_cm') }}"></div>
<div class="field"><label>العرض (سم)</label><input name="width_cm" type="number" step="0.001" min="0" value="{{ old('width_cm') }}"></div>
<div class="field"><label>الارتفاع (سم)</label><input name="height_cm" type="number" step="0.001" min="0" value="{{ old('height_cm') }}"></div>
<div class="field"><label>الوزن (كجم)</label><input name="weight_kg" type="number" step="0.001" min="0" value="{{ old('weight_kg') }}"></div>
<div class="field span4"><label class="checkline"><input type="checkbox" name="is_visible" value="1" @checked(old('is_visible',1))> متاح للبيع</label></div>
@if($attributeGroups->isNotEmpty())
<div class="field span4"><label>صفات المنتج — اختر المجموعات المستخدمة، ثم قيمة كل مجموعة للكود الأول</label>
<div class="group-picks">
@foreach($attributeGroups as $group)
<div class="group-pick">
<label class="checkline"><input class="group-toggle" type="checkbox" name="attribute_group_ids[]" value="{{ $group->id }}" data-target="initial-group-{{ $group->id }}" @checked(in_array((string)$group->id,array_map('strval',old('attribute_group_ids',[])),true))> {{ $group->name }}</label>
<select id="initial-group-{{ $group->id }}" name="initial_variant[attribute_value_ids][]" disabled><option value="">اختر {{ $group->name }}</option>@foreach($group->values as $value)<option value="{{ $value->id }}">{{ $value->value }}</option>@endforeach</select>
</div>
@endforeach
</div></div>
@endif
<div class="field"><label>كود / SKU</label><input id="initialSku" name="initial_variant[sku]" value="{{ old('initial_variant.sku') }}" required maxlength="50"><button type="button" class="btn soft" onclick="generateProductCode(document.getElementById('initialSku'))">توليد باركود داخلي</button></div>
<div class="field"><label>اسم التركيبة (اختياري)</label><input name="initial_variant[variant_name]" value="{{ old('initial_variant.variant_name') }}" maxlength="180"></div>
<div class="field"><label>سعر القائمة</label><input name="initial_variant[list_price]" type="number" step="0.01" min="0" value="{{ old('initial_variant.list_price') }}" required></div>
<div class="field"><label>أقل سعر بيع مسموح</label><input name="initial_variant[minimum_sale_price]" type="number" step="0.01" min="0" value="{{ old('initial_variant.minimum_sale_price') }}" required></div>
</div>
<p class="muted">لا تُسجل تكلفة المنتج أو رصيد المخزون أو الحجز المسبق في شاشة تعريف المنتج.</p>
<div class="actions"><button class="btn primary" type="submit">حفظ المنتج والكود الأول</button></div>
</form></section>
@endif

<section class="panel"><h2>البحث والتصفية</h2><form class="filter" method="get" action="{{ route('admin.products.index') }}"><input name="q" value="{{ request('q') }}" placeholder="اسم المنتج أو كود SKU"><select name="category_id"><option value="">كل التصنيفات</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)request('category_id')===(string)$category->id)>{{ $category->name }}</option>@endforeach</select><select name="status"><option value="">كل الحالات</option><option value="active" @selected(request('status')==='active')>نشط</option><option value="inactive" @selected(request('status')==='inactive')>موقوف</option></select><button class="btn primary">بحث</button></form></section>

<div class="list-head"><div><h2>قائمة المنتجات</h2><span class="muted">{{ $products->total() }} منتج</span></div></div>
<div class="product-grid">
@forelse($products as $product)
<article class="product-card">
<div class="product-head"><div>@if($product->image_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($product->image_path) }}" alt="{{ $product->name }}" style="width:74px;height:74px;object-fit:cover;border-radius:10px;float:right;margin-left:10px">@endif<h3>{{ $product->name }}</h3><span class="muted">{{ $product->category->name }} · {{ $product->unit->name }} {{ $product->unit->symbol }}</span></div><div><span class="status {{ $product->is_active?'on':'off' }}">{{ $product->is_active?'نشط':'موقوف' }}</span><br><span class="status {{ $product->is_visible?'on':'off' }}">{{ $product->is_visible?'متاح للبيع':'غير معروض' }}</span></div></div>
@if($product->description)<p class="muted">{{ \Illuminate\Support\Str::limit($product->description,150) }}</p>@endif
@if($product->attributeGroups->isNotEmpty())<div class="chips">@foreach($product->attributeGroups as $group)<span class="chip">{{ $group->name }}</span>@endforeach</div>@endif
<div class="table-wrap"><table class="variant-table"><thead><tr><th>الكود</th><th>الصفات</th><th>سعر القائمة</th><th>الحد الأدنى</th><th>الحالة</th><th>الإجراء</th></tr></thead><tbody>
@foreach($product->variants as $variant)
<tr><td><strong>{{ $variant->sku }}</strong>@if($variant->variant_name)<div class="muted">{{ $variant->variant_name }}</div>@endif</td><td>{{ $variant->attributeValues->map(fn($value)=>$value->group->name.': '.$value->value)->implode('، ') ?: '—' }}</td><td>{{ number_format((float)$variant->list_price,2) }}</td><td>{{ number_format((float)$variant->minimum_sale_price,2) }}</td><td><span class="status {{ $variant->is_active?'on':'off' }}">{{ $variant->is_active?'نشط':'موقوف' }}</span></td><td>@if(auth()->user()->hasPermission('products.activate'))<form method="post" action="{{ route('admin.products.variants.active',[$product,$variant]) }}">@csrf @method('patch')<input type="hidden" name="is_active" value="{{ $variant->is_active?0:1 }}"><button class="btn soft">{{ $variant->is_active?'إيقاف':'تفعيل' }}</button></form>@endif</td></tr>
@endforeach
</tbody></table></div>
<div class="actions">
@if(auth()->user()->hasPermission('products.activate'))<form method="post" action="{{ route('admin.products.active',$product) }}">@csrf @method('patch')<input type="hidden" name="is_active" value="{{ $product->is_active?0:1 }}"><button class="btn {{ $product->is_active?'danger':'soft' }}">{{ $product->is_active?'إيقاف المنتج':'تفعيل المنتج' }}</button></form>@endif
@if(auth()->user()->hasPermission('products.update'))<form method="post" action="{{ route('admin.products.visible',$product) }}">@csrf @method('patch')<input type="hidden" name="is_visible" value="{{ $product->is_visible?0:1 }}"><button class="btn soft">{{ $product->is_visible?'إخفاء من البيع':'إتاحته للبيع' }}</button></form>@endif
</div>
@if(auth()->user()->hasPermission('products.update'))
<details class="details"><summary>تعديل بيانات المنتج</summary><form method="post" enctype="multipart/form-data" action="{{ route('admin.products.update',$product) }}">@csrf @method('put')
<div class="fields">
<div class="field span2"><label>الاسم</label><input name="name" value="{{ $product->name }}" required></div>
<div class="field"><label>التصنيف</label><select name="category_id" required>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($product->category_id===$category->id)>{{ $category->name }}</option>@endforeach</select></div>
<div class="field"><label>الوحدة</label><select name="unit_id" required>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected($product->unit_id===$unit->id)>{{ $unit->name }} {{ $unit->symbol }}</option>@endforeach</select></div>
<div class="field span4"><label>الوصف</label><textarea name="description">{{ $product->description }}</textarea></div><div class="field span4"><label>استبدال صورة المنتج</label><input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"></div>
<div class="field"><label>الطول (سم)</label><input name="length_cm" type="number" step="0.001" min="0" value="{{ $product->length_cm }}"></div>
<div class="field"><label>العرض (سم)</label><input name="width_cm" type="number" step="0.001" min="0" value="{{ $product->width_cm }}"></div>
<div class="field"><label>الارتفاع (سم)</label><input name="height_cm" type="number" step="0.001" min="0" value="{{ $product->height_cm }}"></div>
<div class="field"><label>الوزن (كجم)</label><input name="weight_kg" type="number" step="0.001" min="0" value="{{ $product->weight_kg }}"></div>
<div class="field span4"><label class="checkline"><input type="checkbox" name="is_visible" value="1" @checked($product->is_visible)> متاح للبيع</label></div>
</div><div class="actions"><button class="btn primary">حفظ التعديلات</button></div></form></details>
@endif
@if(auth()->user()->hasPermission('products.create') && $product->is_active && $product->attributeGroups->isNotEmpty())
<details class="details"><summary>إضافة كود / تركيبة جديدة</summary><form class="variant-form" method="post" action="{{ route('admin.products.variants.store',$product) }}">@csrf
<div class="fields">
<div class="field"><label>الكود / SKU</label><input name="sku" required maxlength="50"><button type="button" class="btn soft" onclick="generateProductCode(this.form.querySelector('[name=&quot;sku&quot;]'))">توليد باركود</button></div><div class="field"><label>اسم التركيبة</label><input name="variant_name" maxlength="180"></div><div class="field"><label>سعر القائمة</label><input name="list_price" type="number" min="0" step="0.01" required></div><div class="field"><label>أقل سعر بيع</label><input name="minimum_sale_price" type="number" min="0" step="0.01" required></div>
@foreach($product->attributeGroups as $group)<div class="field"><label>{{ $group->name }}</label><select name="attribute_value_ids[]" required><option value="">اختر</option>@foreach($group->values->where('is_active',true) as $value)<option value="{{ $value->id }}">{{ $value->value }}</option>@endforeach</select></div>@endforeach
</div><div class="actions"><button class="btn primary">إضافة الكود</button></div></form></details>
@endif
@if(auth()->user()->hasPermission('products.update'))
@foreach($product->variants as $variant)
<details class="details"><summary>تعديل الكود {{ $variant->sku }}</summary><form method="post" action="{{ route('admin.products.variants.update',[$product,$variant]) }}">@csrf @method('put')
<div class="fields">
<div class="field"><label>الكود / SKU</label><input name="sku" value="{{ $variant->sku }}" required></div><div class="field"><label>اسم التركيبة</label><input name="variant_name" value="{{ $variant->variant_name }}"></div><div class="field"><label>سعر القائمة</label><input name="list_price" type="number" step="0.01" min="0" value="{{ $variant->list_price }}" required></div><div class="field"><label>أقل سعر بيع</label><input name="minimum_sale_price" type="number" step="0.01" min="0" value="{{ $variant->minimum_sale_price }}" required></div>
@foreach($product->attributeGroups as $group)<div class="field"><label>{{ $group->name }}</label><select name="attribute_value_ids[]" required><option value="">اختر</option>@foreach($group->values->where('is_active',true) as $value)<option value="{{ $value->id }}" @selected($variant->attributeValues->contains('id',$value->id))>{{ $value->value }}</option>@endforeach</select></div>@endforeach
</div><div class="actions"><button class="btn primary">حفظ الكود</button></div></form></details>
@endforeach
@endif
</article>
@empty<div class="empty">لا توجد منتجات مسجلة بعد.</div>@endforelse
</div>
<div style="margin-top:18px">{{ $products->links() }}</div>
</main>
<script>
document.querySelectorAll('.group-toggle').forEach(function(toggle){
    const select=document.getElementById(toggle.dataset.target);
    const sync=function(){select.disabled=!toggle.checked;select.required=toggle.checked;if(!toggle.checked)select.value='';};
    toggle.addEventListener('change',sync);sync();
});
async function generateProductCode(input){
    try{
        const response=await fetch("{{ route('admin.products.generate-codes') }}",{
            method:"POST",
            headers:{"X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content,"Accept":"application/json","Content-Type":"application/x-www-form-urlencoded;charset=UTF-8"},
            body:"count=1"
        });
        const result=await response.json();
        if(!response.ok) throw new Error(result.message||"تعذر توليد الكود");
        input.value=result.codes[0];
    }catch(error){alert(error.message);}
}
</script>
@endsection
