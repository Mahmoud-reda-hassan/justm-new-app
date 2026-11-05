// Back to tiles view
function backToTiles() {
	$('#metro-container').show(); // Show the metro tiles again
	$('.form-container').removeClass('active'); // Hide all forms
	$('#btnBack').hide();
	$('.btnPay').hide();
	if (!$("#crv").hasClass("container-fluid")) {
		$("#crv").addClass('container-fluid');
	}
	
}

// Show the form based on clicked tile
function showForm(formType) {
	
	
	$('#metro-container').hide();  // Hide the metro tiles
	$('#btnBack').hide();
	$('.form-container').removeClass('active');  // Hide all forms
	$('#form-' + formType).addClass('active');   // Show the selected form
	if(!$('#metro-container').is(':visible')){
		$('#btnBack').show();
	}else{
		
	}
	
	if($('#form-mandob').is(':visible')) {
		//if ($("#crv").hasClass("container-fluid")) {
		    $(".crv").removeClass('container-fluid');
		    $('.btnPay').show();
		    $("#btnCancelAllPayment").click();
		    $('#SelectUserDelivery').val(null).trigger('change');
		//} 
	}
}

$(document).ready(function() {
  $('.btnPay').hide();
  $('.btn-app-pade').append('<button id="btnBack" class="btn btn-app-tools btnh text-dark btnBack" style="display:none" onclick="backToTiles();" style="display:none;" ><i class="fas fa-arrow-left"></i><span class="btnname">الرجوع</span><span class="badge badge-danger navbar-badge onumBtnay mr-2" ></span></button>');
  
  // إخفاء جميع الفورم
  function hideAllForms() {
    $('#form-mandob, #form-masouq, #form-morad, #form-omer-sell, #form-open').hide();
  }

  // التعامل مع اختيار الأزرار
  $('.metro-option').on('click', function() {
    let option = $(this).data('option');
    
    hideAllForms();  // إخفاء جميع الفورم

    if (option == 1) {
      $('#form-mandob').show();
      loadMandobData();  // تحميل بيانات المندوبين
    } else if (option == 2) {
      $('#form-masouq').show();
      loadMasouqData();  // تحميل بيانات المسوقين
    } else if (option == 3) {
      $('#form-morad').show();
      loadMoradData();  // تحميل بيانات الموردين
    } else if (option == 4) {
      $('#form-omer-sell').show();
      loadClientData();  // تحميل بيانات العملاء
    } else if (option == 5) {
      $('#form-open').show();
    }
  });

  // تحميل بيانات المندوبين
  function loadMandobData() {
    // استعلام بيانات المندوبين من قاعدة البيانات أو API
    // هنا نقوم بتحميل البيانات من الخادم باستخدام jQuery AJAX
    $.ajax({
      url: 'get_mandob_data.php',  // نقطة النهاية للحصول على بيانات المندوبين
      method: 'GET',
      success: function(data) {
        let mandobData = JSON.parse(data);
        let mandobSelect = $('#select-mandob');
        mandobSelect.empty();  // إفراغ القائمة الحالية
        mandobSelect.append('<option value="">اختر المندوب</option>');
        mandobData.forEach(function(mandob) {
          mandobSelect.append('<option value="' + mandob.id + '">' + mandob.name + '</option>');
        });

        mandobSelect.on('change', function() {
          let selectedMandobId = $(this).val();
          if (selectedMandobId) {
            // استعلام للحصول على إجمالي حساب المندوب
            $.ajax({
              url: 'get_mandob_balance.php',
              method: 'GET',
              data: { mandob_id: selectedMandobId },
              success: function(balanceData) {
                let totalBalance = JSON.parse(balanceData).total_balance;
                $('#total-mandob').text(totalBalance);
                $('#amount-mandob').attr('max', totalBalance);  // ضبط الحد الأقصى للمبلغ
              }
            });
          }
        });
      }
    });
  }

  // تحميل بيانات المسوقين
  function loadMasouqData() {
    // استعلام بيانات المسوقين من قاعدة البيانات أو API
    $.ajax({
      url: 'get_masouq_data.php',
      method: 'GET',
      success: function(data) {
        let masouqData = JSON.parse(data);
        let masouqSelect = $('#select-masouq');
        masouqSelect.empty();
        masouqSelect.append('<option value="">اختر المسوق</option>');
        masouqData.forEach(function(masouq) {
          masouqSelect.append('<option value="' + masouq.id + '">' + masouq.name + '</option>');
        });

        masouqSelect.on('change', function() {
          let selectedMasouqId = $(this).val();
          if (selectedMasouqId) {
            // استعلام للحصول على إجمالي حساب المسوق
            $.ajax({
              url: 'get_masouq_balance.php',
              method: 'GET',
              data: { masouq_id: selectedMasouqId },
              success: function(balanceData) {
                let totalBalance = JSON.parse(balanceData).total_balance;
                $('#total-masouq').text(totalBalance);
                $('#amount-masouq').attr('max', totalBalance);  // ضبط الحد الأقصى للمبلغ
              }
            });
          }
        });
      }
    });
  }

  // تحميل بيانات الموردين
  function loadMoradData() {
    // استعلام بيانات الموردين من قاعدة البيانات أو API
    $.ajax({
      url: 'get_morad_data.php',
      method: 'GET',
      success: function(data) {
        let moradData = JSON.parse(data);
        let moradSelect = $('#select-morad');
        moradSelect.empty();
        moradSelect.append('<option value="">اختر المورد</option>');
        moradData.forEach(function(morad) {
          moradSelect.append('<option value="' + morad.id + '">' + morad.name + '</option>');
        });

        moradSelect.on('change', function() {
          let selectedMoradId = $(this).val();
          if (selectedMoradId) {
            // استعلام للحصول على إجمالي المبلغ الذي يجب سداده للمورد
            $.ajax({
              url: 'get_morad_balance.php',
              method: 'GET',
              data: { morad_id: selectedMoradId },
              success: function(balanceData) {
                let totalBalance = JSON.parse(balanceData).total_balance;
                $('#total-morad').text(totalBalance);
                $('#amount-morad').attr('max', totalBalance);  // ضبط الحد الأقصى للمبلغ
              }
            });
          }
        });
      }
    });
  }

  // تحميل بيانات العملاء
  function loadClientData() {
    // استعلام بيانات العملاء من قاعدة البيانات أو API
    $.ajax({
      url: 'get_client_data.php',
      method: 'GET',
      success: function(data) {
        let clientData = JSON.parse(data);
        let clientSelect = $('#select-client');
        clientSelect.empty();
        clientSelect.append('<option value="">اختر العميل</option>');
        clientData.forEach(function(client) {
          clientSelect.append('<option value="' + client.id + '">' + client.name + '</option>');
        });

        clientSelect.on('change', function() {
          let selectedClientId = $(this).val();
          if (selectedClientId) {
            // استعلام للحصول على أوامر البيع الخاصة بالعميل
            $.ajax({
              url: 'get_orders_for_client.php',
              method: 'GET',
              data: { client_id: selectedClientId },
              success: function(orderData) {
                let orderDataParsed = JSON.parse(orderData);
                let orderSelect = $('#select-order');
                orderSelect.empty();
                orderSelect.append('<option value="">اختر أمر البيع</option>');
                orderDataParsed.forEach(function(order) {
                  orderSelect.append('<option value="' + order.id + '">' + order.order_number + '</option>');
                });

                orderSelect.on('change', function() {
                  let selectedOrderId = $(this).val();
                  if (selectedOrderId) {
                    // استعلام للحصول على المبلغ المرتجع لهذا الأمر
                    $.ajax({
                      url: 'get_return_amount.php',
                      method: 'GET',
                      data: { order_id: selectedOrderId },
                      success: function(returnData) {
                        let returnAmount = JSON.parse(returnData).return_amount;
                        $('#return-amount').text(returnAmount);
                      }
                    });
                  }
                });
              }
            });
          }
        });
      }
    });
  }

  // تخصيص الفورم للصرف المفتوح
  $('#amount-open').on('input', function() {
    let amount = parseFloat($(this).val());
    if (amount <= 0) {
      Swal.fire('الرجاء إدخال مبلغ صالح');
    }
  });

  // إضافة التحقق من صحة المدخلات عند الحفظ
  $('#save-button').on('click', function() {
    let amount = parseFloat($('#amount-mandob').val() || 0);
    if (amount <= 0) {
      Swal.fire('يرجى إدخال مبلغ صحيح');
      return false;
    }
  });

  // Event listeners for metro tiles
  $('#btn-mandob').click(function () {
        showForm('mandob');
   });

  $('#btn-masouq').click(function () {
    	showForm('masouq');
   });

   $('#btn-morad').click(function () {
    	showForm('morad');
   });

   $('#btn-client-return').click(function () {
		showForm('client-return');
   });

   $('#btn-open').click(function () {
		showForm('open');
   });

});