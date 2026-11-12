<?php

if(!isset($addCountry_id)){$addCountry_id=-1;}
if(!isset($addGov_id)){$addGov_id = -1;}
if(!isset($addCity_id)){$addCity_id = -1;}
$address_session = TRUE;
if (!empty($_SESSION['COUNTRY_ID']) && $address_session ==true) {$addCountry_id = $_SESSION['COUNTRY_ID'];}
if (!empty($_SESSION['GOV_ID']) && $address_session ==true) {$addGov_id = $_SESSION['GOV_ID'];}
if (!empty($_SESSION['CITY_ID']) && $address_session ==true) {$addCity_id = $_SESSION['CITY_ID'];}

$address_js = "  
// --------------addressOption -----------------
    $(document).ready(function () {
        let addCountry,addGov,addCity;
		
		$('.select2').select2({
			//theme: 'classic',
			selectionCssClass:'form-control',
            width:'100%',
            dir:'rtl'
        });
		
		$('#addressGov').prop('disabled','disabled');
		$('#addressCity').prop('disabled','disabled'); 
        
        addCountry = dropdownData({datatbl:'getCountryList', selected:".$addCountry_id."});
		$('#addressCountry').html('')
        $('#addressCountry').html(addCountry)
        
        if($('#addressCountry').val() == true){
            addGov = dropdownData({'datatbl':'getGovList','selected':".$addGov_id.",'where':$('#addressCountry').val()});
            $('#addressGov').html(addGov);
            $('#addressGov').prop('disabled','');
        };
		
		if($('#addressGov').val() == true){
            addCity = dropdownData({'datatbl':'getCityList','selected':".$addCity_id.",'where':$('#addressGov').val()});
            $('#addressCity').html(addCity);
            $('#addressCity').prop('disabled','');
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
    });
";
$addressOption_HTML = "
<div class=form-group text-left>
    <label>الدولة</label>
    <select id=addressCountry class='form-control custom-select required' required></select>
</div>
<div class=form-group text-left>
    <label>المحافظة</label>
    <select id=addressGov class='form-control addressGov custom-select required' required></select>
</div>
<div class=form-group text-left>
    <label>المدينة</label>
    <select id=addressCity class='form-control addressCity custom-select required' required></select>
</div>";
?>