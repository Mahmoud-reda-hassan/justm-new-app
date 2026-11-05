$(document).ready(function () {
/*==========================================
    Add New Items In All Table
==========================================*/
//============= new and update =======================================================
    $(document).on('click','#btnNew', function (){
        let x =''
	    let mode;
	    let wh =true;
        let SwalTitle;
        var data =$(this);
        let obj={};
        var msg ='';
        let objreq;
        let ajaxpar ={};
        if(data.attr('data-id')  !== 'new') {
        	
            SwalTitle = 'تحديث بيانات ';
            ajaxpar['CONTROL']='update'+Go;
            ajaxpar['data_id'] = data.attr('data-id');
            msg = 'تم تغير ' +data.attr('data-namear') + ' ' +  data.attr('data-nameen') + ' إلى ';
        } else {
        	
            SwalTitle = 'اضافة ';
            ajaxpar['CONTROL'] = 'add'+Go;
            msg = 'تم اضافة ';
        }
        if ( Go === 'Countries') {
            SwalTitle += 'دولة';
            obj = {'el':'input','id':'CO_NAME','lbl':'الاسم','place':'من فضلك ادخل الاسم','val': data.attr('data-namear')};
            x   = htmlel(obj);
            obj = {'el':'input','id':'CO_NAME_EN','lbl':'Name','place':'Please enter name','val': data.attr('data-nameen')};
            x += htmlel(obj);
        } else if (Go === 'Gov') {
            SwalTitle += 'محافظة / منطقة';
            obj = {'el':'input','id':'GOV_NAME','lbl':'فضلك ادخل الاسم','val':data.attr('data-namear')};
            x   = htmlel(obj);
            obj = {'el':'input','id':'GOV_NAME_EN','lbl':'Name','place':'Please enter name','val':data.attr('data-nameen')};
            x  += htmlel(obj);
            obj = {'el':'select','id':'COUNTRY_ID','req':'required selet2','lbl':'تابع لـ','place':'','val':data.attr('data-country')};
            x += htmlel(obj);
        } else if (Go === 'City') {
            SwalTitle += 'مدينة';
            obj = {'el':'input','id':'CI_NAME','lbl':'الاسم','place':'من فضلك ادخل الاسم','val': data.attr('data-namear')};
            x   = htmlel(obj);
            obj = {'el':'input','id':'CI_NAME_EN','lbl':'Name','place':'Please enter name','val':data.attr('data-nameen')};
            x  += htmlel(obj);
            obj = {'el':'select','id':'GOV_ID','req':'required select2','lbl':'تابع لـ','val': data.attr('data-gov')};
            x  += htmlel(obj);
        } else if (Go === 'LocalShipping') {
        	if(data.attr('data-id')  == 'new') {
        		wh= false;
        	}
            SwalTitle += 'خط سير للشحن المحلى';
            obj = {'el':'input','id':'ZONE_NAME','lbl':'الاسم','place':'من فضلك ادخل الاسم','val': data.attr('data-namear')};
            x   = htmlel(obj);
            obj = {'el':'input','id':'ZONE_NAME_EN','lbl':'Name','place':'Please enter name','val':data.attr('data-nameen')};
            x   += htmlel(obj);
            obj = {'el':'input','id':'SHIPPING_LOWEST','lbl':'اقل سعر للشحن','type':'Number','place':'من فضلك ادخل اقل قيمه للشحن','val':data.attr('data-lowest')};
            x   += htmlel(obj);
            obj = {'el':'input','id':'SHIPPING_RATE','lbl':'اعلى سعر للشحن','type':'Number','place':'من فضلك ادخل اعلى قيمه للشحن','val':data.attr('data-rate')};
            x   += htmlel(obj);
            obj = {'el':'input','id':'SHIPPING_TIME_MAX','lbl':'الوقت الازم لتسليم الشحنه للعميل(باليوم)','type':'Number','place':'0','val':data.attr('data-time')};
            x   += htmlel(obj);
            obj = {'params':'Warehouse','wh':'2','el':'select','id':'STO_ID','req':'required select2','lbl':'تابع للمخزن','val': data.attr('data-stoid')};
            x   += htmlel(obj);
			obj = {'params':'ShippingType','wh':'','el':'select','id':'SHIPPING_T_ID','req':'required','lbl':',نوع الشحن','val': data.attr('data-shiptype')};
            x   += htmlel(obj);
            obj = {'el':'select','id':'CI_ZONE_ID','req':'required select20','multiple':'multiple','lbl':'المناطق','val': data.attr('data-id'),'wh':data.attr('data-id')};
            x   += htmlel(obj);
        } 
        
        Swal.fire({
            title		        : SwalTitle,
            html		        : x,
            allowOutsideClick	: false,
            showCancelButton	: true,
            confirmButtonColor	: '#3085d6',
            cancelButtonColor	: '#d33',
            confirmButtonText	: 'حفظ',
            cancelButtonText	: 'إغلاق',
            focusConfirm: true,
            didOpen: function(){
                $('.select21').select2({
                    width:'100%',
                    dir:'rtl'
                 });
            }, 
            preConfirm: function () {
                let msgerr = '';
                let x=0;
                $('.required').each(function() {
                    if(!$(this).val()){
                        x++;
                        $(this).addClass('is-invalid');
                    } else {
                        $(this).removeClass('is-invalid');
                        ajaxpar[$(this).attr('id')] ='' + $(this).val();
                        msg += $(this).val() +' ';
                    }
                });
                if (Go === 'LocalShipping' &&  parseInt($('#SHIPPING_LOWEST').val()) !='' && parseInt($('#SHIPPING_LOWEST').val())>parseInt($('#SHIPPING_RATE').val())){
                    $('#SHIPPING_LOWEST').addClass('border-danger');
                    msgerr +='اقل سعر للشحن لايمكن ان يكون اكبر من اعلى سعر';
                }
                if (x>0){
                    msgerr += 'جميع الحقول مطلوبة برجاء ملئ جميع الحقول';
                } else {
                    objreq =  sendRequest(urlApi,ajaxpar);
                    if(objreq[2] == 23000){
                        $('.required').addClass('border-danger');
                        msgerr += 'البيانات التى تم  ادخلها مسجله مسبقا بقواعد البيانات';
                    }
                }
                
                if(msgerr!== ''){
                    Swal.showValidationMessage(msgerr);
                }
				return objreq;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $('td :checkbox').checked = false;
                Toast_fire(result.value)
                table.ajax.reload();
            }
        });
	});//end btn new and update
//================ delete ============================================================
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
});