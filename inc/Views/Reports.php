<div class="container-fluid my-3" id="publicReportPage">
    <div class="card shadow rounded mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-6 col-md-3 form-group"><label>من</label><input type="date" id="pr-from" class="form-control"></div>
                <div class="col-6 col-md-3 form-group"><label>إلى</label><input type="date" id="pr-to" class="form-control"></div>
                <div class="col-12 col-md-3 form-group"><label class="d-none d-md-block">&nbsp;</label><button id="pr-run" class="btn btn-primary btn-block">عرض التقرير</button></div>
            </div>
            <small class="text-muted">الأرقام من دفتر الأستاذ العام: الأرباح والخسائر عن الفترة المختارة، والأرصدة في نهاية الفترة.</small>
        </div>
    </div>
    <div class="row" id="pr-cards">
        <div class="col-6 col-md-3 mb-3"><div class="card h-100"><div class="card-body"><small class="text-muted">إجمالي الإيرادات</small><h4 id="pr-rev" class="mb-0">-</h4></div></div></div>
        <div class="col-6 col-md-3 mb-3"><div class="card h-100"><div class="card-body"><small class="text-muted">إجمالي المصروفات</small><h4 id="pr-exp" class="mb-0">-</h4></div></div></div>
        <div class="col-6 col-md-3 mb-3"><div class="card h-100"><div class="card-body"><small class="text-muted">صافي الربح</small><h4 id="pr-net" class="mb-0">-</h4></div></div></div>
        <div class="col-6 col-md-3 mb-3"><div class="card h-100"><div class="card-body"><small class="text-muted">النقدية</small><h4 id="pr-cash" class="mb-0">-</h4></div></div></div>
        <div class="col-6 col-md-3 mb-3"><div class="card h-100"><div class="card-body"><small class="text-muted">مستحق على العملاء</small><h4 id="pr-cust" class="mb-0">-</h4></div></div></div>
        <div class="col-6 col-md-3 mb-3"><div class="card h-100"><div class="card-body"><small class="text-muted">المخزون</small><h4 id="pr-inv" class="mb-0">-</h4></div></div></div>
        <div class="col-6 col-md-3 mb-3"><div class="card h-100"><div class="card-body"><small class="text-muted">مستحق للموردين</small><h4 id="pr-sup" class="mb-0">-</h4></div></div></div>
        <div class="col-6 col-md-3 mb-3"><div class="card h-100"><div class="card-body"><small class="text-muted">عمولات مستحقة (مندوبين + مسوقين)</small><h4 id="pr-comm" class="mb-0">-</h4></div></div></div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3"><div class="card"><div class="card-header">الإيرادات</div><div class="table-responsive"><table class="table table-sm mb-0"><tbody id="pr-rev-rows"></tbody></table></div></div></div>
        <div class="col-md-6 mb-3"><div class="card"><div class="card-header">المصروفات</div><div class="table-responsive"><table class="table table-sm mb-0"><tbody id="pr-exp-rows"></tbody></table></div></div></div>
    </div>
</div>
