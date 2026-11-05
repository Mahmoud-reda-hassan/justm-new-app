<?php

//AddStockPermission
?>

    <div id="PurchaseList" class="card PurchaseList" style="display:none">
		<div class="card-header p-2">
			<ul id="Olhbtn" class="nav nav-pills">
				<li class="nav-item"><a id="OLAll"			class="OLF nav-link" href="#All" 				  data-toggle="tab">الكل</a></li>
				<li class="nav-item"><a id="OLCompleted"    class="OLF nav-link" href="#PurchasesCompleted"   data-toggle="tab">منتى</a></li>
				<li class="nav-item"><a id="OLUnderReceipt" class="OLF nav-link" href="#PurchasesCompleted"   data-toggle="tab">قيد الاستلام</a></li>
			</ul>
		</div>
		<div class="card-body p-4">
			<table id="dputbl" class="table table-hover table-bordered table-striped  text-nowarp  w-100 col-sm-12 p-0 m-0">
			</table>
		</div>
	</div>
	
<!--	<div id="ReceiveOrderPurchase" class="ReceiveOrderPurchase w-100" style="display:none;">
		<div class="container p-0">
			<div class="row invoice-info text-left m-0 p-0">
			</div>
		</div>
		<div class="row ml-0 mr-0 col-12">
			<div class="card card-info card-outline w-100">
		        <div class="card-body">
					<table id="tableROPU" data-id="" class="table tableROPU table-hover table-responsive table-bordered table-striped p-0 m-0 ">
						
					</table>
				</div>
			</div>
		</div>
	</div>
-->
	<div id="addnewstock" style ="dispxlay:block">
        <div class="container">
		    <div class="row">
		        <div class="col-md-6 col-12">
		            <div class="form-group mt-0 zindexselect2">
		                <label for="suppliers" class="mb-1 mt-0">اختيار المورد</label>
		                <select id="suppliers" class="form-control mt-0 mb-0 suppliers  required"  placeholder="اختيار المورد">
		                    <option value="" disabled selected>اختر المورد</option>
		                    
		                </select>
		            </div>
		            <div class="form-group mt-0">
		                <label for="reference" class="mb-1 mt-0">الرقم المرجعي</label>
		                <input id="reference" type="text" class="form-control mt-0 mb-0 reference" placeholder="الرقم المرجعي">
		            </div>
		        </div>
		        <div class="col-md-6 col-12 mt-0">
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
				            <select id="itemName" class="form-control itemName" placeholder="كود/اسم الصنف" oninput="focusNext('unitprice')">
				                <!-- إضافة خيارات -->
				            </select>
				        </div>
				        
				        <!-- حقل الكمية الأول -->
				        <div class="col-md-3 col-6 p-1">
				            <input id="unitprice" type="text" class="form-control unitprice" pattern="[0-9]*" inputmode="numeric" onchange="focusNext('quantity')"  placeholder="سعر الواحده"/>
				        </div>
				
				        <!-- حقل الكمية الثاني -->
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
				            <th>سعر الوحده</th>
				            <th>الكمية</th>
				            <th>الاجمالى</th>
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
	