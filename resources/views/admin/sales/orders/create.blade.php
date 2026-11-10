@extends('layouts.app')

@section('title', 'إنشاء أمر بيع')
@section('page-title', 'إنشاء أمر بيع')
@section('breadcrumb', 'إنشاء أمر بيع')

@push('styles')
<style>
    .order-step{display:none}
    .order-step.active{display:block}
    .order-totals .description-header{font-size:1.15rem;font-weight:700}
    .order-totals .description-text{font-size:.85rem}
    .order-actions{display:flex;gap:.5rem;justify-content:flex-start;flex-wrap:wrap}
    @media(max-width:575.98px){.order-actions .btn{flex:1 1 auto}}
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <p class="text-muted mb-2">أدخل بيانات العميل أولًا، ثم أضف المنتجات وراجع الأمر قبل حفظه كمسودة.</p>
    <a class="btn btn-default btn-sm mb-2" href="{{ route('admin.sales.orders.index') }}"><i class="fas fa-list ml-1"></i> قائمة الأوامر</a>
</div>

@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('status') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
    </div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert">
        <strong>راجع البيانات التالية:</strong>
        <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<form method="post" action="{{ route('admin.sales.orders.store') }}" id="order-form">
    @csrf
    <section class="card card-outline card-primary order-step active" id="step-customer">
        <div class="card-header"><h3 class="card-title">1. بيانات العميل</h3></div>
        <div class="card-body">
            <p class="text-muted">اختر العميل وحدد عنوان التوصيل. رسوم الشحن مستقلة على مستوى أمر البيع.</p>
            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="customer_id">العميل</label>
                    <select class="form-control select2" name="customer_id" id="customer_id" required>
                        <option value="">اختر العميل</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" data-address="{{ e($customer->address ?? '') }}">{{ $customer->name }} — {{ $customer->phones->firstWhere('is_primary', true)?->number ?: ($customer->mobile ?: $customer->phone) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-lg-3">
                    <label for="shipping_fee">رسوم الشحن</label>
                    <input class="form-control" type="number" name="shipping_fee" id="shipping_fee" min="0" step="0.01" value="0">
                </div>
                <div class="form-group col-md-5">
                    <label for="customer-address-select">العناوين المحفوظة للعميل</label>
                    <select class="form-control" id="customer-address-select" disabled><option value="">اختر العميل أولًا</option></select>
                </div>
                <div class="form-group col-md-7">
                    <label for="shipping_address">عنوان التوصيل (يمكن تعديله لهذا الطلب)</label>
                    <textarea class="form-control" name="shipping_address" id="shipping_address" rows="2" maxlength="2000"></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer order-actions">
            <button type="button" class="btn btn-primary" id="to-products">التالي: اختيار المنتجات <i class="fas fa-arrow-left mr-1"></i></button>
        </div>
    </section>

    <section class="card card-outline card-primary order-step" id="step-products">
        <div class="card-header"><h3 class="card-title">2. اختيار المنتجات</h3></div>
        <div class="card-body">
            <p class="text-muted">اختر كود المنتج والكمية وسعر البيع. السعر لا يقل عن الحد الأدنى ولا يزيد عن سعر القائمة.</p>
            <div class="form-row align-items-end">
                <div class="form-group col-lg-6">
                    <label for="variant-select">المنتج / الكود</label>
                    <select class="form-control select2" id="variant-select">
                        <option value="">اختر كود المنتج</option>
                        @foreach($variants as $variant)
                            <option value="{{ $variant->id }}" data-name="{{ e($variant->product->name) }}" data-sku="{{ e($variant->sku) }}" data-list="{{ $variant->list_price }}" data-min="{{ $variant->minimum_sale_price }}">
                                {{ $variant->product->name }} — {{ $variant->variant_name ?: $variant->sku }}
                                @foreach($variant->attributeValues as $value) / {{ $value->value }} @endforeach
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-3 col-lg-2">
                    <label for="item-quantity">الكمية</label>
                    <input class="form-control" id="item-quantity" type="number" min="0.001" step="0.001" value="1">
                </div>
                <div class="form-group col-md-3 col-lg-2">
                    <label for="item-price">سعر البيع</label>
                    <input class="form-control" id="item-price" type="number" min="0" step="0.01">
                </div>
                <div class="form-group col-lg-2">
                    <button type="button" class="btn btn-primary btn-block" id="add-item"><i class="fas fa-plus ml-1"></i> إضافة المنتج</button>
                </div>
            </div>
            <div class="callout callout-info mb-0" id="price-hint">اختر كود المنتج لعرض الحد الأدنى وسعر القائمة.</div>
        </div>
        <div class="card-footer order-actions">
            <button type="button" class="btn btn-default" id="back-customer"><i class="fas fa-arrow-right ml-1"></i> رجوع للعميل</button>
            <button type="button" class="btn btn-primary" id="to-review">مراجعة الطلب <i class="fas fa-arrow-left mr-1"></i></button>
        </div>
    </section>

    <section class="card card-outline card-primary order-step" id="step-review">
        <div class="card-header"><h3 class="card-title">3. مراجعة الطلب</h3></div>
        <div class="card-body">
            <p class="text-muted">راجع تفاصيل البنود والأسعار والخصومات ورسوم الشحن. يمكنك تعديل أي بند أو حذفه قبل الحفظ.</p>
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-striped">
                    <thead><tr><th>المنتج</th><th>الكمية</th><th>سعر القائمة</th><th>سعر البيع</th><th>الخصم</th><th>الإجمالي</th><th>الإجراءات</th></tr></thead>
                    <tbody id="review-items"></tbody>
                </table>
            </div>
            <div class="row order-totals">
                <div class="col-md-3 col-6"><div class="description-block border-left"><span class="description-text">قيمة البيع</span><h5 class="description-header" id="subtotal">0.00</h5></div></div>
                <div class="col-md-3 col-6"><div class="description-block border-left"><span class="description-text">إجمالي الخصم</span><h5 class="description-header" id="discount-total">0.00</h5></div></div>
                <div class="col-md-3 col-6"><div class="description-block border-left"><span class="description-text">الشحن</span><h5 class="description-header" id="shipping-total">0.00</h5></div></div>
                <div class="col-md-3 col-6"><div class="description-block"><span class="description-text">الإجمالي النهائي</span><h5 class="description-header text-primary" id="grand-total">0.00</h5></div></div>
            </div>
            <div class="form-group mt-3">
                <label for="order-notes">ملاحظات</label>
                <textarea class="form-control" id="order-notes" name="notes" rows="2" maxlength="5000"></textarea>
            </div>
        </div>
        <div class="card-footer order-actions">
            <button type="button" class="btn btn-default" id="back-products"><i class="fas fa-arrow-right ml-1"></i> رجوع للمنتجات</button>
            <button type="submit" class="btn btn-success" id="save-order"><i class="fas fa-save ml-1"></i> حفظ أمر البيع كمسودة</button>
        </div>
    </section>
</form>
@endsection

@push('scripts')
<script>
(() => {
    const $ = id => document.getElementById(id);
    const customerAddresses = @json($customers->mapWithKeys(fn ($customer) => [$customer->id => $customer->addresses->map(fn ($address) => ['title' => $address->title, 'address' => $address->address, 'city' => $address->city?->name_ar])->values()])->all());
    const renderCustomerAddresses = () => {
        const select = $('customer-address-select');
        const selectedCustomer = $('customer_id').selectedOptions[0];
        const addresses = customerAddresses[$('customer_id').value] || [];
        select.innerHTML = '';
        if (addresses.length) {
            select.disabled = false;
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'اختر عنوان التوصيل';
            select.appendChild(placeholder);
            addresses.forEach((address, index) => {
                const option = document.createElement('option');
                option.value = address.address;
                option.textContent = address.title + (address.city ? ' — ' + address.city : '');
                select.appendChild(option);
                if (index === 0) option.selected = true;
            });
            $('shipping_address').value = addresses[0].address;
        } else {
            select.disabled = true;
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'لا توجد عناوين محفوظة';
            select.appendChild(option);
            $('shipping_address').value = selectedCustomer?.dataset.address || '';
        }
    };
    $('customer_id').addEventListener('change', renderCustomerAddresses);
    $('customer-address-select').addEventListener('change', function () {
        if (this.value) $('shipping_address').value = this.value;
    });
    renderCustomerAddresses();


    const steps = ['step-customer', 'step-products', 'step-review'];
    const items = [];
    const money = value => (Number(value) || 0).toFixed(2);
    const show = id => steps.forEach(step => $(step).classList.toggle('active', step === id));
    const selected = () => $('variant-select').selectedOptions[0];

    const renderHint = () => {
        const option = selected();
        if (!option || !option.value) {
            $('price-hint').textContent = 'اختر كود المنتج لعرض الحد الأدنى وسعر القائمة.';
            $('item-price').value = '';
            return;
        }
        $('item-price').value = option.dataset.list;
        $('price-hint').textContent = 'الحد الأدنى: ' + money(option.dataset.min) + ' — سعر القائمة: ' + money(option.dataset.list);
    };

    const renderReview = () => {
        const body = $('review-items');
        body.innerHTML = '';
        let subtotal = 0;
        let discountTotal = 0;

        items.forEach((item, index) => {
            const discount = (item.list - item.price) * item.quantity;
            const line = item.price * item.quantity;
            subtotal += line;
            discountTotal += discount;

            const tr = document.createElement('tr');
            tr.innerHTML = '<td><strong></strong><br><small class="text-muted"></small></td><td></td><td></td><td></td><td></td><td></td><td><div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-primary edit-item">تعديل</button><button type="button" class="btn btn-outline-danger remove-item">حذف</button></div></td>';
            tr.children[0].querySelector('strong').textContent = item.name;
            tr.children[0].querySelector('small').textContent = item.sku;
            [item.quantity, money(item.list), money(item.price), money(discount), money(line)].forEach((value, cell) => {
                tr.children[cell + 1].textContent = value;
            });

            tr.querySelector('.edit-item').addEventListener('click', async () => {
                const result = await Swal.fire({
                    title: 'تعديل بند الطلب',
                    html: '<div class="text-right"><label for="swal-item-quantity">الكمية</label><input id="swal-item-quantity" class="swal2-input" type="number" min="0.001" step="0.001" value="' + item.quantity + '"><label for="swal-item-price">سعر البيع</label><input id="swal-item-price" class="swal2-input" type="number" min="0" step="0.01" value="' + item.price + '"><small class="text-muted d-block">السعر المسموح من ' + money(item.min) + ' إلى ' + money(item.list) + '</small></div>',
                    focusConfirm: false,
                    showCancelButton: true,
                    confirmButtonText: 'حفظ التعديل',
                    cancelButtonText: 'إلغاء',
                    preConfirm: () => {
                        const quantity = Number($('swal-item-quantity').value);
                        const price = Number($('swal-item-price').value);
                        if (!(quantity > 0) || !Number.isFinite(quantity)) {
                            Swal.showValidationMessage('أدخل كمية صحيحة.');
                            return false;
                        }
                        if (!Number.isFinite(price) || price < item.min || price > item.list) {
                            Swal.showValidationMessage('سعر البيع خارج النطاق المسموح.');
                            return false;
                        }
                        return { quantity, price };
                    }
                });
                if (result.isConfirmed) {
                    item.quantity = result.value.quantity;
                    item.price = result.value.price;
                    renderReview();
                }
            });

            tr.querySelector('.remove-item').addEventListener('click', () => {
                items.splice(index, 1);
                renderReview();
                if (!items.length) show('step-products');
            });
            body.appendChild(tr);
        });

        const shipping = Number($('shipping_fee').value) || 0;
        $('subtotal').textContent = money(subtotal);
        $('discount-total').textContent = money(discountTotal);
        $('shipping-total').textContent = money(shipping);
        $('grand-total').textContent = money(subtotal + shipping);

        document.querySelectorAll('#order-form input[name^="items["]').forEach(input => input.remove());
        items.forEach((item, index) => {
            [['product_variant_id', item.id], ['quantity', item.quantity], ['sale_price', item.price]].forEach(([key, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'items[' + index + '][' + key + ']';
                input.value = value;
                $('order-form').appendChild(input);
            });
        });
    };

    $('customer_id').addEventListener('change', () => {
        const option = $('customer_id').selectedOptions[0];
        $('shipping_address').value = option?.dataset.address || '';
    });
    $('variant-select').addEventListener('change', renderHint);
    if (window.jQuery && jQuery.fn.select2) {
        $('.select2').select2({ dir: 'rtl', width: '100%' });
    }

    $('to-products').addEventListener('click', () => {
        if (!$('customer_id').value) {
            Swal.fire({ icon: 'warning', text: 'اختر العميل أولًا.' });
            return;
        }
        show('step-products');
    });
    $('back-customer').addEventListener('click', () => show('step-customer'));
    $('back-products').addEventListener('click', () => show('step-products'));

    $('add-item').addEventListener('click', () => {
        const option = selected();
        const quantity = Number($('item-quantity').value);
        const price = Number($('item-price').value);
        if (!option?.value) {
            Swal.fire({ icon: 'warning', text: 'اختر المنتج أولًا.' });
            return;
        }
        if (!(quantity > 0) || !Number.isFinite(quantity)) {
            Swal.fire({ icon: 'warning', text: 'أدخل كمية صحيحة.' });
            return;
        }
        if (!Number.isFinite(price) || price < Number(option.dataset.min) || price > Number(option.dataset.list)) {
            Swal.fire({ icon: 'warning', text: 'سعر البيع خارج النطاق المسموح.' });
            return;
        }

        const existing = items.find(item => item.id === option.value);
        if (existing) {
            existing.quantity += quantity;
            existing.price = price;
        } else {
            items.push({
                id: option.value,
                name: option.dataset.name,
                sku: option.dataset.sku,
                list: Number(option.dataset.list),
                min: Number(option.dataset.min),
                quantity,
                price
            });
        }

        $('variant-select').value = '';
        if (window.jQuery && jQuery.fn.select2) jQuery('#variant-select').val(null).trigger('change');
        $('item-quantity').value = '1';
        renderHint();
        $('price-hint').textContent = 'تمت إضافة المنتج. يمكنك اختيار منتج آخر.';
    });

    $('to-review').addEventListener('click', () => {
        if (!items.length) {
            Swal.fire({ icon: 'warning', text: 'أضف منتجًا واحدًا على الأقل.' });
            return;
        }
        renderReview();
        show('step-review');
    });

    $('order-form').addEventListener('submit', event => {
        if (!items.length) {
            event.preventDefault();
            Swal.fire({ icon: 'warning', text: 'أضف منتجًا واحدًا على الأقل.' });
        }
    });
})();
</script>
@endpush
