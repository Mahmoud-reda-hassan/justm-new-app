/*
Math.ceil();
* id = userView		"منطقة عرض المستخدمين"  
* id = userCard		"بطاقة المستخدم" 
* id = userType		"نوع المستخدم"  
* id = userName		"اسم المستخدم" 
* id = userAdress	"عنوان المستخدم"  
* id = userPhone	"تلفون المستخدم" 
* id = userImg		"صورة المستخدم" 
* id = btnUEdit 
* id = btnUEdit		"زر تعديل بيانات المستخدم"  
* id = pageNav		"ناقل الصفحات"  
*/

let errmail=false,errpass=5;
let obj = {},action = "";
const plength = 9;
const addressoption     = sendRequestGET(urlApi+'?CONTROL=getSelectOptionCountries');
const company = sendRequestGET(urlApi+'?CONTROL=getdropEnterpris');
const branch  = sendRequestGET(urlApi+'?CONTROL=getdropBranches');
const ugop    = sendRequestGET(urlApi+'?CONTROL=getdropUserGroup');
const uaffi   = sendRequestGET(urlApi+'?CONTROL=UserList&utype=2&start=0');
const zone    = sendRequestGET(urlApi+'?CONTROL=getdropLocalShipping2');
/*==========================================
function
==========================================*/
function validMail(e) {
    var x = false, regex = /^([a-z0-9!#?$%&'*+\-/=?^_`{|}~]+(?:\.[a-zA-Z0-9!#$%&'*+\-/=?^_`{|}~]+)*)@((?:[a-z0-9]+(?:[a-z-0-9-]*)\.)+[a-z]{2,})$/gi;
    $('#mailspan').remove();
    e.removeClass('is-invalid');
    e.removeClass('is-valid');
    if (regex.test(e.val()) && e.val().length > 0) {
        e.addClass('is-valid');
		errmail=true;
        x = true;
    } else if (e.length > 0) {
		errmail=false;
		e.addClass('is-invalid');
        e.after('<span id="mailspan" class="text-danger">تنسيق البريد الصحيح : مثال ( yorname@domin.com ) </span>');
    }
    return x;
}
function validPass(e) {
    let emsg = "<ol class='text-left mt-3 mb-3 mr-0 pr-3 pl-3 ml-0' style='font-size:0.7em'> ",password=e.val(),err=0;
	if (typeof password != "string") {
        password = "" + password;
    }
    if (password.length < 8) {
        emsg +=  '<li class="text-danger">يجب أن يكون طول كلمة المرور 8 أحرف على الاقل</li>';
		err++;
    } else {
		emsg +=  '<li class="text-success"><del>يجب أن يكون طول كلمة المرور 8 أحرف على الاقل</del></li>';
	}
    if (password.search(/[0-9]/) === -1) {
        emsg +=  '<li class="text-danger">لابد ان تحتوى كلمة المرور على رقم واحد على الاقل</li>';
		err++;
    } else {
		emsg +=  '<li class="text-success"><del>لابد ان تحتوى كلمة المرور على رقم واحد على الاقل</del></li>';
	}
    if (password.search(/[a-z]/) === -1) {
        emsg +=  '<li class="text-danger">لابد ان تحتوى كلمة المرور على حرف صغيرة واحدة على الأقل [a-z]</li>';
		err++;
    } else {
		emsg +=  '<li class="text-success"><del>لابد ان تحتوى كلمة المرور على حرف صغيرة واحدة على الأقل [a-z]</del></li>';
	}
    if (password.search(/[A-Z]/) === -1) {
        emsg +=  '<li class="text-danger">يجب ان تحتوى كلمة المرور على حرف كبير واحدة على الأقل [A-Z]</li>';
		err++;
    } else {
		emsg +=  '<li class="text-success"><del>يجب ان تحتوى كلمة المرور على حرف كبير واحدة على الأقل [A-Z]</del></li>';
	}
    if (password.search(/[`~!@#\$%\^&\*\(\)\-_\{\}\=\+'"\>\.\,\?<\/\|\\\:;\]\[]/) === -1) {
        emsg +=  '<li  class="text-danger">حرف خاص واحد على الأقل [~! @ # $٪ ^ & * () _ + {} ":؟> <...]</li>';
		err++;
    } else {
		emsg +=  '<li  class="text-success"><del>حرف خاص واحد على الأقل [~! @ # $٪ ^ & * () _ + {} ":؟> <...]</del></li>';
	}
	
	errpass=err;
	emsg += "</ol>";
	e.removeClass('is-invalid');
	e.removeClass('is-valid');
	if(err>0){
		e.addClass('is-invalid');
	}else{
		emsg="<a class='text-success'>كلمة المرور اصبحت قوية الان</a>";
		e.addClass('is-valid');
	}
	$('#passSpan').remove();
    e.parent().append('<span id="passSpan">'+emsg+'</span>');
}
function udata(start,length,utype,search,draw){
    let dataRq = sendRequestGET(urlApi+'?CONTROL=UserList&start='+ start+'&length='+plength+'&utype='+utype+'&search='+search+'&draw='+draw);
    return dataRq
}
function PSelected(d){
	$('.page-item').each(function(){
		let x = $(this).attr('data-index');
		if( x == d){
			$(this).addClass("active");
		}
	})
}
function viewListUser(data){
	let d = data['data'],pageCount = Math.ceil(parseInt(data['iTotalRecords'])/plength),elu="",elp="",img="";
	for(let x in d){
		if(d[x]['STATUSE'] ==0){
		    $('#userCard .userType').html('الحساب موقوف عن العمل');
		    $('#userCard .userType').removeClass('badge-success');
		    $('#userCard .userType').addClass('badge-danger');
		}else{
		    $('#userCard .userType').html('الحساب نشط');
		    $('#userCard .userType').removeClass('badge-danger');
		    $('#userCard .userType').addClass('badge-success');
		}
		$('#userCard .userName').html(d[x]['NAME']);
		$('#userCard .userAdress').html(d[x]['ADDRISS']);
		$('#userCard .userPhone').html(d[x]['PHONE']);
		$('#userCard .userEmail').html(d[x]['USER_LOGIN']);
	//	$('#USER_PASSWORD').parent('div').show();
		if(d[x]['UIMG']){
			img = '<img  src="../../../img/'+d[x]['UIMG']+'" alt="user-avatar" class="img-circle img-fluid">';
		}else{
			img = '<img  src="../../img/usericon.png" alt="user-avatar" class="img-circle img-fluid">';
		}
		let job="";
		if(d[x]['ADMIN_TYPE']==1){
		    job +='<li class="small pl-2 mb-1 badge-primary"><span class="fa-li text-secondary"><i class="fa fa-lg fa-briefcase"></i></span><b><a>'+d[x]['JOP']+'</a></b></li>';
		}
		if(d[x]['DELIVERY_TYPE']==1){
		    job +='<li class="small pl-2 mb-1 badge-success"><span class="fa-li text-secondary"><i class="fa fa-lg fa-truck"></i></span><b><a> موظف شحن </a></b></li>';
		}
		if(d[x]['AFFILIATE_TYPE']==1){
		    job +='<li class="small pl-2 mb-1 badge-danger"><span class="fa-li text-secondary"><i class="fa fa-lg fa-bullhorn"></i></span><b><a> منسق </a></b></li>';
		}
		if(d[x]['INTERMEDIARY_TYPE']==1){
		    job +='<li class="small pl-2 mb-1"><span class="fa-li "><i class="fa fa-lg  fa-handshake"></i></span><b><a> وسيط بيع</a> </b></li>';
		}
		if(d[x]['MERCHANT_TYPE']==1){
		    job +='<li class="small pl-2 mb-1"><span class="fa-li"><i class="fa fa-lg fa-user-plus"></i></span><b><a>عميـل جملة</a> </b></li>';
		}
		if(d[x]['DELIVERY_TYPE']==0 && d[x]['AFFILIATE_TYPE'] ==0 && d[x]['INTERMEDIARY_TYPE'] ==0 && d[x]['ADMIN_TYPE'] ==0 && d[x]['MERCHANT_TYPE'] ==0){
		    job +='<li class="small pl-2 mb-1"><span class="fa-li"><i class="fa fa-lg fa-user"></i></span><b><a> عميـل تجزئة </a></b></li>';
		}
		$('.btnUEdit').attr('data-userid',d[x]['ID']);
		$('.btnUEdit').attr('data-telid',d[x]['TEL_ID']);
        $('.btnUEdit').attr('data-addressid',d[x]['ADDRESS_ID']); 
		$('#userCard .job').html(job);
		$('#userCard .userImg').html(img);
		$('#btnUEdit').attr('data-id',d[x]['ID']);
		elu += $('#userCard').html();
		
	}
    for(let i = 0; i < pageCount; i++){
	    if(i == data['drow']){
	        elp += '<li class="page-item active" data-index="'+i+'"><a class="page-link" href="#">'+(i+1)+'</a></li>';
	    }else{
	        elp += '<li class="page-item" data-index="'+i+'"><a class="page-link" href="#">'+(i+1)+'</a></li>';
	    }
    }
	$('#userView').html(elu);
	$('#pageNav').html(elp);
	$(document).ready(function(){
		PSelected(data['draw']);
	});
}
function data_to_option(d,val_id,val_text){
    let htm = '<option value="">فارغ</option>';
    for(let i in d){
	    htm +='<option value="'+d[i][val_id]+'">'+d[i][val_text]+'</option>'
    }
    return htm;
}
/////////////////////////////////////////////////////////////////////////////////////////////////
$(document).ready(function(){
    $('#COMPANY_ID').html(data_to_option(company,0,2));
    $('#BRANCH_ID').html(data_to_option(branch,0,2));
    $('#USER_GRUOP_ID').html(data_to_option(ugop,0,1));
    $('#ZONE_ID').html(data_to_option(zone,0,2));
    $('#AFFILIATEED_WITH').html(data_to_option(uaffi['data'],'ID','NAME'));
	$('#btnSave').hide();
	$('#btnEdit').hide();
	$('#btnCancel').hide();
	$("#btnDelete").hide();
	let dat=udata(0,plength,"","",0);
	viewListUser(dat);
    //============ user type ==================
	$('#userTypeoption').change(function(){
		let rqu,utval=$(this).val();
		setTimeout(function(){
		    rqu = udata(0,plength,utval,$("#userSearch").val(),0);
			viewListUser(rqu);
		},100)
	});
	//============ Search =====================
	$('#userSearch').keyup(function(){
		let rqt,valu = $(this).val();
		setTimeout(function(){
		    rqt = udata(0,plength,$('#userTypeoption').val(),valu,0);
			viewListUser(rqt);
		},1000)
	});
    /////////////////////////////////////////////////////////////////////////////////////////////////
    /*=====================================================================
    	Buttons pages
    =======================================================================*/
	$(document).on('click','#btnNew',function(){
		
		let e = $(this);
	    $('.req').each(function(){
			if($(this).attr('type') !== "radio"){
				$(this).val("").change();
				$(this).removeClass('is-invalid , is-valid');
			}
		})
		$('#usersView').fadeOut(100);
	    $('#btnNew').fadeOut(1);
	    $('#addNewUser').fadeIn(100);
	    $('#btnSave').fadeIn(100);
	    $('#btnCancel').fadeIn(100);
		$('.breadcrumb .active ').removeClass('active');
		$('.breadcrumb').append('<li class="breadcrumb-item unew active">مستخدم جديد</li>');
		addressclient($("#userAddress-Option"),"",1,1);
		elPhone($("#userTel"));
		$('.pass').keyup(function(){
			let e = validPass($(this));
			$('#passSpan').html(e);
		})
		$('.pass').change(function(){
			let e = validPass($(this));
			$('#passSpan').html(e);
		})
		$('.email').keyup(function(){
			 errmail = validMail($(this))
		})
		$('#FORENAME').keyup(function(){
			let x = $(this).val(),i=x.length-1;
			 if(x.charAt(i) === " "){
				 $('#LASTNAME').focus();
			 }
		})
		$('#LASTNAME').keyup(function(){
			let x = $(this).val();
			 if(x === ''){
				 $('#FORENAME').focus();
			 }
		})
		$('.cli').change(function(){
			 if(!$(this).val()){
				 $(this).addClass('is-invalid')
			 } else {
				 $(this).removeClass('is-invalid')
			 } 
		})
		$('#USER_LOGIN').prop('disabled',false);
		$('#USER_PASSWORD').parent('div').show();
		$('#SUSPENDED').prop('checked','true');
		$('#whatsapp').prop('checked','');
		$('#telegram').prop('checked','');
		$('#switch1').prop('checked','');
		$('#switch2').prop('checked','');
		$('#switch3').prop('checked','');
		$('#switch4').prop('checked','');
		$('#switch5').prop('checked','');
		$('#switch6').prop('checked','');
		$('#passSpan').remove();
		$('#telspan').remove();
		$('#mailspan').remove();
		$('#defultTel').prop('checked','true');
		$('#defultAdd').prop('checked','true');
	    $('#defultTel').parent().hide();
	    $('#defultAdd').parent().hide();
		$('#admin_setting').hide();
		$('#shipping_setting').hide();
		$('#affiliate_setting').hide();
		$('.btnPass').hide();
	    //code
        if(e.attr('data-id')  !== "new" && e.attr('data-telid') !== "" && e.attr('data-addressid') !== ""){
			obj['USER_ID']      = e.attr('data-userid');
			obj['TEL_ID']       = e.attr('data-telid');
			obj['ADDRESS_ID']   = e.attr('data-addressid');
			action = 'updateUser';
			let u= sendRequestGET(urlApi+'?CONTROL=UserData&user_id='+obj['USER_ID']);
			u = u.data[0];
				$('#FORENAME').val(u['FORENAME']);
				$('#LASTNAME').val(u['LASTNAME']);
				
				if(u['TELEGRAM'] == 1){$('#telegram').prop('checked',true);}else{$('#telegram').prop('checked','');}
				if(u['WHATSAPP'] == 1){$('#whatsapp').prop('checked',true);}else{$('#whatsapp').prop('checked','');}
				$('#cli_tel').val(u['PHONE']).change();
				$('#addressCountry').val(u['COUNTRY_ID']).change();
				$('#addressGov').val(u['GOV_ID']).change();
				$('#addressCity').val(u['CITY_ID']).change();
				$('#cli_address').val(u['ADDRESS_TEXT']).change();
				
				$('#COMPANY_ID').val(u['COUNTRY_ID']).change();
				$('#BRANCH_ID').val(u['BRANCH_ID']).change();
				
				if(u['GENDER'] == 'male'){
					$('#gender1').prop('checked',true);
				}else{
					$('#gender2').prop('checked',true);
				}
				if(u['ADDRESS_TITLE'] == 'المنزل'){
					$('#address_type1').prop('checked', true);
				}else{
					$('#address_type2').prop('checked', true);
				}

				if(u['STATUSE'] == 1){
					$('#SUSPENDED').prop('checked',true);
				}else{
					$('#SUSPENDED').prop('checked',false);
				}
				if(u['ADMIN_TYPE'] == 1){
					$('#switch1').prop('checked',true).change();
				}else{
					$('#switch1').prop('checked',false).change();
				}
				if(u['DELIVERY_TYPE'] == 1){
					$('#switch2').prop('checked',true).change();
				}else{
					$('#switch2').prop('checked',false).change();
				}
				if(u['AFFILIATE_TYPE'] == 1){
					$('#switch3').prop('checked',true).change();
				}else{
					$('#switch3').prop('checked',false).change();
				}
				//switch4 
				//switch5
				//switch6
				
				$('#USER_GRUOP_ID').val(u['USER_GRUOP_ID']).change(); 
				
				$('#ZONE_ID').val(u['SHIPPING_ZONE_ID']).change(); 
				$('#SHIPP_PERCENT').val(u['PERCENT']).change(); 
				$('#USER_LOGIN').val(u['USER_LOGIN']).change();
				
			//	$('#USER_PASSWORD').val('*************').change();
				$('#AFFILIATEED_WITH').val(u['AFFILIATEED_WITH']).change(); 
				$('#COMMISSION_SALES').val(u['COMMISSION_SALES']).change(); 
				$('#TEAM_COMMISSION').val(u['TEAM_COMMISSION']).change(); 
                //$('#USER_LOGIN').attr('disabled',true);
			    //$('#USER_PASSWORD').removeClass('req');
                $('#USER_LOGIN').attr('disabled',true);
			    $('#USER_PASSWORD').parent('div').hide();
				$('.btnPass').show();
		} else {
			obj['USER_ID'] = "";
			obj['TEL_ID'] = "";
			obj['ADDRESS_ID'] = "";
			action = 'insertUser';
		}

	})
	$(document).on('click','#btnSave',function(){
	    let errel=0,errtype=0;
		let Phone = checkPhoneNum($("#cli_tel"));
		function reading_data(el){
			if(el.attr('type') === 'checkbox'){
	            obj[el.attr('name')]= el.is(':checked');
	       }else if(el.attr('type') === 'radio' && el.is(':checked')){
				obj[el.attr('name')]=el.val();
	       }else if(el.attr('type') !=='radio' && el.attr('type') !=='checkbox'){
	            obj[el.attr('name')]=el.val();
	       }
	       if(el.is(':visible')){
			   let type = el.attr('type');
	           if((type !=="radio" && type !=="checkbox") && el.hasClass('req') && !el.hasClass('affw') && el.val() == ''){
	               el.addClass('is-invalid');
	               errel++;
	           } else {
	               if(el.hasClass('is-invalid') && !el.hasClass("cli")){
	                    el.removeClass('is-invalid');
	               }
	           }
	           if(el.hasClass("sw") && el.is(':checked')){
	                errtype++;
	           }
	        }
		}
	    $('input').each(function (){
	       reading_data($(this))
	    })
		$('select').each(function (){
	       reading_data($(this))
	    })
		$('textarea').each(function (){
	       reading_data($(this))
	    })
	    if(obj['USER_ID']!==''){
		    errpass =0;
		    errmail =true;
		}
	    if(errtype > 0 && errmail == true && errpass === 0 && Phone == true){
	        let data = sendRequest(urlControl,params={'CONTROL':action,'data':obj});
	        if(data[0] == 's'){
	            Swal.fire({
				  title: 'تم حفظ بيانات المستخدم بنجاح!',
				  icon: 'success',
				  html: 'جارى عرض بيانات المستخدمين',
				  timer: 2000,
				  timerProgressBar: true,
				  didOpen: () => {
					Swal.showLoading()
					const b = Swal.getHtmlContainer().querySelector('b')
					timerInterval = setInterval(() => {
					 // b.textContent = Swal.getTimerLeft()
					}, 1000)
				  },
				  willClose: () => {
					clearInterval(timerInterval)
				  }
				}).then((result) => {
                    $('#addNewUser').fadeOut(100);
	                $('#btnSave').fadeOut(1);
	                $('#btnCancel').fadeOut(1);
	                $('#usersView').fadeIn(100);
	                $('#btnNew').fadeIn(100);
	                $(".breadcrumb li").last().remove ();
	                $(".breadcrumb li").last().addClass('active');
	                $("#userSearch").val('');
	                $('#userTypeoption').val('').change();
	                $('#SUSPENDED').attr('checked',false);
	                $('#switch1').attr('checked',false);
	                $('#switch2').attr('checked',false);
	                $('#switch3').attr('checked',false);
	                $('#switch4').attr('checked',false);
	                $('#switch5').attr('checked',false);
	                $('#switch6').attr('checked',false);
				})
	        } else {
	            Toast_fire(data);
	        }
	    } else {
	        let msg = "<lo>";
	        if(errtype == 0){
	            msg+='<li>يجب اختيار صلاحية دخول واحده على الاقل</li>';
	        }
			if(errel>0 || Phone==false){
				msg+='<li>جميع الحقول المحدده باللون الاحمر مطلوبة</li>';
			}
	        msg+='</lo>';
	        Toast_fire(['e',msg]);
	    }
	})
	$(document).on('click','#btnCancel',function(){
	    $('#addNewUser').fadeOut(100);
	    $('#btnSave').fadeOut(1);
	    $('#btnCancel').fadeOut(1);
	    $('#usersView').fadeIn(100);
	    $('#btnNew').fadeIn(100);
	    
		$(".breadcrumb li").last().remove ();
	    $(".breadcrumb li").last().addClass('active');
	    // code
	})
	$(document).on('click','#btnUEdit',function(){
	    
	})
/*=====================================================================
	switch user type
=======================================================================*/
	//ADMIN_TYPE
	$(document).on('change','#switch1',function(){
	    if($(this).is(':checked')){
	        let opt ='<option val="" selected>اختار وظيفة للمستخدم</option>';
	        for(let v in ugop){
	            opt +='<option val="'+ugop[v][1]+'">'+ugop[v][1]+'</option>';
	        }
	        $('#adminGroup').html(opt);
	        $('#admin_setting').fadeIn(700);
	        $('#adminGroup').attr('disabled',false);
	    }else{
	        $('#adminGroup').html('');
	        $('#admin_setting').fadeOut(700);
	        $('#adminGroup').attr('disabled',true);
	    }
	})
	//DELIVERY_TYPE
	$(document).on('change','#switch2',function(){
	    if($(this).is(':checked')){
	        $('#shipping_setting').fadeIn(700);
	    }else{
	        $('#SHIPP_PERCENT').val('');
	        $('#shipping_setting').fadeOut(700);
	    }
	})
	//AFFILIATE_TYPE
	$(document).on('change','#switch3',function(){
	    if($(this).is(':checked')){
	        $('#affiliate_setting').fadeIn(700);
	    }else{
	        $('#CommissionSales').val('');
	        $('#affiliate_setting').fadeOut(700);
	    }
	})
	//INTERMEDIARY_TYPE
	/*$(document).on('change','#switch1',function(){
	    if($(this).is(':checked')){
	        $('#admin_setting').fadeIn(700);
	        $('#adminGroup').attr('disabled',false);
	    }else{
	        $('#adminGroup').html('');
	        $('#admin_setting').fadeOut(700);
	        $('#adminGroup').attr('disabled',true);
	    }
	})
	//MERCHANT_TYPE
	$(document).on('change','#switch1',function(){
	    if($(this).is(':checked')){
	        $('#adminGroup').attr('disabled',false);
	    }else{
	        $('#adminGroup').html('');
	        $('#adminGroup').attr('disabled',true);
	    }
	})
	//=========== USER public TYPE ===================
	$(document).on('change','#switch1',function(){
	    if($(this).is(':checked')){
	        $('#adminGroup').attr('disabled',false);
	    }else{
	        $('#adminGroup').html('');
	        $('#adminGroup').attr('disabled',true);
	    }
	})*/
/*=====================================================================
	PAGES LINK
=======================================================================*/
	$(document).on('click','.page-item',function(){
	    let s  = $(this).attr('data-index') *2;
	    let rqs = udata(s,plength,$("#userTypeoption").val(),$("#userSearch").val(),$(this).attr('data-index'));
	    viewListUser(rqs);
	});
	$(document).on('click','.btnPass',function(){
		//uppass {user_id,uppass}
	    Swal.fire({
	        html: `<div class="form-group">
                        <label for="EDIT_USER_PASSWORD">من فضلك ادخل كلمة المرور الجديده</label>
                        <input type="password" class="form-control editpass  req" name="EDIT_USER_PASSWORD" id="USER_PASSWORD" placeholder="Password" required="">
                    </div>`,
	        icon: "info",
	        showCancelButton: true,
	        confirmButtonColor: '#3085d6',
	        cancelButtonColor: '#d33',
	        cancelButtonText: 'الغاء',
	        confirmButtonText: 'حفظ',
	        didOpen: () => {
	            $(document).on('keyup','.editpass',function(){
	            	validPass($(this))
	            })
	        },
	        preConfirm: function () {
	        	if ($(".editpass").hasClass("is-invalid")) {
					Swal.showValidationMessage('كلمة المرور لا تستوفى جميع الشروط المذكوره');
					return false;
	        	}
	        	return $('.editpass').val()
	        }
	    }).then((result) => {
	        if(result.isConfirmed ){
	        	const req = sendRequest('../../inc/Api.php',{'CONTROL':'uppass','user_id' : obj['USER_ID'] ,'uppass' : result.value});
	        	Toast_fire(req);
	        }
	    });
	});
		
})