const columnDef = [],
	column =[];
let groupColumn1 = 0; // العمود الأول BR_NAME
let groupColumn2 = 1; // العمود الثاني STO_NAME
let drawCallback = ()=>{};
if (Go == 'stockShortageReport'){
	columnDef.push({ visible: false, targets: [groupColumn1, groupColumn2] });
	column.push(
            { title: "اسم الفرع", data: "BR_NAME" }, // العمود الأول
            { title: "اسم المخزن", data: "STO_NAME" }, // العمود الثاني
            { title: "كود المنتج", data: "PRO_CODE",className:"text-nowrap col-6" }, // العمود الرابع
            { title: "اسم المنتج", data: "PRO_NAME",className:"text-nowrap col-6 text-center" }, // العمود الثالث
            { title: "خيارات المنتج", data: "POPTION",className:"text-nowrap col-6" }, // العمود الخامس
            { title: "الحد الأدنى للمخزون", data: "CODE_MIN_STOCK",className:"text-nowrap" }, // العمود السادس
            { title: "كمية المخزون", data: "STO_QTY",className:"text-nowrap" } // العمود السابع
        );
    drawCallback = function (settings) {
        var api = this.api();
            var rows = api.rows({ page: 'current' }).nodes();
            var lastGroup1 = null;
            var lastGroup2 = null;
			var lastGroup3 = null;
            api.column(groupColumn1, { page: 'current' }).data().each(function (group1, i) {
                var group2 = api.column(groupColumn2, { page: 'current' }).data()[i];
				
                if (lastGroup1 !== group1) {
                    $(rows).eq(i).before(
                        `<tr class="group bg-dark group-level-1">
                            <td class="text-left" colspan="${api.columns().nodes().length}"><strong>الفرع : ${group1}</strong></td>
                        </tr>`
                    );
                    lastGroup1 = group1;
                    lastGroup2 = null; // Reset lastGroup2
                }

                if (lastGroup2 !== group2) {
                    $(rows).eq(i).before(
                        `<tr class="group bg-secondary group-level-2">
                            <td class="text-left pl-5" colspan="${api.columns().nodes().length}">المخزن : ${group2}</td>
                        </tr>`
                    );
                    lastGroup2 = group2;
                }

            });
        }
}else if(Go == 'SalesMovementReport'){
	columnDef.push({});
	column.push(
	{ title: "رقم الامر", data:"SUB_ORDER_ID",className:"text-nowrap text-center "},
	{ title: "تاريخ الانشاء", data:"DATE_CREATED",className:"text-nowrap text-center "},
	{ title: "المنشئ", data:"ORDERBY",className:"text-nowrap text-center "},
	{ title: "اجمالى قطع الامر", data:"TOTAL_ORDER_QTY",className:"text-nowrap text-center"},
	{ title: "اسم العميل", data:"CLIENT_NAME",className:"text-nowrap text-center "},
	{ title: "هاتف العميل", data:"TELEPHONE",className:"text-nowrap text-center "},
	{ title: "المحافظه", data:"GOV_NAME",className:"text-nowrap text-center "},
	{ title: "عنوان العميل", data:"ADDRESS",className:"text-nowrap text-center "},
	{ title: "وصف الصنف", data:"OPT_JSON",className:"text-nowrap text-center "},
	{ title: "وصف الصنف٢", data:"OPT_JSON",className:"text-nowrap text-center "},
	{ title: "الكميه المطلوبه", data:"QTY",className:"text-nowrap text-center "},
	{ title: "سعر الواحده", data:"PRICE",className:"text-nowrap text-center "},
	{ title: "اسم الصنف", data:"PRO_NAME",className:"text-nowrap text-center "},
	{ title: "اجمالى", data:"TOTAL",className:"text-nowrap text-center "},
	{ title: "حالة المنتج", data:"PRO_STATUSE",className:"text-nowrap text-center"},
	{ title: "تاريخ الخروج", data:"SHIPPING_DATE",className:"text-nowrap text-center"},
	{ title: "المندوب", data:"DELIVERYBY",className:"text-nowrap text-center "},
	
	{ title: "ملاحظات امر البيع", data:"COMMENT",className:"text-nowrap text-center "},
	{ title: "الحالة الادارية", data:"EXISTS_NAME",className:"text-nowrap text-center "},
	
	{ title: " التسليم", data:"DELIVERED_QTY",className:"text-nowrap text-center "},
	{ title: "راجع", data:"RETURN_QTY",className:"text-nowrap text-center "},
	{ title: "حالة الامر", data:"STA_NAME",className:"text-nowrap text-center "},
	{ title: "تاريخ التسليم", data:"DELIVERED_DATE",className:"text-nowrap text-center"},
	{ title: "تاريخ التسليم", data:"DELIVERED_DATE",className:"text-nowrap text-center"},
	{ title: "كود الصنف", data:"PRO_CODE",className:"text-nowrap text-center "},
	
	{ title: "تاريخ الشحن", data:"DELIVERY_DATE",className:"text-nowrap text-center "},
	{ title: "قيمة الشحن", data:"SHIPPING_VAL",className:"text-nowrap text-center "},
	{ title: "الحاله منتهى", data:"ORDER_SUCCESS",className:"text-nowrap text-center "},
	{ title: "نوع امر البيع", data:"ORDER_TYPE_NAME",className:"text-nowrap text-center "},
	{ title: "بريد العميل", data:"CLIENT_EMAIL",className:"text-nowrap text-center "},
	{ title: "المدينه", data:"CI_NAME",className:"text-nowrap text-center "},
	{ title: "الخصم", data:"DISCOUNT",className:"text-nowrap text-center "},
			
	);
	drawCallback = function (settings) {};
}
const urlvr = `../../inc/controller/reports.control.php`;
$(document).ready(function () {
   
    if (Go == 'SalesMovementReport') {
    const today = moment().startOf('day');
    $('#date_range').daterangepicker({
        startDate: today.clone().subtract(29, 'days'),
        endDate: today.clone(),
        autoUpdateInput: true,
        locale: {
            format: 'YYYY-MM-DD',
            separator: ' - ',
            applyLabel: 'تأكيد',
            cancelLabel: 'إلغاء',
            fromLabel: 'من',
            toLabel: 'إلى',
            customRangeLabel: 'تاريخ مخصص',
            weekLabel: 'أسبوع',
            daysOfWeek: ['أحد', 'إثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'],
            monthNames: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'],
            firstDay: 6
        },
        opens: 'left',
        drops: 'down',
        showDropdowns: true,
        timePicker: false,
        ranges: {
            'اليوم': [today.clone(), today.clone()],
            'أمس': [today.clone().subtract(1, 'days'), today.clone().subtract(1, 'days')],
            'آخر 7 أيام': [today.clone().subtract(6, 'days'), today.clone()],
            'آخر 30 يوم': [today.clone().subtract(29, 'days'), today.clone()],
            'هذا الشهر': [today.clone().startOf('month'), today.clone().endOf('month')],
            'الشهر الماضي': [today.clone().subtract(1, 'month').startOf('month'), today.clone().subtract(1, 'month').endOf('month')]
        }
    });

    $('#date_range').on('apply.daterangepicker', function (ev, picker) {
        $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
    });
    }

    var table = $('#tbl').DataTable({
        scrollY : '70vh',
	    scrollX : true,
        processing: true, // عرض مؤشر التحميل
        serverSide: true, // تفعيل المعالجة على الخادم
        ajax: {
            url: urlvr, // رابط الخادم
            type: 'POST', // نوع الطلب
            data: function (d) {
                d.CONTROL = Go;
                if (Go == 'SalesMovementReport') {
                    const picker = $('#date_range').data('daterangepicker');
                    d.start_date = picker.startDate.format('YYYY-MM-DD');
                    d.end_date = picker.endDate.format('YYYY-MM-DD');
                    d.date_field = $('#date_field').val() || 'DATE_CREATED';
                }
            }
        },
        columnDefs: columnDef,
        columns: column,
        order: [[groupColumn1, 'asc'], [groupColumn2, 'asc']],
		pageLength: Go == 'SalesMovementReport' ? 50 : 40000,
		
		buttons: [
	        'copy', 
	        'print',
	        Go == 'SalesMovementReport' ? {
	            text: 'تصدير Excel (كل النتائج)',
	            action: function (e, dt, node, config) {
	                const picker = $('#date_range').data('daterangepicker');
	                const exportParams = {
	                    CONTROL: 'SalesMovementReportExport',
	                    start_date: picker.startDate.format('YYYY-MM-DD'),
	                    end_date: picker.endDate.format('YYYY-MM-DD'),
	                    date_field: $('#date_field').val() || 'DATE_CREATED',
	                    search: { value: dt.search() }
	                };

	                $.ajax({
	                    url: urlvr,
	                    type: 'POST',
	                    data: exportParams,
	                    dataType: 'json',
	                    success: function (response) {
	                        const rows = response && response.data ? response.data : [];
	                        const JSZip = $.fn.dataTable.Buttons.jszip();
	                        if (!JSZip) {
	                            alert('مكتبة إنشاء ملفات Excel غير متاحة. يرجى التواصل مع الدعم.');
	                            return;
	                        }

	                        const escapeXml = function (value) {
	                            return String(value == null ? '' : value)
	                                .replace(/&/g, '&amp;')
	                                .replace(/</g, '&lt;')
	                                .replace(/>/g, '&gt;')
	                                .replace(/"/g, '&quot;')
	                                .replace(/'/g, '&apos;');
	                        };
	                        const columnName = function (index) {
	                            let name = '';
	                            while (index >= 0) {
	                                name = String.fromCharCode((index % 26) + 65) + name;
	                                index = Math.floor(index / 26) - 1;
	                            }
	                            return name;
	                        };
	                        const reportColumns = column;
	                        const xmlRows = [];
	                        const makeCell = function (reference, value) {
	                            return '<c r="' + reference + '" t="inlineStr"><is><t xml:space="preserve">' +
	                                escapeXml(value) + '</t></is></c>';
	                        };

	                        xmlRows.push('<row r="1">' + reportColumns.map(function (col, index) {
	                            return makeCell(columnName(index) + '1', col.title);
	                        }).join('') + '</row>');

	                        rows.forEach(function (row, rowIndex) {
	                            const excelRow = rowIndex + 2;
	                            xmlRows.push('<row r="' + excelRow + '">' + reportColumns.map(function (col, colIndex) {
	                                return makeCell(columnName(colIndex) + excelRow, row[col.data]);
	                            }).join('') + '</row>');
	                        });

	                        const lastColumn = columnName(Math.max(0, reportColumns.length - 1));
	                        const lastRow = rows.length + 1;
	                        const zip = new JSZip();

	                        zip.file('[Content_Types].xml',
	                            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
	                            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' +
	                            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' +
	                            '<Default Extension="xml" ContentType="application/xml"/>' +
	                            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' +
	                            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' +
	                            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' +
	                            '</Types>'
	                        );
	                        zip.folder('_rels').file('.rels',
	                            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
	                            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
	                            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' +
	                            '</Relationships>'
	                        );
	                        zip.folder('xl').file('workbook.xml',
	                            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
	                            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' +
	                            '<sheets><sheet name="تقرير حركة المبيعات" sheetId="1" r:id="rId1"/></sheets></workbook>'
	                        );
	                        zip.folder('xl').folder('_rels').file('workbook.xml.rels',
	                            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
	                            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
	                            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' +
	                            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' +
	                            '</Relationships>'
	                        );
	                        zip.folder('xl').file('styles.xml',
	                            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
	                            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' +
	                            '<fonts count="1"><font><sz val="11"/><name val="Arial"/></font></fonts>' +
	                            '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>' +
	                            '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' +
	                            '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' +
	                            '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>' +
	                            '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>'
	                        );
	                        zip.folder('xl').folder('worksheets').file('sheet1.xml',
	                            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
	                            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' +
	                            '<dimension ref="A1:' + lastColumn + lastRow + '"/>' +
	                            '<sheetViews><sheetView workbookViewId="0" rightToLeft="1"/></sheetViews>' +
	                            '<sheetFormatPr defaultRowHeight="15"/>' +
	                            '<sheetData>' + xmlRows.join('') + '</sheetData>' +
	                            '</worksheet>'
	                        );

	                        zip.generateAsync({
	                            type: 'blob',
	                            mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
	                        }).then(function (blob) {
	                            const link = document.createElement('a');
	                            const url = URL.createObjectURL(blob);
	                            link.href = url;
	                            link.download = 'SalesMovementReport_' + exportParams.start_date + '_' + exportParams.end_date + '.xlsx';
	                            document.body.appendChild(link);
	                            link.click();
	                            document.body.removeChild(link);
	                            setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
	                        }).catch(function () {
	                            alert('تعذر إنشاء ملف Excel. حاول مرة أخرى.');
	                        });
	                    },
	                    error: function () {
	                        alert('تعذر تصدير التقرير. حاول مرة أخرى.');
	                    }
	                });
	            }
	        } : 'excel', 
	        {
	            text: 'تحديث التقرير',
	            action: function (e, dt, node, config) {
	                dt.ajax.reload(); // إعادة تحميل البيانات
	            }
	        },
	    ],
    	drawCallback : drawCallback
    });

    if (Go == 'SalesMovementReport') {
        $('#btnApplyDateRange').on('click', function () {
            table.ajax.reload();
        });
    }
});
        