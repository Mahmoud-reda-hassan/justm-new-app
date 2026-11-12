<?php
$address_session = false;
$addCountry_id=-1;
$addGov_id = 1;
$addCity_id = 1;
include $_SERVER["DOCUMENT_ROOT"]."/inc/Forms/ElementHtml.php";
$perm->privetjs .= "

// --------------addressOption -----------------
function addressdata(){
        let addCountry,addGov,addCity;
		let data = sendRequestGET(urlApi+'?CONTROL=getEnterprise');
			$('.enpr').each(function() {
			$('#' + $(this).attr('id')).val(data[0][$(this).attr('id')]);
		});
		$('.select2').select2({
			//theme: 'classic',
			selectionCssClass:'form-control',
            width:'100%',
            dir:'rtl'
        });
		$('#addressGov').prop('disabled','disabled');
		$('#addressCity').prop('disabled','disabled'); 
        
        addCountry = dropdownData({datatbl:'getCountryList', selected:data[0][$(this).attr('CO_ID')]});
		$('#addressCountry').html('')
        $('#addressCountry').html(addCountry)
        
        if($('#addressCountry').val() == true){
            addGov = dropdownData({'datatbl':'getGovList','selected':data[0][$(this).attr('GOV_ID')],'where':$('#addressCountry').val()});
            $('#addressGov').html(addGov);
            //$('#addressGov').prop('disabled','');
        };
		if($('#addressGov').val() == true){
            addCity = dropdownData({'datatbl':'getCityList','selected':data[0][$(this).attr('CITY_ID')],'where':$('#addressGov').val()});
            $('#addressCity').html(addCity);
            //$('#addressCity').prop('disabled','');
        };
        $('#addressCountry').change( function () {
            if ($('#addressCountry').val() > 0) {
                $('#addressGov').prop('disabled','');
                addGov = dropdownData({'datatbl':'getGovList','selected':1,'where':$('#addressCountry').val()});
                $('#addressGov').html(' ');
                $('#addressGov').html(addGov);
                addGov = '';
                if ($('#addressGov').val() > 0){
                    $('#addressGov').prop('disabled','');
                    $('#addressCity').prop('disabled','disabled');
                } else {
                    $('#addressGov').prop('disabled','disabled');
                }
            };
            if ($('#addressGov').val() > 0 ) {
                addCity     = dropdownData({'datatbl':'getCityList','selected':-1,'where':$(this).val()});
                $('#addressCity').html(' ');
                $('#addressCity').html(addCity);
                addCity = '';
                $('#addressCity').prop('disabled','');
            } else {
                $('#addressCity').prop('disabled','disabled');
            };
        });
        
        $('#addressGov').change( function () {
            if ($(this).val() !== undefined || $(this).val() !== '') {
                addCity     = dropdownData({'datatbl':'getCityList','selected':-1,'where':$(this).val()});
                $('#addressCity').html(' ');
                $('#addressCity').html(addCity);
                City = ''
                $('#addressCity').prop('disabled','');
                if ($(this).val() !==''){
                    $('#addressCity').prop('disabled','');
                } else {
                    $('#addressCity').prop('disabled','disabled');
                }
            };
        });
        $('.select2').css('z-index','1');
		$.fn.modal.Constructor.prototype.enforceFocus = function() {
			$('#addressCity').attr('class','border border-primary');
		};
};
//------------------Enterprise--------------------
	addressdata();
	$('.enpr').prop('disabled',true );
    $('#addressCountry').prop('disabled','true');
    $('#addressGov').prop('disabled','true');
    $('#addressCity').prop('disabled','true');
	$(document).on('keyup','.required', function(){
		let x = $(this).val();
		if(x !== ''){
			$(this).removeClass('is-invalid');
		}
	});
	
	$(document).on('click','#btnCancel', function (){
		$('.required').each(function() {
			$(this).removeClass('is-invalid');
		});
		addressdata();
		$('#addressCountry').prop('disabled',true);
		$('#addressGov').prop('disabled',true);
		$('#addressCity').prop('disabled',true);
	    $('.enpr').prop('disabled',true);
		$(this).prop('disabled',true);
		$('#btnEdit').prop('disabled','');
		$('#btnSave').prop('disabled',true);
	});
	
	$(document).on('click','#btnEdit', function (){
	    $('#addressCountry').prop('disabled','');
		$('#addressGov').prop('disabled','');
		$('#addressCity').prop('disabled','');
	    $('.enpr').prop('disabled','');
		$('#btnCancel').prop('disabled','');
		$('#btnSave').prop('disabled','');
		$(this).prop('disabled',true);
	});
	
	$(document).on('click','#btnSave', function (){
		let x=0;
		$('.required').each(function() {
                    if(!$(this).val()){
                        x++;
                        $(this).addClass('is-invalid');
                    } else {
						$(this).removeClass('is-invalid');
						
                    }
					
                });
				if(x < 1){
							$('#addressCountry').prop('disabled',true);
							$('#addressGov').prop('disabled',true);
							$('#addressCity').prop('disabled',true);
							$('.enpr').prop('disabled',true);
							$('#btnCancel').prop('disabled',true);
							$('#btnEdit').prop('disabled','');
							$(this).prop('disabled',true);
							let opj={};
							opj['CONTROL'] = 'updateEnterprise';
							$('.enpr').each(function() {
								opj[$(this).attr('id')] = '' + $(this).val();
							});
							opj['CITY_ID'] = $('#addressCity').val();
							let req = sendRequest(urlApi,opj);
							Toast_fire(req);
							sessionStorage.setItem('COMPANY_NAME', $('#COM_NAME').val());
							
							
						//	alert (sessionStorage.getItem('COMPANY_NAME'));
						
				    
				        } else {
							Toast.fire({
                            position: 'top',
                            icon: 'warning',
                            title: '',
                            html : '<spen> لم يتم الحفظ : جميع الحقول المحددة بالون الاحمر مطلوبة<spen>',
                            customClass: {
                                popup:'border border-danger',
                            },
                            showConfirmButton: false,
                            timer: 7500
                        });
						}
	});
	$(document). ready (function (){
		$('.btn-app-pade #btnCancel').show();
		$('.btn-app-pade #btnEdit').show();
		$('.btn-app-pade #btnSave').show();
	});
"
?>
<div class="card">
    <div class="card-body">
        
        <div class=''>
           <?php echo $elementHtml['Enterprise']['HTML'];?> 
        </div>
    </div>
<!-- /.card-body -->
</div>