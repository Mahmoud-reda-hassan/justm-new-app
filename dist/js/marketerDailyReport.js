
	$(document).ready(function() {

		// ==================== GLOBAL VARIABLES ====================
		let report1Data = [];
		let report2Data = [];
		let report1Table, report2Table;
		let currentStatusName = '';
		let currentOrderStatus = '';

		// ==================== DATE RANGE PICKER ====================
		$('#dateRange').daterangepicker({
			autoUpdateInput: false,
			locale: {
				format: 'YYYY-MM-DD',
				applyLabel: 'تطبيق',
				cancelLabel: 'إلغاء',
				fromLabel: 'من',
				toLabel: 'إلى',
				customRangeLabel: 'فترة مخصصة',
				weekLabel: 'أس',
				daysOfWeek: ['ح', 'ن', 'ث', 'ر', 'خ', 'ج', 'س'],
				monthNames: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'],
				firstDay: 6
			},
			ranges: {
				'اليوم': [moment(), moment()],
				'أمس': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
				'آخر 7 أيام': [moment().subtract(6, 'days'), moment()],
				'آخر 30 يوم': [moment().subtract(29, 'days'), moment()],
				'هذا الشهر': [moment().startOf('month'), moment().endOf('month')],
				'الشهر الماضي': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
			}
		});

		$('#dateRange').on('apply.daterangepicker', function(ev, picker) {
			$(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
		});

		$('#dateRange').on('cancel.daterangepicker', function(ev, picker) {
			$(this).val('');
		});

		// Select2 will be initialized after data is loaded in populateColumnFilters

		// ==================== STATUS BADGE HELPER ====================
		function getStatusBadgeClass(statusName) {
			const status = (statusName || '').toLowerCase();
			if (status.includes('تم') && status.includes('توصيل')) return 'badge-success';
			if (status.includes('تم') && status.includes('شحن')) return 'badge-info';
			if (status.includes('قيد') || status.includes('تسوية')) return 'badge-warning';
			if (status.includes('رفض') || status.includes('إلغاء')) return 'badge-danger';
			if (status.includes('جديد') || status.includes('استلام')) return 'badge-primary';
			if (status.includes('مرتجع')) return 'badge-secondary';
			return 'badge-light';
		}

		// ==================== FORMAT NUMBER ====================
		function formatNumber(num) {
			return num ? parseFloat(num).toLocaleString('ar-EG'): '0';
		}

		// ==================== GET STATUS COLOR ====================
		function getStatusColor(statusName) {
			const status = (statusName || '').toLowerCase();
			if (status.includes('تم') && status.includes('توصيل')) return '#28a745';
			if (status.includes('تم') && status.includes('شحن')) return '#17a2b8';
			if (status.includes('قيد') || status.includes('تسوية')) return '#ffc107';
			if (status.includes('رفض') || status.includes('إلغاء')) return '#dc3545';
			if (status.includes('جديد') || status.includes('استلام')) return '#007bff';
			if (status.includes('مرتجع')) return '#6c757d';
			return '#6c757d';
		}

		// ==================== LOAD REPORT 1 ====================
		function loadReport1() {
			$('#report1Loading').show();

			const dateRange = $('#dateRange').val();
			let requestData = {
				CONTROL: 'marketerDailyReport'
			};

			// لو فيه تاريخ، ضيفه للريكوست (لو الـ backend بيدعم)
			// if (dateRange) {
			//     const dates = dateRange.split(' - ');
			//     requestData.from_date = dates[0];
			//     requestData.to_date = dates[1];
			// }

			$.ajax({
				url: '/inc/controller/reports.control.php',
				type: 'POST',
				data: requestData,
				dataType: 'json',
				success: function(response) {
					$('#report1Loading').hide();
					report1Data = response.data || response || [];
					initReport1Table();
					updateStats(report1Data);
				},
				error: function(xhr, status, error) {
					$('#report1Loading').hide();
					console.error('Error loading report 1:', error);
					// Load demo data for preview
					//loadDemoReport1();
				}
			});
		}

		// ==================== DEMO DATA (for preview) ====================
		function loadDemoReport1() {
			report1Data = [{
				ORDER_STATUSE: 'تم الاستلام',
				COUNT_ORDERS: 15,
				QTY: 320,
				DELIVERED_QTY: 300,
				RETURNED_QTY: 10,
				REJECTED_QTY: 10
			},
				{
					ORDER_STATUSE: 'جديد',
					COUNT_ORDERS: 8,
					QTY: 150,
					DELIVERED_QTY: 0,
					RETURNED_QTY: 0,
					REJECTED_QTY: 0
				},
				{
					ORDER_STATUSE: 'تم الشحن',
					COUNT_ORDERS: 22,
					QTY: 540,
					DELIVERED_QTY: 480,
					RETURNED_QTY: 30,
					REJECTED_QTY: 30
				},
				{
					ORDER_STATUSE: 'تم التوصيل',
					COUNT_ORDERS: 45,
					QTY: 890,
					DELIVERED_QTY: 870,
					RETURNED_QTY: 15,
					REJECTED_QTY: 5
				},
				{
					ORDER_STATUSE: 'قيد التسوية المالية',
					COUNT_ORDERS: 12,
					QTY: 210,
					DELIVERED_QTY: 200,
					RETURNED_QTY: 0,
					REJECTED_QTY: 10
				},
				{
					ORDER_STATUSE: 'مرفوض',
					COUNT_ORDERS: 5,
					QTY: 85,
					DELIVERED_QTY: 0,
					RETURNED_QTY: 0,
					REJECTED_QTY: 85
				},
				{
					ORDER_STATUSE: 'مرتجع',
					COUNT_ORDERS: 3,
					QTY: 45,
					DELIVERED_QTY: 0,
					RETURNED_QTY: 45,
					REJECTED_QTY: 0
				}];
			initReport1Table();
			updateStats(report1Data);
		}

		function loadDemoReport2(statusName) {
			report2Data = [];
			const count = Math.floor(Math.random() * 20) + 5;
			const statuses = ['تم الاستلام',
				'جديد',
				'تم الشحن',
				'تم التوصيل',
				'قيد التسوية المالية',
				'مرفوض',
				'مرتجع'];

			for (let i = 1; i <= count; i++) {
				const qty = Math.floor(Math.random() * 100) + 10;
				const delivered = Math.floor(Math.random() * qty);
				const returned = Math.floor(Math.random() * (qty - delivered));
				const rejected = qty - delivered - returned;

				report2Data.push({
					ORDER_ID: 10000 + i,
					ORDER_BY: 501,
					DATE_CREATED: '2024-01-' + String(Math.floor(Math.random() * 28) + 1).padStart(2, '0'),
					DELIVERY_DATE: '2024-01-' + String(Math.floor(Math.random() * 28) + 1).padStart(2, '0'),
					SHIPPING_DATE: '2024-01-' + String(Math.floor(Math.random() * 28) + 1).padStart(2, '0'),
					DELIVERED_DATE: delivered > 0 ? '2024-01-' + String(Math.floor(Math.random() * 28) + 1).padStart(2, '0'): '-',
					SUB_ORDER_ID: 'SUB-' + (2000 + i),
					EXISTS_ID: Math.floor(Math.random() * 5) + 1,
					ORDER_STATUS: Math.floor(Math.random() * 8),
					ORDER_STATUSE: statusName || statuses[Math.floor(Math.random() * statuses.length)],
					QTY: qty,
					DELIVERED_QTY: delivered,
					RETURNED_QTY: returned,
					credit: Math.random() > 0.5 ? (qty * 15).toFixed(2): '-',
					debit: rejected > 0 ? (rejected * 15).toFixed(2): '-'
				});
			}
			initReport2Table(report2Data);
			populateColumnFilters(report2Data);
		}

		// ==================== UPDATE STATS CARDS ====================
		function updateStats(data) {
			let totalOrders = 0,
			totalQty = 0,
			totalDelivered = 0,
			totalReturned = 0,
			totalRejected = 0;

			data.forEach(function(row) {
				totalOrders += parseInt(row.COUNT_ORDERS) || 0;
				totalQty += parseFloat(row.QTY) || 0;
				totalDelivered += parseFloat(row.DELIVERED_QTY) || 0;
				totalReturned += parseFloat(row.RETURNED_QTY) || 0;
				totalRejected += parseFloat(row.REJECTED_QTY) || 0;
			});

			animateValue('totalOrders', totalOrders);
			animateValue('totalQty', totalQty);
			animateValue('totalDelivered', totalDelivered);
			animateValue('totalReturned', totalReturned);
			animateValue('totalRejected', totalRejected);

			// Update footer sums
			$('#sumCount').text(formatNumber(totalOrders));
			$('#sumQty').text(formatNumber(totalQty));
			$('#sumDelivered').text(formatNumber(totalDelivered));
			$('#sumReturned').text(formatNumber(totalReturned));
			$('#sumRejected').text(formatNumber(totalRejected));
		}

		function animateValue(elementId, value) {
			const element = document.getElementById(elementId);
			const start = 0;
			const end = value;
			const duration = 800;
			const startTime = performance.now();

			function update(currentTime) {
				const elapsed = currentTime - startTime;
				const progress = Math.min(elapsed / duration, 1);
				const easeProgress = 1 - Math.pow(1 - progress, 3);
				element.textContent = formatNumber(Math.floor(start + (end - start) * easeProgress));

				if (progress < 1) {
					requestAnimationFrame(update);
				}
			}
			requestAnimationFrame(update);
		}

		// ==================== INIT REPORT 1 TABLE ====================
		function initReport1Table() {
			if (report1Table) {
				report1Table.clear().destroy();
			}

			report1Table = $('#report1Table').DataTable({
				processing: true,
		        serverSide: false,
		        //responsive: true,
		        pageLength: 25,
				ajax: {
		            url: '/inc/controller/reports.control.php',
		
		            type: 'POST',
		
		            data: {
						CONTROL: 'marketerDailyReport', 
		            },
		
		            dataSrc: ''
		
		        },

				columns: [{
						data: null,
						render: function(data, type, row, meta) {
							return meta.row + 1;
						}
					},
					{
						data: 'ORDER_STATUSE',
						render: function(data) {
							return '<span class="status-badge badge ' + getStatusBadgeClass(data) + '">' + data + '</span>';
						}
					},
					{
						data: 'COUNT_ORDERS', render: function(d) {
							return formatNumber(d);
						}
					},
					{
						data: 'QTY', render: function(d) {
							return formatNumber(d);
						}
					},
					{
						data: 'DELIVERED_QTY', render: function(d) {
							return formatNumber(d);
						}
					},
					{
						data: 'RETURNED_QTY', render: function(d) {
							return formatNumber(d);
						}
					},
					{
						data: 'REJECTED_QTY', render: function(d) {
							return formatNumber(d);
						}
					},
					{
						data: null,
						orderable: false,
						render: function(data) {
							return '<button class="btn btn-sm btn-primary view-details" data-status="' + data.ORDER_STATUSE + '">' +
							'<i class="fas fa-eye"></i> عرض التفاصيل</button>';
						}
					}],
				order: [[2, 'desc']],
				lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, 'الكل']],
				autoWidth: false,
				/*drawCallback: function() {
					// Highlight max values
					highlightMaxValues();
				}*/
			});

			// Handle row click and view details button
			$('#report1Table tbody').on('click', 'tr', function(e) {
				if ($(e.target).closest('.view-details').length) return;

				const data = report1Table.row(this).data();
				if (data) {
					showReport2(data.ORDER_STATUSE);
				}
			});

			$('#report1Table tbody').on('click',
				'.view-details',
				function(e) {
					e.stopPropagation();
					const statusName = $(this).data('status');
					showReport2(statusName);
				});
		}

		function highlightMaxValues() {
			const columns = [2, 3, 4, 5, 6]; // numeric columns
			columns.forEach(function(colIdx) {
				let maxVal = 0;
				$('#report1Table tbody tr').each(function() {
					const val = parseFloat($(this).find('td:eq(' + colIdx + ')').text().replace(/,/g, '')) || 0;
					if (val > maxVal) maxVal = val;
				});
				$('#report1Table tbody tr').each(function() {
					const cell = $(this).find('td:eq(' + colIdx + ')');
					const val = parseFloat(cell.text().replace(/,/g, '')) || 0;
					if (val === maxVal && maxVal > 0) {
						cell.addClass('font-weight-bold text-success');
					}
				});
			});
		}

		// ==================== SHOW REPORT 2 ====================
		function showReport2(statusName) {
			currentStatusName = statusName;
			$('#selectedStatusName').text(statusName).css('background', getStatusColor(statusName));

			// Hide report 1, show report 2 with animation
			$('#report1-section').fadeOut(300, function() {
				$('#report2-section').addClass('visible').hide().fadeIn(400);
				$('html, body').animate({
					scrollTop: 0
				},
					400);
			});

			loadReport2(statusName);
		}

		// ==================== LOAD REPORT 2 ====================
		function loadReport2(statusName) {
			$('#report2Loading').show();

			/*$.ajax({
				url: '/inc/controller/reports.control.php',
				type: 'POST',
				data: {
					CONTROL: 'marketerDailyDetails',
					order_statuse: statusName
				},
				dataType: 'json',
				success: function(response) {
					$('#report2Loading').hide();
					report2Data = response.data || response || [];
					initReport2Table(report2Data);
					populateColumnFilters(report2Data);
				},
				error: function(xhr,
					status,
					error) {
					$('#report2Loading').hide();
					console.error('Error loading report 2:',
						error);
					//loadDemoReport2(statusName);
				}
			});*/
		}

		// ==================== INIT REPORT 2 TABLE ====================
		function initReport2Table(data) {
			if (report2Table) {
				report2Table.clear().destroy();
			}

			report2Table = $('#report2Table').DataTable({
				processing: true,
		        serverSide: false,
		        //responsive: true,
		        pageLength: 25,
				ajax: {
		            url: '/inc/controller/reports.control.php',
		
		            type: 'POST',
		
		            data: {
						CONTROL: 'marketerDailyDetails',
						order_statuse: statusName
					},
		
		            dataSrc: ''
		
		        },

				columns: [{
						data: null,
						render: function(data, type, row, meta) {
							return meta.row + 1;
						}
					},
					{
						data: 'ORDER_ID'
					},
					{
						data: 'DATE_CREATED'
					},
					{
						data: 'DELIVERY_DATE'
					},
					{
						data: 'SHIPPING_DATE'
					},
					{
						data: 'DELIVERED_DATE'
					},
					{
						data: 'SUB_ORDER_ID'
					},
					{
						data: 'EXISTS_ID'
					},
					{
						data: 'ORDER_STATUSE',
						render: function(data) {
							return '<span class="status-badge badge ' + getStatusBadgeClass(data) + '">' + data + '</span>';
						}
					},
					{
						data: 'QTY', render: function(d) {
							return formatNumber(d);
						}
					},
					{
						data: 'DELIVERED_QTY', render: function(d) {
							return formatNumber(d);
						}
					},
					{
						data: 'RETURNED_QTY', render: function(d) {
							return formatNumber(d);
						}
					},
					{
						data: 'credit'
					},
					{
						data: 'debit'
					}],
				order: [[0, 'asc']],

				lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'الكل']],

				autoWidth: false,
				dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
				'<"row"<"col-sm-12"tr>>' +
				'<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
				initComplete: function() {
					// Apply filters
					applyColumnFilters();
				}
			});
		}

		// ==================== POPULATE COLUMN FILTERS (Select2) ====================
		function populateColumnFilters(data) {
			const columns = {
				filterOrderId: 'ORDER_ID',
				filterDateCreated: 'DATE_CREATED',
				filterDeliveryDate: 'DELIVERY_DATE',
				filterShippingDate: 'SHIPPING_DATE',
				filterDeliveredDate: 'DELIVERED_DATE',
				filterSubOrderId: 'SUB_ORDER_ID',
				filterExistsId: 'EXISTS_ID',
				filterOrderStatus: 'ORDER_STATUSE',
				filterQty: 'QTY',
				filterDeliveredQty: 'DELIVERED_QTY',
				filterReturnedQty: 'RETURNED_QTY',
				filterCredit: 'credit',
				filterDebit: 'debit'
			};

			for (const [filterId, field] of Object.entries(columns)) {
				const $select = $('#' + filterId);

				// Destroy existing Select2 if initialized
				try {
					$select.select2('destroy');
				} catch(e) {
					/* not initialized yet */
				}

				// Clear and rebuild options
				$select.empty();

				// Get unique values and sort
				const uniqueValues = [...new Set(data.map(row => row[field]))]
				.filter(v => v !== null && v !== undefined && v !== '')
				.sort();

				// Add options
				uniqueValues.forEach(function(value) {
					$select.append(new Option(value, value, false, false));
				});

				// Initialize Select2 for this specific filter
				$select.select2({
					theme: 'bootstrap4',
					dir: 'rtl',
					width: '100%',
					placeholder: $select.data('placeholder') || 'اختر...',
					language: {
						noResults: function() {
							return 'لا توجد نتائج';
						},
						searching: function() {
							return 'جاري البحث...';
						}
					},
					allowClear: true
				});
			}
		}

		// ==================== APPLY COLUMN FILTERS ====================
		function applyColumnFilters() {
			const filterMap = [{
				select: '#filterOrderId',
				column: 1
			},
				{
					select: '#filterDateCreated',
					column: 2
				},
				{
					select: '#filterDeliveryDate',
					column: 3
				},
				{
					select: '#filterShippingDate',
					column: 4
				},
				{
					select: '#filterDeliveredDate',
					column: 5
				},
				{
					select: '#filterSubOrderId',
					column: 6
				},
				{
					select: '#filterExistsId',
					column: 7
				},
				{
					select: '#filterOrderStatus',
					column: 8
				},
				{
					select: '#filterQty',
					column: 9
				},
				{
					select: '#filterDeliveredQty',
					column: 10
				},
				{
					select: '#filterReturnedQty',
					column: 11
				},
				{
					select: '#filterCredit',
					column: 12
				},
				{
					select: '#filterDebit',
					column: 13
				}];

			filterMap.forEach(function(filter) {
				$(filter.select).on('change', function() {
					const selectedValues = $(this).val() || [];

					if (selectedValues.length === 0) {
						report2Table.column(filter.column).search('').draw();
					} else {
						// Build regex for multiple values
						const escapedValues = selectedValues.map(v => v.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
						const regex = '^(' + escapedValues.join('|') + ')$';
						report2Table.column(filter.column).search(regex, true, false).draw();
					}
				});
			});
		}

		// ==================== CLEAR ALL FILTERS ====================
		$('#clearFilters').on('click', function() {
			$('.select2-filter').val(null).trigger('change');
			report2Table.search('').columns().search('').draw();
		});

		// ==================== BACK TO REPORT 1 ====================
		$('#backToReport1').on('click', function() {
			$('#report2-section').fadeOut(300,
				function() {
					$(this).removeClass('visible');
					$('#report1-section').fadeIn(400);
				});
		});

		// ==================== REFRESH BUTTONS ====================
		$('#refreshReport1').on('click', function() {
			const $btn = $(this).find('i');
			$btn.addClass('fa-spin');
			setTimeout(() => $btn.removeClass('fa-spin'),
				1000);
			loadReport1();
		});

		$('#refreshReport2').on('click', function() {
			const $btn = $(this).find('i');
			$btn.addClass('fa-spin');
			setTimeout(() => $btn.removeClass('fa-spin'),
				1000);
			if (currentStatusName) {
				loadReport2(currentStatusName);
			}
		});

		// ==================== APPLY DATE FILTER ====================
		$('#applyDateFilter').on('click',function() {
				loadReport1();
			
		});

		// ==================== INITIAL LOAD ====================
		loadReport1();

	});
