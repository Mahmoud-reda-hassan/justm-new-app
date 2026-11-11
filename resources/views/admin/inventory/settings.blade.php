@extends('layouts.app')

@section('title', 'إعدادات المخازن العامة')
@section('page-title', 'إعدادات المخازن العامة')
@section('breadcrumb', 'إعدادات المخازن')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card card-outline card-primary">
            <div class="card-header"><h3 class="card-title">المخزون الافتراضي حسب حالات أوامر البيع</h3></div>
            <form method="POST" action="{{ route('admin.inventory.settings.virtual-stock.update') }}">
                @csrf
                <div class="card-body">
                    <div class="alert alert-info">
                        هذه الإعدادات تتحكم في الكميات المحجوزة افتراضيًا عند عرض المتاح لكل شاشة. لا تغيّر الرصيد الفعلي ولا تنشئ حركة صرف مخزني.
                    </div>

                    <h5 class="font-weight-bold">1. مخزون إنشاء أمر البيع</h5>
                    <p class="text-muted">حدد حالات أوامر البيع التي تخصم كمياتها من المخزون الأصلي عند إنشاء أمر بيع جديد.</p>
                    <div class="row">
                        @foreach($stockStatuses as $status => $label)
                            <div class="col-xl-3 col-md-4 col-6 mb-2">
                                <div class="custom-control custom-checkbox">
                                    <input class="custom-control-input" type="checkbox"
                                           id="create-status-{{ $status }}" name="create_order_statuses[]"
                                           value="{{ $status }}"
                                           @checked(in_array($status, $virtualStock['create_order_statuses'], true))>
                                    <label class="custom-control-label" for="create-status-{{ $status }}">{{ $label }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <hr>
                    <h5 class="font-weight-bold">2. مخزون خدمة العملاء</h5>
                    <p class="text-muted">كل صف يمثل حالة أمر البيع المفتوح في شاشة خدمة العملاء، وكل عمود يحدد الحالات التي تخصم كمياتها من المخزون الأصلي في هذه الشاشة.</p>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm text-center virtual-stock-matrix">
                            <thead class="thead-light">
                                <tr>
                                    <th class="text-right align-middle">حالة الشاشة</th>
                                    @foreach($stockStatuses as $columnStatus => $columnLabel)
                                        <th class="align-middle">{{ $columnLabel }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stockStatuses as $rowStatus => $rowLabel)
                                    <tr>
                                        <th class="text-right align-middle">{{ $rowLabel }}</th>
                                        @foreach($stockStatuses as $columnStatus => $columnLabel)
                                            <td class="align-middle">
                                                <input type="checkbox"
                                                       aria-label="خصم {{ $columnLabel }} عند شاشة {{ $rowLabel }}"
                                                       name="customer_service_matrix[{{ $rowStatus }}][]"
                                                       value="{{ $columnStatus }}"
                                                       @checked(in_array($columnStatus, $virtualStock['customer_service_matrix'][$rowStatus] ?? [], true))>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted">عند فتح أمر قائم، يُستثنى الأمر الحالي وحده من حساب المتاح، ولا يُفك حجزه بسبب هذا الاستثناء.</small>

                    <hr>
                    <h5 class="font-weight-bold">3. مخزون أمين المخزن / التجهيز</h5>
                    <p class="text-muted">حدد الحالات التي تخصم كمياتها من المتاح المعروض لمسؤول التجهيز. الافتراضي هو خصم الطلبات التي تم تجهيزها فقط.</p>
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" type="checkbox" id="warehouse-prepared"
                               name="warehouse_statuses[]" value="prepared"
                               @checked(in_array('prepared', $virtualStock['warehouse_statuses'], true))>
                        <label class="custom-control-label" for="warehouse-prepared">تم التجهيز</label>
                    </div>
                    <div class="alert alert-secondary mt-3 mb-0">
                        الرصيد الفعلي لا يتغير بتثبيت التجهيز؛ المتاح الظاهر لموظف المخزن في منظور التجهيز هو الرصيد الأصلي ناقص كميات «تم التجهيز» فقط. الصرف للمندوب حركة فعلية مستقلة.
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">حفظ إعدادات المخزون الافتراضي</button>
                </div>
            </form>
        </div>

        <div class="card card-outline card-secondary">
            <div class="card-header"><h3 class="card-title">سياسة الرصيد السالب الفعلي</h3></div>
            <form method="POST" action="{{ route('admin.inventory.settings.update') }}">
                @csrf @method('PUT')
                <div class="card-body">
                    <div class="form-group mb-0">
                        <label>السماح بالرصيد السالب</label>
                        <select name="allow_negative_stock" class="form-control" required>
                            <option value="0" @selected(! $allowNegativeStock)>منع الرصيد السالب (الافتراضي)</option>
                            <option value="1" @selected($allowNegativeStock)>السماح بالرصيد السالب</option>
                        </select>
                        @error('allow_negative_stock')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                    @if($updatedAt)<small class="text-muted d-block mt-3">آخر تحديث: {{ $updatedAt->format('Y-m-d H:i') }} @if($updatedBy) بواسطة {{ $updatedBy }} @endif</small>@endif
                </div>
                <div class="card-footer"><button type="submit" class="btn btn-secondary">حفظ سياسة الرصيد السالب</button></div>
            </form>
        </div>
    </div>
</div>
<style>
.virtual-stock-matrix { min-width: 1050px; }
.virtual-stock-matrix th { white-space: normal; min-width: 105px; font-size: .82rem; }
.virtual-stock-matrix td { min-width: 70px; }
.virtual-stock-matrix input { width: 18px; height: 18px; }
</style>
@endsection
