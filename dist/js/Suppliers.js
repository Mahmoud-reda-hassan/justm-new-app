
/*====================================================================================================
function client data
======================================================================================================*/
function edittell(tel_id, tel_num, whatsapp, telegram, defultTel,cli_id ='') {

	Swal.fire({
	    title: 'إضافة رقم هاتف جديد',
	    html: '<div id="swl-tel" style="font-size:15px"></div>',
	    showCancelButton: true,
	    confirmButtonText: 'حفظ',
	    cancelButtonText: 'إلغاء',
	    didOpen: () => {
	        // إعداد عناصر الإدخال في النموذج
	        
	        elPhone($("#swl-tel"));
	        
	        $("#cli_tel").val(tel_num || '').change();
	        $("#whatsapp").prop('checked', whatsapp);
	        $("#telegram").prop('checked', telegram);
	
	        if (defultTel === 1) {
	            $("#defultTel").prop('checked', true).prop('disabled', true);
	        }
	    },
	    preConfirm: () => {
	        const ajaxpar = {
	            CONTROL: 'updateSupplierTel',
	            tel_id: tel_id,
	            cli_tel: $('#cli_tel').val().trim(),
	            tel_type: $('.tele-type1').is(':checked') ? 'هاتف جوال' :
	                      ($('.tele-type2').is(':checked') ? 'هاتف أرضى' : ''),
	            whatsapp: $('.whatsapp').is(':checked') ? '1' : '0',
	            telegram: $('.telegram').is(':checked') ? '1' : '0',
	            defultTel: $('#defultTel').is(':checked') ? '1' : '0',
	        };
	
	        // التحقق من صحة الإدخالات
	        if ($('#cli_tel').hasClass('is-invalid') || ajaxpar.cli_tel === '' || !$('#cli_tel').hasClass('is-valid')) {
	            $('#cli_tel').addClass('is-invalid');
	            return Swal.showValidationMessage('جميع الحقول المحددة باللون الأحمر مطلوبة');
	        }
	
	        // إرسال الطلب
	        const datarq = sendRequest(urlControl, ajaxpar);
	
	        if (datarq[0] === "msg") {
	            return Swal.showValidationMessage(datarq[1]);
	        } else {
				datarq.push(tel_num);
	            return datarq
	        }
	    }
	}).then((result) => {
	    if ( result.isConfirmed && result.value[0] =='s') {
	    	html=getSuppData(cli_id, 0, 0, result.value[2]);
			$('#result').html('');
			$("#editSupplierstData").html("");
			$("#editSupplierstData").html(html);
			$('#btnAddNewOrder').remove();
			Toast_fire(result.value);
	    }
	    
	});
	
};
function delclitel(cli_id, tel_id, tel_num) {
    let el = $(this), datarq, ajaxpar = {};
    ajaxpar['CONTROL'] = 'deleteSupplierTel';
    ajaxpar["cli_id"] = cli_id;
    ajaxpar["tel_id"] = tel_id;

    Swal.fire({
        title: 'انت متأكد ؟',
        text: " سوف يتم حذف "+tel_num.toString()+" نهائيا ولا يمكن استرجعاه مره اخرى",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'حذف',
        cancelButtonText: 'إلغاء',
        preConfirm: function () {
            datarq = sendRequest(urlControl, ajaxpar);
            datarq.push(tel_num);
            return datarq;
        }
    }).then((result) => {
	    if (result.value[0] =='s') {
	    	html=getSuppData(cli_id, 0, 0, result.value[2]);
			$('#result').html('');
			$("#editSupplierstData").html("");
			$("#editSupplierstData").html(html);
			$('#btnAddNewOrder').remove();
	    }
	    Toast_fire(result.value);
	});
};
function editAdd(add_id, country_id, gov_id, city_id, address_type, address, defultAdd) {
    Swal.fire({
        title: 'تعديل عنوان',
        //icon: 'success',
        html: '<div id="swl-Add" style="font-size:15px"></div>',
        showCancelButton: true,
        confirmButtonText: 'حفظ',
        cancelButtonText: 'إلغاء',
        didOpen: () => {
            addressclient($('#swl-Add'), "", country_id, gov_id, city_id);
            $("#cli_address").val(address);
            if (address_type === 'المنزل') {
                $('#address_type1').prop('checked', true)
            } else {
                $('#address_type2').prop('checked', true)
            }
            if (defultAdd === 1) {
                $('#defultAdd').prop('checked', true);
                $('#defultAdd').prop('disabled', true);
            }
        },
        preConfirm: function () {
            let ajaxpar = {};
            ajaxpar['CONTROL'] = 'updateClientAdd';
            ajaxpar['add_id'] = add_id;
            ajaxpar['addressCity'] = $("#addressCity").val();
            ajaxpar['cli_address'] = $("#cli_address").val();

            if ($('#address_type1').is(':checked')) {
                ajaxpar['address_type'] = 'المنزل';
            } else if ($('#address_type2').is(':checked')) {
                ajaxpar['address_type'] = 'العمل'
            }
            if ($('#defultAdd').is(':checked')) {
                ajaxpar['defultAdd'] = '1';
            } else {
                ajaxpar['defultAdd'] = '0';
            }
            $(".req").each(function() {
                // alert($(this).attr('id')+"-"+$(this).val());
                $(this).removeClass('is-invalid');
                if ($(this).val() === "" || $(this).val() === null) {
                    $(this).addClass('is-invalid');
                }
            })
            if ($('#cli_address').val() === "" || $('#addressCity').val() === null) {
                Swal.showValidationMessage('جميع الحقول المحدده باللون الاحمر مطلوبه');
            } else {
                let datarq = sendRequest(urlControl, ajaxpar);
                if (datarq[0]==="msg") {
                    Swal.showValidationMessage(datarq[1]);
                } else {
                    let d = JSON.parse(localStorage.getItem('orderlog'));
                    if(d && typeof d == "object" && address != ajaxpar['cli_address']){
                        d.push('* تم تغير العنوان: '+ address +' الى: '+ajaxpar["cli_address"]+' - للعميل: '+$(".widget-user-username").text());
                        localStorage.setItem('orderlog',JSON.stringify(d))
                    }
                    return datarq;
                }
            }
        }
    }).then((result) => {
        if (typeof result.value === "object") {
            if($("#sreachform").is(":visible")){
                $("#btnSearchClient").click();
            } else if($("#editClientData").is(":visible")){
                $(".editcliOrder").click();
            }
            Toast_fire(result.value);
        }
        $(".addradio").each(function () {
            if (add_id*1 === $(this).attr('id')*1) {
                $(this).prop('checked', true);
            }
        });
    })
}
function delcliAdd(cli_id, add_id,el) {
    let datarq, ajaxpar = {};
    let addresstext=el.parents('tr').children('td').eq(0).children('div').children('label').html();
    ajaxpar['CONTROL'] = 'deleteClientAdd';
    ajaxpar["cli_id"] = cli_id;
    ajaxpar["add_id"] = add_id;
    Swal.fire({
        title: 'انت متأكد ؟',
        text: " سوف يتم حذف هذا العنوان نهائيا ولا يمكن استرجعاه مره اخرى",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'حذف',
        cancelButtonText: 'إلغاء',
        preConfirm: function () {
            datarq = sendRequest(urlControl,ajaxpar);
            Toast_fire(datarq);
            if($("#sreachform").is(":visible")){
                $("#btnSearchClient").click();
            } else if($("#editClientData").is(":visible")){
                $(".editcliOrder").click();
            }
            let d = JSON.parse(localStorage.getItem('orderlog'));
            if(d && typeof d == "object"){
                d.push('* تم حذف العنوان : '+ addresstext +' - من حساب العميل : '+$(".widget-user-username").text());
                localStorage.setItem('orderlog',JSON.stringify(d))
            }
            return datarq;
        }
    }).then((result) => {
       
    })
};
function getSuppData(id, tel_id, add_id, number) {
    let datarq;
    datarq = sendRequestGET(urlApi+'?CONTROL=searchSuppliers&cli_search='+number+'&cli_id='+id+'&tel_id='+tel_id+'&add_id='+add_id);
    return datarq;
}
$(document).ready(function () {
	$(document).on("click",".newtel",   function (){

        let cli_id= $(this).attr("data-cli_id");

        Swal.fire({
            title: 'إضافة رقم هاتف جديد',
            html:'<div id="swl-tel" style="font-size:15px"><div>',//'<div class="form-group"><label class="col-sm-2 d-flex justify-content-start" for="COM_TEL">الهاتف</label><input type="number" class="form-control phone1 cli req" id="cli_tel" name="clitel" placeholder="ادخل رقم الهاتف" required></div>',
            showCancelButton: true,
            confirmButtonText:  'حفظ',
            cancelButtonText: 'إلغاء',
            didOpen: () =>{
				elPhone($("#swl-tel"));
            },
            preConfirm: function () {
				let err = 0,el=$("#swl-tel"),ajaxpar={};
	            ajaxpar['CONTROL']= 'addSupplierTel';
	            ajaxpar['cli_id']=cli_id;
	            ajaxpar['cli_tel']=$('#cli_tel').val();
	            if($('.tele-type1').is(':checked')){
	                ajaxpar['tel_type'] = 'هاتف جوال';
	            } else if($('.tele-type2').is(':checked')){
	                ajaxpar['tel_type'] = 'هاتف أرضى'
	            }
	            if($('.whatsapp').is(':checked')){
	                ajaxpar['whatsapp'] = '1';
	            } else {
	                ajaxpar['whatsapp'] = '0';
	            }
	            if ($('.telegram').is(':checked')){
	                ajaxpar['telegram'] = '1';
	            } else {
	                ajaxpar['telegram'] = '0';
	            }
	            if ($('#defultTel').is(':checked')){
	                ajaxpar['defultTel'] = '1';
	            } else {
	                ajaxpar['defultTel'] = '0';
	            }
	            if($('#cli_tel').hasClass('is-invalid') === true ){
	                Swal.showValidationMessage('جميع الحقول المحدده باللون الاحمر مطلوبة');
	            }else if($('#cli_tel').val()==''){
                    Swal.showValidationMessage('جميع الحقول المحدده باللون الاحمر مطلوبة');
                    el.children('#cli_tel').addClass("is-invalid");
	            } else {
	                let datarq = sendRequest(urlControl,ajaxpar);
	                if(datarq[0] !== "msg"){
	                    let d = JSON.parse(localStorage.getItem('orderlog'));
                        if(d && typeof d == "object"){
                            d.push('* تم اضافة الهاتف : '+ajaxpar["cli_tel"]+' - للعميل: '+$(".widget-user-username").text());
                            localStorage.setItem('orderlog',JSON.stringify(d))
                        }
                        datarq.push($('#cli_tel').val());
	                    return datarq
	                } else {
	                    Swal.showValidationMessage(datarq[1]);
	                }
                }
            }
        }).then((result) => {
            
                if(result.value[0]== 's'){
               
                	html=getSuppData(cli_id, 0, 0, result.value[2]);
			        $('#result').html('');
			        $("#editSupplierstData").html("");
			        $("#editSupplierstData").html(html);
			        $('#btnAddNewOrder').remove();
                } 
                Toast_fire(result.value);
            
        })
    });
	$(document).on("click",".newAdd",   function (){
	        let cli_id= $(this).attr("data-cli_id");
	        Swal.fire({
	                title: 'إضافة عنوان جديد',
	                //icon: 'success',
	                html:'<div id="swl-Add" style="font-size:15px"></div>',            
	                showCancelButton: true,
	                confirmButtonText:  'حفظ',
	                cancelButtonText: 'إلغاء',
	                didOpen: () =>{
	                    addressclient($('#swl-Add'),"",1,1);
	                    
	                },
	                preConfirm: function () {
	                    let city = $("#addressCity").children(':selected').text();
	                    let Gov = $("#addressGov").children(':selected').text();
	                    let Country = $("#addressCountry").children(':selected').text();
	                    let err = 0,el=$("#swl-Add") ,ajaxpar={};
		                ajaxpar['CONTROL']= 'addClientAdd';
		                ajaxpar['cli_id']=cli_id;
		                ajaxpar['addressCity']=$("#addressCity").val();
		                ajaxpar['cli_address']=$("#cli_address").val();
		                 
		                if($('#address_type1').is(':checked')){
		                    ajaxpar['address_type'] = 'المنزل';
		                } else if($('#address_type2').is(':checked')){
		                    ajaxpar['address_type'] = 'العمل'
		                }
		                if ($('#defultAdd').is(':checked')){
		                    ajaxpar['defultAdd'] = '1';
		                } else {
		                    ajaxpar['defultAdd'] = '0';
		                }
		                $(".req").each(function(){
		                   // alert($(this).attr('id')+"-"+$(this).val());
		                   $(this).removeClass('is-invalid');
		                   if( $(this).val()==="" ||  $(this).val()===null){
		                       $(this).addClass('is-invalid');
		                   }
		                })
		                if($("#swl-Add .is-invalid").length > 0){
	                        Swal.showValidationMessage('جميع الحقول المحدده باللون الاحمر مطلوبه');
	                    } else {
		                    let datarq = sendRequest(urlControl,ajaxpar);
	                        if(datarq[0] !== "msg"){ 
	                            let d = JSON.parse(localStorage.getItem('orderlog'));
	                            if(typeof d == "object"){
	                                d.push('* تم اضافة العنوان : ('+ ajaxpar['cli_address']+' - '+city+' - '+Gov+' - '+Country+') - لحساب العميل: '+$(".widget-user-username").text());
	                                localStorage.setItem('orderlog',JSON.stringify(d))
	                            }
	                            return datarq;
		                    } else {
		                        Swal.showValidationMessage(datarq[1]);
		                    }
	                    }
	                }
	            }).then((result) => {
	                if(typeof result.value==="object"){
	                    if($("#sreachform").is(":visible")){
	                        $("#btnSearchClient").click();
	                    } else if($("#editClientData").is(":visible")){
	                        $(".editcliOrder").click();
	                    }
	                    Toast_fire(result.value);
	                }
	            })
	    });
});
// وظيفة للتحقق من صحة الحقول
function validateField(selector, validateFunc, invalidClass = 'is-invalid', validClass = 'is-valid') {
	 const $field = $(selector);
	 if (validateFunc($field.val())) {
	    $field.removeClass(invalidClass).addClass(validClass);
	} else {
        $field.removeClass(validClass).addClass(invalidClass);
    }
}

$(document).ready(function () {
    //$('.order-foot').html(drop);
	///const rq = sendRequest(urlControl,params={'CONTROL':'getSelectOptionCountries'});
	$('#btnSave').hide();
	$('#btnNew, #btnDelete, #SuppliersDataTables').fadeIn()

	$(document).on("click","#btnNew",   function (){
		
		$(this).hide();
		$('#btnDelete, #SuppliersDataTables').hide();
		$('#btnCancel ,#btnSave ,#addSuppliresform').fadeIn();
		addressclient($("#coAd"),"",1,1);
	
		elPhone($("#cliTel"));
		$('.cli').val('').change().removeClass('is-valid').removeClass('is-invalid');
		$("#cli_name").focus();
	    
	    $('#addSuppliresform').fadeIn();
		$("#defultAdd").parent().hide();
		$("#defultTel").parent().hide();
		$(".req").keyup(function(){
		   if($(this).attr("id")=== "cli_name" && $(this).val().length <=2){
		       $(this).removeClass('is-valid');
		       $(this).addClass("is-invalid");
		   }else if($(this).attr("id")=== "cli_name" ){
		       $(this).removeClass('is-invalid');
	           $(this).addClass('is-valid');
		   };
		});
		$("#cli_address").blur(function(){
			$("#cli_name").focus();
		})
		$("#cli_name").ready(function() {
			$("#cli_name").focus();
		});	
		
	    

	});
	$(document).on("click","#btnCancel",function (){
		$('#btnNew, #btnDelete, #SuppliersDataTables').fadeIn();
		$("#btnSave ,#btnCancel ,#addSuppliresform ,#editSupplierstData").hide();
		$('#mailspan').remove();
		$('#whatsapp ,#telegram').prop('checked',false);
		$("#tel_type1 ,#address_type1").prop('checked',true);
		$('#clisreach').focus();
		$('.cli').each(function (){
			$(this).val("");
			$(this).removeClass('is-invalid , is-valid');
	    });
	    table.ajax.reload(); 
		
	});
	$(document).on("click","#btnSave",  function (){
		    $('.orderOv').hide();
		    
		    $('.btnSave_order').attr('disabled',false);
		    let err = 0 ,datarq ,ajaxpar={};
		    ajaxpar['CONTROL']= 'addSuppliers';
		    $('.cli').each(function(){
		        ajaxpar[$(this).attr('id')]=$(this).val();
		    });
		    if($('#tel_type1').is(':checked')){
		        ajaxpar['tel_type'] = 'هاتف جوال';
		    } else if($('#tel_type2').is(':checked')){
		        ajaxpar['tel_type'] = 'هاتف أرضى'
		    }
		    if($('#address_type1').is(':checked')){
		        ajaxpar['address_type'] = 'المنزل';
		    } else if($('#address_type2').is(':checked')){
		        ajaxpar['address_type'] = 'العمل'
		    }
		    if($('#whatsapp').is(':checked')){
		        ajaxpar['whatsapp'] = '1';
		    } else {
		        ajaxpar['whatsapp'] = '0';
		    }
		    if($('#telegram').is(':checked')){
		        ajaxpar['telegram'] = '1';
		    } else {
		        ajaxpar['telegram'] = '0';
		    }
		    if($('#defultTel').is(':checked')){
		        ajaxpar['defultTel'] = 1;
		    } else {
		        ajaxpar['defultTel'] = 0;
		    }
	        if($('#defultAdd').is(':checked')){
		        ajaxpar['defultAdd'] = 1;
		    } else {
		        ajaxpar['defultAdd'] = 0;
		    }
		    if($("#cli_email").val().length>0 && validateEmail($("#cli_email"))!==true){
		        $("#cli_email").removeClass("is-valid");
		        $("#cli_email").addClass("is-invalid");
		    } else if($("#cli_email").val().length>0 && validateEmail($("#cli_email"))!==true){
		        $("#cli_email").removeClass("is-invalid");
		        $("#cli_email").addClass("is-valid");
		    }
		    if(checkPhoneNum($("#cli_tel"))===false){
		        $("#cli_tel").removeClass("is-valid");
		        $("#cli_tel").addClass("is-invalid");
		    } else if(checkPhoneNum($("#cli_tel"))!==false){
		        $("#cli_tel").removeClass("is-invalid");
		        $("#cli_tel").addClass("is-valid");
		    }
		    if($("#cli_name").val().length <=2){
		        $("#cli_name").removeClass("is-valid");
		        $("#cli_name").addClass("is-invalid");
		    } else if($("#cli_name").val().length >=2){
		        $("#cli_name").removeClass("is-invalid");
		        $("#cli_name").addClass("is-valid");
		    }
		    if($(".addressCity").val()===""){
		        $(".addressCity").removeClass("is-valid");
		        $(".addressCity").addClass("is-invalid");
		    } else if($(".addressCity").val()!==""){
		        $(".addressCity").removeClass("is-invalid");
		        $(".addressCity").addClass("is-valid");
		    }
		    if($("#cli_address").val()===""){
		        $("#cli_address").removeClass("is-valid");
		        $("#cli_address").addClass("is-invalid");
		    } else if($("#cli_address").val()!==""){
		        $("#cli_address").removeClass("is-invalid");
		        $("#cli_address").addClass("is-valid");
		    }
		    if($(".is-invalid").length<=0){
		        datarq = sendRequest(urlControl,ajaxpar);
		    	
		    	let elm = $("#addressCity").children(":selected");
			    // done
			    if(typeof datarq =="object" && datarq[0] === "done"){
			       
			        Swal.fire({
		                title: 'تم حفظ بيانات العميل بنجاح',
		                icon: 'success',
		                showCancelButton: true,
		                cancelButtonText:  'اغلاق وعوده',
		                confirmButtonText: 'العوده لتسجيل بيانات جديده',
		                preConfirm: function () {
		                	$('#btnCancel').click();
		                	$('#btnNew').click();
		                	table.ajax.reload(); 
		                }
		            }).then((result) => {
		            	if(result.isDismissed){
		            		$('#btnCancel').click();
		            		$('#SuppliersDataTables').show();
		            		table.ajax.reload(); 
		            	}
		            });
			    } else {
			    	if(typeof datarq =="object" && datarq[0] === "msg"){
				       let msg = `<ul>`;
				       if(datarq[1]){
				    		msg+=`<li> رقم الهاتف مسجل بالفعل <br> ${datarq[1]}</li>`
				       }
				       if(datarq[2]){
				       		msg+=`<li> البريد الالكتروني مسجل بالفعل <br> ${datarq[2]}</li>`
				       }
				       msg+=`</ul>`;
				        Toast.fire({
			                    position: 'top-end',
			                    icon: 'error',
			                    title: 'خطاء',
			                    html:msg,
			                    showConfirmButton: false,
			                    timer: 2000
			            });
			    	} else {
			    		Toast.fire({
			                    position: 'top-end',
			                    icon: 'error',
			                    title: datarq[1],
			                    html:  datarq[2],
			                    showConfirmButton: false,
			                    timer: 2000
			            });
			    	}
			    }
		    }else{
		    	Toast.fire({
		                    position: '',
		                    icon: 'info',
		                    title: 'انتبه',
		                    html: 'الحقول المحدده بـ اللون الاحمر مطلوبه',
		                    showConfirmButton: false,
		                    timer: 3000
		         });
		    }
		    
		});
	$(document).on('click','#btnDelete', function (){	
        let id = [];
		var delParams = {}
        var el='';
        $('input:checkbox:checked').each(function () {
            if( $(this).is(':checked') && $(this).attr('id') !== 'select-all' ){
                id.push($(this).attr('id'));
                el += '<li class=text-left>' +$(this).parents('tr').children().eq(1).text();
                el += ' - ' + $(this).parents('tr').children().eq(2).text() + '</li>';
            };
        });
        delParams['CONTROL']     = 'delete'+Go;
        delParams[Go+'_id']      = id.join();
        Swal.fire({
            title		        : 'هل انت متأكد من حذف البيانات التالية؟',
            html: '<div id="sweldel" class=card-body><ul></ul></div><div class=swal2-validation-message id=swal2-validation-message style=font-size:14px; display: flex; margin-left: -20px; margin-right: -20px;> فى حال تأكيد الحذف لم يتم استعادة البيانات مرة اخرى</div>',
            allowOutsideClick	: false,
            showCancelButton	: true,
            confirmButtonColor	: '#3085d6',
            cancelButtonColor	: '#d33',
            confirmButtonText	: 'حفظ',
            denyButtonText	: 'إغلاق',
            focusConfirm: true,
            didOpen: ()=>{
				$('#sweldel ul').html(el);
            },
        }).then((result) => {
            if (result.isConfirmed) {
                $('td :checkbox').checked = false;
                Toast_fire(sendRequest(urlApi,delParams));
                table.ajax.reload();
            }
        });
        $('tr :checkbox').each(function() {
            this.checked = false;
            $('#btnDelete').prop( 'disabled', true );
        });
	}); 
	$(document).on("click",".editSupOrder",function(){
		let uid = $(this).attr('data-supid'),
		telid = $(this).attr('data-telid'),
		addid = $(this).attr('data-addid'),
		telstr= $(this).attr('data-telstr'),
        html=getSuppData(uid, telid, addid, telstr);
        $('#result').html('');
        $("#editSupplierstData").html("");
        $("#editSupplierstData").html(html);
        $('#btnAddNewOrder').remove();
        $('#btnNew, #btnDelete ,#SuppliersDataTables').hide();
        $("#editSupplierstData ,#btnCancel").fadeIn();
	});
//	$('#btnSave, #btnEdit, #btnCancel, #btnNew, #btnDelete, #addClientform, #addorderform').hide();
	/*=========================================================
	 عرض بيانات اوامر البيع بالجدول
	=========================================================*/
	table = $('#tbl').DataTable({
	        scrollY : '70vh',
	        scrollX : true,
	        ajax: {
	        	url:urlControl,
	        	type:'POST',
	        	data:{'CONTROL':'listSuppliers'}
	        },
	        processing: true,
	        serverSide: true,
	        columns: [
	        	{targets: 0, orderable: false, title: '<>'},
	            {targets: 1, orderData: false, title: 'اسم المورد'},
	            {targets: 2, orderData: false, title: 'البريد الالكترونى'},
	            {targets: 3, orderable: false, title: 'العنوان'},
	            {targets: 4, orderable: false, title: 'رقم الهاتف'},
	            {targets: 5, orderable: false, title: 'تحرير'}
	        ],
	        
	        createdRow: function (row, data, dataIndex) {
            	$('td', row).addClass('text-nowrap'); //
	        },
	        
	        buttons: [
		        {
		            text: 'تحديث البيانات',
		            action: function ( e, dt, node, config ) {
		                dt.ajax.reload();
		            }
		        },
		        'print',
		        'excel', 
        		'pdf', 
		    ],
	        paging: {
	            pageLength: 50,
	        },
	                    
	}).on('draw', function() {
		
	});
});
 
