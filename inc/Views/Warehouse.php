<?php
$geturl = $_GET['Go'];

$swl_js="$('.select2').select2({
			selectionCssClass:'form-control',
			width:'100%',
            dir:'rtl'
        });";
$Columns = "[{targets:0,title:'<input id=select-all type=checkbox>',orderable: false},
			{targets:1,className:'fild f1',title:'اسم المخزن'},
			{targets:2,className:'fild f2',title:'تابع لـ'},
			{targets:3,className:'fild f3',title:'النوع'},
			{targets:4,title: 'تحرير',orderable: false }]";
	$furl=$perm->furl();
	$perm->privetjs .= "

//-----------------Warehouse PAGE--------------
$(document).ready(function () {
     Columns = ".$Columns."
	 tblGET('".$furl."inc/Api.php?CONTROL=getlist".$geturl."');
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
        if(data.attr('data-id')  > 0) {
            SwalTitle = 'تحديث بيانات ';
            ajaxpar['CONTROL'] ='update'+Go;
            ajaxpar['data_id'] = data.attr('data-id');
            msg = 'تم تغير ' +data.attr('data-namear') + ' ' +  data.attr('data-nameen') + ' إلى ';
        } else {
            SwalTitle = 'اضافة ';
            ajaxpar['CONTROL'] = 'add'+Go;
            msg = 'تم اضافة ';
        }
        
       if (Go === 'Warehouse') {
            SwalTitle += 'مخزن';
            obj = {'el':'input','id':'STO_NAME','req':'param required','val':data.attr('data-sto_name') ,'lbl':'الاسم','place':'من فضلك ادخل الاسم'};
            x   = htmlel(obj);
			obj = {'el':'select','id':'COM_ID','req':'param required','val':data.attr('data-com_id'),'lbl':'تابع لمؤسسةـ',params:'Enterpris'};
            x  += htmlel(obj);
            obj = {'el':'select','id':'BRANCH_ID','req':'param required','val':data.attr('data-branch_id'),'lbl':'فرع',params:'Branches' };
            x  += htmlel(obj);
            obj = {'el':'select','id':'STTY_ID','req':'param required','val':data.attr('data-stty_id'),'lbl':'نوع المخزن',params:'Stty'};
            x  += htmlel(obj);
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
            didOpen: function(){
			//	$('#STO_NAME').val($(this).attr('data-sto_name'));
				$('#BRANCH_ID').val(data.attr('data-branch_id'));
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
				$('.param').each(function() {
						ajaxpar[$(this).attr('id')] ='' + $(this).val();
                });
                if (x>0){
                    msgerr += 'جميع الحقول المحدده بالون الاحمر مطلوبة برجاء ملئ جميع الحقول';
                } else {
                    objreq =  sendRequest(urlControl,ajaxpar);
                    
                        if(objreq[2] == 23000){
                            $('.required').addClass('border-danger');
                            msgerr += 'البيانات التى تم  ادخلها مسجله مسبقا بقواعد البيانات';
                        }else if(objreq[2] == 10001){
                            msgerr += objreq[1];
                        }
                }
                if(msgerr !== ''){
                    Swal.showValidationMessage(msgerr);
                }
			  
			    return objreq;
			    
            }
        }).then((result) => {
            $('td :checkbox').checked = false;
            if(result.isConfirmed){
                Toast_fire(result.value);
				table.ajax.reload();
            }
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
                    table.ajax.reload();
                    Toast_fire(data_return);
                }
        })
        $('tr :checkbox').each(function() {
                this.checked = false;
                $('#btnDelete').prop( 'disabled', true );
        });
	}); //end btn delete
});";

?>
<div class="card">
    <div class="card-body">
        <div>
            <table id="tbl" class="cell-border table table-striped table-bordered text-center text-nowrap display" style="width:100%">
            </table>
        </div>
    </div>
<!-- /.card-body -->
</div>