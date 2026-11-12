<?php
//AddStockPermission
?>
<!--view order data-->
<div class="card DOV" style="display:none;">   
        
</div>

<div id="publicStoId"  data-stoid ="null" ></div>
	<div id="SelectOrderType" class="warehouse container m-4 form-section p-5">
	    <h4 class="text-center mb-4">أختار نوع أذن الاستلام</h4>
	    <div class="row">
	    	<div id="2" class="col-md-3 col-sm-6 mb-4 p-2 text-center type-select">
	    		<div class="warehouse-card warehouse-3">
			    	<i class="fas fa-dolly"></i>
			        استلام منتجات من المورد
				</div>
			</div>
			<div id="1" class="col-md-3 col-sm-6 mb-4 p-2 text-center type-select">
	    		<div class="warehouse-card warehouse-1">
					<i class="fas fa-cart-shopping"></i>
					استلام مرتجع امر بيع
				</div>
			</div>
		</div>
	</div>
    
	<div id="SelectInventory" class="warehouse container m-4 form-section" style="display:none">
	    <h4 class="text-center mb-4">اختر المخزن</h4>
	    
	</div>

	<div id="PurchaseList" class="card PurchaseList form-section" style="display:none">
		<div class="card-header p-2">
			<ul id="Olhbtn" class="nav nav-pills">
				<!--<li class="nav-item"><a id="OLAll"			class="OLF nav-link" href="#All" 				  data-toggle="tab">الكل</a></li>-->
				<li class="nav-item"><a id="OLUnderReceipt" class="OLF nav-link" href="#PurchasesCompleted"   data-toggle="tab">قيد الاستلام</a></li>
				<li class="nav-item"><a id="OLCompleted"    class="OLF nav-link" href="#PurchasesCompleted"   data-toggle="tab">تم الاستلام</a></li>
			</ul>
		</div>
		<div class="card-body p-4">
			<table id="dputbl" class="table table-hover table-bordered table-striped  text-nowarp  w-100 col-sm-12 p-0 m-0">
			</table>
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
					<table id="tableROPU" data-id="" class="table tableROPU table-hover table-responsive table-bordered table-striped p-0 m-0 ">
						
					</table>
				</div>
			</div>
		</div>
	</div>

	<div id="addnewstock" class="form-section" style ="display:none">
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
				        
				        <!-- حقل سعر الشراء -->
				        <div class="col-md-3 col-6 p-1">
				            <input id="unitprice" type="text" class="form-control unitprice" pattern="[0-9]*" inputmode="numeric" onchange="focusNext('quantity')"  placeholder="سعر الواحده"/>
				        </div>
				
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
	
	<!--<div id="ordersReturn" class="card ordersReturn w-100 form-section" style="display:none;">
		<div class="row ml-0 mr-0 col-12">
			<div class="card card-info card-outline w-100">
		        <div class="card-body">
					<table id="tableOrdersReturn" data-id="" class="table tableROPU table-hover table-responsive table-bordered table-striped p-0 m-0 ">
						
					</table>
				</div>
			</div>
		</div>
	</div>-->
	
	<div id="ordersReturn" class="card ordersReturn form-section"  style="overflow-x:auto;display:none">
		<div class="card-body p-4">
			<table id="tableOrdersReturn" class="table table-hover table-bordered! table-striped text-nowrap w-100 col-sm-12 p-0 m-0">
				<thead>
					<tr>
						<th>رقم الامر</th>
						<th>الحالة</th>
						<th>بيانات العميل</th>
						<th>تواريخ</th>
						<th>تعديل</th>
					</tr>
				</thead>
				<tbody>
				</tbody>
			</table>
		</div>
	</div>
	
	<script>
		var orderIssueStoId ;
		// جلب بيانات المخازن
		async function getInventoryData(orderType) {
			    const controller = new AbortController(); // لإنشاء كائن التحكم في الإلغاء
			    const signal = controller.signal;
			
			    // تحديد المهلة الزمنية (5 ثوانٍ)
			    const timeout = setTimeout(() => controller.abort(), 5000);
			
			    try {
			        let response = await fetch(`${urlApi}?CONTROL=getdropWarehouse&type=${orderType}`, { signal });
			        clearTimeout(timeout); // إيقاف المهلة إذا وصل الرد في الوقت المحدد
			        if (!response.ok) throw new Error("خطأ في جلب البيانات");
			        let stores = await response.json();
			        displayStores(stores, 'SelectInventory');
			    } catch (error) {
			        if (error.name === "AbortError") {
			            Swal.fire({
				            icon: 'error',
				            title: 'خطأ في الاتصال',
				            text: 'انتهى الوقت استغرق الخادم وقت طويل فى الرد . يرجى المحاولة لاحقًا.',
				        });
				    } else {
			            console.error("خطأ:", error);
			        }
			    }
			}
		// دالة عرض البيانات في المخازن فى الشاشة
		function displayStores(stores,elementID) {
			    const container = document.getElementById(elementID);
				$('#'+elementID+' .inv-select').remove();
				$('.btn-app-pade .btnBack').show();
				$('.btn-app-pade .btnBack').off('click').on('click', btnBack);
				
				showSection(elementID)
				$('#SelectOrderType').hide();
				//انشاء عرض المخازن
				Object.keys(stores).forEach((key, index) => {
					const [id, nun, name] = stores[key];
					// توليد اسم الكلاس ديناميكيًا
					const classNumber = (id % 10) || 10;
					const className = `warehouse-${classNumber}`;
					// إنشاء عنصر المستودع
					const warehouseDiv = document.createElement("div");
					warehouseDiv.id = id;
					warehouseDiv.className = "col-md-3 col-sm-6 mb-4 p-1 text-center inv-select";
					warehouseDiv.innerHTML = `<div class="warehouse-card ${className}">
												<i class="fas fa-warehouse"></i>
												${name}
											  </div>`;
					if (index % 4 === 0) {
						row = document.createElement("div");
						row.className = "row";
						container.appendChild(row);
					}
					// إضافة عنصر المستودع إلى الصف الحالي
					row.appendChild(warehouseDiv);
				});
				// اختيار المخزن
				$('.inv-select').click(function() {
					orderIssueStoId = $(this).attr('id');
					
					$('#publicStoId').data('stoid', orderIssueStoId);
					if(orderIssueType == 2){
						showSection('PurchaseList');
						$('#btnPlus').show();				
					}else if(orderIssueType == 1){
						showSection('ordersReturn');
					}
				})
			}
		$(document).ready(function () {
			$('.btn-app-pade').append('<button id="btnPlus"         class="btn btn-app-tools btnh text-dark btnPlus" style="display:none" onclick="addnewstock();" ><i class="fas fa-plus"></i><span class="btnname">جديد</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>');
			// اختيار النوع
			$('.type-select').click(function() {
				orderIssueType = $(this).attr('id');
				getInventoryData(orderIssueType);
				title = 'إذن ' + $(this).text();
				$('#tableTitle').html(title);
			})
	
		})
	</script>