@extends('layouts.app')

@section('title', 'المنتجات')
@section('page-title', 'المنتجات')
@section('breadcrumb', 'المنتجات')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <div class="mb-2">
        <p class="text-muted mb-0">إدارة المنتجات وأكواد البيع والصفات والأسعار وبيانات الشحن.</p>
    </div>
    <div class="mb-2">
        @if(auth()->user()->hasPermission('product_categories.view'))<a class="btn btn-default btn-sm" href="{{ route('admin.catalog.categories') }}">التصنيفات</a>@endif
        @if(auth()->user()->hasPermission('units.view'))<a class="btn btn-default btn-sm" href="{{ route('admin.catalog.units') }}">وحدات القياس</a>@endif
        @if(auth()->user()->hasPermission('product_attributes.view'))<a class="btn btn-default btn-sm" href="{{ route('admin.product-attributes.index') }}">صفات المنتجات</a>@endif
    </div>
</div>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><strong>راجع البيانات:</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="row">
    <div class="col-lg-3 col-6"><div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-boxes"></i></span><div class="info-box-content"><span class="info-box-text">كل المنتجات</span><span class="info-box-number">{{ number_format($stats['total']) }}</span></div></div></div>
    <div class="col-lg-3 col-6"><div class="info-box"><span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span><div class="info-box-content"><span class="info-box-text">منتجات نشطة</span><span class="info-box-number">{{ number_format($stats['active']) }}</span></div></div></div>
    <div class="col-lg-3 col-6"><div class="info-box"><span class="info-box-icon bg-primary"><i class="fas fa-store"></i></span><div class="info-box-content"><span class="info-box-text">متاحة للبيع</span><span class="info-box-number">{{ number_format($stats['visible']) }}</span></div></div></div>
    <div class="col-lg-3 col-6"><div class="info-box"><span class="info-box-icon bg-secondary"><i class="fas fa-barcode"></i></span><div class="info-box-content"><span class="info-box-text">أكواد نشطة</span><span class="info-box-number">{{ number_format($stats['variants']) }}</span></div></div></div>
</div>

@if(auth()->user()->hasPermission('products.create'))
<div class="card card-outline card-primary">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-plus-circle ml-1"></i> إضافة منتج</h3></div>
    <form method="post" enctype="multipart/form-data" action="{{ route('admin.products.store') }}">
        @csrf
        <div class="card-body">
            <p class="text-muted">كل منتج يحتاج كود بيع واحدًا على الأقل. يمكن إضافة أكواد أخرى بعد الحفظ، سواء للمنتج ذي الصفات أو المنتج ذي الكود الواحد.</p>
            <div class="form-row">
                <div class="form-group col-lg-6"><label>اسم المنتج</label><input class="form-control" name="name" value="{{ old('name') }}" required maxlength="180"></div>
                <div class="form-group col-lg-3 col-md-6"><label>التصنيف</label><select class="form-control" name="category_id" required><option value="">اختر التصنيف</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('category_id')===(string)$category->id)>{{ $category->name }}</option>@endforeach</select></div>
                <div class="form-group col-lg-3 col-md-6"><label>وحدة القياس</label><select class="form-control" name="unit_id" required><option value="">اختر الوحدة</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected((string)old('unit_id')===(string)$unit->id)>{{ $unit->name }} {{ $unit->symbol }}</option>@endforeach</select></div>
                <div class="form-group col-md-6"><label>وصف المنتج</label><textarea class="form-control" name="description" rows="3">{{ old('description') }}</textarea></div>
                <div class="form-group col-md-6"><label>صورة المنتج</label><div class="custom-file"><input class="custom-file-input" type="file" name="image" accept=".jpg,.jpeg,.png,.webp"><label class="custom-file-label">اختر صورة</label></div></div>
            </div>
            <div class="border rounded p-3 mb-3">
                <h6 class="font-weight-bold mb-3">بيانات الشحن والأبعاد</h6>
                <div class="form-row">
                    <div class="form-group col-md-3"><label>الطول (سم)</label><input class="form-control" name="length_cm" type="number" step="0.001" min="0" value="{{ old('length_cm') }}"></div>
                    <div class="form-group col-md-3"><label>العرض (سم)</label><input class="form-control" name="width_cm" type="number" step="0.001" min="0" value="{{ old('width_cm') }}"></div>
                    <div class="form-group col-md-3"><label>الارتفاع (سم)</label><input class="form-control" name="height_cm" type="number" step="0.001" min="0" value="{{ old('height_cm') }}"></div>
                    <div class="form-group col-md-3"><label>الوزن (كجم)</label><input class="form-control" name="weight_kg" type="number" step="0.001" min="0" value="{{ old('weight_kg') }}"></div>
                </div>
            </div>
            <div class="form-group">
                <div class="custom-control custom-checkbox"><input class="custom-control-input" id="product-visible" type="checkbox" name="is_visible" value="1" @checked(old('is_visible',1))><label class="custom-control-label" for="product-visible">متاح للبيع</label></div>
                <div class="custom-control custom-checkbox mt-2"><input class="custom-control-input" id="product-preorder" type="checkbox" name="allow_preorder" value="1" @checked(old('allow_preorder'))><label class="custom-control-label" for="product-preorder">السماح بالحجز المسبق عند نفاد المخزون</label></div>
            </div>
            @if($attributeGroups->isNotEmpty())
            <div class="border rounded p-3 mb-3">
                <h6 class="font-weight-bold">صفات المنتج</h6>
                <p class="text-muted small">اختر مجموعات الصفات المستخدمة، ثم حدد قيمة كل مجموعة للكود الأول.</p>
                <div class="row">
                    @foreach($attributeGroups as $group)
                    <div class="col-lg-4 col-md-6 mb-2">
                        <div class="custom-control custom-checkbox mb-2"><input class="custom-control-input group-toggle" id="group-toggle-{{ $group->id }}" type="checkbox" name="attribute_group_ids[]" value="{{ $group->id }}" data-target="initial-group-{{ $group->id }}" @checked(in_array((string)$group->id,array_map('strval',old('attribute_group_ids',[])),true))><label class="custom-control-label" for="group-toggle-{{ $group->id }}">{{ $group->name }}</label></div>
                        <select class="form-control form-control-sm" id="initial-group-{{ $group->id }}" name="initial_variant[attribute_value_ids][]" disabled><option value="">اختر {{ $group->name }}</option>@foreach($group->values as $value)<option value="{{ $value->id }}">{{ $value->value }}</option>@endforeach</select>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
            <div class="border rounded p-3">
                <h6 class="font-weight-bold mb-3">كود البيع الأول وأسعاره</h6>
                <div class="form-row">
                    <div class="form-group col-lg-3 col-md-6"><label>كود / SKU</label><div class="input-group"><input class="form-control" id="initialSku" name="initial_variant[sku]" value="{{ old('initial_variant.sku') }}" required maxlength="50"><div class="input-group-append"><button type="button" class="btn btn-info" onclick="generateProductCode(document.getElementById('initialSku'))"><i class="fas fa-barcode"></i> توليد</button></div></div></div>
                    <div class="form-group col-lg-3 col-md-6"><label>اسم التركيبة (اختياري)</label><input class="form-control" name="initial_variant[variant_name]" value="{{ old('initial_variant.variant_name') }}" maxlength="180"></div>
                    <div class="form-group col-lg-3 col-md-6"><label>سعر القائمة</label><input class="form-control" name="initial_variant[list_price]" type="number" step="0.01" min="0" value="{{ old('initial_variant.list_price') }}" required></div>
                    <div class="form-group col-lg-3 col-md-6"><label>أقل سعر بيع مسموح</label><input class="form-control" name="initial_variant[minimum_sale_price]" type="number" step="0.01" min="0" value="{{ old('initial_variant.minimum_sale_price') }}" required></div>
                </div>
            </div>
            <div class="callout callout-info mt-3 mb-0"><p class="mb-0">تكلفة المخزون وكمياته والحجز المسبق تُدار في وحدات المخزون والمشتريات والحجوزات، ولا تُسجل من تعريف المنتج. أسعار البيع هنا تحدد سعر القائمة والحد الأدنى المسموح به.</p></div>
        </div>
        <div class="card-footer"><button class="btn btn-primary"><i class="fas fa-save ml-1"></i> حفظ المنتج والكود الأول</button></div>
    </form>
</div>
@endif

<div class="card card-outline card-secondary">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-search ml-1"></i> البحث والتصفية</h3></div>
    <form method="get" action="{{ route('admin.products.index') }}">
        <div class="card-body">
            <div class="form-row align-items-end">
                <div class="form-group col-lg-5 col-md-6"><label>اسم المنتج أو كود SKU</label><input class="form-control" name="q" value="{{ request('q') }}" placeholder="اكتب اسم المنتج أو الكود"></div>
                <div class="form-group col-lg-3 col-md-6"><label>التصنيف</label><select class="form-control" name="category_id"><option value="">كل التصنيفات</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)request('category_id')===(string)$category->id)>{{ $category->name }}</option>@endforeach</select></div>
                <div class="form-group col-lg-2 col-md-6"><label>الحالة</label><select class="form-control" name="status"><option value="">كل الحالات</option><option value="active" @selected(request('status')==='active')>نشط</option><option value="inactive" @selected(request('status')==='inactive')>موقوف</option></select></div>
                <div class="form-group col-lg-2 col-md-6"><button class="btn btn-primary btn-block"><i class="fas fa-filter ml-1"></i> بحث</button></div>
            </div>
        </div>
    </form>
</div>

<div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
    <h3 class="h5 mb-2">قائمة المنتجات</h3>
    <span class="text-muted small mb-2">{{ number_format($products->total()) }} منتج</span>
</div>

@forelse($products as $product)
<div class="card card-outline {{ $product->is_active ? 'card-primary' : 'card-secondary' }}">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div class="d-flex align-items-center mb-2">
                @if($product->image_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($product->image_path) }}" alt="{{ $product->name }}" class="img-thumbnail ml-2" style="width:68px;height:68px;object-fit:cover">@endif
                <div><h3 class="card-title font-weight-bold">{{ $product->name }}</h3><div class="text-muted small mt-1">{{ $product->category->name }} · {{ $product->unit->name }} {{ $product->unit->symbol }}</div></div>
            </div>
            <div class="mb-2"><span class="badge {{ $product->is_active?'badge-success':'badge-secondary' }}">{{ $product->is_active?'نشط':'موقوف' }}</span> <span class="badge {{ $product->is_visible?'badge-info':'badge-light' }}">{{ $product->is_visible?'متاح للبيع':'غير معروض' }}</span></div>
        </div>
    </div>
    <div class="card-body">
        @if($product->description)<p class="text-muted">{{ \Illuminate\Support\Str::limit($product->description,180) }}</p>@endif
        @if($product->attributeGroups->isNotEmpty())<div class="mb-3">@foreach($product->attributeGroups as $group)<span class="badge badge-light border ml-1">{{ $group->name }}</span>@endforeach</div>@endif
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped">
                <thead><tr><th>الكود</th><th>الصفات</th><th>سعر القائمة</th><th>الحد الأدنى</th><th>الحالة</th><th>الإجراء</th></tr></thead>
                <tbody>
                @foreach($product->variants as $variant)
                    <tr>
                        <td><strong>{{ $variant->sku }}</strong>@if($variant->variant_name)<div class="text-muted small">{{ $variant->variant_name }}</div>@endif</td>
                        <td>{{ $variant->attributeValues->map(fn($value)=>$value->group->name.': '.$value->value)->implode('، ') ?: '—' }}</td>
                        <td>{{ number_format((float)$variant->list_price,2) }}</td>
                        <td>{{ number_format((float)$variant->minimum_sale_price,2) }}</td>
                        <td><span class="badge {{ $variant->is_active?'badge-success':'badge-secondary' }}">{{ $variant->is_active?'نشط':'موقوف' }}</span></td>
                        <td>
                            @if(auth()->user()->hasPermission('products.activate'))
                            <form method="post" action="{{ route('admin.products.variants.active',[$product,$variant]) }}">@csrf @method('patch')<input type="hidden" name="is_active" value="{{ $variant->is_active?0:1 }}"><button class="btn btn-xs {{ $variant->is_active?'btn-outline-danger':'btn-outline-success' }}">{{ $variant->is_active?'إيقاف':'تفعيل' }}</button></form>
                            @else<span class="text-muted">—</span>@endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-flex flex-wrap mb-3">
            @if(auth()->user()->hasPermission('products.activate'))
            <form method="post" action="{{ route('admin.products.active',$product) }}" class="ml-2 mb-2">@csrf @method('patch')<input type="hidden" name="is_active" value="{{ $product->is_active?0:1 }}"><button class="btn btn-sm {{ $product->is_active?'btn-danger':'btn-success' }}">{{ $product->is_active?'إيقاف المنتج':'تفعيل المنتج' }}</button></form>
            @endif
            @if(auth()->user()->hasPermission('products.update'))
            <form method="post" action="{{ route('admin.products.visible',$product) }}" class="mb-2">@csrf @method('patch')<input type="hidden" name="is_visible" value="{{ $product->is_visible?0:1 }}"><button class="btn btn-sm btn-outline-primary">{{ $product->is_visible?'إخفاء من البيع':'إتاحته للبيع' }}</button></form>
            @endif
        </div>

        @if(auth()->user()->hasPermission('products.update'))
        <details class="border rounded p-3 mb-3">
            <summary class="font-weight-bold text-primary">تعديل بيانات المنتج</summary>
            <form class="mt-3" method="post" enctype="multipart/form-data" action="{{ route('admin.products.update',$product) }}">@csrf @method('put')
                <div class="form-row">
                    <div class="form-group col-lg-6"><label>الاسم</label><input class="form-control" name="name" value="{{ $product->name }}" required></div>
                    <div class="form-group col-lg-3 col-md-6"><label>التصنيف</label><select class="form-control" name="category_id" required>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($product->category_id===$category->id)>{{ $category->name }}</option>@endforeach</select></div>
                    <div class="form-group col-lg-3 col-md-6"><label>الوحدة</label><select class="form-control" name="unit_id" required>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected($product->unit_id===$unit->id)>{{ $unit->name }} {{ $unit->symbol }}</option>@endforeach</select></div>
                    <div class="form-group col-md-6"><label>الوصف</label><textarea class="form-control" name="description" rows="3">{{ $product->description }}</textarea></div>
                    <div class="form-group col-md-6"><label>استبدال صورة المنتج</label><input class="form-control-file" type="file" name="image" accept=".jpg,.jpeg,.png,.webp"></div>
                    <div class="form-group col-md-3"><label>الطول (سم)</label><input class="form-control" name="length_cm" type="number" step="0.001" min="0" value="{{ $product->length_cm }}"></div>
                    <div class="form-group col-md-3"><label>العرض (سم)</label><input class="form-control" name="width_cm" type="number" step="0.001" min="0" value="{{ $product->width_cm }}"></div>
                    <div class="form-group col-md-3"><label>الارتفاع (سم)</label><input class="form-control" name="height_cm" type="number" step="0.001" min="0" value="{{ $product->height_cm }}"></div>
                    <div class="form-group col-md-3"><label>الوزن (كجم)</label><input class="form-control" name="weight_kg" type="number" step="0.001" min="0" value="{{ $product->weight_kg }}"></div>
                    <div class="form-group col-12"><div class="custom-control custom-checkbox"><input class="custom-control-input" id="visible-{{ $product->id }}" type="checkbox" name="is_visible" value="1" @checked($product->is_visible)><label class="custom-control-label" for="visible-{{ $product->id }}">متاح للبيع</label></div><div class="custom-control custom-checkbox mt-2"><input class="custom-control-input" id="preorder-{{ $product->id }}" type="checkbox" name="allow_preorder" value="1" @checked($product->allow_preorder)><label class="custom-control-label" for="preorder-{{ $product->id }}">السماح بالحجز المسبق عند نفاد المخزون</label></div></div>
                </div>
                <button class="btn btn-primary btn-sm">حفظ التعديلات</button>
            </form>
        </details>
        @endif

        @if(auth()->user()->hasPermission('products.create') && $product->is_active)
        <details class="border rounded p-3 mb-3">
            <summary class="font-weight-bold text-success">إضافة كود / تركيبة جديدة</summary>
            <form class="mt-3" method="post" action="{{ route('admin.products.variants.store',$product) }}">@csrf
                <div class="form-row">
                    <div class="form-group col-lg-3 col-md-6"><label>الكود / SKU</label><div class="input-group"><input class="form-control" name="sku" required maxlength="50"><div class="input-group-append"><button type="button" class="btn btn-info" onclick="generateProductCode(this.form.querySelector('[name=&quot;sku&quot;]'))">توليد</button></div></div></div>
                    <div class="form-group col-lg-3 col-md-6"><label>اسم التركيبة</label><input class="form-control" name="variant_name" maxlength="180"></div>
                    <div class="form-group col-lg-3 col-md-6"><label>سعر القائمة</label><input class="form-control" name="list_price" type="number" min="0" step="0.01" required></div>
                    <div class="form-group col-lg-3 col-md-6"><label>أقل سعر بيع</label><input class="form-control" name="minimum_sale_price" type="number" min="0" step="0.01" required></div>
                    @foreach($product->attributeGroups as $group)
                    <div class="form-group col-lg-3 col-md-6"><label>{{ $group->name }}</label><select class="form-control" name="attribute_value_ids[]" required><option value="">اختر</option>@foreach($group->values->where('is_active',true) as $value)<option value="{{ $value->id }}">{{ $value->value }}</option>@endforeach</select></div>
                    @endforeach
                </div>
                <button class="btn btn-success btn-sm">إضافة الكود</button>
            </form>
        </details>
        @endif

        @if(auth()->user()->hasPermission('products.update'))
        @foreach($product->variants as $variant)
        <details class="border rounded p-3 mb-2">
            <summary class="font-weight-bold">تعديل الكود {{ $variant->sku }}</summary>
            <form class="mt-3" method="post" action="{{ route('admin.products.variants.update',[$product,$variant]) }}">@csrf @method('put')
                <div class="form-row">
                    <div class="form-group col-lg-3 col-md-6"><label>الكود / SKU</label><input class="form-control" name="sku" value="{{ $variant->sku }}" required></div>
                    <div class="form-group col-lg-3 col-md-6"><label>اسم التركيبة</label><input class="form-control" name="variant_name" value="{{ $variant->variant_name }}"></div>
                    <div class="form-group col-lg-3 col-md-6"><label>سعر القائمة</label><input class="form-control" name="list_price" type="number" step="0.01" min="0" value="{{ $variant->list_price }}" required></div>
                    <div class="form-group col-lg-3 col-md-6"><label>أقل سعر بيع</label><input class="form-control" name="minimum_sale_price" type="number" step="0.01" min="0" value="{{ $variant->minimum_sale_price }}" required></div>
                    @foreach($product->attributeGroups as $group)
                    <div class="form-group col-lg-3 col-md-6"><label>{{ $group->name }}</label><select class="form-control" name="attribute_value_ids[]" required><option value="">اختر</option>@foreach($group->values->where('is_active',true) as $value)<option value="{{ $value->id }}" @selected($variant->attributeValues->contains('id',$value->id))>{{ $value->value }}</option>@endforeach</select></div>
                    @endforeach
                </div>
                <button class="btn btn-primary btn-sm">حفظ الكود</button>
            </form>
        </details>
        @endforeach
        @endif
    </div>
</div>
@empty
<div class="card"><div class="card-body text-center text-muted">لا توجد منتجات مسجلة بعد.</div></div>
@endforelse

<div class="mt-3">{{ $products->links() }}</div>

<script>
document.querySelectorAll('.group-toggle').forEach(function(toggle){
    const select=document.getElementById(toggle.dataset.target);
    const sync=function(){select.disabled=!toggle.checked;select.required=toggle.checked;if(!toggle.checked)select.value='';};
    toggle.addEventListener('change',sync);sync();
});
document.querySelectorAll('.custom-file-input').forEach(function(input){
    input.addEventListener('change',function(){if(this.files.length)this.nextElementSibling.textContent=this.files[0].name;});
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
