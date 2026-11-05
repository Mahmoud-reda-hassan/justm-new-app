<?php

?>
<body class="hold-transition login-page">
<div class="rounded mx-auto d-block">
    <div class="spinner-border m-auto" role="status" style="font-size:20px;display:none; position: absolute;left:37%;top:30%">
        <span class="visually-hidden"></span>
    </div>

<div id="loginbox" class="login-box" style="display: block" >
		<div class="login-logo">
			<a  href=""><b><?php echo $_SERVER['SERVER_NAME']; ?></b></a>
		</div>
		<div class="card">
			<div class="card-body login-card-body">
	<!-- /.col -->
				<p id="form_name" class="login-box-msg mb-3">سجل الدخول لبدأ العمل</p>
				<div id="login_massage" class="callout callout-danger text-danger text-center disabled color-palette" style="display:none; font-size:12px;">برجاء التأكد من ادخال البريد الإلكترونى وكلمة المرور الصحيحه</div>
				<form role="form"  id="loginform" action="" method="POST" class="needs-validation" >
		    <div for="login_email_input" id="login_email_div" class="input-group mb-3 flex-row-reverse">
				<input type="email" name="email" id="login_email_input" class="form-control" placeholder="البريد الالكترونى" required>
				<div for="login_email_input" id="login_email_icon" class="input-group-append">
					<div class="input-group-text">
						<span class="fas fa-envelope"></span>
					</div>
				</div>
			</div>
				
					<div id="login_pass_div" class="input-group mb-3 flex-row-reverse">
						<input id="login_pass_input" name="pass" type="password" class="form-control <?php // echo $class_invaled . ' ' . $class_pass ;?> " placeholder="كلمة المرور" required>
						<div id="login_pass_icon" class="input-group-append">
							<div class="input-group-text">
								<span  class="fas fa-lock"></span>
							</div>
						</div>
					</div>
					<div class="row flex-row-reverse">
<!-- button-->
						<div class="col-4">
							<button type="submit" id="login_btn" name="CONTROL" data-type= "<?php if($loginType === 1){echo "admin-" . $pageType ; } else if($loginType === 0){echo "user-" . $pageType; }?>" class="btn btn-primary btn-block btn-flat">دخول</button>
						</div>
<!-- /.button -->
<!-- remember -->
					<div class="col-8 d-flex justify-content-start">
						<div id="login_remember_div" class="icheck-primary">
							<input id="login_remember_input" type="checkbox" id="remember">
							<label for="login_remember_input" id="login_remember_label" for="remember">حفظ الحساب</label>
					</div>
						
						</div>
<!-- /. remember -->
					</div>
					
				</form>
<!-- forget_pass -->
					<p id="login_forget_pass" class="mb-1 d-flex justify-content-start">
						<!--<a id="login_forget_pass_href"   href = "login.php?do=verifyemail">نسيت كلمة السر</a>-->
					</p>
<!-- /.forget_pass -->
<!-- new_account -->
					<p id="login_new_account" class="mb-0 d-flex justify-content-start">
						<!--<a id="login_new_account_href"  href="login.php?do=NewAccount"   class="text-center">انشاء حساب</a>-->
					</p>
<!-- /.new_account -->
			</div>
		</div>
	</div>

<?php 

    echo '
	
    <script src="../../libraries/sweetalert2/sweetalert2.min.js?v='.filemtime("libraries/sweetalert2/sweetalert2.min.js").'"></script>
	<script src="../../dist/js/adminlte.min.js?v='.filemtime("dist/js/adminlte.min.js").'"></script>
	<script src="../../dist/js/demo.js?v='.filemtime("dist/js/demo.js").'"></script>
	<script src="../../dist/js/custom.js?v='.filemtime("dist/js/custom.js").'"></script>';

?>
</body>












