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
    <input type="hidden" name="shipping_address_id" id="shipping_address_id">
    <input type="hidden" name="shipping_phone_id" id="shipping_phone_id">
    <section class="card card-outline card-primary order-step active" id="step-customer">
        <div class="card-header"><h3 class="card-title">1. بيانات العميل</h3></div>
        <div class="card-body">
            <p class="text-muted">اختر العميل وحدد عنوان التوصيل. رسوم الشحن مستقلة على مستوى أمر البيع.</p>
            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="customer-search">بحث العميل بالاسم أو رقم الهاتف</label>
                    <input class="form-control mb-2" type="search" id="customer-search" autocomplete="off" placeholder="اكتب اسم العميل أو رقم الهاتف">
                    <label for="customer_id">العميل</label>
                    <select class="form-control select2" name="customer_id" id="customer_id" required>
                        <option value="">اختر العميل</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" data-name="{{ e($customer->name) }}" data-entity-type="{{ $customer->entity_type }}" data-mobile="{{ e($customer->mobile ?? $customer->phone ?? '') }}" data-email="{{ e($customer->email ?? '') }}" data-address="{{ e($customer->address ?? '') }}" data-search="{{ e(mb_strtolower($customer->name.' '.$customer->phone.' '.$customer->mobile.' '.$customer->phones->pluck('number')->implode(' '))) }}">{{ $customer->name }} — {{ $customer->phones->firstWhere('is_primary', true)?->number ?: ($customer->mobile ?: $customer->phone) }}</option>
                        @endforeach
                    </select>
                    <div class="form-group mt-2 mb-2">
                        <label for="customer-phone-select">رقم هاتف التواصل والتوصيل</label>
                        <select class="form-control" id="customer-phone-select" disabled>
                            <option value="">اختر العميل أولًا</option>
                        </select>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        @if(auth()->user()?->hasPermission('parties.create') && auth()->user()?->hasPermission('structure.scope.manage_all'))
                            <button type="button" class="btn btn-sm btn-outline-primary" id="add-customer"><i class="fas fa-user-plus ml-1"></i> إضافة عميل</button>
                        @endif
                        @if(auth()->user()?->hasPermission('parties.update') && auth()->user()?->hasPermission('structure.scope.manage_all'))
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="edit-customer" disabled><i class="fas fa-user-edit ml-1"></i> تعديل بيانات العميل</button>
                        @endif
                    </div>
                    <div id="customer-phone-data" class="d-none">
                        @foreach($customers as $customer)
                            <div data-customer-id="{{ $customer->id }}">
                                @foreach($customer->phones as $phone)
                                    <span data-phone data-id="{{ $phone->id }}" data-number="{{ $phone->number }}" data-type="{{ $phone->type }}" data-primary="{{ $phone->is_primary ? '1' : '0' }}" data-whatsapp="{{ $phone->is_whatsapp ? '1' : '0' }}"></span>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <div id="customer-order-history" class="mt-3" aria-live="polite">
                        <div class="text-muted small">اختر العميل لعرض آخر أوامره السابقة.</div>
                    </div>
                    <div id="customer-address-data" class="d-none">
                        @foreach($customers as $customer)
                            <div data-customer-id="{{ $customer->id }}">
                                @foreach($customer->addresses as $address)
                                    <span data-address data-id="{{ $address->id }}" data-title="{{ $address->title }}" data-address-text="{{ $address->address }}" data-city="{{ $address->city?->name_ar }}" data-city-id="{{ $address->city_id }}" data-zone="{{ $address->city?->shippingZone?->is_active && $address->city?->shippingZone?->warehouse?->is_active && $address->city?->shippingZone?->warehouse?->type?->key === 'sales_dispatch' && $address->city?->shippingZone?->warehouse?->branch?->is_active && $address->city?->shippingZone?->warehouse?->branch?->company?->is_active ? $address->city?->shippingZone?->name_ar : '' }}" data-fee-min="{{ $address->city?->shippingZone?->shipping_fee_min }}" data-fee-max="{{ $address->city?->shippingZone?->shipping_fee_max }}" data-delivery-days="{{ $address->city?->shippingZone?->delivery_days }}"></span>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="form-group col-lg-3">
                    <label for="shipping_fee">رسوم الشحن</label>
                    <input class="form-control" type="number" name="shipping_fee" id="shipping_fee" min="0" step="0.01" value="0">
                    <small class="form-text text-muted" id="shipping-route-hint">اختر عنوانًا لمعرفة منطقة الشحن والرسوم ومدة التوصيل.</small>
                </div>
                <div class="form-group col-md-5">
                    <label for="customer-address-select">العناوين المحفوظة للعميل</label>
                    <select class="form-control" id="customer-address-select" disabled><option value="">اختر العميل أولًا</option></select>
                </div>
                <div class="form-group col-md-7">
                    <label for="shipping_address">عنوان التوصيل (يمكن تعديله لهذا الطلب)</label>
                    <textarea class="form-control" name="shipping_address" id="shipping_address" rows="2" maxlength="2000"></textarea>
                </div>
                <div class="form-group col-md-4">
                    <label for="shipping_date">موعد الشحن المتوقع</label>
                    <input class="form-control" type="date" name="shipping_date" id="shipping_date" disabled>
                    <small class="form-text text-muted">يمكن تأجيل الموعد، ولا يمكن اختياره قبل مدة التوصيل المحددة للمنطقة.</small>
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
    const customerAddresses = Array.from(document.querySelectorAll('#customer-address-data [data-customer-id]')).reduce((map, group) => {
        map[group.dataset.customerId] = Array.from(group.querySelectorAll('[data-address]')).map(node => ({
            id: node.dataset.id,
            title: node.dataset.title,
            address: node.dataset.addressText,
            city: node.dataset.city,
            cityId: node.dataset.cityId,
            zone: node.dataset.zone,
            feeMin: node.dataset.feeMin,
            feeMax: node.dataset.feeMax,
            deliveryDays: node.dataset.deliveryDays
        }));
        return map;
    }, {});
    const customerPhones = Array.from(document.querySelectorAll('#customer-phone-data [data-customer-id]')).reduce((map, group) => {
        map[group.dataset.customerId] = Array.from(group.querySelectorAll('[data-phone]')).map(node => ({
            id: node.dataset.id,
            number: node.dataset.number,
            type: node.dataset.type,
            isPrimary: node.dataset.primary === '1',
            isWhatsapp: node.dataset.whatsapp === '1'
        }));
        return map;
    }, {});
    const renderCustomerPhones = () => {
        const select = $('customer-phone-select');
        const phones = customerPhones[$('customer_id').value] || [];
        select.innerHTML = '';
        if (!phones.length) {
            select.disabled = true;
            select.appendChild(new Option('لا يوجد رقم هاتف نشط للعميل', ''));
            $('shipping_phone_id').value = '';
            return;
        }
        select.disabled = false;
        select.appendChild(new Option('اختر رقم الهاتف', ''));
        phones.forEach(phone => {
            const label = phone.number + (phone.isPrimary ? ' — أساسي' : '') + (phone.isWhatsapp ? ' — WhatsApp' : '');
            select.appendChild(new Option(label, phone.id, false, false));
        });
        const primary = phones.find(phone => phone.isPrimary) || phones[0];
        select.value = String(primary.id);
        $('shipping_phone_id').value = String(primary.id);
    };
    $('customer-phone-select').addEventListener('change', function () {
        $('shipping_phone_id').value = this.value;
    });
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
                option.value = address.id;
                option.textContent = address.title + (address.city ? ' — ' + address.city : '');
                select.appendChild(option);
                if (index === 0) option.selected = true;
            });
            $('shipping_address_id').value = addresses[0].id;
            $('shipping_address').value = addresses[0].address;
            applyShippingRoute(addresses[0]);
        } else {
            select.disabled = true;
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'لا توجد عناوين محفوظة';
            select.appendChild(option);
            $('shipping_address_id').value = '';
            $('shipping_address').value = selectedCustomer?.dataset.address || '';
            $('shipping-route-hint').textContent = 'لا يوجد عنوان محفوظ مرتبط بمنطقة شحن؛ أضف عنوانًا للعميل واربط مدينته بمنطقة شحن أولًا.';
            $('shipping_fee').value = 0;
            $('shipping_fee').min = 0;
            $('shipping_fee').removeAttribute('max');
            $('shipping_date').value = '';
            $('shipping_date').min = '';
            $('shipping_date').disabled = true;
        }
    };
    const localDateString = date => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    };
    const applyShippingRoute = address => {
        const fee = $('shipping_fee');
        const hint = $('shipping-route-hint');
        const shippingDate = $('shipping_date');
        if (address.zone && address.feeMin !== '' && address.feeMax !== '') {
            fee.min = address.feeMin;
            fee.max = address.feeMax;
            fee.value = address.feeMin;
            const earliest = new Date();
            earliest.setHours(0, 0, 0, 0);
            earliest.setDate(earliest.getDate() + (Number(address.deliveryDays) || 0));
            const earliestValue = localDateString(earliest);
            shippingDate.disabled = false;
            shippingDate.min = earliestValue;
            if (!shippingDate.value || shippingDate.value < earliestValue) shippingDate.value = earliestValue;
            hint.textContent = 'منطقة الشحن: ' + address.zone + ' — الرسوم من ' + Number(address.feeMin).toFixed(2) + ' إلى ' + Number(address.feeMax).toFixed(2) + ' جنيه — مدة التوصيل المتوقعة: ' + address.deliveryDays + ' يوم';
        } else {
            fee.value = 0;
            fee.min = 0;
            fee.removeAttribute('max');
            shippingDate.value = '';
            shippingDate.min = '';
            shippingDate.disabled = true;
            hint.textContent = 'لا توجد منطقة شحن نشطة مرتبطة بمدينة هذا العنوان. لن يمكن حفظ الأمر قبل إعداد منطقة الشحن والمخزن المرتبط بها.';
        }
    };
    let customerOptions = Array.from($('customer_id').options).slice(1);
    $('customer-search').addEventListener('input', function () {
        const term = this.value.trim().toLocaleLowerCase();
        const selectedId = $('customer_id').value;
        customerOptions.forEach(option => {
            const matches = !term || (option.dataset.search || '').includes(term);
            option.hidden = !matches && option.value !== selectedId;
        });
        if (selectedId && $('customer_id').selectedOptions[0]?.hidden) {
            $('customer_id').value = '';
            $('customer_id').dispatchEvent(new Event('change'));
            if (window.jQuery && jQuery.fn.select2) jQuery('#customer_id').val(null).trigger('change');
        }
    });
    let customerHistoryRequest = 0;
    const loadCustomerHistory = async () => {
        const requestId = ++customerHistoryRequest;
        const customerId = $('customer_id').value;
        const panel = $('customer-order-history');
        panel.replaceChildren();
        if (!customerId) {
            panel.textContent = 'اختر العميل لعرض آخر أوامره السابقة.';
            return;
        }

        panel.textContent = 'جارٍ تحميل أوامر العميل السابقة...';
        const url = @json(route('admin.sales.orders.customer-history', ['customer' => '__CUSTOMER__']));
        try {
            const response = await fetch(url.replace('__CUSTOMER__', encodeURIComponent(customerId)), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) throw new Error('history');
            const payload = await response.json();
            if (requestId !== customerHistoryRequest) return;

            panel.replaceChildren();
            const heading = document.createElement('strong');
            heading.className = 'd-block mb-2';
            heading.textContent = 'آخر أوامر العميل';
            panel.appendChild(heading);

            if (!payload.orders || payload.orders.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'text-muted small';
                empty.textContent = 'لا توجد أوامر سابقة لهذا العميل.';
                panel.appendChild(empty);
                return;
            }

            const labels = { draft: 'مسودة', pending: 'قيد المراجعة', confirmed: 'مؤكد', cancelled: 'ملغي' };
            const list = document.createElement('div');
            list.className = 'list-group';
            payload.orders.forEach(order => {
                const item = document.createElement('div');
                item.className = 'list-group-item py-2 d-flex justify-content-between align-items-center flex-wrap';
                const details = document.createElement('span');
                details.textContent = (order.order_number || 'أمر بيع') + ' — ' + (labels[order.status] || order.status) + ' — ' + (order.date || '');
                const total = document.createElement('strong');
                total.textContent = Number(order.total || 0).toFixed(2) + ' جنيه';
                item.append(details, total);
                list.appendChild(item);
            });
            panel.appendChild(list);
        } catch (error) {
            if (requestId !== customerHistoryRequest) return;
            panel.textContent = 'تعذر تحميل أوامر العميل السابقة. يمكنك متابعة إنشاء الطلب.';
        }
    };
    const activeCities = @json($cities->pluck('name_ar', 'id'));
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[char]));
    const customerEditorUrl = @json(route('admin.parties.quick-customer.store'));
    const updateCustomerUrl = @json(route('admin.parties.quick-customer.update', ['party' => '__PARTY__']));
    const csrfToken = $('order-form').querySelector('input[name="_token"]').value;

    const openCustomerEditor = async (customerId = null) => {
        const option = customerId ? Array.from($('customer_id').options).find(item => item.value === String(customerId)) : null;
        const addresses = customerId ? (customerAddresses[customerId] || []) : [];
        const address = addresses[0] || {};
        const cityOptions = Object.entries(activeCities).map(([id, name]) =>
            '<option value="' + escapeHtml(id) + '"' + (String(address.cityId || '') === String(id) ? ' selected' : '') + '>' + escapeHtml(name) + '</option>'
        ).join('');
        const result = await Swal.fire({
            title: customerId ? 'تعديل بيانات العميل' : 'إضافة عميل جديد',
            html: '<div class="text-right">'
                + '<div class="form-group"><label for="quick-customer-type">نوع العميل</label><select id="quick-customer-type" class="form-control"><option value="individual"' + ((option?.dataset.entityType || 'individual') === 'individual' ? ' selected' : '') + '>فرد</option><option value="company"' + (option?.dataset.entityType === 'company' ? ' selected' : '') + '>شركة</option></select></div>'
                + '<div class="form-group"><label for="quick-customer-name">اسم العميل</label><input id="quick-customer-name" class="form-control" maxlength="180" value="' + escapeHtml(option?.dataset.name || '') + '"></div>'
                + '<div class="form-group"><label for="quick-customer-mobile">رقم الهاتف</label><input id="quick-customer-mobile" class="form-control" maxlength="40" value="' + escapeHtml(option?.dataset.mobile || '') + '"></div>'
                + '<div class="form-group"><label for="quick-customer-email">البريد الإلكتروني (اختياري)</label><input id="quick-customer-email" type="email" class="form-control" maxlength="190" value="' + escapeHtml(option?.dataset.email || '') + '"></div>'
                + '<div class="form-group"><label for="quick-customer-city">المدينة</label><select id="quick-customer-city" class="form-control"><option value="">اختر المدينة</option>' + cityOptions + '</select></div>'
                + '<div class="form-group"><label for="quick-customer-address">عنوان التوصيل</label><textarea id="quick-customer-address" class="form-control" rows="2" maxlength="4000">' + escapeHtml(address.address || '') + '</textarea></div>'
                + '</div>',
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: customerId ? 'حفظ التعديلات' : 'إضافة العميل',
            cancelButtonText: 'إلغاء',
            preConfirm: async () => {
                const data = {
                    entity_type: $('quick-customer-type').value,
                    name: $('quick-customer-name').value.trim(),
                    mobile: $('quick-customer-mobile').value.trim(),
                    email: $('quick-customer-email').value.trim(),
                    city_id: $('quick-customer-city').value,
                    address: $('quick-customer-address').value.trim()
                };
                if (!data.name || !data.mobile || !data.city_id || !data.address) {
                    Swal.showValidationMessage('أدخل الاسم ورقم الهاتف والمدينة والعنوان.');
                    return false;
                }
                try {
                    const response = await fetch(customerId
                        ? updateCustomerUrl.replace('__PARTY__', encodeURIComponent(customerId))
                        : customerEditorUrl, {
                        method: customerId ? 'PUT' : 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(data)
                    });
                    const payload = await response.json();
                    if (!response.ok) {
                        const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : payload.message;
                        throw new Error(firstError || 'تعذر حفظ بيانات العميل.');
                    }
                    return payload.customer;
                } catch (error) {
                    Swal.showValidationMessage(error.message || 'تعذر حفظ بيانات العميل.');
                    return false;
                }
            }
        });

        if (!result.isConfirmed || !result.value) return;
        const customer = result.value;
        const firstAddress = customer.addresses?.[0] || {};
        let customerOption = Array.from($('customer_id').options).find(item => item.value === String(customer.id));
        if (!customerOption) {
            customerOption = document.createElement('option');
            customerOption.value = customer.id;
            $('customer_id').appendChild(customerOption);
            customerOptions.push(customerOption);
        }
        customerOption.textContent = customer.name + ' — ' + customer.mobile;
        customerOption.dataset.name = customer.name;
        customerOption.dataset.entityType = customer.entity_type || 'individual';
        customerOption.dataset.mobile = customer.mobile || '';
        customerOption.dataset.email = customer.email || '';
        customerOption.dataset.address = firstAddress.address || '';
        customerOption.dataset.search = (customer.name + ' ' + (customer.mobile || '') + ' ' + (customer.phone || '')).toLocaleLowerCase();
        customerPhones[customer.id] = (customer.phones || []).map(item => ({
            id: String(item.id),
            number: item.number,
            type: item.type,
            isPrimary: !!item.isPrimary,
            isWhatsapp: !!item.isWhatsapp
        }));
        customerAddresses[customer.id] = (customer.addresses || []).map(item => ({
            id: String(item.id),
            title: item.title,
            address: item.address,
            city: item.city,
            cityId: item.city_id,
            zone: item.zone || '',
            feeMin: item.feeMin ?? '',
            feeMax: item.feeMax ?? '',
            deliveryDays: item.deliveryDays ?? ''
        }));
        $('customer-search').value = '';
        customerOptions.forEach(item => { item.hidden = false; });
        $('customer_id').value = String(customer.id);
        if (window.jQuery && jQuery.fn.select2) {
            jQuery('#customer_id').val(String(customer.id)).trigger('change');
        } else {
            $('customer_id').dispatchEvent(new Event('change'));
        }
        $('edit-customer')?.removeAttribute('disabled');
        Swal.fire({ icon: 'success', text: customerId ? 'تم تحديث بيانات العميل.' : 'تمت إضافة العميل.', timer: 1400, showConfirmButton: false });
    };

    $('add-customer')?.addEventListener('click', () => openCustomerEditor());
    $('edit-customer')?.addEventListener('click', () => {
        if ($('customer_id').value) openCustomerEditor($('customer_id').value);
    });
    $('customer_id').addEventListener('change', () => {
        if ($('edit-customer')) $('edit-customer').disabled = !$('customer_id').value;
    });

    $('customer_id').addEventListener('change', () => {
        renderCustomerPhones();
        renderCustomerAddresses();
        loadCustomerHistory();
    });
    $('customer-address-select').addEventListener('change', function () {
        const addresses = customerAddresses[$('customer_id').value] || [];
        const address = addresses.find(item => String(item.id) === this.value);
        if (address) {
            $('shipping_address_id').value = address.id;
            $('shipping_address').value = address.address;
            applyShippingRoute(address);
        }
    });
    renderCustomerPhones();
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
        if (!$('shipping_phone_id').value) {
            Swal.fire({ icon: 'warning', text: 'اختر رقم هاتف نشطًا للعميل.' });
            return;
        }
        const selectedAddress = (customerAddresses[$('customer_id').value] || [])
            .find(address => String(address.id) === $('shipping_address_id').value);
        if (!selectedAddress || !selectedAddress.zone) {
            Swal.fire({ icon: 'warning', text: 'اختر عنوانًا مرتبطًا بمنطقة شحن ومخزن نشطين قبل متابعة الطلب.' });
            return;
        }
        const fee = Number($('shipping_fee').value);
        if (!Number.isFinite(fee) || fee < Number(selectedAddress.feeMin) || fee > Number(selectedAddress.feeMax)) {
            Swal.fire({ icon: 'warning', text: 'رسوم الشحن خارج النطاق المحدد لعنوان العميل.' });
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
