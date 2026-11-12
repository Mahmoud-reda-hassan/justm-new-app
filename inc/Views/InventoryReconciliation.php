<div id="SelectInventory" class="warehouse container my-5">
    <h4 class="text-center mb-4">اختر المخزن</h4>
    
</div>





<div id="selectType" class="warehouse container text-center" style="display:none">
        <h4>اختر طريقة تحديد المنتجات</h4>
        <div class="row">
           <!-- <div class="select-type col-md-4">
                <div class="card option-card warehouse-1 m-2" id="by-location">
                    <i class="fas fa-map-marker-alt text-primary"></i>
                    <h5>حسب مكان المنتج</h5>
                </div>
            </div>-->
            <div class="select-type col-md-4">
                <div class="card option-card warehouse-4 m-2" id="by-category">
                    <i class="fas fa-tags text-success"></i>
                    <h5>حسب التصنيف</h5>
                </div>
            </div>
            <div class="select-type col-md-4">
                <div class="card option-card warehouse-2 m-2" id="by-product">
                    <i class="fas fa-box-open text-warning"></i>
                    <h5>منتج واحد أو أكثر</h5>
                </div>
            </div>
        </div>

        <!--<div class="form-section" id="form-location">
            <h4 class="mb-3">تحديد المنتجات حسب المكان</h4>
            <select class="form-control select2 mb-3">

            </select>
            <button class="btn btn-primary" onclick="confirmSelection('مكان المنتج')">تأكيد</button>
        </div>-->
	
        <div class="form-section" id="form-category">
            <h4 class="mb-3">تحديد المنتجات حسب التصنيف</h4>
            <select id="category" class="form-control select2 mb-3" multiple>
            </select>
            <button class="btn btn-success" onclick="confirmSelection('category',$('#category').val())">تأكيد</button>
        </div>

        <div class="form-section" id="form-product">
            <h4 class="mb-3">تحديد منتج واحد أو أكثر</h4>
            <select id="product" class="form-control select2 mb-3" multiple>
            </select>
            <button class="btn btn-warning" onclick="confirmSelection('product',$('#product').val())">تأكيد</button>
        </div>
 	</div>

	<div id="PurchaseList" class="card PurchaseList" style="display:none">
	    <div class="card-body p-4">
	    	<h3 id></h3>
	        <div class="table-responsive">
	            <table id="products-table" class="table table-hover table-bordered table-striped text-nowrap w-100">
	            </table>
	        </div>
	    </div>
	</div>
