

<!--<div class="warehouse container my-4">-->

    <!-- اختيار المخزن المصدر -->
    <div id="SelectFromInventory" class="warehouse container my-5 SelectFromInventory">
        <h4 class="text-center mb-4">اختر المخزن المصدر</h4>
    </div>

    <!-- اختيار المخزن المستلم -->
    <div id="SelectToInventory" class="warehouse container my-5 SelectToInventory" style="display:none">
        <h4 class="text-center mb-4">اختر المخزن المستلم</h4>
    </div>

    <!-- طريقة اختيار المنتجات -->
    <div id="selectType" class="warehouse container text-center selectType" style="display:none">
        <h4>اختر طريقة تحديد المنتجات</h4>
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card option-card m-2" id="by-category">
                    <i class="fas fa-tags text-success"></i>
                    <h5>حسب التصنيف</h5>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card option-card m-2" id="by-product">
                    <i class="fas fa-box-open text-warning"></i>
                    <h5>منتج واحد أو أكثر</h5>
                </div>
            </div>
        </div>

        <!-- اختيار تصنيف -->
        <div class="form-section mt-4" id="form-category" style="display:none">
            <select id="category" class="form-control select2 mb-3" multiple></select>
            <button class="btn btn-success" onclick="confirmSelection('category')">تأكيد</button>
        </div>

        <!-- اختيار منتجات -->
        <div class="form-section mt-4" id="form-product" style="display:none">
            <select id="product" class="form-control select2 mb-3" multiple></select>
            <button class="btn btn-warning" onclick="confirmSelection('product')">تأكيد</button>
        </div>
    </div>

    <!-- جدول المنتجات -->
    <div id="TransferList" class="card mt-4 TransferList" style="display:none">
        <div class="card-body">
            <h4 class="mb-3">المنتجات المحولة</h4>
            <div class="table-responsive">
                <table id="products-table" class="table table-bordered table-striped w-100"></table>
            </div>
        </div>
    </div>
     
	<div id="addTransferPro" class="form-section" style ="display:none">
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

<!--</div>-->