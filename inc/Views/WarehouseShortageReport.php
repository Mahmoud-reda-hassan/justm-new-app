<?php

?>
<div class="card shadow rounded mb-3">
    <div class="card-body">
        <div class="row align-items-end">
            <div class="col-md-8">
                <label for="date_range">فترة التقرير</label>
                <input type="text" id="date_range" class="form-control" autocomplete="off">
            </div>
            <div class="col-md-4 mt-2 mt-md-0">
                <button type="button" id="btnApplyDateRange" class="btn btn-primary btn-block">
                    <i class="fas fa-filter"></i>
                    عرض التقرير
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card p-0 w-100" style="margin: 0 0 70px 0">
    <div class="card-body w-100">
        <table id="tbl" class="tbl table table-hover table-striped w-100 text-table"></table>
    </div>
</div>
