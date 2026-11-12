	var dateRange = '1900-01-01 - 1900-01-01';
	var did = 0;
	var reportType = 'DelegatesReport';
	function back(){
		if($('#Daily-table').is(':visible')){
			showElment('f1');
			$('#btnBack').hide();
		}else if($('#Details-table').is(':visible')){
			showElment('f2');
		}
	}
	function showElment(elid = ''){
		    
			$('.hid').css('display', 'none');
			let el = $('.'+elid);
			if(elid == ''){
				$('.f1').show();
			}else{
				$('#filter-form').show();
				if(elid=='f2'){
					$('#hti').html('يومية عهدة المندوبين');
				}else if(elid =='f3'){
					$('#hti').html('تفصيلى عهدة المندوبين');
				}else{
					$('#hti').html('اجمالى عهدة المندوبين');
				}
				
			}
			
			el.show();
	}
	function getColor(action) {
	  if (action.includes("استلام")) return "#4caf50";      // أخضر
	  if (action.includes("استرداد") || action.includes("مرتجع")) return "#f44336"; // أحمر
	  if (action.includes("تسليم")) return "#2196f3";        // أزرق
	  return "#9e9e9e"; // رمادي افتراضي
	}
	function getBootstrapColorClass(action) {
		if(action){
		  if (action.includes("بدء الشحن")) return "bg-primary";
		  if (action.includes("تجميد")) return "bg-dark";
		  if (action.includes("استرداد")) return "bg-info";
		  if (action.includes("تم التسليم")) return "bg-success";
		  if (action.includes("تسليم جزئى")) return "bg-warning";
		  if (action.includes("مرفوض")) return "bg-danger";
		  if (action.includes("تسويه مخزنيه")) return "bg-info";
		  if (action.includes("تسويه ماليه")) return "bg-success";
		  if (action.includes("مرتجع بعد البيع")) return "bg-danger";
		  
		}
		return "bg-dark";
		
	}
	
	$(document).ready(function () {
		$(document).on("click", "#Olhbtn .OLF" ,function () {
			
			const val = $(this).attr('id');
			reportType = val ? val : 'DelegatesReport';
			
			if(reportType == 'DelegatesReport'){
				showElment('f1');
			}else{
				showElment('f4');
			}
		});


		$('.btn-app-pade').append('<button id="btnBack"         class="btn btn-app-tools btnh text-dark btnBack" style="display:none" onclick="back();" ><i class="fas fa-arrow-left"></i><span class="btnname">الرجوع</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>');
	
  		$('[data-toggle="tooltip"]').tooltip();

		const urlvr = `../inc/controller/reports.control.php`;
		$(async function() {
		    $('#dateRange').daterangepicker({
		      	autoUpdateInput: true,
			    locale: {
			        format: 'YYYY-MM-DD',
					separator: ' - ',
					applyLabel: "تأكيد",
					cancelLabel: "إلغاء",
					fromLabel: "من",
					toLabel: "إلى",
					customRangeLabel: "تاريخ مخصص",
					weekLabel: "أسبوع",
					daysOfWeek: ["أحد", "إثنين", "ثلاثاء", "أربعاء", "خميس", "جمعة", "سبت"],
					monthNames: ["يناير", "فبراير", "مارس", "أبريل", "مايو", "يونيو", "يوليو", "أغسطس", "سبتمبر", "أكتوبر", "نوفمبر", "ديسمبر"],
					firstDay: 6
				},
				minDate: moment().subtract(2, 'years'),
				maxDate: moment().add(1, 'years'),
				startDate: moment().startOf('month'),
				endDate: moment().endOf('month'),
				opens: "left",
				drops: "down",
				showDropdowns: true,
				showWeekNumbers: true,
				timePicker: false,
				ranges: {
					"اليوم": [moment(), moment()],
					"أمس": [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
					"آخر 7 أيام": [moment().subtract(6, 'days'), moment()],
					"آخر 30 يوم": [moment().subtract(29, 'days'), moment()],
					"هذا الشهر": [moment().startOf('month'), moment().endOf('month')],
					"الشهر الماضي": [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
				},
		    });
		
		    $('#dateRange').on('apply.daterangepicker', function(ev, picker) {
		      $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
		    });
		
		    $('#dateRange').on('cancel.daterangepicker', function(ev, picker) {
		      $(this).val('');
		    });
		});
		
		var tablea = $('#total-table').DataTable({
		    processing: true,
		    serverSide: true,
		    searching: false,
		    ajax: {
		      url: urlvr,
		      type: 'POST',
		      data: function (d) {
		        return $.extend({}, d, {
		          CONTROL: 'DelegatesReportTotal',
		          daterange: dateRange,//$('input[name=from_date]').val(),
			      did: did, //data.DELIVERY_ID,
		        });
		      }
		    },
		    columns: [
		     {	
	                data: '', 
	                title: '',
	                className: 'text-center',
	                render: (data, type, row) => {
	                    return `
	                    <button id="btnOview" type="button" class="dt-controlerA btn btn-app-tools btn-xs text-primary border btnOview  w-100 h-100 f-5">
		    				<i class="fas fa-eye"></i>
		    			</button>`;
	                }
	          },
		      { data: 'DELIVERY_NAME',className:"text-nowrap text-center"},
		      { data: 'TOTAL_ORDERS',className:"text-nowrap text-center" },
		      //{ data: 'COUT_ORDERS_SHIPPING',className:"text-nowrap text-center" },
			  { data: 'TOTAL_QTY',className:"text-nowrap text-center" },
			  { data: 'WAITING_QTY',className:"text-nowrap text-center" },
			  { data: 'REFOUND_PARTIAL_QTY',className:"text-nowrap text-center" },
			  { data: 'DELIVERED_QTY',className:"text-nowrap text-center" },
		      { data: 'REJECTED_QTY',className:"text-nowrap text-center"},
		      { data: 'REJECTED_PERSENT',className:"text-nowrap"},
		      //{ data: 'running_balance',className:"text-nowrap text-center"},
		    ],
		    columnDefs: [
	            {
	                targets: [0],
	                className: 'mdc-data-table__cell text-nowarp text-center ',
	            },
	        ],
		    pageLength: -1,
	        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "الكل"]],
		  });
		var tableb = $('#Daily-table').DataTable({
			    processing: true,
			    serverSide: true,
			    searching: false,
			    ajax: {
			      url: urlvr,
			      type: 'POST',
			      data: function (d) {
			        return $.extend({}, d, {
			          CONTROL: 'DelegatesReportDaily',
			          daterange: dateRange,//$('input[name=from_date]').val(),
			          did: did, //data.DELIVERY_ID,
			        });
			      }
			    },
			    columns: [
				 {	
		                data: '', 
		                title: '',
		                className: 'text-center',
		                render: (data, type, row) => {
		                    return `
		                    <button id="btnOview" type="button" class="dt-controlerB btn btn-app-tools btn-xs text-primary border btnOview  w-100 h-100 f-5">
			    				<i class="fas fa-eye"></i>
			    			</button>`;
		                }
		          },
			      { data: 'date',className:"text-nowrap text-center"},
			      { data: 'DELIVERY_NAME',className:"text-nowrap text-center"},
			      { data: 'TOTAL_ORDERS',className:"text-nowrap text-center" },
			      { data: 'TOTAL_QTY',className:"text-nowrap text-center" },
				  { data: 'WAITING_QTY',className:"text-nowrap text-center" },
				  { data: 'REFOUND_PARTIAL_QTY',className:"text-nowrap text-center" },
				  { data: 'DELIVERED_QTY',className:"text-nowrap text-center" },
				  { data: 'REJECTED_QTY',className:"text-nowrap text-center" },
			      { data: 'REJECTED_PERSENT',className:"text-nowrap text-center"},
			    ],
			    columnDefs: [
		            {
		                targets: ['_all'],
		                className: 'mdc-data-table__cell text-nowarp text-center dt-controler',
		            },
		        ],
			    pageLength: -1,
		        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "الكل"]],
			  });
		var tablec = $('#Details-table').DataTable({
			    processing: true,
			    serverSide: true,
			    searching: false,
			    ajax: {
			      url: urlvr,
			      type: 'POST',
			      data: function (d) {
			        return $.extend({}, d, {
			          CONTROL: 'DelegatesReportDetails',
			          daterange: dateRange,//$('input[name=from_date]').val(),
			          did: did, //data.DELIVERY_ID,
			        });
			      }
			    },
			    columns: [
			      	{ data: 'date',className:"text-nowrap text-center"},
			      	{ data: 'DELIVERY_NAME',className:"text-nowrap text-center"},
			      	{ data: 'SUB_ORDER_ID',className:"text-nowrap text-center" },
			      	{ data: 'TOTAL_QTY',className:"text-nowrap text-center" },
				  	{ data: 'WAITING_QTY',className:"text-nowrap text-center" },
				  	{ data: 'REFOUND_PARTIAL_QTY',className:"text-nowrap text-center" },
				  	{ data: 'DELIVERED_QTY',className:"text-nowrap text-center" },
				  	{ data: 'REJECTED_QTY',className:"text-nowrap text-center" },
			     // { data: 'movement_history',className:"text-nowrap text-center"},
					/*{
					  data: 'movement_history',
					  title: "حركة الشحن",
					  className: "text-nowrap text-center",
					  render: function (data, type, row) {
					    let movements = [];
					    try {
					      movements = JSON.parse(data);
					    } catch (e) {
					      return '<div class="text-danger">تنسيق غير صالح</div>';
					    }
					
					    const items = movements.map(entry => {
					      let [action, date, time] = entry.split(" | ");
						  time = time?.slice(0, 5); // ياخد بس أول 5 خانات: HH:MM
					      const colorClass = getBootstrapColorClass(action);
					      return `
					        <div class="timeline-step" data-toggle="tooltip" title="${action} - ${date} ${time}">
					          <div class="timeline-time">${date} | ${time}</div>
					          <div class="timeline-dot ${colorClass}"></div>
					          <div class="timeline-label">${action}</div>
					        </div>
					      `;
					    }).join('');
					
					    return `<div class="timeline-container">${items}</div>`;
					  }
					}*/
					{
					  data: 'movement_history',
					  title: "حركة الشحن",
					  className: "text-nowrap text-center",
					  render: function (data) {
					    let movements = [];
					    try {
					      movements = JSON.parse(data);
					    } catch (e) {
					      return '<div class="text-danger">تنسيق غير صالح</div>';
					    }
					
					    const items = movements.map(entry => {
							if(entry && typeof entry === "string"){
								let [action, date, time] = entry.split(" | ");
								time = time?.slice(0, 5); // HH:MM
								const colorClass = getBootstrapColorClass(action);
								const textColorClass = colorClass.replace(/^bg/, "text"); 
							
								// يستبدل "ABC" في بداية النص بـ "XYZ"
								// النتيجة: "XYZ123"
								return `
									<div class="timeline-step">
									  <div class="timeline-dot ${colorClass}"></div>
									  <div class="timeline-content">
										<div class="timeline-time">${date} - ${time}</div>
										<div class="timeline-label ${textColorClass}">${action}</div>
									  </div>
									</div>
								 `;
							}
					    }).join('');
					
					    return `<div class="timeline-container">${items}</div>`;
					  }
					}
			    ],
			    pageLength: -1,
		        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "الكل"]],
			  });
		var tabled = $('#shipping-report').DataTable({
			    processing: true,
			    serverSide: true,
			    searching: false,
			    ajax: {
			      url: urlvr,
			      type: 'POST',
			      data: function (d) {
			        return $.extend({}, d, {
			          CONTROL: 'ShippingReport',
			          daterange: dateRange,//$('input[name=from_date]').val(),
			          did: did, //data.DELIVERY_ID,
			        });
			      }
			    },
			    columns: [
			    	{
					    data: null,
					    title: "#",
					    orderable: false,
					    searchable: false,
					    render: function(data, type, row, meta) {
					        return meta.settings._iDisplayStart + meta.row + 1;
					    }
					},
			      	{ data: 'CustName',className:"text-nowrap text-center"},
			      	{ data: 'company',className:"text-nowrap text-center"},
			      	{ data: 'coust',className:"text-nowrap text-center" },
			      	{ data: 'Parcode',className:"text-nowrap text-center" },
				  	{ data: 'Phone',className:"text-nowrap text-center" },
				  	{ data: 'City',className:"text-nowrap text-center" },
				  	{ data: 'Region',className:"text-nowrap text-center" },
				  	{ data: 'address',className:"text-nowrap text-center" },
			        { data: 'createdDate',className:"text-nowrap text-center" },
				  	{ data: 'notes',className:"text-nowrap text-center" },
				  	{ data: 'Replacing',className:"text-nowrap text-center" },
					{ data: 'OrderContent',className:"text-nowrap text-center" },
				  	{ data: 'QTY',className:"text-nowrap text-center" },
				  	{ data: 'PRICE',className:"text-nowrap text-center" },
					{ data: 'SHIPPING_VAL',className:"text-nowrap text-center" }
			    ],
			    pageLength: -1,
		        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "الكل"]],
		});

		tablea.on('click', '.dt-controlerA', function(e) {
			let tr = e.target.closest('tr');
			let row = tablea.row(tr);
			let data= row.data();
			showElment('f2');
			$('#btnBack').show();
			tableb.clear();
			dateRange = $('input[name=from_date]').val();
			did		  = data.delivery_id;
			tableb.ajax.reload();
			
		});
		
		tableb.on('click', '.dt-controlerB', function(e) {
			let tr = e.target.closest('tr');
			let row = tableb.row(tr);
			let data= row.data();
			showElment('f3');
			$('#dateRange').data('daterangepicker').setStartDate(data.date);
			$('#dateRange').data('daterangepicker').setEndDate(data.date);
			dateRange = $('input[name=from_date]').val();
			did		  = data.delivery_id;
			tablec.ajax.reload();
			
		});
		
		/*$('#filter-form').on('submit', function (e) {
		    e.preventDefault();
		    dateRange = $('input[name=from_date]').val();
		    if($('#Daily-table').is(':visible')){
		    	tableb.clear();
		    	tableb.ajax.reload();
		    }else if($('#Details-table').is(':visible')){
		    	tablec.clear();
		    	tablec.ajax.reload();
		    }
		});*/
		
		$(document).on('submit', '#filter-form', function (e) {
			e.preventDefault();

			dateRange = $('#dateRange').val();

			if ($('#Daily-table').is(':visible')) {
				tableb.ajax.reload(null, true);
			}else if ($('#Details-table').is(':visible')) {
				tablec.ajax.reload(null, true);
			}else if ($('#shipping-report').is(':visible')) {
				
				tabled.ajax.reload(null, true);
			} else {
				tablea.ajax.reload(null, true);
			}
		});
		
		// حقل اختيار المورد Select2
	    $('#partnerselect').select2({
	        placeholder: `اختر ${$('#partnerLebal').html()}`,
	        allowClear: true,
	        language: 'ar', // دعم اللغة
	        selectionCssClass: 'form-control',
		    width: '100%',
		    dir: 'rtl',
	        ajax: {
	            url: `${urlApi}?CONTROL=getDeliveryList`, // المسار إلى ملف PHP
	            method: 'GET',
	            dataType: 'json',
	            delay: 250, // تأخير الطلب لتقليل التحميل
	            
	            data: function(params) {
	                return {
	                    query: params.term // مصطلح البحث
	                };
	            },
	            processResults: function(data) {
	                // معالجة البيانات وإرجاع النتائج إلى Select2
	                return {
	                    results: data.map(function(supplier) {
	                        return {
	                            id: supplier.id, // القيمة
	                            text: supplier.name // النص المعروض
	                        };
	                    })
	                };
	            },
	            cache: true
	        }
	    });
		
		$('#partnerselect').on('change', function () {
			const val = $(this).val();
			did = val ? val : 0;
		});
		
			  
	});
