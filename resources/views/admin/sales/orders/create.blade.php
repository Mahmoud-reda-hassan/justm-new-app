@extends('layouts.app')

@section('title', 'إنشاء أمر بيع')
@section('page-title', 'إنشاء أمر بيع')
@section('breadcrumb', 'إنشاء أمر بيع')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/order-storefront-legacy.css') }}">
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

<form method="post" action="{{ route('admin.sales.orders.store') }}" id="order-form" class="jm-order-storefront-page">
    @csrf
    <nav class="jm-order-steps" aria-label="مراحل إنشاء أمر البيع">
        <div class="jm-order-step active" data-order-step="step-customer"><span>1</span> بيانات العميل</div>
        <i aria-hidden="true"></i>
        <div class="jm-order-step" data-order-step="step-products"><span>2</span> اختيار المنتجات</div>
        <i aria-hidden="true"></i>
        <div class="jm-order-step" data-order-step="step-review"><span>3</span> مراجعة وحفظ</div>
    </nav>
    <input type="hidden" name="shipping_address_id" id="shipping_address_id">
    <input type="hidden" name="shipping_phone_id" id="shipping_phone_id">
    <section class="card card-outline card-primary order-step active" id="step-customer">
        <div class="card-header"><h3 class="card-title">1. بيانات العميل</h3></div>
        <div class="card-body">
            <p class="text-muted">اختر العميل وحدد عنوان التوصيل. رسوم الشحن مستقلة على مستوى أمر البيع.</p>
            <div class="form-row">
                <div class="form-group col-lg-6">
                    <div class="card card-outline card-secondary mb-3">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-users ml-1"></i> دليل العملاء</h3></div>
                        <div class="card-body">
                            <label for="customer-search">بحث برقم هاتف العميل فقط</label>
                            <div class="input-group mb-3">
                                <input class="form-control" type="search" id="customer-search" inputmode="numeric" autocomplete="off" placeholder="اكتب رقم الهاتف">
                                <div class="input-group-append"><button class="btn btn-primary" type="button" id="customer-search-btn"><i class="fas fa-search ml-1"></i> بحث</button></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center flex-wrap mt-3 mb-2">
                                <strong id="customer-directory-title">العملاء</strong>
                                <small class="text-muted" id="customer-directory-count"></small>
                            </div>
                            <div class="row" id="customer-directory-cards" aria-live="polite"></div>
                            <div class="d-flex justify-content-center align-items-center flex-wrap mt-2" id="customer-directory-pagination"></div>
                            <div class="small text-muted" id="customer-directory-message" aria-live="polite"></div>
                        </div>
                    </div>
                    <select class="form-control d-none" name="customer_id" id="customer_id">
                        <option value="">اختر العميل</option>
                    </select>
                    <div class="form-group mt-2 mb-2">
                        <label for="customer-phone-select">رقم هاتف التواصل والتوصيل</label>
                        <select class="form-control d-none" id="customer-phone-select" disabled>
                            <option value="">اختر العميل أولًا</option>
                        </select>
                    </div>
                    <div id="customer-contact-card" class="card card-outline card-info mt-2 d-none">
                        <div class="card-header py-2"><strong id="customer-contact-name">بيانات العميل</strong></div>
                        <div class="card-body py-2" id="customer-phone-details"></div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        @if(auth()->user()?->hasPermission('parties.create') && auth()->user()?->hasPermission('structure.scope.manage_all'))
                            <button type="button" class="btn btn-sm btn-outline-primary" id="add-customer"><i class="fas fa-user-plus ml-1"></i> إضافة عميل</button>
                        @endif
                        @if(auth()->user()?->hasPermission('parties.update') && auth()->user()?->hasPermission('structure.scope.manage_all'))
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="edit-customer" disabled><i class="fas fa-user-edit ml-1"></i> تعديل العميل</button>
                        @endif
                    </div>
                    <div id="customer-order-history" class="mt-3" aria-live="polite">
                        <div class="text-muted small">اختر العميل لعرض آخر أوامره السابقة.</div>
                    </div>
                </div>
                <div class="form-group col-lg-3">
                    <label for="shipping_fee">رسوم الشحن</label>
                    <input class="form-control" type="number" name="shipping_fee" id="shipping_fee" min="0" step="0.01" value="0">
                    <small class="form-text text-muted" id="shipping-route-hint">اختر عنوانًا لمعرفة منطقة الشحن والرسوم ومدة التوصيل.</small>
                </div>
                <div class="form-group col-md-5">
                    <label for="customer-address-select">العناوين المحفوظة للعميل</label>
                    <select class="form-control d-none" id="customer-address-select" disabled><option value="">اختر العميل أولًا</option></select>
                </div>
                <div class="form-group col-md-7">
                    <div id="customer-address-card" class="card card-outline card-secondary mb-2 d-none">
                        <div class="card-header py-2"><strong>اختيار عنوان التوصيل</strong></div>
                        <div class="card-body py-2" id="customer-address-details"></div>
                    </div>
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
            deliveryDays: node.dataset.deliveryDays,
            isPrimary: node.dataset.primary === '1'
        }));
        return map;
    }, {});
    const customerPhones = Array.from(document.querySelectorAll('#customer-phone-data [data-customer-id]')).reduce((map, group) => {
        map[group.dataset.customerId] = Array.from(group.querySelectorAll('[data-phone]')).map(node => ({
            id: node.dataset.id,
            number: node.dataset.number,
            type: node.dataset.type,
            isPrimary: node.dataset.primary === '1',
            isWhatsapp: node.dataset.whatsapp === '1',
            isTelegram: node.dataset.telegram === '1'
        }));
        return map;
    }, {});
    const renderPhoneDetails = (phones, selectedId) => {
        const box = $('customer-phone-details');
        box.replaceChildren();
        $('customer-contact-card').classList.remove('d-none');
        phones.forEach(phone => {
            const row = document.createElement('div');
            row.className = 'd-flex justify-content-between align-items-center border-bottom py-2';
            const label = document.createElement('label');
            label.className = 'mb-0';
            const radio = document.createElement('input');
            radio.type = 'radio'; radio.name = 'customer_phone_choice';
            radio.value = String(phone.id); radio.checked = String(phone.id) === String(selectedId);
            radio.className = 'ml-2';
            radio.addEventListener('change', () => {
                $('customer-phone-select').value = String(phone.id);
                $('shipping_phone_id').value = String(phone.id);
            });
            const number = document.createElement('span');
            number.textContent = phone.number + (phone.isPrimary ? ' ★' : '');
            label.append(radio, number);
            const links = document.createElement('span');
            if (phone.isWhatsapp) {
                const a = document.createElement('a');
                a.href = 'https://wa.me/' + String(phone.number).replace(/\D/g, '');
                a.target = '_blank'; a.rel = 'noopener noreferrer'; a.className = 'text-success ml-3';
                a.innerHTML = '<i class="fab fa-whatsapp fa-lg"></i>'; a.title = 'واتساب'; links.appendChild(a);
            }
            if (phone.isTelegram) {
                const a = document.createElement('a');
                a.href = 'https://t.me/+' + String(phone.number).replace(/\D/g, '');
                a.target = '_blank'; a.rel = 'noopener noreferrer'; a.className = 'text-info';
                a.innerHTML = '<i class="fab fa-telegram fa-lg"></i>'; a.title = 'تيليجرام'; links.appendChild(a);
            }
            row.append(label, links); box.appendChild(row);
        });
    };
    const renderAddressDetails = (addresses, selectedId) => {
        const card = $('customer-address-card');
        const box = $('customer-address-details');
        box.replaceChildren(); card.classList.remove('d-none');
        addresses.forEach(address => {
            const label = document.createElement('label');
            label.className = 'd-block border-bottom py-2 mb-0';
            const radio = document.createElement('input');
            radio.type = 'radio'; radio.name = 'customer_address_choice';
            radio.value = String(address.id); radio.checked = String(address.id) === String(selectedId);
            radio.className = 'ml-2';
            radio.addEventListener('change', () => {
                $('customer-address-select').value = String(address.id);
                $('shipping_address_id').value = String(address.id);
                $('shipping_address').value = address.address;
                applyShippingRoute(address);
            });
            const text = document.createElement('span');
            text.textContent = address.title + (address.isPrimary ? ' ★' : '') +
                (address.city ? ' — ' + address.city : '') + ' — ' + address.address;
            label.append(radio, text); box.appendChild(label);
        });
    };
    const renderCustomerPhones = () => {
        const select = $('customer-phone-select');
        const phones = customerPhones[$('customer_id').value] || [];
        select.innerHTML = '';
        if (!phones.length) {
            select.disabled = true;
            select.appendChild(new Option('لا يوجد رقم هاتف نشط للعميل', ''));
            $('shipping_phone_id').value = '';
            $('customer-contact-card').classList.add('d-none');
            $('customer-phone-details').replaceChildren();
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
        $('customer-contact-name').textContent = $('customer_id').selectedOptions[0]?.textContent || 'بيانات العميل';
        renderPhoneDetails(phones, primary.id);
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
                if (address.isPrimary) option.selected = true;
            });
            const primaryAddress = addresses.find(address => address.isPrimary) || addresses[0];
            select.value = String(primaryAddress.id);
            $('shipping_address_id').value = primaryAddress.id;
            $('shipping_address').value = primaryAddress.address;
            applyShippingRoute(primaryAddress);
            renderAddressDetails(addresses, primaryAddress.id);
        } else {
            select.disabled = true;
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'لا توجد عناوين محفوظة';
            select.appendChild(option);
            $('shipping_address_id').value = '';
            $('customer-address-card').classList.add('d-none');
            $('customer-address-details').replaceChildren();
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
    const directoryUrl = @json(route('admin.sales.orders.customers.directory'));
    const selectionUrl = @json(route('admin.sales.orders.customers.selection', ['customer' => '__CUSTOMER__']));
    const directoryCards = $('customer-directory-cards');
    const directoryPagination = $('customer-directory-pagination');

    const selectCustomerFromDirectory = async customerId => {
        const message = $('customer-directory-message');
        message.textContent = 'جارٍ تحميل بيانات العميل...';
        try {
            const response = await fetch(selectionUrl.replace('__CUSTOMER__', encodeURIComponent(customerId)), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) throw new Error('selection');
            const payload = await response.json();
            const customer = payload.customer;
            let option = Array.from($('customer_id').options).find(item => item.value === String(customer.id));
            if (!option) {
                option = document.createElement('option');
                option.value = customer.id;
                $('customer_id').appendChild(option);
                customerOptions.push(option);
            }
            option.textContent = customer.name;
            option.dataset.name = customer.name;
            option.dataset.entityType = customer.entity_type || 'individual';
            option.dataset.mobile = customer.mobile || customer.phone || '';
            option.dataset.email = customer.email || '';
            option.dataset.address = customer.addresses?.[0]?.address || '';
            customerPhones[customer.id] = (customer.phones || []).map(item => ({
                id: String(item.id), number: item.number, type: item.type,
                isPrimary: !!item.isPrimary, isWhatsapp: !!item.isWhatsapp,
                isTelegram: !!item.isTelegram
            }));
            customerAddresses[customer.id] = (customer.addresses || []).map(item => ({
                id: String(item.id), title: item.title, address: item.address,
                city: item.city, cityId: item.cityId, zone: item.zone || '',
                feeMin: item.feeMin ?? '', feeMax: item.feeMax ?? '',
                deliveryDays: item.deliveryDays ?? '', isPrimary: !!item.isPrimary
            }));
            $('customer_id').value = String(customer.id);
            $('customer_id').dispatchEvent(new Event('change'));
            message.textContent = 'تم اختيار العميل. راجع الهاتف والعنوان قبل المتابعة.';
            $('edit-customer')?.removeAttribute('disabled');
        } catch (error) {
            message.textContent = 'تعذر تحميل بيانات العميل. حاول مرة أخرى.';
        }
    };

    const makeDirectoryCard = customer => {
        const col = document.createElement('div');
        col.className = 'col-xl-3 col-md-4 col-sm-6 mb-2';
        const card = document.createElement('div');
        card.className = 'card h-100 mb-0 border';
        const body = document.createElement('div');
        body.className = 'card-body p-3';
        const name = document.createElement('strong');
        name.className = 'd-block text-truncate';
        name.textContent = customer.name;
        const phone = document.createElement('small');
        phone.className = 'text-muted d-block';
        phone.textContent = customer.phone || 'لا يوجد هاتف';

        if (customer.is_most_delivered) {
            const topBadge = document.createElement('span');
            topBadge.className = 'badge badge-success mt-2';
            topBadge.textContent = 'الأكثر تسليمًا';
            body.appendChild(topBadge);
        }

        const orderStats = document.createElement('div');
        orderStats.className = 'd-flex flex-wrap mt-2';
        [
            { label: 'تم التسليم', count: customer.delivered_orders_count, css: 'badge-success' },
            { label: 'قيد التجهيز', count: customer.preparing_orders_count, css: 'badge-info' },
            { label: 'قيد الشحن', count: customer.shipping_orders_count, css: 'badge-primary' },
            { label: 'قيد التأكيد', count: customer.pending_confirmation_orders_count, css: 'badge-warning' }
        ].forEach(stat => {
            const tag = document.createElement('span');
            tag.className = 'badge ' + stat.css + ' mr-1 mb-1';
            tag.textContent = stat.label + ': ' + (Number(stat.count) || 0);
            orderStats.appendChild(tag);
        });

        const actions = document.createElement('div');
        actions.className = 'btn-group btn-group-sm d-flex mt-2';
        const choose = document.createElement('button');
        choose.type = 'button';
        choose.className = 'btn btn-outline-primary';
        choose.textContent = 'اختيار العميل';
        choose.addEventListener('click', () => selectCustomerFromDirectory(customer.id));
        actions.appendChild(choose);
        if (@json(auth()->user()?->hasPermission('parties.update') && auth()->user()?->hasPermission('structure.scope.manage_all'))) {
            const edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'btn btn-outline-secondary';
            edit.innerHTML = '<i class="fas fa-edit ml-1"></i> تعديل العميل';
            edit.title = 'تعديل العميل';
            edit.addEventListener('click', async () => {
                await selectCustomerFromDirectory(customer.id);
                if ($('customer_id').value === String(customer.id)) openCustomerEditor(customer.id);
            });
            actions.appendChild(edit);
        }
        body.append(name, phone, orderStats, actions);
        card.appendChild(body);
        col.appendChild(card);
        return col;
    };

    const loadCustomerDirectory = async (page = 1, phone = '') => {
        directoryCards.replaceChildren();
        directoryPagination.replaceChildren();
        $('customer-directory-message').textContent = 'جارٍ تحميل العملاء...';
        const query = new URLSearchParams({ page: String(page) });
        if (phone) query.set('phone', phone);
        try {
            const response = await fetch(directoryUrl + '?' + query.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) throw new Error('directory');
            const payload = await response.json();
            const paginator = payload.customers;
            paginator.data.forEach(customer => directoryCards.appendChild(makeDirectoryCard(customer)));
            $('customer-directory-title').textContent = phone ? 'نتائج البحث برقم الهاتف' : 'كل العملاء — مرتبين حسب عدد الطلبات المسلّمة';
            $('customer-directory-count').textContent = paginator.total + ' عميل';
            if (!paginator.data.length) {
                $('customer-directory-message').textContent = phone ? 'لا يوجد عميل مسجل بهذا الرقم أو بجزء منه.' : 'لا يوجد عملاء مسجلون حاليًا.';
            } else {
                $('customer-directory-message').textContent = '';
            }
            if (paginator.current_page > 1) {
                const previous = document.createElement('button');
                previous.type = 'button';
                previous.className = 'btn btn-default btn-sm mx-1';
                previous.textContent = 'السابق';
                previous.addEventListener('click', () => loadCustomerDirectory(paginator.current_page - 1, phone));
                directoryPagination.appendChild(previous);
            }
            const pageLabel = document.createElement('span');
            pageLabel.className = 'small text-muted mx-2';
            pageLabel.textContent = 'صفحة ' + paginator.current_page + ' من ' + paginator.last_page;
            directoryPagination.appendChild(pageLabel);
            if (paginator.current_page < paginator.last_page) {
                const next = document.createElement('button');
                next.type = 'button';
                next.className = 'btn btn-default btn-sm mx-1';
                next.textContent = 'التالي';
                next.addEventListener('click', () => loadCustomerDirectory(paginator.current_page + 1, phone));
                directoryPagination.appendChild(next);
            }
        } catch (error) {
            $('customer-directory-message').textContent = 'تعذر تحميل دليل العملاء.';
        }
    };

    $('customer-search-btn').addEventListener('click', () => loadCustomerDirectory(1, $('customer-search').value.trim()));
    $('customer-search').addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            loadCustomerDirectory(1, $('customer-search').value.trim());
        }
    });
    $('customer-search').addEventListener('search', () => loadCustomerDirectory(1, $('customer-search').value.trim()));
    loadCustomerDirectory();
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
        const phones = customerId ? (customerPhones[customerId] || []).map(item => ({...item})) : [];
        const addresses = customerId ? (customerAddresses[customerId] || []).map(item => ({...item})) : [];
        const cityOptions = Object.entries(activeCities).map(([id, name]) =>
            '<option value="' + escapeHtml(id) + '">' + escapeHtml(name) + '</option>'
        ).join('');
        const result = await Swal.fire({
            title: customerId ? 'تعديل بيانات العميل' : 'إضافة عميل جديد',
            width: '900px',
            html: '<div class="text-right">'
                + '<div class="form-group"><label for="quick-customer-type">نوع العميل</label><select id="quick-customer-type" class="form-control"><option value="individual"' + ((option?.dataset.entityType || 'individual') === 'individual' ? ' selected' : '') + '>فرد</option><option value="company"' + (option?.dataset.entityType === 'company' ? ' selected' : '') + '>شركة</option></select></div>'
                + '<div class="form-group"><label for="quick-customer-name">اسم العميل</label><input id="quick-customer-name" class="form-control" maxlength="180" value="' + escapeHtml(option?.dataset.name || '') + '"></div>'
                + '<div class="form-group"><label for="quick-customer-email">البريد الإلكتروني (اختياري)</label><input id="quick-customer-email" type="email" class="form-control" maxlength="190" value="' + escapeHtml(option?.dataset.email || '') + '"></div>'
                + '<div class="card card-outline card-info"><div class="card-header py-2 d-flex justify-content-between"><strong>أرقام الهواتف</strong><button type="button" id="quick-add-phone" class="btn btn-sm btn-outline-primary">إضافة رقم</button></div><div class="card-body p-2" id="quick-phone-list"></div></div>'
                + '<div class="card card-outline card-secondary mt-3"><div class="card-header py-2 d-flex justify-content-between"><strong>العناوين</strong><button type="button" id="quick-add-address" class="btn btn-sm btn-outline-primary">إضافة عنوان</button></div><div class="card-body p-2" id="quick-address-list"></div></div>'
                + '</div>',
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'حفظ بيانات العميل',
            cancelButtonText: 'إلغاء',
            didOpen: () => {
                const phoneList = $('quick-phone-list');
                const addressList = $('quick-address-list');
                const addPhoneRow = phone => {
                    const row = document.createElement('div');
                    row.className = 'border rounded p-2 mb-2 quick-phone-row';
                    row.dataset.id = phone?.id || '';
                    row.innerHTML = '<div class="form-row align-items-center">'
                        + '<div class="col-md-4 mb-2"><label>رقم الهاتف</label><input class="form-control phone-number" maxlength="40" value="' + escapeHtml(phone?.number || '') + '"></div>'
                        + '<div class="col-md-3 mb-2"><label>النوع</label><select class="form-control phone-type"><option value="mobile">محمول</option><option value="landline">أرضي</option></select></div>'
                        + '<div class="col-md-3 mb-2"><label class="d-block">التطبيقات</label><label class="mr-2"><input type="checkbox" class="phone-whatsapp"> واتساب</label><label><input type="checkbox" class="phone-telegram"> تيليجرام</label></div>'
                        + '<div class="col-md-2 mb-2"><label class="d-block">افتراضي</label><label><input type="radio" name="quick-primary-phone" class="phone-primary"> أساسي</label><button type="button" class="btn btn-sm btn-link text-danger remove-phone d-block p-0">حذف</button></div>'
                        + '</div>';
                    row.querySelector('.phone-type').value = phone?.type || 'mobile';
                    row.querySelector('.phone-whatsapp').checked = !!phone?.isWhatsapp;
                    row.querySelector('.phone-telegram').checked = !!phone?.isTelegram;
                    row.querySelector('.phone-primary').checked = !!phone?.isPrimary;
                    row.querySelector('.remove-phone').addEventListener('click', () => row.remove());
                    phoneList.appendChild(row);
                };
                const addAddressRow = address => {
                    const row = document.createElement('div');
                    row.className = 'border rounded p-2 mb-2 quick-address-row';
                    row.dataset.id = address?.id || '';
                    row.innerHTML = '<div class="form-row">'
                        + '<div class="col-md-3 mb-2"><label>اسم العنوان</label><input class="form-control address-title" maxlength="80" value="' + escapeHtml(address?.title || 'عنوان جديد') + '"></div>'
                        + '<div class="col-md-4 mb-2"><label>المدينة</label><select class="form-control address-city"><option value="">اختر المدينة</option>' + cityOptions + '</select></div>'
                        + '<div class="col-md-5 mb-2"><label>العنوان بالتفصيل</label><textarea class="form-control address-text" rows="2" maxlength="4000">' + escapeHtml(address?.address || '') + '</textarea></div>'
                        + '<div class="col-12 d-flex justify-content-between align-items-center"><label class="mb-0"><input type="radio" name="quick-primary-address" class="address-primary"> العنوان الافتراضي</label><button type="button" class="btn btn-sm btn-link text-danger remove-address">حذف</button></div>'
                        + '</div>';
                    row.querySelector('.address-city').value = String(address?.cityId || '');
                    row.querySelector('.address-primary').checked = !!address?.isPrimary;
                    row.querySelector('.remove-address').addEventListener('click', () => row.remove());
                    addressList.appendChild(row);
                };
                phones.forEach(addPhoneRow);
                addresses.forEach(addAddressRow);
                if (!phones.length) addPhoneRow({type:'mobile',isPrimary:true});
                if (!addresses.length) addAddressRow({title:'العنوان الرئيسي',isPrimary:true});
                $('quick-add-phone').addEventListener('click', () => addPhoneRow({type:'mobile'}));
                $('quick-add-address').addEventListener('click', () => addAddressRow({title:'عنوان جديد'}));
            },
            preConfirm: async () => {
                const phoneRows = Array.from(document.querySelectorAll('.quick-phone-row'));
                const addressRows = Array.from(document.querySelectorAll('.quick-address-row'));
                const data = {
                    entity_type: $('quick-customer-type').value,
                    name: $('quick-customer-name').value.trim(),
                    email: $('quick-customer-email').value.trim(),
                    phones: phoneRows.map(row => ({
                        ...(row.dataset.id ? {id:Number(row.dataset.id)} : {}),
                        number: row.querySelector('.phone-number').value.trim(),
                        type: row.querySelector('.phone-type').value,
                        is_whatsapp: row.querySelector('.phone-whatsapp').checked,
                        is_telegram: row.querySelector('.phone-telegram').checked,
                        is_primary: row.querySelector('.phone-primary').checked
                    })),
                    addresses: addressRows.map(row => ({
                        ...(row.dataset.id ? {id:Number(row.dataset.id)} : {}),
                        title: row.querySelector('.address-title').value.trim(),
                        city_id: row.querySelector('.address-city').value,
                        address: row.querySelector('.address-text').value.trim(),
                        is_primary: row.querySelector('.address-primary').checked
                    }))
                };
                if (!data.name || !data.phones.length || data.phones.some(item => !item.number)) {
                    Swal.showValidationMessage('أدخل اسم العميل ورقم هاتف واحدًا على الأقل.');
                    return false;
                }
                if (!data.addresses.length || data.addresses.some(item => !item.title || !item.city_id || !item.address)) {
                    Swal.showValidationMessage('أدخل عنوانًا واحدًا على الأقل وحدد المدينة لكل عنوان.');
                    return false;
                }
                if (data.phones.filter(item => item.is_primary).length > 1 || data.addresses.filter(item => item.is_primary).length > 1) {
                    Swal.showValidationMessage('حدد رقمًا افتراضيًا واحدًا وعنوانًا افتراضيًا واحدًا فقط.');
                    return false;
                }
                if (!data.phones.some(item => item.is_primary)) data.phones[0].is_primary = true;
                if (!data.addresses.some(item => item.is_primary)) data.addresses[0].is_primary = true;
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
                        body: JSON.stringify(customerId ? data : {
                            entity_type: data.entity_type,
                            name: data.name,
                            mobile: data.phones.find(item => item.type === 'mobile')?.number || data.phones[0].number,
                            email: data.email,
                            city_id: data.addresses.find(item => item.is_primary)?.city_id || data.addresses[0].city_id,
                            address: data.addresses.find(item => item.is_primary)?.address || data.addresses[0].address
                        })
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
        customerOption.textContent = customer.name;
        customerOption.dataset.name = customer.name;
        customerOption.dataset.entityType = customer.entity_type || 'individual';
        customerOption.dataset.mobile = customer.mobile || customer.phones?.[0]?.number || '';
        customerOption.dataset.email = customer.email || '';
        customerOption.dataset.address = firstAddress.address || '';
        customerPhones[customer.id] = (customer.phones || []).map(item => ({
            id: String(item.id), number: item.number, type: item.type,
            isPrimary: !!item.isPrimary, isWhatsapp: !!item.isWhatsapp, isTelegram: !!item.isTelegram
        }));
        customerAddresses[customer.id] = (customer.addresses || []).map(item => ({
            id: String(item.id), title: item.title, address: item.address,
            city: item.city, cityId: item.city_id ?? item.cityId,
            zone: item.zone || '', feeMin: item.feeMin ?? '',
            feeMax: item.feeMax ?? '', deliveryDays: item.deliveryDays ?? '',
            isPrimary: !!item.isPrimary
        }));
        $('customer_id').value = String(customer.id);
        $('customer_id').dispatchEvent(new Event('change'));
        $('edit-customer')?.removeAttribute('disabled');
        await loadCustomerDirectory(1, $('customer-search').value.trim());
        Swal.fire({ icon: 'success', text: 'تم حفظ بيانات العميل.', timer: 1400, showConfirmButton: false });
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
            renderAddressDetails(addresses, address.id);
        }
    });
    renderCustomerPhones();
    renderCustomerAddresses();


    const steps = ['step-customer', 'step-products', 'step-review'];
    const items = [];
    const money = value => (Number(value) || 0).toFixed(2);
    const show = id => {
        steps.forEach(step => $(step).classList.toggle('active', step === id));
        document.querySelectorAll('[data-order-step]').forEach((step, index) => {
            const currentIndex = steps.indexOf(id);
            step.classList.toggle('active', step.dataset.orderStep === id);
            step.classList.toggle('done', index < currentIndex);
        });
    };
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
        if (!$('customer_id').value || !$('shipping_phone_id').value || !$('shipping_address_id').value) {
            event.preventDefault();
            Swal.fire({ icon: 'warning', text: 'اختر العميل ورقم الهاتف وعنوان التوصيل أولًا.' });
            return;
        }
        if (!items.length) {
            event.preventDefault();
            Swal.fire({ icon: 'warning', text: 'أضف منتجًا واحدًا على الأقل.' });
        }
    });
})();
</script>
@endpush
