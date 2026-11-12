<?php

?>
<!-- user view-->
<div id="usersView">
<!-- user card Templet -->
<div id="userCard" style="display: none !important;">
	<div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-4  d-flex align-items-stretch">
		<div class="card bg-light">
			<div class="card-header badge-danger border-bottom-0 userType">
			</div>
			<div class="card-body pt-0">
			    <div class="row mt-2">
					<div class="col-6">
						<h2 class="lead userName"><b> --- </b></h2>
						<ul class="ml-2 mb-0 mt-0 fa-ul text-muted">
							<hr>
							<a class="job"></a>
							<hr>
							<li class="small mb-2"><span class="fa-li"><i class="fas fa-lg fa-phone"></i></span><b>  </b><a class="userPhone"></a></li>
						</ul>
					</div>
					<div class="col-6 text-center userImg">
						<img src="" alt="" class="img-circle img-fluid">
					</div>
				</div>
				<div class="row mt-0">
				    <div class="col-12">
				        <ul class="ml-2 mb-0 fa-ul text-muted">
				            <li class="small mb-2"><span class="fa-li"><i class="fas fa-lg fa-envelope"></i></span><b>Email : </b><a class="userEmail"></a></li>
				            <li class="small mb-2"><span class="fa-li"><i class="fas fa-lg fa-building"></i></span><b>العنوان : </b><a class="userAdress"></a></li>
						</ul>
				    </div>
				</div>
			</div>
			<div class="card-footer">
				<div class="text-right">
					<!--<a href="#" class="btn btn-sm bg-teal">
						<i class="fas fa-comments"></i>
					</a>-->
					<a href="#" id="btnNew" data-userid="" data-addressid="" data-telid="" class="btn btn-sm btn-primary btnUEdit">
						<i class="fas fa-user"></i> View Profile
					</a>
				</div>
			</div>
		</div>
	</div>
</div>
<!-- user card view -->
<div class="card card-solid" style="">
    <div class="card-header text-muted border-bottom-0 ">
        <div class="row">
            <div class="col-sm-6 input-group mb-3">
                <select id="userTypeoption" name="userType" class="form-control">
					<option value="" selected>كل المستخدمين</option>
					<option value="1">الاداريين</option>
					<option value="3">مندوبين الشحن</option>
					<option value="2">المسوقين</option>
					<option value="4">الوسطاء</option>
					<option value="5">التجار</option>
					<option value="6">التجزئة</option>
					<option value="-1">الحسابات الموقوفة</option>
				</select>
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-tags" aria-hidden="true"></i></span>
                </div>
            </div>
            <div class="col-sm-6 input-group mb-3">
                <input id="userSearch" type="search" name="statuse" class="form-control" placeholder="بحث">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-search" aria-hidden="true"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body pb-0">
        <div id="userView" class="row">

        </div>
    </div>
    <!-- footar card view -->
    <div class="card-footer">
        <nav aria-label="Contacts Page Navigation">
            <ul id="pageNav" class="pagination justify-content-center m-0">

            </ul>
        </nav>
    </div>
</div>
</div>
<!-- add New user -->
<div id="addNewUser" style="display:none;">
	<div class="card">
		<div class="card-body">
			<div class="">
			   <div class="row">
                    <div class="col-sm-6">
						<div class=" border rounded p-3 mt-4 mb-3"> 
                            <b class="badge badge-light text-muted"style="margin-top:-27px">البيانات الشخصية</b>
						    <div class="row">
						        <div class="col-6 form-group">
							        <label for="FORENAME">الاسم الاول</label>
							        <input id="FORENAME" name="FORENAME" type="text" class="form-control cli req" placeholder="أدخل الاسم الاول" inputmode="text" required="">
						        </div>
						        <div class="col-6 form-group">
							        <label for="LASTNAME">الاسم الاخير</label>
							        <input id="LASTNAME" name="LASTNAME" type="text" class="form-control cli req" placeholder="ادخل الاسم الاخير" required inputmode="text" required="">
						        </div>
						        <div class="form-group row ml-3">
                                    <div class="form-check">
                                        <input id="gender1" class="form-check-input req" type="radio" name="GENDER" value="male" checked>
                                        <label class="form-check-label text-primary">ذكر</label>
                                    </div>
                                    <div class="form-check">
                                        <input id="gender2" class="form-check-input req" type="radio" name="GENDER" value="female">
                                        <label class="form-check-label text-primary">انثى</label>
                                    </div>
                                </div>
						    </div>
						    <div id="userTel" class=""></div>
					        <div id="userAddress-Option" class=''></div>
					    </div>
                    </div>
					<div class="col-sm-6">
					    <div class=" border rounded p-3 mt-4 mb-3"> 
                            <b class="badge badge-light text-muted"style="margin-top:-27px">بيانات تسجيل الدخول</b>
                            <div class="form-group">
                                <label for="exampleInputEmail1">البريدالالكترونى</label>
                               <div class="input-group">
                                <input type="email" class="form-control email req" name="USER_LOGIN" id="USER_LOGIN" placeholder="Enter email" required="">
                            	<button class="btn btnPass btn-danger">تغير كلمة السر</button>
                               </div>
                            </div>
                            <div class="form-group">
                                <label for="USER_PASSWORD">كلمة المرور</label>
                                <input type="password" class="form-control pass  req" name="USER_PASSWORD" id="USER_PASSWORD" placeholder="Password" required="">
                            </div>
                            <div class="form-group">
                                <label>تابع لـ شركة</label>
                                <select id="COMPANY_ID" class="form-control cli req" name="COMPANY_ID" required >
                                <option val="1">JastM</option>
								</select>
                            </div>
							<div class="form-group">
                                <label>تابع لـ فرع</label>
                                <select id="BRANCH_ID" class="form-control cli req" name="BRANCH_ID" required >
                                </select>
                            </div>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input req" id="SUSPENDED" name="SUSPENDED" checked>
                                <label class="custom-control-label text-primary" for="SUSPENDED">الحساب نشط</label>
                            </div>
                        </div>
						<div class=" border rounded p-3 mt-4 mb-3"> 
                            <b class="badge badge-light text-muted"style="margin-top:-27px">صلاحيات تسجيل الدخول</b>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input sw req" id="switch1" name="ADMIN_TYPE">
                                <label class="custom-control-label text-primary " for="switch1">الدخول الى لوحة تحكم الادارة</label>
                            </div>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input sw req" id="switch2" name="DELIVERY_TYPE">
                                <label class="custom-control-label text-primary " for="switch2">الدخول كـ موظف شحن</label>
                            </div>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input sw req" id="switch3" name="AFFILIATE_TYPE">
                                <label class="custom-control-label text-primary " for="switch3">الدخول كـ مسوق</label>
                            </div>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input sw req" id="switch4" name="INTERMEDIARY_TYPE" disabled>
                                <label class="custom-control-label text-primary " for="switch4">الدخول كـ وسيط بيع</label>
                            </div>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input sw req" id="switch5" name="MERCHANT_TYPE" disabled>
                                <label class="custom-control-label text-primary" for="switch5">الدخول كـ تاجر </label>
                            </div>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input sw req" id="switch6" name="customer" disabled>
                                <label class="custom-control-label text-primary " for="switch6">الدخول كـ عميل تجزئة</label>
                            </div>
                        </div>
                        <div id="admin_setting" class="border rounded p-3 mt-4 mb-3" style="display:none;"> 
                            <b class="badge badge-light text-muted"style="margin-top:-27px">قم بإعداد الحساب الادارى</b>
                            <div class="form-group">
                                <label>الوظيفة</label>
                                <select id="USER_GRUOP_ID" class="form-control cli req" name="USER_GRUOP_ID" required >
                                    
                                </select>
                            </div>
                        </div>
                        <div id="shipping_setting" class="border rounded p-3 mt-4 mb-3" style="display:none;"> 
                            <b class="badge badge-light text-muted"style="margin-top:-27px">قم بإعداد حساب موظف الشحن</b>
								<div class="form-group">
                                    <label for="ZONE_ID">خط السير</label>
						            <div class="input-group mb-3">
                                        <select id="ZONE_ID" class="form-control cli req" name="ZONE_ID" required >
									    </select>
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-location-dot"></i></span>
                                        </div>
                                    </div>
                                </div>
								
						        <div class="form-group">
                                    <label for="SHIPP_PERCENT">يتقاضى الموظف نسبة  مئوية من قيمة الشحن وتساوى:</label>
						            <div class="input-group mb-3">
                                        <input id="SHIPP_PERCENT" type="number" class="form-control cli req" name="SHIPP_PERCENT" placeholder="0.00" required inputmode="number" required="">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-percent"></i></span>
                                        </div>
                                    </div>
                                </div>
                        </div>
						<div id="affiliate_setting" class="border rounded p-3 mt-4 mb-3" style="display:none;"> 
                            <b class="badge badge-light text-muted"style="margin-top:-27px">قم بإعداد حساب المسوق</b>
                            <div class="form-group">
                                <label for="COMMISSION_SALES">تحت قيادة</label>
						        <div class="input-group mb-3">
                                    <select id="AFFILIATEED_WITH" class="form-control affw req" name="AFFILIATEED_WITH" required >
                                    </select>
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6 form-group">
                                    <label for="COMMISSION_SALES">عمولة المسوق</label>
						            <div class="input-group mb-3">
                                        <input id="COMMISSION_SALES" type="number" class="form-control cli req" name="COMMISSION_SALES" placeholder="0.00" inputmode="number" required="">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-sack-dollar" aria-hidden="true"></i></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 form-group">
                                    <label for="TEAM_COMMISSION">عمولة مبيعات الفريق</label>
						            <div class="input-group mb-3">
                                        <input id="TEAM_COMMISSION" type="number" class="form-control cli req" name="TEAM_COMMISSION" placeholder="0.00" required inputmode="number" required="">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-sack-dollar" aria-hidden="true"></i></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
					    </div> 
			        </div>
		        </div>
<!-- /.card-body -->
	</div>
        </div>
    </div>
</div>