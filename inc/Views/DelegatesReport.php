<?php
session_start();
?>
<div class="card shadow rounded">
    <div class="card-header p-2">
		<ul id="Olhbtn" class="nav nav-pills">
			<li class="nav-item"><a id="DelegatesReport" class="OLF nav-link" href="#DelegatesReport"  data-toggle="tab">عهدة المندوب</a></li>
			<li class="nav-item"><a id="shippingCompany" class="OLF nav-link" href="#shippingCompany"   data-toggle="tab">عهدة شركة الشحن</a></li>
			<li class="nav-item"><a id="custodyWaiting" class="OLF nav-link" href="#custodyWaiting"   data-toggle="tab">قيد الشحن على العهدة</a></li>
			<li class="nav-item"><a id="unsettledWarehouse" class="OLF nav-link" href="#unsettledWarehouse"   data-toggle="tab">لم تُسوَّ مخزنيًا</a></li>
			<li class="nav-item"><a id="unsettledFinancial" class="OLF nav-link" href="#unsettledFinancial"   data-toggle="tab">لم تُسوَّ ماليًا</a></li>
		</ul>
	</div>
    <div class="card-body">
      <form id="filter-form">
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>الفترة</label>
            <input id="dateRange" type="text" class="form-control" name="from_date" required>
          </div>
          <div class="form-group col-md-6" >
            <label id='partnerLebal'>المندوب/شركة الشحن</label>
            <select id="partnerselect" class="form-control" name="partner_id" >
              <!-- يتم ملؤها ديناميكيًا -->
            </select>
          </div>
        </div>
        <div class="form-check mb-2" id="allPeriodBox" style="display:none">
          <input class="form-check-input" type="checkbox" id="allPeriod" checked>
          <label class="form-check-label mr-4" for="allPeriod">كل فترة التعامل (تجاهل التاريخ)</label>
        </div>
        <button type="submit" class="btn btn-success">
          <i class="fas fa-search"></i> بحث
        </button>
      </form>
    </div>
</div>
<div class="card shadow rounded mt-4 ml-0 mr-0 ">
    <div class="card-header bg-secondary text-white">
      <h5 id="hti" class="mb-0">اجمالى عهدة المندوبين</h5>
    </div>
    <div class="card-body table-responsive p-2">
    
    <div id="f1" class="hid f1 p-1">
      <table id="total-table" class="table table-bordered table-hover table-striped hid f1">
        <thead class="thead-dark">
          <tr>
          	<th>عرض</th>
          	<th>المندوب</th>
          	<th>اجمالى الاوامر</th>
          	<th>اجمالى القطع</th>
          	<th>قطع قيد الشحن</th>
          	<th>قطع مرتجع تسليم جزئى</th>
          	<th>قطع تم التسليم</th>
          	<th>قطع مرفوضه</th>
          	<th>نسبة الرفض</th>
          </tr>
        </thead>
      </table>
    </div>
      
    <div id="f2" class="hid f2"  style="display:none;">
	      <table id="Daily-table" class="table table-bordered table-hover table-striped hid f2">
		     <thead class="thead-dark">
		       <tr>
		       	<th>عرض</th>
	          	<th>تاريخ الخروج</th>
	          	<th>المندوب</th>
	          	<th>اجمالى الاوامر</th>
	          	<th>اجمالى القطع</th>
	          	<th>قطع قيد الشحن</th>
	          	<th>قطع مرتجع تسليم جزئى</th>
	          	<th>تم التسليم</th>
	          	<th>مرفوض</th>
	          	<th>نسبة الرفض</th>
		       </tr>
		     </thead>
		  </table>
	  </div>
	  
	<div id="f3" class="hid f3"  style="display:none;">
		  <table id="Details-table" class="table table-bordered table-hover table-striped hid f3">
		     <thead class="thead-dark">
		       <tr>
		       	<th>تاريخ الخروج</th>
		       	<th>المندوب</th>
		       	<th>الامر</th>
	          	<th>اجمالى القطع</th>
	          	<th>قطع قيد الشحن</th>
	          	<th>قطع مرتجع تسليم جزئى</th>
	          	<th>تم التسليم</th>
	          	<th>مرفوض</th>
	          	<th>حركة الشحن</th>
		       </tr>
		     </thead>
		  </table>
	  </div>
    
    <div id="f4" class="hid f4"  style="display:none;">
		  <table id="shipping-report" class="table table-bordered table-hover table-striped hid f4">
		     <thead class="thead-dark">
		       <tr>
		       	<th>#</th>
		       	<th>CustName</th>
		       	<th>company</th>
		       	<th>coust</th>
	          	<th>Parcode</th>
	          	<th>Phone</th>
	          	<th>City</th>
	          	<th>Region</th>
	          	<th>Address</th>
	          	<th>createdDate</th>
	          	<th>Notes</th>
	          	<th>Replacing</th>
	          	<th>OrderContent</th>
	          	<th>QTY</th>
	          	<th>PRICE</th>
	          	<th>SHIPPING_VAL</th>
		       </tr>
		     </thead>
		  </table>
	  </div>
    <div id="f5" class="hid f5"  style="display:none;">
		  <div class="mb-2"><b>أوامر قيد الشحن (لم تُسلَّم أو تُرفض بعد) على عهدة المندوب أو شركة الشحن</b> — العدد: <span id="custodyWaitingCount" class="badge badge-dark">0</span></div>
		  <table id="custody-waiting-report" class="table table-bordered table-hover table-striped hid f5">
		     <thead class="thead-dark">
		       <tr>
		       	<th>#</th>
	          	<th>CustName</th>
	          	<th>company</th>
	          	<th>coust</th>
	          	<th>Parcode</th>
	          	<th>Phone</th>
	          	<th>City</th>
	          	<th>Region</th>
	          	<th>Address</th>
	          	<th>createdDate</th>
	          	<th>Notes</th>
	          	<th>Replacing</th>
	          	<th>OrderContent</th>
	          	<th>QTY</th>
	          	<th>PRICE</th>
	          	<th>SHIPPING_VAL</th>
	          	<th>المندوب / الشركة</th>
	          	<th>تاريخ الخروج</th>
		       </tr>
		     </thead>
		  </table>
	  </div>
    <div id="f6" class="hid f6"  style="display:none;">
		  <div class="mb-2"><b>أوامر لم تُسوَّ مخزنيًا: رجعت (مرفوضة / جزئية / مجمّدة) ولم يستلم المخزن مرتجعها بعد</b> — العدد: <span id="warehouseUnsettledCount" class="badge badge-dark">0</span></div>
		  <table id="warehouse-unsettled-report" class="table table-bordered table-hover table-striped hid f6">
		     <thead class="thead-dark">
		       <tr>
		       	<th>#</th>
	          	<th>CustName</th>
	          	<th>company</th>
	          	<th>coust</th>
	          	<th>Parcode</th>
	          	<th>Phone</th>
	          	<th>City</th>
	          	<th>Region</th>
	          	<th>Address</th>
	          	<th>createdDate</th>
	          	<th>Notes</th>
	          	<th>Replacing</th>
	          	<th>OrderContent</th>
	          	<th>QTY</th>
	          	<th>PRICE</th>
	          	<th>SHIPPING_VAL</th>
	          	<th>المندوب / الشركة</th>
	          	<th>تاريخ الخروج</th>
	          	<th>الحالة</th>
	          	<th>الكمية المرتجعة</th>
		       </tr>
		     </thead>
		  </table>
	  </div>
    <div id="f7" class="hid f7"  style="display:none;">
		  <div class="mb-2"><b>أوامر تم تسليمها أو رفضها ولم يتم الاستلام المالي (التسوية المالية) لها بعد</b> — العدد: <span id="financialUnsettledCount" class="badge badge-dark">0</span></div>
		  <table id="financial-unsettled-report" class="table table-bordered table-hover table-striped hid f7">
		     <thead class="thead-dark">
		       <tr>
		       	<th>#</th>
	          	<th>CustName</th>
	          	<th>company</th>
	          	<th>coust</th>
	          	<th>Parcode</th>
	          	<th>Phone</th>
	          	<th>City</th>
	          	<th>Region</th>
	          	<th>Address</th>
	          	<th>createdDate</th>
	          	<th>Notes</th>
	          	<th>Replacing</th>
	          	<th>OrderContent</th>
	          	<th>QTY</th>
	          	<th>PRICE</th>
	          	<th>SHIPPING_VAL</th>
	          	<th>المندوب / الشركة</th>
	          	<th>تاريخ الخروج</th>
	          	<th>الحالة</th>
	          	<th>الكمية المرتجعة</th>
		       </tr>
		     </thead>
		  </table>
	  </div>
    </div>
</div>

<script src="/dist/js/DelegatesShipmentReport.js?ver18"></script>
