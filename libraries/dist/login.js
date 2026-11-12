//=============== function send request (get or post)============
function sendRequest(url_target, params_obj, method_req, data_type, sync){
    var data_return  = null;
    method_req = method_req || 'POST';
    sync               = sync || false;
    data_type     = data_type || "";
    //console.log(data_obj);
    //alert(url_target +' - ' +method_req + ' - ' + sync +' -' +data_type);
    $.ajax({
        method : method_req,
        async : sync,
        url : url_target,
        data : params_obj,
        dataType : data_type ,
        success: function (data,statuse){
         data_return = data;
        },
        error: function (xhr,statuse, error){
            console.log(error);
        }

    });
    return data_return;
 };
//============== js style fildes login & forget & sinup=============


//=========== function controllers backend for login page ============
function login_post(){
    const x= $('#login_btn').val();
    const url='libraries/controllers.php';
    var params = {};
    
    if (x === 'login') {
        params.doo = x;
        params.email = $("[name='email']").val();
        params.pass = $("[name='pass']").val();
        if (params.email === "" || params.pass ==="") {
                if(!$("[name='email']").val()){$('#login_email_input').addClass('is-invalid');} else {$('#login_email_input').removeClass('is-invalid');}
                if(!$("[name='pass']").val()){$('#login_pass_input').addClass('is-invalid');} else {$('#login_email_input').removeClass('is-invalid');}
            } else {
                const z = sendRequest(url,params);
                
                if (z === 'login-true'){
                    location.reload();
                } else {
                    document.getElementById("login_massage").style.display = "block";
                    document.getElementById("login_massage").innerHTML = "اسم المستخدم أوكلمة المرور غير صحية";
                } ;
            }
            
      } else if ( x === 'NewAccount') {
        params.doo = x;
        params.regfname= $("[name='regfname']").val();
        params.reglname = $("[name='reglname']").val();
        params.email = $("[name='email']").val(); 
        params.pass = $("[name='pass']").val();
        params.repass = $("[name='repass']").val();
        
      } else if ( x === 'verifyemail') {
        params.doo = x;
        params.email = $("[name='email']").val(); 
        if (params.email !=="") {
            const z = sendRequest(url,params);
            alert(z);
            if (z === 'user_is_recorded'){
                $('#strpost').val()='verifyemail';
                login_page_view();
                } else {
                    $('#login_massage').addClass('text-danger');
                    document.getElementById("login_massage").innerHTML = "الحساب غير مسجل لدينا";
                } ;
            } else {
                if(!$("[name='email']").val()){$('#login_email_input').addClass('is-invalid');}
            }
        
      } else if ( x === 'approvalcode') {
        params.doo = x;
        params.email = $("[name='email']").val();
        params.code = $("[name='code']").val();
        
      } else if ( x === 'reset_password') {
        params.doo = x;
        params.email = $("[name='email']").val(); 
        params.pass = $("[name='pass']").val();
        params.repass = $("[name='repass']").val();
      }
    
};
//=============== View control for login page ==============
$(function  login_page_view() {
    
	x=  $('#strpost').val();

	if ( x == "NewAccount") {
			document.getElementById("form_name").innerHTML="انشاء حساب جديد";
        document.getElementById("login_btn").value="NewAccount";
			$('#button').attr('onClick', 'login_post("NewAccount")');
			document.getElementById("login_btn").innerHTML="انشاء";
			document.getElementById("loginbox").style.display = "block";
			document.getElementById("login_remember_div").remove();
			document.getElementById("login_forget_pass").remove();
			document.getElementById("login_code_div").remove();
			document.getElementById("login_new_account").remove();
		} else if ( x == "verifyemail") {
			document.getElementById("form_name").innerHTML = " إسترداد الحساب";
       document.getElementById("login_btn").innerHTML="إرسال";
			document.getElementById("login_btn").value = "verifyemail";
        document.getElementById("login_btn").onClick ="login_post('verifyemail')";
			if (document.getElementById("login_massage").innerHTML == ""){
					document.getElementById("login_massage").innerHTML = "من فضلك أدخل البريد الالكتروني لارسال كود التأكيد";
				};
			
			document.getElementById("login_reg_name").remove();
        document.getElementById("login_pass_div").remove();
        document.getElementById("login_repass_div").remove();
        document.getElementById("login_forget_pass").remove();
			document.getElementById("login_remember_div").remove();
			document.getElementById("login_code_div").remove();
			document.getElementById("loginbox").style.display = "block";
		} else if (x == "approvalcode") {
			
			document.getElementById("login_reg_name").remove();
			document.getElementById("login_pass_div").remove();
			document.getElementById("login_repass_div").remove();
			document.getElementById("login_remember_div").remove();
			document.getElementById("login_forget_pass").remove();
			document.getElementById("login_login").remove();
			document.getElementById("login_new_account").remove();
        document.getElementById("login_email_input").classList.remove("is-invalid");
        document.getElementById("form_name").innerHTML = " إسترداد الحساب";
			document.getElementById("login_btn").innerHTML = "تأكيد";
			document.getElementById("login_btn").value = "approvalcode";
        document.getElementById("login_btn").onclick="login_post('approvalcode')";
        document.getElementById("login_email_input").readonly = "readonly";
			document.getElementById("loginbox").style.display = "block";
		} else if (x == "reset_password") {
			document.getElementById("form_name").innerHTML="إعادة تعين كلمة المرور";
			document.getElementById("login_reg_name").remove();
			document.getElementById("login_new_account").remove();
        document.getElementById("login_remember_div").remove();
			document.getElementById("login_login").remove();
			document.getElementById("login_forget_pass").remove();
			document.getElementById("login_code_div").remove();
        document.getElementById("login_btn").innerHTML = "حفظ";
			document.getElementById("login_btn").value = "reset_password";
        document.getElementById("login_btn").onclick="login_post('reset_password')";
			document.getElementById("loginbox").style.display = "block";
        } else {
			document.getElementById("form_name").innerHTML = "سجل الدخول لبدأ العمل";
        document.getElementById("login_btn").value = "login";
			document.getElementById("login_reg_name").remove();
			document.getElementById("login_repass_div").remove();
			document.getElementById("login_login").remove();
			document.getElementById("login_code_div").remove();
			document.getElementById("loginbox").style.display = "block";
     };
    });
    