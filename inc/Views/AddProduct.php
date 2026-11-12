<?php
?>
<div class="tab-content"  id="custom-tabs-three-tabContent">
    <div id="ProductView" class="tab-pane card  tab-pane fade active show">
	    <div class="card-body">
            <div >
                <table id="tbl" class="col-12 tbl table table-hover table-striped m-0 text-table"style="width:100%;">
                </table>
            </div>
        </div>
    </div>
    <!-- /.Card-Product-view -->
    <div id="ProductAdd" class="tab-pane card card-primary card-outline card-tabs tab-pane fade active">
		<div class="card-header p-0 pt-1 border-bottom-0">
			<ul class="nav nav-tabs" id="custom-tabs-three-tab" role="tablist">
				<li class="nav-item">
					<a class="nav-link active" id="custom-tabs-three-productdata-tab" data-toggle="pill" href="#custom-tabs-three-productdata" role="tab" aria-controls="custom-tabs-three-productdata" aria-selected="true">بيانات اساسية</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" id="custom-tabs-three-description-tab" data-toggle="pill" href="#custom-tabs-three-description" role="tab" aria-controls="custom-tabs-three-description" aria-selected="false">وصف المنتج</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" id="custom-tabs-three-Shipping-tab" data-toggle="pill" href="#custom-tabs-three-Shipping" role="tab" aria-controls="custom-tabs-three-Shipping" aria-selected="false">بيانات الشحن</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" id="custom-tabs-three-Shipping-tab" data-toggle="pill" href="#custom-tabs-three-img" role="tab" aria-controls="custom-tabs-three-img" aria-selected="false">صورة المنتج</a>
				</li>
			</ul>
		</div>
		<div class="card-body">
		    <div class="tab-content" id="custom-tabs-three-tabContent">
			    <div class="tab-pane fade active show" id="custom-tabs-three-productdata" role="tabpanel" aria-labelledby="custom-tabs-three-productdata-tab">

				    <div class="row">
				        <div class="form-group col-sm-6">
							<label for="ProductName">اسم المنتج</label>
							<input id="ProductName" data-id="" class="form-control pro-data tblreq req" type="text" placeholder="من فضلك ادخل اسم المنتج">
						</div>
					    <div class="form-group col-sm-6">
						    <label for="ProductCat">تايع لـ</label>
						    <select id="ProductCat"  class="form-control pro-data tblreq req" >
						    </select>
					    </div>
					    <div id="ProductTy" class="form-group col-sm-6 fpop">
						    <label for="ProductType">صفات المنتج</label>
						    <select id="ProductType"  class="form-control fpop" >
							    <option value="1" selected>منتج ليس له صفات</option>
							    <option value="2">منتج متعدد الصفات</option>
						    </select>
					    </div>
					    <div class="form-group col-sm-6 one_product">
						    <label for="ProductCode">EAN Code</label>
						    <div class="input-group ml-0 mr-0 p-0">
						        <input id="ProductCode" data-codeid='' class="form-control req code one_product p-0" type="number" min="12" max="13" step="1" placeholder="">
						        <div class="input-group-append">
						            <button id="btnGenerateCodes" class="btn btn-info" onclick="getlistProCodes()">
						                <i class="fas fa-barcode"></i>
						            </button>
						        </div>
						    </div>
						</div>
					    
					    <div class="form-group col-sm-6 one_product">
						    <label for="ProductCost">سعر التكلفة</label>
						    <input id="ProductCost" class="form-control req one-product cost" type="number" placeholder="0.00">
					    </div>
					    <div class="form-group col-sm-6 one_product">
						    <label for="minProductPrice">سعر الجمله</label>
						    <input id="minProductPrice" class="form-control req one-product minPrice" type="number" placeholder="0.00">
					    </div>
					    <div class="form-group col-sm-6 one_product">
						    <label for="ProductPrice">سعر البيع</label>
						    <input id="ProductPrice" class="form-control req one_product price" type="number" placeholder="0.00">
				        </div>
				        <div class="form-group col-sm-6 one_product">
						    <label for="ProductMin">الحد الادنى للمخزون</label>
						    <input id="ProductMin" class="form-control req one_product min" type="number" placeholder="0.000">
					    </div>
					    <div class="form-group col-sm-6 one_product">
						    <label for="ProductMax">الحد الاقصى للمخزون</label>
						    <input id="ProductMax" class="form-control req one_product max" type="number" placeholder="0.000">
					    </div>
					    <div class="col-6 icheck-primary">
						    <label for="PRO_VISBLE">متاح للبيع</label>
						    <input id="PRO_VISBLE"   class="pro-data" type="checkbox">
					    </div>
		                <div class="col-6 icheck-primary">
						    <label for="PER_BOOKING">حجز مسبق</label>
						    <input id="PER_BOOKING"  class="pro-data"  type="checkbox">
					    </div>
				    </div>
				    <div id="selectoption" class="row">
				        
				    </div>
			    </div>
			
			
			    <div class="tab-pane fade" id="custom-tabs-three-description" role="tabpanel" aria-labelledby="custom-tabs-three-description-tab">
                    <div class="mb-3">
                        <textarea id="ProductDescription" class="textareadescription pro-data q" placeholder="Place some text here" style="width: 100%; height: 200px; font-size: 14px; line-height: 18px; border: 1px solid #dddddd; padding: 10px;"></textarea>
                    </div>
                    <p class="text-sm mb-0">
                        Editor <a href="https://github.com/summernote/summernote">Documentation and license
                        information.</a>
                    </p>
			    </div>
			
			
			    <div class="tab-pane fade" id="custom-tabs-three-Shipping" role="tabpanel" aria-labelledby="custom-tabs-three-Shipping-tab">
			        <div class="row">
				        <div class="form-group col-sm-6">
					        <label for="ProductLength">الطول</label>
					        <input type="text" class="form-control pro-data q" id="ProductLength" placeholder="ادخل الطول بالسنتيمتر">
				        </div>
				        <div class="form-group col-sm-6">
					        <label for="ProductWidth">العرض</label>
					        <input type="text" class="form-control pro-data q" id="ProductWidth" placeholder="ادخل العرض بالسنتيمتر">
				        </div>
				    </div>
				    <div class="row">
				        <div class="form-group col-sm-6">
					        <label for="ProductHeight">الارتفاع</label>
					        <input type="text" class="form-control pro-data q" id="ProductHeight" placeholder="ادخل الارتفاع بالسنتيمتر">
				        </div>
				        <div class="form-group col-sm-6">
					        <label for="ProductWeight">الوزن</label>
					        <input type="text" class="form-control pro-data q" id="ProductWeight" placeholder="ادخل الوزن بالكيلو جرام">
				        </div>
				    </div>
			    </div>
				<div class="tab-pane fade" id="custom-tabs-three-img" role="tabpanel" aria-labelledby="custom-tabs-three-img-tab">
			        <div class="row">
				        <form action="upload.php" method="post" enctype="multipart/form-data">
						  Select image to upload:
						  <input type="file" name="fileToUpload" id="fileToUpload">
						  <input type="submit" value="Upload Image" name="submit">
						</form>
				    </div>
			    </div>
		    </div>
	    </div>
	    <!-- /.Card-Product-add body -->
	    <div>
	    </div>
    </div>
    <!-- /.Card-Product-add -->
</div>

	

