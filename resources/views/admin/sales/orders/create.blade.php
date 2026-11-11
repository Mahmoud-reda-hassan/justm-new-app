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
    .jm-choice-check{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border:1px solid #adb5bd;border-radius:4px;color:#28a745;font-weight:700;opacity:.25;margin:0 .35rem}
    .jm-choice-radio{position:absolute;opacity:0;pointer-events:none}
    .jm-choice-radio:checked + .jm-choice-check{opacity:1;border-color:#28a745;background:#e8f7ed}
    #customer-phone-legacy-group,#customer-address-legacy-group,#customer-contact-card,#customer-address-card,#customer-order-history,#edit-customer{display:none!important}
    .customer-directory-card .customer-directory-name{font-size:1rem}
    .customer-directory-card label{cursor:pointer}
    @media(max-width:575.98px){.order-actions .btn{flex:1 1 auto}}

/* Customer directory cards: responsive grid and mirrored contact controls. */
.jm-order-storefront-page #customer-directory-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.jm-order-storefront-page #customer-directory-cards>.col-xl-4{width:100%;max-width:none;padding:0;margin:0!important}
.jm-order-storefront-page .customer-directory-card{border-radius:12px;box-shadow:0 3px 12px rgba(25,55,90,.06)}
.jm-order-storefront-page .customer-contact-row{display:flex;direction:ltr;align-items:center;justify-content:space-between;gap:10px;padding:9px 0;border-bottom:1px solid #edf0f4}
.jm-order-storefront-page .customer-contact-choice{display:flex;direction:rtl;align-items:flex-start;gap:8px;min-width:0;margin:0;flex:1;cursor:pointer}
.jm-order-storefront-page .customer-contact-radio{width:17px;height:17px;flex:0 0 17px;margin:2px 0 0;accent-color:#1769d2;cursor:pointer}
.jm-order-storefront-page .customer-contact-value{font-size:13px;line-height:1.7;overflow-wrap:anywhere;color:#344761}
.jm-order-storefront-page .customer-contact-middle{display:flex;direction:ltr;align-items:center;justify-content:center;gap:12px;flex:0 0 auto;white-space:nowrap}
.jm-order-storefront-page .customer-contact-middle a{display:inline-flex;align-items:center}
.jm-order-storefront-page .customer-contact-edit{display:flex;align-items:center;justify-content:flex-start;flex:0 0 30px}
.jm-order-storefront-page .customer-default-check{display:inline-flex;align-items:center;justify-content:center;margin-inline-start:5px;font-size:14px;font-weight:800}
.jm-order-storefront-page .customer-card-section-header{direction:rtl}
 .jm-order-storefront-page .customer-card-section-header>button{margin-left:0!important}
.jm-order-storefront-page #step-products>.card-header,.jm-order-storefront-page #step-review>.card-header{display:none}
.jm-order-storefront-page #step-products .jm-store-layout{display:block;margin:0}
.jm-order-storefront-page #step-products .jm-store-products{width:100%;grid-column:auto;grid-row:auto}
.jm-order-storefront-page #step-review .jm-store-layout{display:block}
.jm-order-storefront-page #step-products>.card-body,.jm-order-storefront-page #step-review>.card-body{padding:0;background:transparent}
.jm-order-storefront-page .jm-wizard-taskbar .jm-order-step{cursor:pointer}
.jm-order-storefront-page #step-review .jm-store-cart{width:100%;position:static}
.jm-order-storefront-page #step-review .jm-store-bottom{grid-template-columns:repeat(3,minmax(0,1fr))}
@media(max-width:767.98px){.jm-order-storefront-page #step-review .jm-store-bottom{grid-template-columns:1fr}}
@media(max-width:1100px){.jm-order-storefront-page #customer-directory-cards{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:650px){.jm-order-storefront-page #customer-directory-cards{grid-template-columns:1fr}}
.jm-order-storefront-page .jm-store-layout{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(280px,.8fr);gap:16px;align-items:start}
.jm-order-storefront-page .jm-store-products,.jm-order-storefront-page .jm-store-cart{padding:14px;border:1px solid #dce5f0;border-radius:12px;background:#fff;min-width:0}
.jm-order-storefront-page .jm-store-product-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.jm-order-storefront-page .jm-store-product{border:1px solid #e0e8f2;border-radius:12px;overflow:hidden;background:#fff}
.jm-order-storefront-page .jm-store-product-image{height:120px;background:#f5f8fc;text-align:center}
.jm-order-storefront-page .jm-store-product-image img{max-width:100%;max-height:100%;object-fit:contain;padding:8px}
.jm-order-storefront-page .jm-store-cart{position:sticky;top:15px}
@media(max-width:991.98px){.jm-order-storefront-page .jm-store-layout{grid-template-columns:1fr}.jm-order-storefront-page .jm-store-product-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.jm-order-storefront-page .jm-store-cart{position:static}}
@media(max-width:575.98px){.jm-order-storefront-page .jm-store-product-grid{gap:7px}.jm-order-storefront-page .jm-store-product-image{height:90px}.jm-order-storefront-page .jm-store-products,.jm-order-storefront-page .jm-store-cart{padding:9px}}
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
    <input type="hidden" name="shipping_address_id" id="shipping_address_id">
    <input type="hidden" name="shipping_phone_id" id="shipping_phone_id">
    <section class="card card-outline card-primary order-step active" id="step-customer">
        <div class="card-header"><h3 class="card-title">1. بيانات العميل</h3></div>
        <div class="card-body">
            <p class="text-muted">اختر العميل ورقم الهاتف وعنوان التوصيل.</p>
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
                    <div class="form-group mt-2 mb-2" id="customer-phone-legacy-group">
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
                <div class="form-group col-md-5" id="customer-address-legacy-group">
                    <label for="customer-address-select">العناوين المحفوظة للعميل</label>
                    <select class="form-control d-none" id="customer-address-select" disabled><option value="">اختر العميل أولًا</option></select>
                </div>
                <div class="form-group col-md-7">
                    <div id="customer-address-card" class="card card-outline card-secondary mb-2 d-none">
                        <div class="card-header py-2"><strong>اختيار عنوان التوصيل</strong></div>
                        <div class="card-body py-2" id="customer-address-details"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="card card-outline card-primary order-step" id="step-products">
        <div class="card-header"><h3 class="card-title">2. اختيار المنتجات</h3></div>
        <div class="card-body">
            <div class="jm-client-step-intro">
                <h5>اختيار المنتجات</h5>
                <p>ابحث عن المنتج أو تصفح الأقسام، ثم افتح تفاصيله لاختيار المواصفات والكمية وسعر البيع.</p>
            </div>
            <div class="jm-store-layout" dir="rtl">
                <section class="jm-store-products" aria-label="كتالوج المنتجات">
                    <div class="jm-store-section-head">
                        <div><h5>اختار المنتجات</h5><p>ابحث عن المنتج أو تصفح الأقسام</p></div>
                        <span class="jm-store-count" id="jmProductCount">{{ $catalogProducts->count() }} منتج</span>
                    </div>
                    <div class="jm-store-tools">
                        <div class="jm-store-search"><i class="fas fa-search"></i><input id="jmStoreSearch" type="search" placeholder="ابحث باسم المنتج أو الكود أو المواصفات..."></div>
                        <div class="jm-store-categories" id="jmStoreCategories"><button type="button" class="active" data-category="all">كل الأقسام</button></div>
                    </div>
                    <div id="jmStoreProductGrid" class="jm-store-product-grid">
                        <div class="jm-store-loading"><i class="fas fa-spinner fa-spin"></i> جارٍ تجهيز المنتجات...</div>
                    </div>
                    <div id="jmProductDetails" class="jm-product-details" hidden></div>
                    <div class="jm-step1-selection-status">
                        <span><i class="fas fa-shopping-basket"></i> المنتجات المختارة</span>
                        <strong><span id="jmStep1CartCount">0</span> قطعة</strong>
                        <small>سيتم عرض تفاصيل السلة في خطوة مراجعة الطلب.</small>
                    </div>
                </section>
            </div>
        </div>
    </section>
    <section class="card card-outline card-primary order-step" id="step-review">
        <div class="card-header"><h3 class="card-title">3. مراجعة الطلب</h3></div>
        <div class="card-body">
            <div class="jm-client-step-intro">
                <h5>مراجعة السلة وإتمام الحفظ</h5>
                <p>راجع بيانات العميل والمنتجات ورسوم الشحن وموعده قبل حفظ أمر البيع.</p>
            </div>
            <div class="jm-client-review" id="review-customer-card">
                <div class="jm-client-review-head"><h5>بيانات العميل</h5><span>بيانات التواصل والتوصيل المختارة</span></div>
                <p class="mb-0"><strong id="review-customer-name">—</strong><br><span id="review-customer-phone">—</span><br><span id="review-customer-address">—</span></p>
            </div>
            <div class="jm-store-layout" dir="rtl">
                <aside class="jm-store-cart" aria-label="سلة الطلب">
                    <div class="jm-store-cart-head"><h5><i class="fas fa-shopping-cart"></i> سلة الطلب</h5><span id="store-cart-count">0</span></div>
                    <div id="store-cart-items" class="jm-cart-items" aria-live="polite">
                        <div class="jm-cart-empty-inline"><i class="fas fa-basket-shopping"></i><strong>السلة فارغة</strong><span>ارجع لاختيار المنتجات وأضف الأصناف المطلوبة.</span></div>
                    </div>
                </aside>
            </div>
            <div class="jm-store-bottom">
                <div class="col-lg-4">
                    <section class="jm-order-summary">
                        <p class="lead">ملخص الحساب</p>
                        <table class="table">
                            <tr><th style="width:48%">قيمة المنتجات:</th><td id="subtotal">0.00</td></tr>
                            <tr><th>إجمالي الخصم:</th><td id="discount-total">0.00</td></tr>
                            <tr>
                                <th>قيمة الشحن:</th>
                                <td>
                                    <input class="form-control form-control-sm" type="number" name="shipping_fee" id="shipping_fee" min="0" step="0.01" value="0" aria-describedby="shipping-route-hint">
                                    <small class="form-text text-muted" id="shipping-route-hint">تُحدد الرسوم حسب عنوان التوصيل.</small>
                                </td>
                            </tr>
                            <tr><th>الإجمالي النهائي:</th><td id="grand-total">0.00</td></tr>
                        </table>
                    </section>
                </div>
                <div class="col-lg-4">
                    <section class="jm-order-extra">
                        <div class="jm-order-extra-head"><b><i class="fas fa-truck"></i> بيانات التوصيل وموعد الشحن</b></div>
                        <div class="form-group">
                            <label for="shipping_address">عنوان التوصيل (يمكن تعديله لهذا الطلب)</label>
                            <textarea class="form-control" name="shipping_address" id="shipping_address" rows="2" maxlength="2000"></textarea>
                        </div>
                        <div class="form-group mb-0">
                            <label for="shipping_date">موعد الشحن المتوقع</label>
                            <input class="form-control" type="date" name="shipping_date" id="shipping_date" disabled>
                            <small class="form-text text-muted">يمكن تأجيل الموعد، ولا يمكن اختياره قبل مدة التوصيل المحددة للمنطقة.</small>
                        </div>
                    </section>
                </div>
                <div class="col-lg-4">
                    <section class="jm-order-extra">
                        <div class="jm-order-extra-head"><b><i class="fas fa-clipboard-list"></i> ملاحظات العميل</b></div>
                        <div class="form-group mb-0">
                            <label for="order-notes">ملاحظات</label>
                            <textarea class="form-control" id="order-notes" name="notes" rows="5" maxlength="5000"></textarea>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </section>

    <div class="jm-wizard-taskbar" role="region" aria-label="إجراءات أمر البيع">
        <nav class="jm-order-steps" aria-label="مراحل إنشاء أمر البيع">
            <div class="jm-order-step active" data-order-step="step-customer" role="button" tabindex="0"><span>1</span> بيانات العميل</div>
            <i aria-hidden="true"></i>
            <div class="jm-order-step" data-order-step="step-products" role="button" tabindex="0"><span>2</span> اختيار المنتجات</div>
            <i aria-hidden="true"></i>
            <div class="jm-order-step" data-order-step="step-review" role="button" tabindex="0"><span>3</span> مراجعة وحفظ</div>
        </nav>
        <div class="jm-wizard-navigation" dir="rtl">
            <span id="jmWizardHint" role="status">ابدأ باختيار العميل ورقم الهاتف وعنوان التوصيل.</span>
            <button type="button" id="jmWizardNext" class="btn btn-primary">اختيار المنتجات <i class="fas fa-arrow-left"></i></button>
        </div>
        <div class="order-foot">
            <button type="submit" class="btn btn-success" id="save-order"><i class="fas fa-save ml-1"></i> حفظ أمر البيع كمسودة</button>
        </div>
    </div>
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
            radio.className = 'jm-choice-radio';
            const check = document.createElement('span');
            check.className = 'jm-choice-check';
            check.textContent = '✓';
            radio.addEventListener('change', () => {
                $('customer-phone-select').value = String(phone.id);
                $('shipping_phone_id').value = String(phone.id);
            });
            const number = document.createElement('span');
            number.textContent = phone.number + (phone.isPrimary ? ' ★' : '');
            label.append(radio, check, number);
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
            radio.className = 'jm-choice-radio';
            const check = document.createElement('span');
            check.className = 'jm-choice-check';
            check.textContent = '✓';
            radio.addEventListener('change', () => {
                $('customer-address-select').value = String(address.id);
                $('shipping_address_id').value = String(address.id);
                $('shipping_address').value = address.address;
                applyShippingRoute(address);
            });
            const text = document.createElement('span');
            text.textContent = address.title + (address.isPrimary ? ' ★' : '') +
                (address.city ? ' — ' + address.city : '') + ' — ' + address.address;
            label.append(radio, check, text); box.appendChild(label);
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
            return true;
        } catch (error) {
            message.textContent = 'تعذر تحميل بيانات العميل. حاول مرة أخرى.';
            return false;
        }
    };

    const canEditCustomer = @json(auth()->user()?->hasPermission('parties.update') && auth()->user()?->hasPermission('structure.scope.manage_all'));

    const makeDirectoryCard = customer => {
        const col = document.createElement('div');
        col.className = 'col-xl-4 col-lg-6 col-12 mb-3';
        const card = document.createElement('div');
        card.className = 'card customer-directory-card border h-100';
        const body = document.createElement('div');
        body.className = 'card-body p-3';

        const heading = document.createElement('div');
        heading.className = 'd-flex justify-content-between align-items-center mb-3';
        const nameArea = document.createElement('div');
        nameArea.className = 'd-flex align-items-center flex-wrap';
        const name = document.createElement('strong');
        name.className = 'customer-directory-name';
        name.textContent = customer.name;
        nameArea.appendChild(name);
        if (customer.is_most_delivered) {
            const topBadge = document.createElement('span');
            topBadge.className = 'badge badge-success mr-2';
            topBadge.textContent = 'الأكثر تسليمًا';
            nameArea.appendChild(topBadge);
        }
        heading.appendChild(nameArea);
        if (canEditCustomer) {
            const editName = document.createElement('button');
            editName.type = 'button';
            editName.className = 'btn btn-sm btn-link text-primary p-1';
            editName.title = 'تعديل اسم العميل';
            editName.setAttribute('aria-label', 'تعديل اسم العميل');
            editName.innerHTML = '<i class="fas fa-edit"></i>';
            editName.addEventListener('click', async () => {
                const loaded = await selectCustomerFromDirectory(customer.id);
                if (loaded) openCustomerEditor(customer.id);
            });
            heading.appendChild(editName);
        }
        body.appendChild(heading);

        const makeSectionHeader = (title, addTitle, onAdd) => {
            const header = document.createElement('div');
            header.className = 'd-flex justify-content-between align-items-center mt-3 mb-1 customer-card-section-header';
            const caption = document.createElement('strong');
            caption.textContent = title;
            const add = document.createElement('button');
            add.type = 'button';
            add.className = 'btn btn-sm btn-link text-primary p-1';
            add.title = addTitle;
            add.setAttribute('aria-label', addTitle);
            add.innerHTML = '<i class="fas fa-plus-circle fa-lg"></i>';
            add.addEventListener('click', onAdd);
            if (canEditCustomer) header.append(caption, add);
            else header.appendChild(caption);
            return header;
        };
        const openEditor = async () => {
            const loaded = await selectCustomerFromDirectory(customer.id);
            if (loaded) openCustomerEditor(customer.id);
        };

        body.appendChild(makeSectionHeader('أرقام الهواتف المتاحة', 'إضافة رقم', openEditor));
        const phoneList = document.createElement('div');
        phoneList.className = 'customer-card-phone-list';
        const phones = customer.phones || [];
        if (!phones.length) {
            const empty = document.createElement('div');
            empty.className = 'text-muted small py-2';
            empty.textContent = 'لا يوجد رقم هاتف نشط';
            phoneList.appendChild(empty);
        }
        phones.forEach(phone => {
            const row = document.createElement('div');
            row.className = 'customer-contact-row';

            // Right: selection circle, phone number, and default check.
            const right = document.createElement('label');
            right.className = 'customer-contact-choice';
            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'directory-phone-' + customer.id;
            radio.className = 'customer-contact-radio';
            radio.value = String(phone.id);
            radio.checked = !!phone.isPrimary || (!phones.some(item => item.isPrimary) && phones[0]?.id === phone.id);
            const number = document.createElement('span');
            number.className = 'customer-contact-value';
            number.textContent = phone.number;
            right.appendChild(radio);
            right.appendChild(number);
            if (phone.isPrimary) {
                const def = document.createElement('span');
                def.className = 'customer-default-check text-success';
                def.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i>';
                def.title = 'الرقم الافتراضي';
                def.setAttribute('aria-label', 'الرقم الافتراضي');
                right.appendChild(def);
            }

            // Middle: WhatsApp and Telegram icons only when enabled for this number.
            const middle = document.createElement('div');
            middle.className = 'customer-contact-middle';
            if (phone.isWhatsapp) {
                const a = document.createElement('a');
                a.href = 'https://wa.me/' + String(phone.number).replace(/\\D/g, '');
                a.target = '_blank'; a.rel = 'noopener noreferrer';
                a.className = 'text-success'; a.title = 'واتساب';
                a.innerHTML = '<i class="fab fa-whatsapp fa-lg"></i>';
                middle.appendChild(a);
            }
            if (phone.isTelegram) {
                const a = document.createElement('a');
                a.href = 'https://t.me/+' + String(phone.number).replace(/\\D/g, '');
                a.target = '_blank'; a.rel = 'noopener noreferrer';
                a.className = 'text-info'; a.title = 'تيليجرام';
                a.innerHTML = '<i class="fab fa-telegram fa-lg"></i>';
                middle.appendChild(a);
            }

            // Left: edit icon.
            const left = document.createElement('div');
            left.className = 'customer-contact-edit';
            const edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'btn btn-sm btn-link text-secondary p-1';
            edit.title = 'تعديل رقم الهاتف';
            edit.setAttribute('aria-label', 'تعديل رقم الهاتف');
            edit.innerHTML = '<i class="fas fa-edit"></i>';
            edit.addEventListener('click', openEditor);
            if (canEditCustomer) left.appendChild(edit);

            row.append(left, middle, right);
            phoneList.appendChild(row);
        });
        body.appendChild(phoneList);

        body.appendChild(makeSectionHeader('العناوين المتاحة', 'إضافة عنوان', openEditor));
        const addressList = document.createElement('div');
        addressList.className = 'customer-card-address-list';
        const addresses = customer.addresses || [];
        if (!addresses.length) {
            const empty = document.createElement('div');
            empty.className = 'text-muted small py-2';
            empty.textContent = 'لا يوجد عنوان نشط';
            addressList.appendChild(empty);
        }
        addresses.forEach(address => {
            const row = document.createElement('div');
            row.className = 'customer-contact-row customer-address-row';
            const right = document.createElement('label');
            right.className = 'customer-contact-choice';
            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'directory-address-' + customer.id;
            radio.className = 'customer-contact-radio';
            radio.value = String(address.id);
            radio.checked = !!address.isPrimary || (!addresses.some(item => item.isPrimary) && addresses[0]?.id === address.id);
            const text = document.createElement('span');
            text.className = 'customer-contact-value';
            text.textContent = (address.city ? address.city + ' — ' : '') + address.address;
            right.append(radio, text);
            if (address.isPrimary) {
                const def = document.createElement('span');
                def.className = 'customer-default-check text-success';
                def.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i>';
                def.title = 'العنوان الافتراضي';
                def.setAttribute('aria-label', 'العنوان الافتراضي');
                right.appendChild(def);
            }
            const left = document.createElement('div');
            left.className = 'customer-contact-actions';
            const edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'btn btn-sm btn-link text-secondary p-1';
            edit.title = 'تعديل العنوان';
            edit.setAttribute('aria-label', 'تعديل العنوان');
            edit.innerHTML = '<i class="fas fa-edit"></i>';
            edit.addEventListener('click', openEditor);
            left.appendChild(edit);
            row.append(left, right);
            addressList.appendChild(row);
        });
        body.appendChild(addressList);

        const orderStats = document.createElement('div');
        orderStats.className = 'd-flex flex-wrap mt-3';
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
        body.appendChild(orderStats);

        const actions = document.createElement('div');
        actions.className = 'd-flex mt-3';
        const choose = document.createElement('button');
        choose.type = 'button';
        choose.className = 'btn btn-outline-primary flex-fill';
        choose.textContent = 'اختيار العميل';
        choose.addEventListener('click', async () => {
            const selectedPhone = card.querySelector('input[name="directory-phone-' + customer.id + '"]:checked')?.value;
            const selectedAddress = card.querySelector('input[name="directory-address-' + customer.id + '"]:checked')?.value;
            const loaded = await selectCustomerFromDirectory(customer.id);
            if (!loaded) return;
            const phone = (customerPhones[customer.id] || []).find(item => String(item.id) === String(selectedPhone))
                || (customerPhones[customer.id] || []).find(item => item.isPrimary)
                || (customerPhones[customer.id] || [])[0];
            const address = (customerAddresses[customer.id] || []).find(item => String(item.id) === String(selectedAddress))
                || (customerAddresses[customer.id] || []).find(item => item.isPrimary)
                || (customerAddresses[customer.id] || [])[0];
            if (!phone || !address || !address.zone) {
                await Swal.fire({icon:'warning', text:'لا يمكن المتابعة قبل تحديد رقم هاتف وعنوان مرتبط بمنطقة شحن نشطة.'});
                return;
            }
            $('customer-phone-select').value = String(phone.id);
            $('shipping_phone_id').value = String(phone.id);
            $('customer-address-select').value = String(address.id);
            $('shipping_address_id').value = String(address.id);
            $('shipping_address').value = address.address;
            renderPhoneDetails(customerPhones[customer.id], phone.id);
            renderAddressDetails(customerAddresses[customer.id], address.id);
            applyShippingRoute(address);
            show('step-products');
        });
        actions.appendChild(choose);
        if (canEditCustomer) {
            const edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'btn btn-outline-secondary mr-2';
            edit.innerHTML = '<i class="fas fa-edit ml-1"></i> تعديل بيانات';
            edit.addEventListener('click', openEditor);
            actions.appendChild(edit);
        }
        body.appendChild(actions);
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
        const searchCard = $('customer-search').closest('.card');
        searchCard?.classList.add('d-none');
        directoryCards.classList.add('d-none');
        directoryPagination.classList.add('d-none');
        const editor = document.createElement('div');
        editor.className = 'card card-outline card-primary mt-2';
        editor.id = 'customer-inline-editor';
        const cityOptions = Object.entries(activeCities).map(([id,name]) =>
            '<option value="' + escapeHtml(id) + '">' + escapeHtml(name) + '</option>'
        ).join('');
        editor.innerHTML = '<div class="card-header"><strong>' + (customerId ? 'تعديل بيانات العميل' : 'إضافة عميل') + '</strong></div>'
            + '<div class="card-body"><div class="form-group"><label>اسم العميل</label><input id="inline-customer-name" class="form-control" maxlength="180" value="' + escapeHtml(option?.dataset.name || '') + '"></div>'
            + '<div class="form-group"><label>البريد الإلكتروني (اختياري)</label><input id="inline-customer-email" type="email" class="form-control" value="' + escapeHtml(option?.dataset.email || '') + '"></div>'
            + '<div class="d-flex justify-content-between align-items-center mb-2"><strong>أرقام الهواتف</strong><button type="button" class="btn btn-sm btn-outline-primary" id="inline-add-phone">إضافة هاتف</button></div><div id="inline-phone-list"></div>'
            + '<div class="d-flex justify-content-between align-items-center mb-2 mt-3"><strong>العناوين</strong><button type="button" class="btn btn-sm btn-outline-primary" id="inline-add-address">إضافة عنوان</button></div><div id="inline-address-list"></div>'
            + '</div><div class="card-footer d-flex flex-wrap"><button type="button" class="btn btn-primary ml-2" id="inline-save-customer">حفظ البيانات</button><button type="button" class="btn btn-default" id="inline-cancel-customer">إلغاء</button></div>';
        directoryCards.parentElement.appendChild(editor);
        const phoneList = editor.querySelector('#inline-phone-list');
        const addressList = editor.querySelector('#inline-address-list');
        const addPhone = phone => {
            const row = document.createElement('div');
            row.className = 'border rounded p-2 mb-2 inline-phone-row';
            row.dataset.id = phone?.id || '';
            row.innerHTML = '<div class="form-row align-items-center"><div class="col-md-4 mb-2"><label>رقم الهاتف</label><input class="form-control p-number" value="' + escapeHtml(phone?.number || '') + '" maxlength="40"></div>'
                + '<div class="col-md-2 mb-2"><label>النوع</label><select class="form-control p-type"><option value="mobile">محمول</option><option value="landline">أرضي</option></select></div>'
                + '<div class="col-md-3 mb-2"><label class="d-block">التطبيقات</label><label class="mr-2"><input type="checkbox" class="p-wa"> واتساب</label><label><input type="checkbox" class="p-tg"> تيليجرام</label></div>'
                + '<div class="col-md-2 mb-2"><label><input type="radio" name="inline-primary-phone" class="p-primary"> افتراضي</label></div><div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger p-remove">حذف</button></div></div>';
            row.querySelector('.p-type').value = phone?.type || 'mobile';
            row.querySelector('.p-wa').checked = !!phone?.isWhatsapp;
            row.querySelector('.p-tg').checked = !!phone?.isTelegram;
            row.querySelector('.p-primary').checked = !!phone?.isPrimary;
            row.querySelector('.p-remove').addEventListener('click',()=>row.remove());
            phoneList.appendChild(row);
        };
        const addAddress = address => {
            const row = document.createElement('div');
            row.className = 'border rounded p-2 mb-2 inline-address-row';
            row.dataset.id = address?.id || '';
            row.innerHTML = '<div class="form-row"><div class="col-md-3 mb-2"><label>اسم العنوان</label><input class="form-control a-title" value="' + escapeHtml(address?.title || 'عنوان جديد') + '" maxlength="80"></div>'
                + '<div class="col-md-3 mb-2"><label>المدينة</label><select class="form-control a-city"><option value="">اختر المدينة</option>' + cityOptions + '</select></div>'
                + '<div class="col-md-5 mb-2"><label>العنوان بالتفصيل</label><textarea class="form-control a-text" rows="2" maxlength="4000">' + escapeHtml(address?.address || '') + '</textarea></div>'
                + '<div class="col-md-1 d-flex flex-column justify-content-between"><label><input type="radio" name="inline-primary-address" class="a-primary"> افتراضي</label><button type="button" class="btn btn-sm btn-outline-danger a-remove">حذف</button></div></div>';
            row.querySelector('.a-city').value = String(address?.cityId || '');
            row.querySelector('.a-primary').checked = !!address?.isPrimary;
            row.querySelector('.a-remove').addEventListener('click',()=>row.remove());
            addressList.appendChild(row);
        };
        phones.forEach(addPhone);
        addresses.forEach(addAddress);
        if (!phones.length) addPhone({isPrimary:true,type:'mobile'});
        if (!addresses.length) addAddress({isPrimary:true,title:'العنوان الرئيسي'});
        editor.querySelector('#inline-add-phone').addEventListener('click',()=>addPhone({type:'mobile'}));
        editor.querySelector('#inline-add-address').addEventListener('click',()=>addAddress({title:'عنوان جديد'}));
        const closeEditor = () => {
            editor.remove();
            searchCard?.classList.remove('d-none');
            directoryCards.classList.remove('d-none');
            directoryPagination.classList.remove('d-none');
        };
        editor.querySelector('#inline-cancel-customer').addEventListener('click',closeEditor);
        editor.querySelector('#inline-save-customer').addEventListener('click',async()=>{
            const phoneRows = Array.from(editor.querySelectorAll('.inline-phone-row'));
            const addressRows = Array.from(editor.querySelectorAll('.inline-address-row'));
            const payload = {
                entity_type: option?.dataset.entityType || 'individual',
                name: editor.querySelector('#inline-customer-name').value.trim(),
                email: editor.querySelector('#inline-customer-email').value.trim(),
                phones: phoneRows.map(row=>({
                    ...(row.dataset.id ? {id:Number(row.dataset.id)} : {}),
                    number:row.querySelector('.p-number').value.trim(),
                    type:row.querySelector('.p-type').value,
                    is_whatsapp:row.querySelector('.p-wa').checked,
                    is_telegram:row.querySelector('.p-tg').checked,
                    is_primary:row.querySelector('.p-primary').checked
                })),
                addresses:addressRows.map(row=>({
                    ...(row.dataset.id ? {id:Number(row.dataset.id)} : {}),
                    title:row.querySelector('.a-title').value.trim(),
                    city_id:row.querySelector('.a-city').value,
                    address:row.querySelector('.a-text').value.trim(),
                    is_primary:row.querySelector('.a-primary').checked
                }))
            };
            if(!payload.name || !payload.phones.length || payload.phones.some(p=>!p.number) || !payload.addresses.length || payload.addresses.some(a=>!a.city_id || !a.address || !a.title)){
                Swal.fire({icon:'warning',text:'أكمل اسم العميل ورقم هاتف واحدًا على الأقل وعنوانًا صحيحًا واحدًا على الأقل.'}); return;
            }
            if(payload.phones.filter(p=>p.is_primary).length>1 || payload.addresses.filter(a=>a.is_primary).length>1){
                Swal.fire({icon:'warning',text:'حدد رقمًا افتراضيًا واحدًا وعنوانًا افتراضيًا واحدًا فقط.'}); return;
            }
            if(!payload.phones.some(p=>p.is_primary)) payload.phones[0].is_primary=true;
            if(!payload.addresses.some(a=>a.is_primary)) payload.addresses[0].is_primary=true;
            const button=editor.querySelector('#inline-save-customer');
            button.disabled=true;
            try{
                const url=customerId?updateCustomerUrl.replace('__PARTY__',encodeURIComponent(customerId)):customerEditorUrl;
                const body=customerId?payload:{
                    entity_type:payload.entity_type,name:payload.name,email:payload.email,
                    mobile:payload.phones.find(p=>p.type==='mobile')?.number || payload.phones[0].number,
                    city_id:payload.addresses.find(a=>a.is_primary)?.city_id || payload.addresses[0].city_id,
                    address:payload.addresses.find(a=>a.is_primary)?.address || payload.addresses[0].address
                };
                const response=await fetch(url,{method:customerId?'PUT':'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrfToken,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(body)});
                const result=await response.json();
                if(!response.ok) throw new Error(result.message || (result.errors ? Object.values(result.errors).flat()[0] : 'تعذر حفظ بيانات العميل.'));
                const saved=result.customer;
                let customerOption=Array.from($('customer_id').options).find(item=>item.value===String(saved.id));
                if(!customerOption){customerOption=new Option(saved.name,saved.id);$('customer_id').appendChild(customerOption);customerOptions.push(customerOption);}
                customerOption.textContent=saved.name;
                customerOption.dataset.name=saved.name;
                customerOption.dataset.entityType=saved.entity_type || 'individual';
                customerOption.dataset.mobile=saved.mobile || saved.phones?.[0]?.number || '';
                customerOption.dataset.email=saved.email || '';
                customerPhones[saved.id]=(saved.phones||[]).map(p=>({id:String(p.id),number:p.number,type:p.type,isPrimary:!!p.isPrimary,isWhatsapp:!!p.isWhatsapp,isTelegram:!!p.isTelegram}));
                customerAddresses[saved.id]=(saved.addresses||[]).map(a=>({id:String(a.id),title:a.title,address:a.address,city:a.city,cityId:a.city_id??a.cityId,zone:a.zone||'',feeMin:a.feeMin??'',feeMax:a.feeMax??'',deliveryDays:a.deliveryDays??'',isPrimary:!!a.isPrimary}));
                $('customer_id').value=String(saved.id);
                $('customer_id').dispatchEvent(new Event('change'));
                closeEditor();
                await loadCustomerDirectory(1,$('customer-search').value.trim());
                Swal.fire({icon:'success',text:'تم حفظ بيانات العميل.',timer:1400,showConfirmButton:false});
            }catch(error){
                Swal.fire({icon:'error',text:error.message || 'تعذر حفظ بيانات العميل.'});
                button.disabled=false;
            }
        });
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
    const catalogProducts = @json($catalogProducts);
    const items = [];
    const money = value => (Number(value) || 0).toFixed(2);
    const escape = escapeHtml;
    let activeProduct = null;
    let activeVariant = null;
    let editingIndex = null;
    let activeCategory = 'all';

    const productSearchText = product => [
        product.name, product.category,
        ...(product.variants || []).flatMap(variant => [
            variant.sku, variant.variant_name,
            ...(variant.attributes || []).map(attribute => attribute.value)
        ])
    ].join(' ').toLocaleLowerCase('ar');

    const renderCategories = () => {
        const names = [...new Set(catalogProducts.map(product => product.category).filter(Boolean))].sort((a,b)=>a.localeCompare(b,'ar'));
        $('jmStoreCategories').innerHTML = '<button type="button" class="active" data-category="all">كل الأقسام</button>'
            + names.map(name => '<button type="button" data-category="' + escape(name) + '">' + escape(name) + '</button>').join('');
    };

    const renderCatalog = () => {
        const term = $('jmStoreSearch').value.trim().toLocaleLowerCase('ar');
        const filtered = catalogProducts.filter(product =>
            (activeCategory === 'all' || product.category === activeCategory)
            && (!term || productSearchText(product).includes(term))
        );
        $('jmProductCount').textContent = filtered.length + ' من ' + catalogProducts.length + ' منتج';
        if (!filtered.length) {
            $('jmStoreProductGrid').innerHTML = '<div class="jm-store-empty-results"><i class="fas fa-search"></i><strong>لا توجد نتائج مطابقة</strong><span>جرّب اسمًا آخر أو اختر قسمًا مختلفًا.</span></div>';
            return;
        }
        $('jmStoreProductGrid').innerHTML = filtered.map(product => {
            const prices = product.variants.map(variant => Number(variant.list_price) || 0);
            const min = Math.min(...prices), max = Math.max(...prices);
            const priceText = min === max ? money(min) : money(min) + ' - ' + money(max);
            const image = product.image || '/img/product-default-3d.svg';
            return '<article class="jm-store-product">'
                + '<div class="jm-store-product-image"><img src="' + escape(image) + '" alt="' + escape(product.name) + '" loading="lazy" onerror="this.onerror=null;this.src=\'/img/product-default-3d.svg\';"></div>'
                + '<div class="jm-store-product-body"><div class="jm-store-product-name">' + escape(product.name) + '</div>'
                + '<div class="jm-store-product-category">' + escape(product.category || 'بدون قسم') + '</div>'
                + '<div class="jm-store-product-meta"><span>' + product.variants.length + ' اختيار متاح</span><span class="jm-store-product-price">' + priceText + ' <small>ج.م</small></span></div>'
                + '<button type="button" class="jm-store-add" data-open-product="' + escape(product.id) + '"><i class="fas fa-cart-plus"></i> تفاصيل وإضافة</button></div></article>';
        }).join('');
    };

    const closeProductDetails = () => {
        $('jmProductDetails').hidden = true;
        $('jmProductDetails').innerHTML = '';
        $('jmStoreProductGrid').hidden = false;
        document.querySelector('.jm-store-tools').hidden = false;
        document.querySelector('.jm-store-section-head').hidden = false;
        activeProduct = null;
        activeVariant = null;
        editingIndex = null;
    };

    const openProductDetails = (productId, editIndex = null) => {
        const product = catalogProducts.find(item => String(item.id) === String(productId));
        if (!product) return;
        activeProduct = product;
        editingIndex = editIndex;
        activeVariant = null;
        const groups = new Map();
        product.variants.forEach(variant => (variant.attributes || []).forEach(attribute => {
            if (!groups.has(String(attribute.group_id))) {
                groups.set(String(attribute.group_id), {id:String(attribute.group_id), name:attribute.group_name, values:new Map()});
            }
            groups.get(String(attribute.group_id)).values.set(String(attribute.value_id), attribute.value);
        }));
        const groupRows = [...groups.values()];
        const firstImage = product.image || '/img/product-default-3d.svg';
        const selectors = groupRows.map(group =>
            '<div class="form-group"><label>' + escape(group.name) + '</label><select class="form-control jm-variant-select" data-group-id="' + escape(group.id) + '"><option value="">اختر ' + escape(group.name) + '</option>'
            + [...group.values.entries()].map(([id,value])=>'<option value="' + escape(id) + '">' + escape(value) + '</option>').join('')
            + '</select></div>'
        ).join('');
        const existing = editIndex !== null ? items[editIndex] : null;
        $('jmStoreProductGrid').hidden = true;
        document.querySelector('.jm-store-tools').hidden = true;
        document.querySelector('.jm-store-section-head').hidden = true;
        $('jmProductDetails').hidden = false;
        $('jmProductDetails').innerHTML =
            '<div class="jm-detail-top"><button type="button" class="btn btn-default jm-detail-back"><i class="fas fa-arrow-right"></i> رجوع للمنتجات</button><span class="jm-detail-step">تفاصيل المنتج</span></div>'
            + '<div class="row jm-detail-layout" dir="rtl"><div class="col-lg-6"><div class="jm-gallery"><div class="jm-gallery-main"><img id="jmGalleryMain" src="' + escape(firstImage) + '" alt="' + escape(product.name) + '" onerror="this.onerror=null;this.src=\'/img/product-default-3d.svg\';"></div></div></div>'
            + '<div class="col-lg-6"><div class="jm-detail-info"><div class="jm-detail-category">' + escape(product.category || '') + '</div><h3>' + escape(product.name) + '</h3><p class="jm-detail-description">' + escape(product.description || '') + '</p><div class="jm-detail-divider"></div>'
            + (groupRows.length ? '<h5>اختر مواصفات المنتج</h5><div class="jm-detail-variants">' + selectors + '</div>' : '')
            + '<div id="jmVariantStatus" class="jm-variant-status" role="status">' + (groupRows.length ? 'اختر جميع المواصفات لعرض السعر.' : '') + '</div>'
            + '<div class="jm-detail-controls" id="jmDetailControls" hidden><div class="jm-price-range-info" id="jmPriceRangeInfo"></div><div class="row"><div class="col-12"><label for="jmDetailPrice">سعر البيع النهائي للوحدة</label><input id="jmDetailPrice" class="form-control" type="number" min="0" step="0.01"></div>'
            + '<div class="col-6 mt-3"><label>الكمية المطلوبة</label><div class="input-group"><div class="input-group-prepend"><button type="button" class="btn btn-outline-secondary" id="jmQtyMinus">−</button></div><input id="jmDetailQty" class="form-control text-center" type="number" min="0.001" step="0.001" value="1"><div class="input-group-append"><button type="button" class="btn btn-outline-secondary" id="jmQtyPlus">+</button></div></div></div>'
            + '<div class="col-6 mt-3"><label>كود المنتج</label><div class="jm-stock-value" id="jmDetailSku">—</div></div></div>'
            + '<div class="jm-detail-total"><span>إجمالي الصنف بعد الخصم</span><strong id="jmDetailTotal">0.00 ج.م</strong></div></div>'
            + '<div class="jm-detail-actions"><button type="button" id="jmAddConfiguredProduct" class="btn btn-primary btn-lg" disabled><i class="fas fa-cart-plus"></i> ' + (existing ? 'حفظ التعديلات' : 'إضافة إلى السلة') + '</button></div></div></div></div>';

        if (groupRows.length) {
            $('jmProductDetails').querySelectorAll('.jm-variant-select').forEach(select => select.addEventListener('change', resolveVariant));
        } else {
            activateVariant(product.variants[0], existing);
        }
        if (existing) {
            groupRows.forEach(group => {
                const attribute = existing.attributes.find(item => String(item.group_id) === group.id);
                const select = $('jmProductDetails').querySelector('[data-group-id="' + group.id + '"]');
                if (select && attribute) select.value = String(attribute.value_id);
            });
            resolveVariant();
        }
        $('jmProductDetails').querySelector('#jmAddConfiguredProduct').addEventListener('click', commitConfiguredProduct);
        $('jmProductDetails').querySelector('#jmDetailPrice').addEventListener('input', updateDetailTotal);
        $('jmProductDetails').querySelector('#jmDetailQty').addEventListener('input', updateDetailTotal);
        $('jmProductDetails').querySelector('.jm-detail-back').addEventListener('click', closeProductDetails);
        $('jmProductDetails').querySelector('#jmQtyMinus').addEventListener('click', () => {
            $('jmDetailQty').value = Math.max(.001, (Number($('jmDetailQty').value) || 1) - 1);
            updateDetailTotal();
        });
        $('jmProductDetails').querySelector('#jmQtyPlus').addEventListener('click', () => {
            $('jmDetailQty').value = (Number($('jmDetailQty').value) || 0) + 1;
            updateDetailTotal();
        });
    };

    const resolveVariant = () => {
        if (!activeProduct) return;
        const selected = {};
        let missing = false;
        $('jmProductDetails').querySelectorAll('.jm-variant-select').forEach(select => {
            selected[select.dataset.groupId] = select.value;
            if (!select.value) missing = true;
        });
        if (missing) {
            $('jmDetailControls').hidden = true;
            $('jmVariantStatus').textContent = 'اختر جميع المواصفات لعرض السعر.';
            $('jmAddConfiguredProduct').disabled = true;
            return;
        }
        const variant = activeProduct.variants.find(item =>
            (item.attributes || []).length === Object.keys(selected).length
            && item.attributes.every(attribute => selected[String(attribute.group_id)] === String(attribute.value_id))
        );
        if (!variant) {
            $('jmDetailControls').hidden = true;
            $('jmVariantStatus').textContent = 'التركيبة المحددة غير متاحة.';
            $('jmVariantStatus').classList.add('text-danger');
            $('jmAddConfiguredProduct').disabled = true;
            return;
        }
        activateVariant(variant, editingIndex !== null ? items[editingIndex] : null);
    };

    const activateVariant = (variant, existing = null) => {
        if (!variant) return;
        activeVariant = variant;
        $('jmDetailControls').hidden = false;
        $('jmVariantStatus').textContent = 'تم تحديد كود المنتج. أدخل الكمية وسعر البيع.';
        $('jmVariantStatus').classList.remove('text-danger');
        $('jmDetailSku').textContent = variant.sku;
        $('jmDetailPrice').min = variant.minimum_sale_price;
        $('jmDetailPrice').max = variant.list_price;
        $('jmDetailPrice').value = existing && String(existing.id) === String(variant.id) ? existing.price : variant.list_price;
        $('jmDetailQty').value = existing && String(existing.id) === String(variant.id) ? existing.quantity : 1;
        $('jmPriceRangeInfo').textContent = 'السعر المسموح من ' + money(variant.minimum_sale_price) + ' إلى ' + money(variant.list_price) + ' جنيه.';
        updateDetailTotal();
    };

    const updateDetailTotal = () => {
        if (!activeVariant) return;
        const price = Number($('jmDetailPrice').value);
        const quantity = Number($('jmDetailQty').value);
        const validPrice = Number.isFinite(price) && price >= Number(activeVariant.minimum_sale_price) && price <= Number(activeVariant.list_price);
        const validQuantity = Number.isFinite(quantity) && quantity > 0;
        $('jmDetailPrice').classList.toggle('is-invalid', !validPrice);
        $('jmDetailTotal').textContent = validPrice && validQuantity ? money(price * quantity) + ' ج.م' : '0.00 ج.م';
        $('jmAddConfiguredProduct').disabled = !(validPrice && validQuantity);
        if (!validPrice) $('jmVariantStatus').textContent = 'سعر البيع خارج النطاق المسموح.';
        else if (!validQuantity) $('jmVariantStatus').textContent = 'أدخل كمية صحيحة أكبر من صفر.';
        else $('jmVariantStatus').textContent = 'السعر والكمية صحيحان.';
    };

    const renderCart = () => {
        const count = items.reduce((sum,item)=>sum+Number(item.quantity||0),0);
        $('store-cart-count').textContent = Number.isInteger(count) ? count : count.toFixed(2);
        $('jmStep1CartCount').textContent = Number.isInteger(count) ? count : count.toFixed(2);
        if (!items.length) {
            $('store-cart-items').innerHTML = '<div class="jm-cart-empty-inline"><i class="fas fa-basket-shopping"></i><strong>السلة فارغة</strong><span>ارجع لاختيار المنتجات وأضف الأصناف المطلوبة.</span></div>';
            return;
        }
        $('store-cart-items').innerHTML = items.map((item,index) => {
            const options = (item.attributes || []).map(attribute=>attribute.group_name + ': ' + attribute.value).join(' · ');
            const line = Number(item.price) * Number(item.quantity);
            return '<article class="jm-cart-item"><div class="jm-cart-item-image"><img src="' + escape(item.image || '/img/product-default-3d.svg') + '" alt="' + escape(item.name) + '" loading="lazy" onerror="this.onerror=null;this.src=\'/img/product-default-3d.svg\';"></div>'
                + '<div class="jm-cart-item-main"><div class="jm-cart-item-title-row"><div><strong>' + escape(item.name) + '</strong><small>' + escape(item.category || '') + '</small></div><span class="jm-cart-item-code">' + escape(item.sku) + '</span></div>'
                + (options ? '<div class="jm-cart-item-options">' + escape(options) + '</div>' : '')
                + '<div class="jm-cart-item-data"><span>الكمية <b>' + escape(item.quantity) + '</b></span><span>سعر الوحدة <b>' + money(item.price) + ' ج.م</b></span>'
                + (Number(item.list) > Number(item.price) ? '<span>خصم الوحدة <b>' + money(Number(item.list)-Number(item.price)) + ' ج.م</b></span>' : '')
                + '<strong class="jm-cart-item-total">' + money(line) + ' ج.م</strong></div>'
                + '<div class="jm-cart-item-actions"><button type="button" data-edit-cart="' + index + '" class="btn"><i class="fas fa-pen"></i> تعديل</button><button type="button" data-delete-cart="' + index + '" class="btn jm-delete-cart-product"><i class="fas fa-trash-alt"></i> حذف</button></div></div></article>';
        }).join('');
    };

    const renderCustomerSummary = () => {
        const customerId = $('customer_id').value;
        const customer = $('customer_id').selectedOptions[0];
        const phone = (customerPhones[customerId] || []).find(item => String(item.id) === $('shipping_phone_id').value);
        const address = (customerAddresses[customerId] || []).find(item => String(item.id) === $('shipping_address_id').value);
        $('review-customer-name').textContent = customer?.textContent || '—';
        $('review-customer-phone').textContent = phone?.number || '—';
        $('review-customer-address').textContent = address ? [address.title,address.city,address.address].filter(Boolean).join(' — ') : '—';
    };

    const renderReview = () => {
        let subtotal = 0, discountTotal = 0;
        items.forEach(item => {
            subtotal += Number(item.price) * Number(item.quantity);
            discountTotal += (Number(item.list) - Number(item.price)) * Number(item.quantity);
        });
        const shipping = Number($('shipping_fee').value) || 0;
        $('subtotal').textContent = money(subtotal);
        $('discount-total').textContent = money(discountTotal);
        $('grand-total').textContent = money(subtotal + shipping);
        renderCart();
        renderCustomerSummary();

        document.querySelectorAll('#order-form input[name^="items["]').forEach(input => input.remove());
        items.forEach((item,index) => {
            [['product_variant_id',item.id],['quantity',item.quantity],['sale_price',item.price]].forEach(([key,value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'items[' + index + '][' + key + ']';
                input.value = value;
                $('order-form').appendChild(input);
            });
        });
    };

    $('jmStoreSearch').addEventListener('input', renderCatalog);
    $('jmStoreCategories').addEventListener('click', event => {
        const button = event.target.closest('[data-category]');
        if (!button) return;
        activeCategory = button.dataset.category;
        $('jmStoreCategories').querySelectorAll('button').forEach(item=>item.classList.toggle('active',item===button));
        renderCatalog();
    });
    $('jmStoreProductGrid').addEventListener('click', event => {
        const button = event.target.closest('[data-open-product]');
        if (button) openProductDetails(button.dataset.openProduct);
    });
    $('store-cart-items').addEventListener('click', event => {
        const edit = event.target.closest('[data-edit-cart]');
        const remove = event.target.closest('[data-delete-cart]');
        if (edit) {
            const index = Number(edit.dataset.editCart);
            const item = items[index];
            if (item) openProductDetails(item.product_id, index);
        }
        if (remove) {
            items.splice(Number(remove.dataset.deleteCart),1);
            renderReview();
        }
    });

    let currentStep = 'step-customer';
    const show = id => {
        currentStep = id;
        steps.forEach(step => $(step).classList.toggle('active',step===id));
        document.querySelectorAll('[data-order-step]').forEach((step,index)=>{
            const currentIndex=steps.indexOf(id);
            step.classList.toggle('active',step.dataset.orderStep===id);
            step.classList.toggle('done',index<currentIndex);
            if (step.dataset.orderStep === id) step.setAttribute('aria-current','step');
            else step.removeAttribute('aria-current');
        });
        $('jmWizardNext').hidden = id === 'step-review';
        $('save-order').hidden = id !== 'step-review';
        $('jmWizardHint').textContent = id === 'step-customer'
            ? 'اختر العميل ورقم الهاتف وعنوان التوصيل أولًا.'
            : id === 'step-products'
                ? 'بعد الانتهاء من اختيار المنتجات، راجع تفاصيل الأمر قبل الحفظ.'
                : 'راجع تفاصيل الطلب ثم أتمم الحفظ.';
        if (id === 'step-review') renderReview();
    };

    const commitConfiguredProduct = () => {
        if (!activeVariant) return;
        const quantity = Number($('jmDetailQty').value);
        const price = Number($('jmDetailPrice').value);
        if (!(quantity > 0) || !Number.isFinite(quantity) || !Number.isFinite(price)
            || price < Number(activeVariant.minimum_sale_price) || price > Number(activeVariant.list_price)) {
            updateDetailTotal();
            return;
        }
        const item = {
            id:String(activeVariant.id),
            product_id:String(activeProduct.id),
            name:activeProduct.name,
            category:activeProduct.category,
            image:activeProduct.image,
            sku:activeVariant.sku,
            variant_name:activeVariant.variant_name,
            attributes:activeVariant.attributes || [],
            list:Number(activeVariant.list_price),
            min:Number(activeVariant.minimum_sale_price),
            quantity,
            price
        };
        if (editingIndex !== null) {
            const duplicateIndex = items.findIndex((row,index)=>index !== editingIndex && String(row.id) === item.id);
            if (duplicateIndex >= 0) {
                items[duplicateIndex].quantity = Number(items[duplicateIndex].quantity) + quantity;
                items[duplicateIndex].price = price;
                items.splice(editingIndex,1);
            } else {
                items[editingIndex] = item;
            }
        } else {
            const existingIndex = items.findIndex(row=>String(row.id)===item.id);
            if (existingIndex >= 0) {
                items[existingIndex].quantity = Number(items[existingIndex].quantity) + quantity;
                items[existingIndex].price = price;
            } else {
                items.push(item);
            }
        }
        closeProductDetails();
        renderReview();
    };

    renderCategories();
    renderCatalog();
    renderCart();

    const validateCustomerStep = () => {
        if (!$('customer_id').value) { Swal.fire({icon:'warning',text:'اختر العميل أولًا.'}); return false; }
        if (!$('shipping_phone_id').value) { Swal.fire({icon:'warning',text:'اختر رقم هاتف نشطًا للعميل.'}); return false; }
        const address=(customerAddresses[$('customer_id').value]||[]).find(item=>String(item.id)===$('shipping_address_id').value);
        if(!address||!address.zone){Swal.fire({icon:'warning',text:'اختر عنوانًا مرتبطًا بمنطقة شحن ومخزن نشطين قبل متابعة الطلب.'});return false;}
        return true;
    };

    const validateOrderStep = () => {
        if (!items.length) { Swal.fire({icon:'warning',text:'أضف منتجًا واحدًا على الأقل.'}); return false; }
        const address=(customerAddresses[$('customer_id').value]||[]).find(item=>String(item.id)===$('shipping_address_id').value);
        if(!address||!address.zone){Swal.fire({icon:'warning',text:'عنوان التوصيل غير مرتبط بمنطقة شحن نشطة.'});return false;}
        const fee=Number($('shipping_fee').value);
        if(!Number.isFinite(fee)||fee<Number(address.feeMin)||fee>Number(address.feeMax)){
            Swal.fire({icon:'warning',text:'رسوم الشحن خارج النطاق المحدد لعنوان العميل.'});return false;
        }
        if($('shipping_date').disabled||!$('shipping_date').value||$('shipping_date').value<$('shipping_date').min){
            Swal.fire({icon:'warning',text:'حدد موعد شحن صحيحًا وفق مدة التوصيل للمنطقة.'});return false;
        }
        return true;
    };

    $('jmWizardNext').addEventListener('click', () => {
        if (currentStep === 'step-customer') {
            if (!validateCustomerStep()) return;
            show('step-products');
            renderCatalog();
            return;
        }
        if (currentStep === 'step-products') {
            if (!validateOrderStep()) return;
            show('step-review');
        }
    });

    document.querySelectorAll('[data-order-step]').forEach(step => {
        const navigate = () => {
            const target = step.dataset.orderStep;
            if (target === 'step-products' && !validateCustomerStep()) return;
            if (target === 'step-review' && (!validateCustomerStep() || !validateOrderStep())) return;
            show(target);
        };
        step.addEventListener('click', navigate);
        step.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                navigate();
            }
        });
    });

    $('shipping_fee').addEventListener('input',renderReview);
    $('customer_id').addEventListener('change',renderCustomerSummary);
    $('order-form').addEventListener('submit', event => {
        if (!$('customer_id').value || !$('shipping_phone_id').value || !$('shipping_address_id').value || !items.length) {
            event.preventDefault();
            Swal.fire({icon:'warning',text:'أكمل بيانات العميل وأضف منتجًا واحدًا على الأقل.'});
            return;
        }
        renderReview();
    });

})();
</script>
@endpush
