<?php
	include $_SERVER["DOCUMENT_ROOT"].'/inc/Forms/addressOptionForm.php';
   // array_push($perm->js_page,"Countries.js");
	$geturl = $_GET['Go'];
    $swl_js="$('.select2').select2({
			    selectionCssClass:'form-control',
                width:'100%',
                dir:'rtl'
             });";
	$Columns = "[{targets:0,title:'<input id=select-all type=checkbox>',orderable: false},
				{targets:1,className:' fild f1',title:'اسم الفرع'},
				{targets:2,className:' fild f2',title:'اسم المؤسسة'},
				{targets:3,className:' fild f3',title:'العنوان'},
				{targets:4,title: 'تحرير',orderable: false }]";
    $furl=$perm->furl();
    $perm->privetjs .= "
//-----------------BRANCHES PAGE--------------
$(document).ready(function () {
     Columns = ".$Columns."
	 tblGET('".$furl."inc/Api.php?CONTROL=getlist".$geturl."');
/*==========================================
    Add New Items In All Table
==========================================*/
//============= new and update =======================================================
    $(document).on('click','#btnNew', function (){
        let x =''
	    let mode;
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
        
       
       if (Go === 'Branches') {
            SwalTitle += 'فرع';
            obj = {'el':'input','id':'BR_NAME','req':'param required','lbl':'الاسم','place':'من فضلك ادخل الاسم','val': data.attr('data-name')};
            x   = htmlel(obj);
			obj = {'el':'select','id':'COMPANIE_ID','req':'param required','lbl':'تابع لـ','val': data.attr('COMPANIE_ID'),params:'Enterpris'};
            x  += htmlel(obj);
            obj = {'el':'input','type':'checkbox','req':'param','id':'BR_MAIN','lbl':'الفرع الرئيسى'};
            x   += htmlel(obj);
            x   += addressOption();
			obj = {'el':'textarea','type':'text','id':'BR_ADDRESS','req':'param required','lbl':'العنوان','place':'من فضلك ادخل العنوان'};
			x 	+= htmlel(obj);
        }
        
        Swal.fire({
            title		        : SwalTitle,
            html		        : x ,
            //type		        : '',
            allowOutsideClick	: false,
            showCancelButton	: true,
            confirmButtonColor	: '#3085d6',
            cancelButtonColor	: '#d33',
            confirmButtonText	: 'حفظ',
            cancelButtonText	: 'إغلاق',
            focusConfirm		: true,
            didOpen:()=>{
				$('#BR_ADDRESS').html(data.attr('data-br_address'));
                if (data.attr('data-br_main') == '1'){
                    $('#BR_MAIN').attr('checked', true);
                }
				$('#addressCity').addClass('param');
				if($('#addressCity').val() == ''){
					addressdata({country:1,gov:-1,city:-1});
				} else {
					addressdata({country:data.attr('data-co_id'),gov:data.attr('data-gov_id'),city:data.attr('data-city_id')});
				}
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
                        msg += $(this).val() +' ';
                    }
                });
				$('.form-control').each(function() {
					if ($(this).attr('id') == 'BR_MAIN') {
						if(this.checked){
							ajaxpar[$(this).attr('id')] = 1;
						} else {
							ajaxpar[$(this).attr('id')] = 0 ;
						}
					} else {
						ajaxpar[$(this).attr('id')] ='' + $(this).val();
					}
                });
                if (x>0){
                    msgerr += 'جميع الحقول مطلوبة برجاء ملئ جميع الحقول';
                } else {
                    objreq =  sendRequest(urlControl,ajaxpar);
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
            $('td :checkbox').checked = false;
            if(result.isConfirmed){
                Toast_fire(result.value);
                table.ajax.reload();
            };
        });
	});//end btn new and update
//================ delete ============================================================
    $(document).on('click','#btnDelete', function (){	

		let data_return;
        let id = [];
        var el='';
        $('input:checkbox:checked').each(function () {
            if( $(this).is(':checked') && $(this).attr('id') !== 'select-all' ){
                id.push($(this).attr('id'));
                el += '<li class=text-left>' +$(this).parents('tr').children().eq(1).text();
                el += ' - ' + $(this).parents('tr').children().eq(2).text() + '</li>';
            };
        });
        var delParams = {}
        delParams['CONTROL']     = 'delete'+Go;
        delParams[Go+'_id']      = id.join();
        Swal.fire({
            title: 'هل انت واثق من حذف البيانات التالية؟',
            html: '<div class=card-body><ul>' + el + '</ul></div><div class=swal2-validation-message id=swal2-validation-message style=font-size:14px; display: flex; margin-left: -20px; margin-right: -20px;> فى حال تأكيد الحذف لم يتم استعادة البيانات مرة اخرى</div>',
            icon: 'warning',
            allowOutsideClick: false,
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'تأكيد الحذف',
            cancelButtonText: 'إغلاق',
            footer: ''

        }).then((result) => {
                if (result.isConfirmed){
                   let data_return = sendRequest(urlControl,delParams);
                   Toast_fire(data_return);
                   table.ajax.reload();
                }
            })
        $('tr :checkbox').each(function() {
                this.checked = false;
                $('#btnDelete').prop( 'disabled', true );
        });
	}); //end btn delete
});"
    
?>
<div class="card">
    <div class="card-body">
        <div>
            <table id="tbl" class="table table-striped text-center text-nowrap display" style="width:100%">
            </table>
        </div>
    </div>
<!-- /.card-body -->
</div>