<?php 
  //اذن صرف مخزنى
?>
<div id="SelectOrderType" class="warehouse container my-5 form-section">
    <h4 class="text-center mb-4">أختار نوع أذن الصرف</h4>
    <div class="row">
    	<div id="2" class="col-md-3 col-sm-6 mb-4 p-1 text-center type-select">
    		<div class="warehouse-card warehouse-3">
				<i class="fas fa-cart-shopping"></i>
				صرف لحساب امر بيع
			</div>
		</div>
		<div id="1" class="col-md-3 col-sm-6 mb-4 p-1 text-center type-select">
    		<div class="warehouse-card warehouse-1">
				<i class="fas fa-dolly"></i>
				صرف مرتجع المورد
			</div>
		</div>
		<div id="3" class="col-md-3 col-sm-6 mb-4 p-1 text-center type-select">
    		<div class="warehouse-card warehouse-2">
				<i class="fas fa-trash-can"></i>
				صرف هوالك
			</div>
		</div>
	</div>
</div>
<!--view order data-->
<div class="card DOV" style="display:none;">   
        
</div>

<div id="SelectInventory" class="warehouse container my-5 form-section" style="display:none">
    <h4 class="text-center mb-4">اختر المخزن</h4>
</div>


<div id="tableList" class="warehouse container my-5 PurchaseList form-section p-1" style="display:none">
	<h4 id="tableTitle" class=" text-center mb-4">اختر المخزن</h4>
	<div class="card p-0 m-0">
		<div class="card-body p-4 ">
			
			<div class="table-responsive1 table-container">
				<table id="table" class="table table-hover  table-striped text-nowrap w-100 col-sm-120 p-0 m-0">
				</table>
			</div>
		</div>
	</div>
</div>
<div id="ReceiveOrderPurchase" class="ReceiveOrderPurchase w-100 form-section" style="display:none;">
	<div class="container p-0">
		<div class="row invoice-info text-left m-0 p-0">
		</div>
	</div>
	<div class="row ml-0 mr-0 col-12">
		<div class="card card-info card-outline w-100">
	        <div class="card-body">
	        	<div class="table-responsive1">
					<table id="tableROPU" data-id="" class="table tableROPU table-hover table-bordered table-striped p-0 m-0 ">
						
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<div id="tblScrapPro" class="form-section" style ="display:none">
        <div class="container">
		    <div class="row">
		        <div class="col-12 mt-0">
		            <div class="form-group">
		                <label for="textbox" class="mb-1 mt-0">ملاحظات</label>
		                <textarea id="textbox" class="form-control pb-0 pt-0 textbox" rows="5" placeholder="اكتب ملاحظاتك هنا..."></textarea>
		            </div>
		        </div>
		    </div>
		</div>
        <div class="card card-info card-outline">
            <div class="card-body">
				<!-- search products-->
				<form id="itemForm" onsubmit="addNewItem(); return false;">
				    <div class="row pb-2">
				        <!-- حقل اختيار الصنف -->
				        <div id="proName" class="col-md-4 col-12 p-1 zindexselect2">
				            <select id="itemName" class="form-control itemName" placeholder="كود/اسم الصنف" oninput="focusNext('quantity')">
				                <!-- إضافة خيارات -->
				            </select>
				        </div>
				        <!-- حقل سعر الشراء -->
				        
				        <!-- حقل الكمية  -->
				        <div class="col-md-3 col-6 p-1">
				            <input id="quantity" type="text" class="form-control quantity" pattern="[0-9]*" inputmode="numeric"  placeholder="الكمية"/>
				        </div>
						
				        <!-- زر الإضافة -->
				        <div class="col-md-2 col-12 p-1">
				            <button type="submit" id="addproform" onclick="" class="btn btn-info btn-block">إضافة</button>
				        </div>
				    </div>
				</form>
				<script>
						
						function focusNext(nextElementId) {
							document.getElementById(nextElementId).focus();
						}
						$(document).ready(function() {
						    $('#itemForm').on('submit', function(e) {
							    e.preventDefault(); // منع تحديث الصفحة
							    return 
							});
						});
				</script>
				<table id="dtbl" class="table table-hover table-bordered table-striped  text-nowarp  w-100 col-sm-12 p-0 m-0">
				    <thead>
				        <tr>
				            <th>كود الصنف</th>
				            <th>اسم الصنف</th>
				            <th>الكمية</th>
				        	<th>حذف</th>
				        </tr>
				    </thead>
				    <tbody>
				        <!-- سيتم إضافة الصفوف الجديدة هنا بواسطة JavaScript -->
				    </tbody>
				</table>
            </div>
        </div>
</div>
	
