const customersData = [
    {
        id: 1,
        name: "أحمد محمد",
        phone: "01000000000",
        date: "2026-02-15T14:00",
        status: "pending"
    },
    {
        id: 2,
        name: "محمد علي",
        phone: "01111111111",
        date: "2026-02-16T16:30",
        status: "pending"
    }
];

function renderStatus(status) {

    switch (status) {
        case "confirmed":
            return '<span class="badge bg-success">تم التأكيد</span>';
        case "reschedule":
            return '<span class="badge bg-warning text-dark">إعادة جدولة</span>';
        case "no_answer":
            return '<span class="badge bg-secondary">لا يرد</span>';
        case "cancelled":
            return '<span class="badge bg-danger">ملغي</span>';
        default:
            return '<span class="badge bg-info text-dark">قيد الانتظار</span>';
    }
}

var table = $('#customers-table').DataTable({
    data: customersData,
    searching: true,
    paging: true,
    ordering: false,
    columns: [
        { title: "اسم العميل", data: "name" },
        { title: "رقم الهاتف", data: "phone" },
        { 
            title: "ميعاد التوصيل",
            data: "date",
            render: function (data) {
                return new Date(data).toLocaleString();
            }
        },
        {
            title: "الحالة",
            data: "status",
            render: function (data) {
                return renderStatus(data);
            }
        },
        {
            title: "إجراءات",
            orderable: false,
            render: function () {
                return `
                    <button class="btn btn-success btn-sm btnCall">
                        <i class="fas fa-phone"></i>
                    </button>
                    <button class="btn btn-primary btn-sm btnConfirm">
                        <i class="fas fa-check"></i>
                    </button>
                `;
            }
        }
    ]
});


/* ===========================
   زر الاتصال
=========================== */

$('#customers-table').on('click', '.btnCall', function () {

    let row = table.row($(this).closest('tr')).data();

    Swal.fire({
        title: 'جاري الاتصال',
        html: `<strong>${row.phone}</strong>`,
        icon: 'info',
        confirmButtonText: 'تم الاتصال'
    });

    window.location.href = "tel:" + row.phone;
});


/* ===========================
   زر التأكيد
=========================== */

$('#customers-table').on('click', '.btnConfirm', function () {

    let rowIndex = table.row($(this).closest('tr')).index();
    let rowData = table.row(rowIndex).data();

    Swal.fire({
        title: 'نتيجة المكالمة',
        input: 'select',
        inputOptions: {
            confirmed: 'تم التأكيد',
            reschedule: 'إعادة جدولة',
            no_answer: 'لا يرد',
            cancelled: 'إلغاء'
        },
        inputPlaceholder: 'اختر الحالة',
        showCancelButton: true,
        confirmButtonText: 'التالي'
    }).then((result) => {

        if (!result.isConfirmed) return;

        let selectedStatus = result.value;

        if (selectedStatus === 'reschedule') {

            Swal.fire({
                title: 'اختر ميعاد جديد',
                input: 'datetime-local',
                showCancelButton: true
            }).then(dateResult => {

                if (!dateResult.isConfirmed) return;

                rowData.date = dateResult.value;
                rowData.status = selectedStatus;

                table.row(rowIndex).data(rowData).draw(false);

                Swal.fire('تم الحفظ', '', 'success');
            });

        } else {

            rowData.status = selectedStatus;

            table.row(rowIndex).data(rowData).draw(false);

            Swal.fire('تم التحديث', '', 'success');
        }
    });
});