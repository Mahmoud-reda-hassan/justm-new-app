
let dt_serverside   = window.APP_CONFIG.datatablesServerSide; //المنسقين و موظفين الشحن
let dt_serverside0  = window.APP_CONFIG.datatablesServerSideEx0; //تتبع طلبات العملاء
let dt_serverside1  = window.APP_CONFIG.datatablesServerSideEx1; //قيد المراجعه
let dt_serverside2  = window.APP_CONFIG.datatablesServerSideEx2; //مؤجل
let dt_serverside3  = window.APP_CONFIG.datatablesServerSideEx3; //غير متوفر
let dt_serverside4  = window.APP_CONFIG.datatablesServerSideEx4; //لا يستجيب
let dt_serverside5  = window.APP_CONFIG.datatablesServerSideEx5; //الغاء امر البيع
let dt_serverside7  = window.APP_CONFIG.datatablesServerSideEx7; //قيدالتجهيز
let dt_serverside8  = window.APP_CONFIG.datatablesServerSideEx8; //تم التجهيز
let dt_serverside9  = window.APP_CONFIG.datatablesServerSideEx9; //جارى الشحن
let dt_serverside10 = window.APP_CONFIG.datatablesServerSideEx10; //عهدة المندوبين
let pageLength      = window.APP_CONFIG.datatablesRowCount;

let offset = 0; // متغير لتحديد بداية البيانات
let totalRecords = 0; // إجمالي عدد السجلات
let batchSize = 500; // عدد الصفوف لكل دفعة
let isLoading = false; // لمنع تشغيل أكثر من عملية تحميل في نفس الوقت
let totalRequests = 0;
let sort = 'desc';
let store_available = 0;
const retryCount = 3;
//const urlvo = `../../inc/controller/viewOrders.control.php`;
//const urlship = `../../inc/controller/shipment.control.php`;

/*DatatablesFromObject*/
/**
 * -----------------------------------------------------------------------------
 * 📌 Function: updateDataTable(newData)
 * -----------------------------------------------------------------------------
 * 🎯 الغرض:
 * تحديث محتوى جدول DataTable بالبيانات الجديدة بطريقة ذكية:
 *   - إضافة السجلات الجديدة أو المعدلة
 *   - حذف السجلات التي لم تعد موجودة
 *   - الحفاظ على ترتيب الجدول وصفحات العرض الحالية
 *
 * -----------------------------------------------------------------------------
 * 🧠 الدور داخل النظام:
 * تُستخدم هذه الدالة بعد تحميل دفعات البيانات أو التحديث الجزئي
 * لتحديث واجهة المستخدم بدون إعادة تحميل الجدول بالكامل.
 *
 * -----------------------------------------------------------------------------
 * ⚙️ آلية التنفيذ:
 *
 * 1️⃣ الحصول على نسخة DataTable:
 *      let table = $("#tbl").DataTable();
 *
 * 2️⃣ ترتيب البيانات الجديدة تصاعديًا حسب oid:
 *      newData.sort((a, b) => Number(a.oid) - Number(b.oid));
 *
 * 3️⃣ إنشاء خريطة للبيانات الجديدة لتسريع البحث:
 *      Map من oid إلى row
 *
 * 4️⃣ إزالة الصفوف التي لم تعد موجودة في newData:
 *      - المرور على existingData
 *      - حذف الصف إذا لم يكن موجودًا في newDataMap
 *
 * 5️⃣ تحديد الصفوف الجديدة أو المعدلة فقط:
 *      - مقارنة كل صف جديد مع النسخة الحالية في الجدول
 *      - إضافة الصفوف المختلفة أو الغير موجودة فقط
 *
 * 6️⃣ إضافة البيانات الجديدة للمجدول:
 *      table.rows.add(filteredData).draw(false);
 *
 * 7️⃣ إعادة ضبط الصفحة الحالية:
 *      table.page(page).draw(false);
 *
 * 8️⃣ ترتيب الجدول حسب العمود الأول تنازليًا:
 *      table.order([0, 'desc']).draw();
 *
 * -----------------------------------------------------------------------------
 * 🚀 المميزات:
 *
 * ✔ تحديث جزئي للجدول بدل إعادة تحميل كامل
 * ✔ الحفاظ على Pagination الحالي
 * ✔ إضافة وتحديث وحذف ديناميكي للصفوف
 * ✔ تحسين الأداء عند التعامل مع بيانات كبيرة
 *
 * -----------------------------------------------------------------------------
 * ⚠️ ملاحظات تقنية:
 *
 * - يعتمد على وجود خاصية oid في كل صف لضمان التطابق الصحيح.
 * - يتم مقارنة الصفوف باستخدام JSON.stringify للتحقق من التغييرات،
 *   قد يكون هذا مكلفًا عند وجود بيانات ضخمة جدًا.
 * - يُفترض أن الجدول قد تم تهيئته مسبقًا باستخدام DataTable.
 * - يمكن تحسين الأداء أكثر باستخدام row().data() بدلاً من إعادة رسم الجدول بالكامل.
 *
 * -----------------------------------------------------------------------------
 * @param {Array<Object>} newData
 * مصفوفة البيانات الجديدة التي سيتم مزامنتها مع الجدول.
 *
 * @returns {void}
 * تقوم بتحديث الجدول مباشرة دون إرجاع قيمة.
 * -----------------------------------------------------------------------------
 */
function updateDataTable(newData) {
    let table = $("#tbl").DataTable();
	newData.sort((a, b) => Number(a.oid) - Number(b.oid));
    let existingData = table.rows().data().toArray();
    let newDataMap = new Map(newData.map(row => [row.oid, row]));
    let page = table.page(); // حفظ رقم الصفحة الحالية
    existingData.forEach((row, index) => {
        if (!newDataMap.has(row.oid)) {
            table.row(index).remove();
        }
    });

    let filteredData = newData.filter(newRow => {
        let existingRow = existingData.find(row => row.oid === newRow.oid);
        return !existingRow || JSON.stringify(existingRow) !== JSON.stringify(newRow);
    });

    if (filteredData.length > 0) {
        table.rows.add(filteredData).draw(false);
        table.page(page).draw(false); // العودة لنفس الصفحة بعد التحديث
    }
    table.order([0, 'desc']).draw();
    //table.columns.adjust();
}

/**
 * -----------------------------------------------------------------------------
 * 📌 Function: updateProgressBar(progress)
 * -----------------------------------------------------------------------------
 * 🎯 الغرض:
 * تحديث شريط التقدم (Progress Bar) أثناء تحميل البيانات وعرض
 * نسبة الاكتمال بشكل مرئي للمستخدم لتحسين تجربة الاستخدام.
 *
 * -----------------------------------------------------------------------------
 * 🧠 الدور داخل النظام:
 * تُستدعى هذه الدالة أثناء التحميل التدريجي للبيانات (Batch Loading)
 * لتعكس نسبة السجلات التي تم تحميلها مقارنةً بإجمالي السجلات.
 *
 * -----------------------------------------------------------------------------
 * ⚙️ آلية التنفيذ:
 *
 * 1️⃣ إظهار حاوية شريط التقدم:
 *      $('.progress-container').show();
 *
 * 2️⃣ الحصول على عنصر شريط التقدم:
 *      document.getElementById("progressBar");
 *
 * 3️⃣ تحديث العرض البصري:
 *      - تعديل عرض الشريط (width) بنسبة progress%
 *      - عرض النص الداخلي بنفس النسبة
 *
 * 4️⃣ عند اكتمال التحميل (progress >= 100):
 *      ✔ إظهار الجدول الرئيسي
 *      ✔ تغيير لون الشريط إلى تدرج نهائي (نجاح)
 *      ✔ إخفاء شريط التقدم بعد مهلة قصيرة
 *
 * -----------------------------------------------------------------------------
 * 🎨 التأثيرات البصرية:
 *
 * - يتم استخدام CSS inline لتحديث العرض ديناميكيًا.
 * - يتم تطبيق تدرج لوني عند اكتمال التحميل.
 * - يتم استخدام fadeOut لإخفاء الحاوية بسلاسة.
 *
 * -----------------------------------------------------------------------------
 * 🚀 المميزات:
 *
 * ✔ تحسين تجربة المستخدم أثناء تحميل البيانات الكبيرة
 * ✔ إظهار حالة النظام بشكل واضح
 * ✔ انتقال بصري سلس عند اكتمال التحميل
 *
 * -----------------------------------------------------------------------------
 * ⚠️ ملاحظات تقنية:
 *
 * - يجب التأكد من وجود:
 *      .progress-container
 *      #progressBar
 *      #tbl
 *
 * - لا يوجد تحقق من أن progress بين 0 و 100
 *   ويُفترض أن يتم تمرير قيمة صحيحة.
 *
 * - يمكن تحسين الأداء بتجنب استدعاء show() في كل تحديث
 *   إذا كانت الحاوية ظاهرة بالفعل.
 *
 * -----------------------------------------------------------------------------
 * @param {number} progress
 * نسبة التقدم (من 0 إلى 100).
 *
 * @returns {void}
 * تقوم بتحديث واجهة المستخدم فقط دون إرجاع قيمة.
 * -----------------------------------------------------------------------------
 */
function updateProgressBar(progress) {
    $('.progress-container').show();
    let progressBar = document.getElementById("progressBar");
    progressBar.style.width = progress + "%";
    progressBar.innerText = progress + "%";
    if (progress >= 100) {
        $('#tbl').show();
        progressBar.style.background = "linear-gradient(90deg, #2196F3, #64B5F6)";
        setTimeout(()=>{$('.progress-container').fadeOut(600);},900)
    }
}

/**
 * -----------------------------------------------------------------------------
 * 📌 Function: getLastUpdate()
 * -----------------------------------------------------------------------------
 * 🎯 الغرض:
 * إنشاء وإرجاع توقيت حالي بصيغة UTC (بتنسيق YYYY-MM-DD HH:MM:SS)
 * لاستخدامه كمرجع زمني عند طلب التحديثات الجزئية من الخادم.
 *
 * -----------------------------------------------------------------------------
 * 🧠 الدور داخل النظام:
 * تُستخدم هذه الدالة لتحديث قيمة lastUpdate بعد:
 *   - الانتهاء من تحميل البيانات
 *   - تنفيذ تحديث جزئي
 *
 * بحيث يتم إرسال هذا التوقيت في الطلب التالي كقيمة:
 *      last_update
 *
 * وبالتالي يطلب العميل فقط البيانات التي تم تعديلها بعد هذا الوقت.
 *
 * -----------------------------------------------------------------------------
 * ⚙️ آلية التنفيذ:
 *
 * 1️⃣ إنشاء كائن Date يمثل الوقت الحالي.
 *
 * 2️⃣ استخراج مكونات التاريخ والوقت بتوقيت UTC:
 *      - السنة        getUTCFullYear()
 *      - الشهر        getUTCMonth() + 1
 *      - اليوم        getUTCDate()
 *      - الساعة       getUTCHours()
 *      - الدقيقة      getUTCMinutes()
 *      - الثانية      getUTCSeconds()
 *
 * 3️⃣ تنسيق القيم باستخدام padStart(2, '0')
 *    لضمان أن كل جزء مكوّن من رقمين.
 *
 * 4️⃣ تجميع الناتج بالشكل:
 *
 *      YYYY-MM-DD HH:MM:SS
 *
 * -----------------------------------------------------------------------------
 * 📦 مثال على الناتج:
 *
 *      2026-02-25 14:37:08
 *
 * -----------------------------------------------------------------------------
 * 🌍 لماذا UTC؟
 *
 * ✔ تجنب مشاكل اختلاف المناطق الزمنية
 * ✔ توحيد التوقيت بين العميل والخادم
 * ✔ منع أخطاء المقارنة الزمنية
 *
 * -----------------------------------------------------------------------------
 * ⚠️ ملاحظات تقنية:
 *
 * - هذه الدالة تُرجع الوقت الحالي فقط،
 *   ولا تعتمد على بيانات فعلية من السيرفر.
 *
 * - في حالة وجود تأخير زمني بين العميل والخادم،
 *   قد يحدث فرق طفيف في التزامن.
 *
 * - يوجد كود مُعلق كان يعتمد على استخراج أحدث olup من البيانات،
 *   ويمكن استخدامه إذا كان النظام يعتمد على آخر تعديل فعلي.
 *
 * -----------------------------------------------------------------------------
 * @returns {string}
 * تاريخ ووقت UTC الحالي بصيغة:
 * YYYY-MM-DD HH:MM:SS
 * -----------------------------------------------------------------------------
 */
function getLastUpdate(/*data*/) {
    //return data.reduce((max, row) => (row.olup > max ? row.olup : max), lastUpdate);
	    const now = new Date();
	const utcDateTime = `${now.getUTCFullYear()}-${String(now.getUTCMonth() + 1).padStart(2, '0')}-${String(now.getUTCDate()).padStart(2, '0')} ` +
	                    `${String(now.getUTCHours()).padStart(2, '0')}:${String(now.getUTCMinutes()).padStart(2, '0')}:${String(now.getUTCSeconds()).padStart(2, '0')}`;
	return utcDateTime;
}

/**
 * -----------------------------------------------------------------------------
 * 📌 Function: removeOrderFromMemory(orderId)
 * -----------------------------------------------------------------------------
 * 🎯 الغرض:
 * حذف أمر معيّن من الذاكرة المحلية (orderslist) بناءً على رقم الأمر (oid)
 * ثم إعادة مزامنة الكائن المفهرس (odata) لضمان اتساق البيانات.
 *
 * -----------------------------------------------------------------------------
 * 🧠 الدور داخل النظام:
 * تُستخدم هذه الدالة عادةً عند:
 *   - حذف أمر من الخادم
 *   - نقل حالة أمر بحيث لا يجب أن يظهر في القائمة الحالية
 *   - استقبال dataDelete من تحديث جزئي
 *
 * -----------------------------------------------------------------------------
 * ⚙️ آلية التنفيذ:
 *
 * 1️⃣ البحث عن موقع الأمر داخل المصفوفة:
 *      findIndex() يتم استخدامه لإيجاد أول عنصر يطابق:
 *          order.oid === orderId
 *
 * 2️⃣ في حال العثور عليه:
 *      - يتم حذفه باستخدام splice(index, 1)
 *
 * 3️⃣ إعادة بناء odata:
 *      - يتم استدعاء convertOrdersListToOData()
 *        لإعادة إنشاء الكائن المفهرس بناءً على الحالة الجديدة للمصفوفة.
 *
 * -----------------------------------------------------------------------------
 * 🚀 المميزات:
 *
 * ✔ الحفاظ على تزامن البيانات بين:
 *      - Array (orderslist)
 *      - Object Map (odata)
 *
 * ✔ إزالة دقيقة بدون التأثير على باقي العناصر
 * ✔ مناسب للأنظمة التي تعتمد على تحديثات لحظية
 *
 * -----------------------------------------------------------------------------
 * ⚠️ ملاحظات تقنية:
 *
 * - إذا لم يتم العثور على العنصر (index === -1)
 *   فلن يتم تنفيذ أي عملية حذف.
 *
 * - يتم إعادة بناء odata بالكامل بعد كل حذف،
 *   وفي حالة البيانات الضخمة يمكن تحسين الأداء عبر
 *   حذف المفتاح مباشرة من odata بدلاً من إعادة بنائه.
 *
 * -----------------------------------------------------------------------------
 * @param {string|number} orderId
 * رقم الأمر المراد حذفه من الذاكرة.
 *
 * @returns {void}
 * تقوم بتحديث orderslist و odata دون إرجاع قيمة.
 * -----------------------------------------------------------------------------
 */
function removeOrderFromMemory(orderId) {
    let index = orderslist.findIndex(order => order.oid === orderId);
    if (index !== -1) {
        orderslist.splice(index, 1);
    }
	odata = convertOrdersListToOData(orderslist);
}

/**
 * -----------------------------------------------------------------------------
 * 📌 Function: removeOrderFromTable(orderId)
 * -----------------------------------------------------------------------------
 * 🎯 الغرض:
 * حذف صف معيّن من جدول DataTable بناءً على رقم الأمر (orderId)
 * دون إعادة تحميل الصفحة أو إعادة ضبط الترقيم (Pagination).
 *
 * -----------------------------------------------------------------------------
 * 🧠 فكرة العمل:
 * يتم البحث داخل جميع صفوف DataTable عن الصف الذي يطابق
 * data.oid مع orderId، ثم يتم حذفه مباشرة من الجدول.
 *
 * -----------------------------------------------------------------------------
 * ⚙️ آلية التنفيذ:
 *
 * 1️⃣ الحصول على نسخة من جدول DataTable:
 *      let table = $('#tbl').DataTable();
 *
 * 2️⃣ استخدام rows() مع دالة فلترة:
 *      - يتم تمرير Function تستقبل:
 *          idx   : رقم الصف الداخلي
 *          data  : بيانات الصف
 *          node  : عنصر DOM
 *
 *      - يتم إرجاع true فقط للصف الذي:
 *          data.oid === orderId
 *
 * 3️⃣ حذف الصف المطابق:
 *      .remove()
 *
 * 4️⃣ إعادة رسم الجدول:
 *      .draw(false)
 *
 *      false تعني:
 *      ✔ عدم إعادة تعيين رقم الصفحة
 *      ✔ الحفاظ على مكان المستخدم الحالي
 *
 * -----------------------------------------------------------------------------
 * 🚀 المميزات:
 *
 * ✔ حذف ديناميكي بدون إعادة تحميل كامل للجدول
 * ✔ الحفاظ على حالة Pagination الحالية
 * ✔ مناسب للتحديثات اللحظية (Real-Time Updates)
 *
 * -----------------------------------------------------------------------------
 * ⚠️ ملاحظات تقنية:
 *
 * - يعتمد على أن كل صف يحتوي على خاصية oid داخل data.
 * - في حالة وجود أكثر من صف بنفس oid سيتم حذفهم جميعًا.
 * - يُفضل التأكد من أن DataTable تم تهيئته مسبقًا قبل استدعاء الدالة.
 *
 * -----------------------------------------------------------------------------
 * @param {string|number} orderId
 * رقم الأمر المراد حذفه من الجدول.
 *
 * @returns {void}
 * تقوم بحذف الصف من DataTable دون إرجاع قيمة.
 * -----------------------------------------------------------------------------
 */
function removeOrderFromTable(orderId) {
    let table = $('#tbl').DataTable();
    
    table.rows(function(idx, data, node) {
        return data.oid === orderId; // البحث عن الصف المطلوب
    }).remove().draw(false); // الحذف بدون إعادة ضبط الصفحة
}

/**
 * -----------------------------------------------------------------------------
 * 📌 Function: convertOrdersListToOData(ordersList)
 * -----------------------------------------------------------------------------
 * 🎯 الغرض:
 * تحويل مصفوفة أوامر البيع (ordersList) إلى كائن مفهرس (Indexed Object)
 * بحيث يكون المفتاح الرئيسي هو رقم الأمر (oid) لتسهيل الوصول المباشر
 * والسريع للبيانات بدون الحاجة للبحث داخل مصفوفة.
 *
 * -----------------------------------------------------------------------------
 * 🧠 لماذا نستخدم هذا التحويل؟
 *
 * عند التعامل مع:
 *   - تحديث أمر معين
 *   - قراءة بيانات أمر سريعًا
 *   - التحقق من وجود أمر
 *
 * البحث داخل Array يتطلب O(n)
 * بينما البحث داخل Object باستخدام مفتاح مباشر يكون O(1)
 *
 * لذلك يتم إنشاء odata كنسخة محسّنة للأداء.
 *
 * -----------------------------------------------------------------------------
 * ⚙️ آلية العمل:
 *
 * 1️⃣ إنشاء كائن فارغ:
 *      let odata = {};
 *
 * 2️⃣ المرور على كل عنصر داخل ordersList
 *
 * 3️⃣ لكل order:
 *      يتم إنشاء خاصية داخل odata
 *      المفتاح = order.oid
 *      القيمة = كائن يحتوي على الخصائص المطلوبة فقط
 *
 * 4️⃣ إرجاع الكائن المفهرس النهائي
 *
 * -----------------------------------------------------------------------------
 * 📦 شكل الناتج:
 *
 * {
 *   "1001": { oid: 1001, shipUid: ..., ... },
 *   "1002": { oid: 1002, shipUid: ..., ... }
 * }
 *
 * -----------------------------------------------------------------------------
 * 🚀 المميزات:
 *
 * ✔ وصول مباشر وسريع لأي أمر عبر:
 *      odata[oid]
 *
 * ✔ تحسين أداء العمليات المتكررة
 * ✔ تقليل عمليات البحث داخل المصفوفات الكبيرة
 * ✔ فصل طبقة العرض (Array) عن طبقة الوصول السريع (Object Map)
 *
 * -----------------------------------------------------------------------------
 * ⚠️ ملاحظات تقنية:
 *
 * - في حال تكرار oid سيتم استبدال القيمة السابقة تلقائيًا.
 * - يتم إنشاء نسخة جديدة من البيانات (Shallow Copy).
 * - إذا كانت ordersList كبيرة جدًا،
 *   يُفضل استدعاء هذه الدالة عند الحاجة فقط لتقليل استهلاك الذاكرة.
 *
 * -----------------------------------------------------------------------------
 * @param {Array<Object>} ordersList
 * مصفوفة تحتوي على أوامر البيع كما تم جلبها من الخادم.
 *
 * @returns {Object}
 * كائن مفهرس يحتوي على الأوامر باستخدام oid كمفتاح رئيسي.
 * -----------------------------------------------------------------------------
 */
function convertOrdersListToOData(ordersList) {
    let odata = {};

    ordersList.forEach(order => {
    	
        odata[order.oid] = {
            oid: order.oid,
            shipUid: order.shipUid,
            shipUName: order.shipUName,
            subOid: order.subOid,
            OTName: order.OTName,
            OStat: order.OStat,
            OSuccess: order.OSuccess,
            OExists: order.OExists,
            OPrint: order.OPrint,
            custstat: order.custstat,
            returns: order.returns,
            olup: order.olup,
            OSid: order.OSid,
            OSStatu: order.OSStatu,
            OSMsgc: order.OSMsgc,
            OSMsgCS: order.OSMsgCS,
            OSMsgEnd: order.OSMsgEnd,
            stoid: order.stoid,
            OInvo: order.OInvo,
            ZName: order.ZName,
            SHIP_VAL: order.SHIP_VAL,
            EXCHANGE: order.EXCHANGE,
            EXCHANGE_SUPID: order.EXCHANGE_SUPID,
            uid: order.uid,
            PRO_QTY: order.PRO_QTY,
            TELEPHONE : order.TELEPHONE
        };
    });

    return odata;
}

/**
 * -----------------------------------------------------------------------------
 * 📌 Function: processResponse(data)
 * -----------------------------------------------------------------------------
 * 🎯 الغرض:
 * معالجة استجابة الخادم بعد جلب دفعة من أوامر البيع، وتحديث الذاكرة،
 * مؤشر التقدم (Progress Bar)، والواجهة عند اكتمال التحميل.
 *
 * -----------------------------------------------------------------------------
 * 🧠 الدور داخل النظام:
 * تُستخدم هذه الدالة أثناء التحميل التدريجي (Batch Loading)،
 * حيث يتم استدعاؤها مع كل دفعة بيانات قادمة من الخادم.
 *
 * -----------------------------------------------------------------------------
 * ⚙️ آلية التنفيذ:
 *
 * 1️⃣ تحديث إجمالي السجلات:
 *    - إذا كانت الاستجابة تحتوي على totalRecords
 *      يتم تحديث المتغير العام totalRecords.
 *
 * 2️⃣ إضافة البيانات الجديدة إلى الذاكرة:
 *    - التحقق أن data.data مصفوفة وغير فارغة.
 *    - إضافة العناصر إلى orderslist باستخدام Spread Operator
 *      للحفاظ على الأداء.
 *
 * 3️⃣ تحديث البيانات المشتقة:
 *    - إعادة بناء odata من orderslist
 *    - تحديث إجماليات العهدة عبر custodyTotal()
 *
 * 4️⃣ تحديث شريط التقدم:
 *    - يتم حساب نسبة التحميل:
 *
 *        progress = (عدد السجلات المحملة ÷ إجمالي السجلات) × 100
 *
 *    - يتم تمرير النسبة إلى updateProgressBar().
 *
 * 5️⃣ إنهاء التحميل:
 *    - إذا كانت hasMore = false
 *      يتم تحديث DataTable نهائيًا عبر updateDataTable().
 *
 * -----------------------------------------------------------------------------
 * 📦 المتغيرات والاعتماديات الخارجية:
 *
 * - totalRecords
 * - orderslist
 * - odata
 * - convertOrdersListToOData()
 * - custodyTotal()
 * - updateProgressBar()
 * - updateDataTable()
 *
 * -----------------------------------------------------------------------------
 * 🚀 المميزات:
 *
 * ✔ فصل منطق المعالجة عن منطق الجلب (Separation of Concerns)
 * ✔ دعم التحميل التدريجي مع عرض نسبة التقدم
 * ✔ تحديث البيانات المشتقة أولًا بأول
 * ✔ عدم تحديث الجدول إلا بعد اكتمال جميع الدفعات
 *
 * -----------------------------------------------------------------------------
 * ⚠️ ملاحظات تقنية:
 *
 * - تعتمد الدالة على وجود خاصية hasMore في استجابة الخادم
 *   لتحديد اكتمال التحميل.
 *
 * - يتم إعادة بناء odata في كل دفعة؛
 *   في حالة البيانات الضخمة يمكن تحسين الأداء بتأجيل ذلك
 *   حتى اكتمال جميع الطلبات.
 *
 * - لا يوجد تحقق من التكرار (Duplicates) عند push،
 *   ويُفترض أن الخادم يضمن عدم تكرار البيانات.
 *
 * -----------------------------------------------------------------------------
 * @param {Object} data
 * كائن الاستجابة القادم من الخادم ويحتوي عادةً على:
 *   {
 *     totalRecords: number,
 *     data: Array,
 *     hasMore: boolean
 *   }
 *
 * @returns {void}
 * تقوم بتحديث الحالة والواجهة دون إرجاع قيمة.
 * -----------------------------------------------------------------------------
 */
function processResponse(data) {
    if (data.totalRecords !== undefined) totalRecords = data.totalRecords ;
    if (Array.isArray(data.data) && data.data.length > 0) {
        orderslist.push(...data.data);
        
		odata = convertOrdersListToOData(orderslist);
		custodyTotal(data);
    }
    let progress = totalRecords > 0 ? Math.round((orderslist.length / totalRecords) * 100) : 0;
    updateProgressBar(progress);
    if (!data.hasMore) {
        updateDataTable(orderslist);
    }
}

/**
 * -----------------------------------------------------------------------------
 * 📌 Function: retryFetch(url, retries = 5)
 * -----------------------------------------------------------------------------
 * 🎯 الغرض:
 * تنفيذ طلب HTTP باستخدام fetch مع دعم إعادة المحاولة التلقائية (Retry Mechanism)
 * في حال حدوث فشل مؤقت في الشبكة أو استجابة غير ناجحة من الخادم.
 *
 * -----------------------------------------------------------------------------
 * 🧠 الفكرة الأساسية:
 * بدلاً من فشل الطلب مباشرة عند حدوث خطأ، تقوم الدالة بمحاولة إعادة
 * التنفيذ عدة مرات بفاصل زمني متزايد (Backoff Strategy) قبل إعلان الفشل النهائي.
 *
 * -----------------------------------------------------------------------------
 * ⚙️ آلية العمل:
 *
 * 1️⃣ حلقة تكرار بعدد المحاولات المحدد (retries).
 *
 * 2️⃣ في كل محاولة:
 *    - إرسال الطلب باستخدام fetch(url).
 *    - التحقق من response.ok:
 *        ✔ إذا كانت الاستجابة ناجحة → يتم إرجاع البيانات بصيغة JSON.
 *        ✖ إذا كانت غير ناجحة → يتم رمي خطأ (HTTP Error).
 *
 * 3️⃣ في حال حدوث خطأ:
 *    - تسجيل تحذير في الـ console.
 *    - الانتظار مدة زمنية متزايدة قبل إعادة المحاولة:
 *
 *        500ms × رقم المحاولة
 *
 *      مثال:
 *        المحاولة الأولى  → 500ms
 *        المحاولة الثانية → 1000ms
 *        المحاولة الثالثة → 1500ms
 *
 *    هذا يُعرف باسم Linear Backoff لتقليل الضغط على السيرفر.
 *
 * 4️⃣ إذا فشلت جميع المحاولات:
 *    - يتم رمي خطأ نهائي يوضح فشل التحميل.
 *
 * -----------------------------------------------------------------------------
 * 📦 الاستخدام النموذجي:
 *
 *    const data = await retryFetch(apiURL, 5);
 *
 * -----------------------------------------------------------------------------
 * 🚀 المميزات:
 *
 * ✔ تحسين الاعتمادية عند وجود مشاكل شبكة مؤقتة
 * ✔ تقليل احتمالية فشل الطلبات الحرجة
 * ✔ تأخير متزايد يمنع إغراق السيرفر بطلبات متتالية
 *
 * -----------------------------------------------------------------------------
 * ⚠️ ملاحظات تقنية:
 *
 * - لا يوجد AbortController، لذلك لا يمكن إلغاء الطلب أثناء التنفيذ.
 * - لا يوجد Timeout مخصص — يعتمد على timeout الافتراضي للمتصفح.
 * - يُفضل استخدام Exponential Backoff في الأنظمة عالية الضغط.
 * - الدالة تفترض أن الاستجابة JSON.
 *
 * -----------------------------------------------------------------------------
 * @param {string} url
 * رابط الـ API المراد استدعاؤه.
 *
 * @param {number} retries
 * عدد مرات إعادة المحاولة قبل الفشل النهائي (الافتراضي = 5).
 *
 * @returns {Promise<Object>}
 * تعيد Promise يحتوي على JSON الناتج من الخادم.
 *
 * @throws {Error}
 * يتم رمي خطأ إذا فشلت جميع المحاولات.
 * -----------------------------------------------------------------------------
 */
async function retryFetch(url, retries = 5) {
    for (let i = 0; i < retries; i++) {
        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
            return await response.json();
        } catch (error) {
            console.warn(`⚠ فشل الطلب ${i + 1}/${retries} لـ: ${url}, المحاولة مجددًا...`);
            await new Promise(resolve => setTimeout(resolve, 500 * (i + 1))); // زيادة مهلة الانتظار بين المحاولات
        }
    }
    throw new Error(`❌ فشل تحميل البيانات بعد ${retries} محاولات: ${url}`);
}

/**
 * -----------------------------------------------------------------------------
 * 📌 Function: updateOrders(loding = true)
 * -----------------------------------------------------------------------------
 * 🎯 الغرض:
 * تحديث أوامر البيع المتغيرة فقط (Incremental Update) دون إعادة تحميل
 * جميع البيانات، وذلك اعتمادًا على قيمة lastUpdate لتقليل استهلاك
 * الشبكة وتحسين الأداء.
 *
 * -----------------------------------------------------------------------------
 * 🧠 فكرة العمل:
 * تعتمد الدالة على إرسال آخر وقت تحديث (lastUpdate) إلى الخادم،
 * ليقوم بإرجاع:
 *
 *  - data        : السجلات الجديدة أو المعدلة فقط
 *  - dataDelete  : المعرفات التي يجب حذفها من الواجهة
 *
 * -----------------------------------------------------------------------------
 * ⚙️ آلية التنفيذ:
 *
 * 1️⃣ منع التداخل:
 *    - إذا كان هناك تحميل جاري (isLoading = true) يتم إيقاف التنفيذ فورًا.
 *
 * 2️⃣ تجهيز رابط الطلب:
 *    - استخدام vieworderlistPram
 *    - إضافة last_update = lastUpdate
 *
 * 3️⃣ عرض مؤشر التحميل (اختياري):
 *    - يتم إظهار Overlay فقط إذا كانت loding = true
 *
 * 4️⃣ إرسال الطلب:
 *    - fetch(finalURL)
 *    - تحويل الاستجابة إلى JSON
 *
 * 5️⃣ معالجة البيانات الجديدة/المعدلة:
 *    - لكل عنصر جديد:
 *        ✔ تحديد مكانه الصحيح في orderslist للحفاظ على الترتيب
 *        ✔ إزالة النسخة القديمة من الجدول والذاكرة إن وجدت
 *        ✔ إدراجه في الموضع الصحيح (Insert Sorted)
 *
 *    - بعد الانتهاء:
 *        ✔ إعادة رسم DataTable
 *        ✔ تحديث lastUpdate بناءً على أحدث البيانات
 *
 * 6️⃣ معالجة البيانات المحذوفة:
 *    - إزالة الصف من الجدول
 *    - إزالة السجل من الذاكرة
 *
 * 7️⃣ تحديث بيانات إضافية:
 *    - custodyTotal(data)
 *
 * -----------------------------------------------------------------------------
 * 🔁 في كتلة finally:
 *
 *    ✔ إعادة ضبط أعمدة الجدول
 *    ✔ إعادة بناء odata إذا وُجدت بيانات
 *    ✔ تعطيل حالة التحميل
 *    ✔ إخفاء Overlay
 *    ✔ إعادة تهيئة أزرار العهدة (optionBtnCustody)
 *
 * -----------------------------------------------------------------------------
 * 📦 المتغيرات والاعتماديات الخارجية:
 *
 * - isLoading
 * - vieworderlistPram
 * - lastUpdate
 * - urlvo
 * - orderslist
 * - removeOrderFromTable()
 * - removeOrderFromMemory()
 * - convertOrdersListToOData()
 * - custodyTotal()
 * - optionBtnCustody()
 * - table (DataTable instance)
 *
 * -----------------------------------------------------------------------------
 * 🚀 المميزات:
 *
 * ✔ تحديث جزئي بدل إعادة تحميل كاملة
 * ✔ تقليل استهلاك الباندويث
 * ✔ الحفاظ على ترتيب البيانات
 * ✔ منع التحديث المتداخل
 * ✔ مزامنة الحذف والتعديل في نفس الطلب
 *
 * -----------------------------------------------------------------------------
 * ⚠️ ملاحظات تقنية مهمة:
 *
 * - يعتمد الترتيب على مقارنة new Date(row.oid)
 *   يجب التأكد أن oid يمثل قيمة زمنية صحيحة.
 *
 * - يتم إعادة رسم الجدول بالكامل (clear + add + draw)
 *   في حالة البيانات الكبيرة يمكن تحسين الأداء باستخدام
 *   row().data() بدلاً من إعادة بناء الجدول بالكامل.
 *
 * - يجب أن يعيد الخادم دائمًا:
 *      {
 *          data: [],
 *          dataDelete: []
 *      }
 *
 * -----------------------------------------------------------------------------
 * @param {boolean} loding
 * تحديد ما إذا كان سيتم عرض مؤشر التحميل أثناء التحديث.
 *
 * @returns {Promise<void>}
 * دالة غير متزامنة تقوم بتحديث الجدول والذاكرة فقط.
 * -----------------------------------------------------------------------------
 */
async function updateOrders(loding = true) {
	let server_sideus ={
		 7  : window.APP_CONFIG.datatablesServerSideEx7, //قيدالتجهيز
		 8  : window.APP_CONFIG.datatablesServerSideEx8, //تم التجهيز
		 9  : window.APP_CONFIG.datatablesServerSideEx9, //جارى الشحن
		 10 : window.APP_CONFIG.datatablesServerSideEx10, //عهدة المندوبين
	};
	

	if( 
		(ptype == 2 && !dt_serverside) || 
		(ptype == 3 && !dt_serverside) || 
		(ptype == 1 && Exists == 0  && !dt_serverside0) || 
		(ptype == 1 && Exists == 1  && !dt_serverside1) || 
		(ptype == 1 && Exists == 2  && !dt_serverside2) || 
		(ptype == 1 && Exists == 3  && !dt_serverside3) || 
		(ptype == 1 && Exists == 4  && !dt_serverside4) || 
		(ptype == 1 && Exists == 5  && !dt_serverside5) || 
		(ptype == 1 && Exists == 7  && !dt_serverside7) || 
		(ptype == 1 && Exists == 8  && !dt_serverside8) || 
		(ptype == 1 && Exists == 9  && !dt_serverside9) || 
		(ptype == 1 && Exists == 10 && !dt_serverside10)
	){
	    if (isLoading) return;
	    isLoading = true;
	    try {
	        const urlParams = new URLSearchParams(vieworderlistPram);
	        urlParams.append("last_update", lastUpdate);
	        const finalURL = urlvo + '?' + urlParams.toString();
			lastUpdate = getLastUpdate();
	        if(loding){
		        await setTimeout(function (){
			    	$('.table-overlay1').fadeIn(200);
			    },100);
			}
	        const response = await fetch(finalURL);
	        const data = await response.json();
			
	        if (data.data.length > 0) {
	            let table = $('#tbl').DataTable();
				// أضف/حدث البيانات في orderslist زي ما انت بتعمل
				data.data.forEach(newRow => {
				    let insertIndex = orderslist.findIndex(row => new Date(row.oid) > new Date(newRow.oid));
				    removeOrderFromTable(newRow.oid);
				    removeOrderFromMemory(newRow.oid);
				
				    if (insertIndex !== -1) {
				        orderslist.splice(insertIndex, 0, newRow);
				    } else {
				        orderslist.push(newRow);
				    }
				});
				
				// بعد الترتيب في orderslist
				table.clear();
				table.rows.add(orderslist);
				table.draw(false);
	
	            lastUpdate = getLastUpdate(data.data); // تحديث `lastUpdate` بعد التعديلات.
				//odata = convertOrdersListToOData(orderslist);
				
	        }
	        if(data.dataDelete.length > 0){
	        	data.dataDelete.forEach(removRow => {
	                // إزالة الصف من الجدول والذاكرة إذا كان موجودًا
	                removeOrderFromTable(removRow);
	                removeOrderFromMemory(removRow);
				});
	        }
			custodyTotal(data);
	    } catch (error) {
	        console.error("⚠ خطأ أثناء تحديث البيانات:", error);
	    } finally {
	    	
	       	table.columns.adjust();
	    	if (orderslist.length > 0) {
	    		odata = convertOrdersListToOData(orderslist);
	    	}else{
				odata = {};
			}
	        isLoading = false;
	        $('.table-overlay1').fadeOut(200);
	        optionBtnCustody();
	    }
	}else{
		table.ajax.reload(null, false);
	}
	$('.table-overlay1').fadeOut(200);
	$('.btnback').click();
}

/**
 * -----------------------------------------------------------------------------
 * 📌 Function: fetchOrders(setparm = null)
 * -----------------------------------------------------------------------------
 * 🎯 الغرض:
 * جلب أوامر البيع (Orders) من الخادم على دفعات (Batch Loading) بطريقة غير متزامنة
 * لتقليل الضغط على السيرفر وتحسين الأداء وتجربة المستخدم عند التعامل مع عدد
 * كبير من السجلات.
 *
 * -----------------------------------------------------------------------------
 * ⚙️ آلية العمل:
 *
 * 1️⃣ تحديد البراميتر:
 *    - إذا تم تمرير setparm يتم استخدامه.
 *    - إذا لم يتم تمرير شيء يتم استخدام vieworderlistPram الافتراضي.
 *
 * 2️⃣ عرض طبقة التحميل (Overlay) وتفعيل حالة التحميل:
 *    - isLoading = true
 *    - إظهار مؤشر التحميل على الجدول.
 *
 * 3️⃣ الطلب الأول (Initial Request):
 *    - يتم إرسال طلب للحصول على أول دفعة بيانات.
 *    - يحتوي على:
 *        offset = 0
 *        limit  = batchSize
 *        order  = desc
 *        last_update = 1970-01-01 00:00:00
 *    - الهدف:
 *        ✔ جلب أول دفعة بيانات
 *        ✔ معرفة totalRecords (إجمالي عدد السجلات)
 *
 * 4️⃣ في حالة عدم وجود بيانات:
 *    - تحديث شريط التقدم إلى 100%
 *    - إخفاء الـ overlay
 *
 * 5️⃣ معالجة أول استجابة:
 *    - تخزين البيانات داخل orderslist عبر processResponse()
 *
 * 6️⃣ حساب عدد الطلبات المتبقية:
 *    totalRequests = Math.ceil(totalRecords / batchSize)
 *
 * 7️⃣ إرسال باقي الطلبات على دفعات:
 *    - يتم إنشاء مجموعة Promises داخل pendingRequests
 *    - كل طلب:
 *        ✔ يحدد offset جديد
 *        ✔ يستخدم retryFetch لإعادة المحاولة عند الفشل
 *        ✔ يعالج البيانات فور استلامها
 *    - يوجد تأخير تدريجي (i * 300ms) لتقليل الضغط على السيرفر.
 *
 * 8️⃣ انتظار انتهاء جميع الطلبات:
 *    - باستخدام Promise.allSettled()
 *
 * 9️⃣ بعد الانتهاء (finally block):
 *    - تحديث lastUpdate
 *    - إعادة ضبط أعمدة DataTable
 *    - تحويل البيانات إلى odata إذا وُجدت بيانات
 *    - تعطيل حالة التحميل
 *    - إخفاء الـ overlay
 *
 * -----------------------------------------------------------------------------
 * 📦 المتغيرات الخارجية المستخدمة:
 *
 * - orderslist              : مصفوفة تخزين الأوامر
 * - batchSize               : حجم الدفعة الواحدة
 * - offset                  : مؤشر بداية القراءة
 * - retryFetch()            : دالة fetch مع إعادة المحاولة
 * - processResponse()       : معالجة وإضافة البيانات
 * - convertOrdersListToOData(): تحويل البيانات إلى صيغة OData
 * - getLastUpdate()         : استخراج آخر وقت تحديث
 * - table                   : كائن DataTable
 * - urlvo                   : رابط API
 * - retryCount              : عدد مرات إعادة المحاولة
 *
 * -----------------------------------------------------------------------------
 * 🚀 مميزات التنفيذ:
 *
 * ✔ تحميل تدريجي للبيانات (Progressive Loading)
 * ✔ تقليل الضغط على السيرفر
 * ✔ دعم إعادة المحاولة في حالة فشل الطلب
 * ✔ عدم إيقاف التنفيذ عند فشل أحد الطلبات
 * ✔ تحسين تجربة المستخدم عبر Overlay + Progress
 *
 * -----------------------------------------------------------------------------
 * ⚠️ ملاحظات مهمة:
 *
 * - يجب التأكد من تصفير offset قبل استدعاء الدالة إن لزم.
 * - تعتمد الدالة على وجود totalRecords في الاستجابة الأولى.
 * - retryFetch يجب أن يعيد Promise صالح.
 * - استخدام setTimeout داخل loop قد لا يضمن ترتيب التنفيذ بدقة.
 *
 * -----------------------------------------------------------------------------
 * @param {Object|null} setparm
 * كائن يحتوي على باراميترات مخصصة للاستعلام، وإن لم يُمرر
 * يتم استخدام الإعدادات الافتراضية vieworderlistPram.
 *
 * @returns {Promise<void>}
 * دالة غير متزامنة لا تعيد قيمة، لكنها تقوم بتحديث الحالة والواجهة.
 * -----------------------------------------------------------------------------
 */
/*async function fetchOrders(setparm = null) {
	orderslist = [];
	//offset = 0;
	var getparm = {};
	if (typeof setparm === 'object' && setparm !== null) {
	    getparm = setparm;
	} else {
	    getparm = vieworderlistPram;
	}
    isLoading = true;
    //let firstparm = geturl;
    //firstparm['count']=true;
    //console.log(firstparm);
    await setTimeout(function (){
    	$('.table-overlay1').fadeIn(200);
    	
    },20);
     
    try {
        // إرسال الطلب الأولي للحصول على إجمالي عدد السجلات
        const firstURL = new URLSearchParams(getparm);
        firstURL.append("offset", 0);
	    firstURL.append("limit", Number(batchSize));
	    firstURL.append("order", 'desc');
	    firstURL.append("last_update", '1970-01-01 00:00:00');
		offset += Number(batchSize);
		
        //إرسال الطلب الأولي وانتظار الاستجابه
        firstResponse = await retryFetch(urlvo + '?' +firstURL.toString(), retryCount);
		
		// فى حالى عدم وجود اى سجلات 
        if (!firstResponse || !firstResponse.totalRecords) {
            setTimeout(function (){updateProgressBar(Math.round(99));},10)
            setTimeout(function (){updateProgressBar(Math.round(100));},50)
            $('.table-overlay1').fadeOut(200);
            
            //return 
        }
        //تحديث العرض والذاكرة المؤقته التى تم جلبها من الطلب الاولى
        processResponse(firstResponse);
   } catch (error) {
        console.error("❌ فشل في جلب البيانات الأولية:", error);
        isLoading = false;
        $('.table-overlay1').fadeOut(200);
        return;
    }
    try{
	//حفظ اجمالى عدد السجلات 
    totalRecords = firstResponse.totalRecords;
	// حساب عدد الطلبات المطلوبة
    totalRequests = Math.ceil(totalRecords / batchSize);
    //متغير لحفظ الطلبات التى سوف يتم ارسلها دفعه واحده
    let pendingRequests = [];
	//ترسال الطلبات دفعه واحده الى الخادم
    for (let i = 1; i < totalRequests; i++) {
    	await setTimeout(function(){
        //let currentOffset = i * batchSize;
        let finalURL = new URLSearchParams(getparm);
        finalURL.append("offset", offset);
	    finalURL.append("limit", batchSize);
	    finalURL.append("last_update", '1970-01-01 00:00:00');
		offset += batchSize

        let requestPromise = retryFetch(urlvo + '?' +finalURL.toString(), retryCount)
            .then(data => {
                if (Array.isArray(data.data) && data.data.length > 0) {
                	processResponse(data);
                }
            })
            .catch(error => {
                console.error(`❌ فشل الطلب ${i}:`, error);
            });

        pendingRequests.push(requestPromise);
    	},i*30);
    }
    // ⏳ انتظار جميع الطلبات حتى تنتهي
    await Promise.allSettled(pendingRequests);
	//تريخ اخر تحديث للشاشة
	
    // تحويل البيانات الى كائن odata
	//odata = convertOrdersListToOData(orderslist);
    // ✅ تحديث الواجهة بعد الانتهاء من جميع الطلبات
    // processResponse(orderslist);
    //updateDataTable(orderslist, odata);
    // تفعيل ارسال طلبات التحديث
    //isLoading = false;
    } catch (error) {
    	
        console.error("❌ فشل في جلب البيانات المتبقيه:", error);
        isLoading = false;
        $('.table-overlay1').fadeOut(200);
        return;
    }finally {
    	
    	lastUpdate = getLastUpdate();
    	table.columns.adjust();
    	if (orderslist.length > 0) {
    		odata = convertOrdersListToOData(orderslist);
    	}else{
			odata = {};
		}
        isLoading = false;
        $('.table-overlay1').fadeOut(200);
    }
   
};*/
async function fetchOrders(setparm = null) {

    orderslist = [];
    offset = 0;
    isLoading = true;

    const getparm = (typeof setparm === 'object' && setparm !== null)
        ? setparm
        : vieworderlistPram;

    $('.table-overlay1').fadeIn(200);

    try {

        // ===== الطلب الأول =====
        const firstURL = new URLSearchParams(getparm);
        firstURL.append("offset", 0);
        firstURL.append("limit", batchSize);
        firstURL.append("order", 'desc');
        firstURL.append("last_update", '1970-01-01 00:00:00');

        const firstResponse = await retryFetch(
            urlvo + '?' + firstURL.toString(),
            retryCount
        );

        if (!firstResponse || !firstResponse.totalRecords) {
            updateProgressBar(100);
            return;
        }

        processResponse(firstResponse);

        const totalRecords = firstResponse.totalRecords;
        const totalRequests = Math.ceil(totalRecords / batchSize);

        // ===== تجهيز كل الطلبات مرة واحدة =====
        const requests = [];

        for (let i = 1; i < totalRequests; i++) {

            const currentOffset = i * batchSize;

            const finalURL = new URLSearchParams(getparm);
            finalURL.append("offset", currentOffset);
            finalURL.append("limit", batchSize);
            finalURL.append("last_update", '1970-01-01 00:00:00');

            requests.push(
                retryFetch(urlvo + '?' + finalURL.toString(), retryCount)
                    .then(data => {
                        if (Array.isArray(data?.data) && data.data.length > 0) {
                            processResponse(data);
                        }
                    })
                    .catch(error => {
                        console.error(`❌ فشل الطلب رقم ${i}`, error);
                    })
            );
        }

        await Promise.allSettled(requests);

    } catch (error) {

        console.error("❌ فشل في جلب البيانات:", error);

    } finally {

        lastUpdate = getLastUpdate();

        if (orderslist.length > 0) {
            odata = convertOrdersListToOData(orderslist);
        } else {
            odata = {};
        }

        table.columns.adjust();
        isLoading = false;
        $('.table-overlay1').fadeOut(200);
    }
}

// تشغيل التحديثات كل 30 ثانيه
/*setInterval(() => {
    if (!isLoading) {
       //updateOrders(false);
    }
}, 70000); */

document.addEventListener("DOMContentLoaded", function() {
	let searchTimeout;
	
	if( 
		(ptype == 2 && !dt_serverside) || 
		(ptype == 3 && !dt_serverside) || 
		(ptype == 1 && Exists == 0  && !dt_serverside0) || 
		(ptype == 1 && Exists == 1  && !dt_serverside1) || 
		(ptype == 1 && Exists == 2  && !dt_serverside2) || 
		(ptype == 1 && Exists == 3  && !dt_serverside3) || 
		(ptype == 1 && Exists == 4  && !dt_serverside4) || 
		(ptype == 1 && Exists == 5  && !dt_serverside5) || 
		(ptype == 1 && Exists == 7  && !dt_serverside7) || 
		(ptype == 1 && Exists == 8  && !dt_serverside8) || 
		(ptype == 1 && Exists == 9  && !dt_serverside9) || 
		(ptype == 1 && Exists == 10 && !dt_serverside10)
	){
		$('#tbl #searchStatus').html(`
		    <option value="">الكل</option>
			<option data-type="2" value="11">بيع مباشر</option>
			<option data-type="2" value="قيد المراجعه">قيد التأكيد</option>
			<option data-type="2" value="مؤجل">مؤجل</option>
		    <option data-type="2" value="غير متوفر">غير متوفر</option>
			<option data-type="2" value="العميل لايستجيب">العميل لا يستجيب</option>
			<option data-type="2" value="إلغاء امر البيع">ملغى</option>
			<option data-type="2" value="طلبات قيد التجهيز">قيد التجهيز</option>
			<option data-type="2" value="طلبات تم التجهيز">تم التجهيز</option>
			<option data-type="2" value="طلبات جارى الشحن">جارى الشحن</option>
						                
			<option data-type="1" value="تم التسليم">تم التسليم</option>
			<option data-type="1" value="تسليم جزئى">تسليم جزئى</option>
			<option data-type="1" value="مرفوض">مرفوض</option>
		`);
		/**
		 * -----------------------------------------------------------------------------
		 * 📌 Auto Initialization on Page Load
		 * -----------------------------------------------------------------------------
		 * 🎯 الغرض:
		 * تشغيل تحميل أوامر البيع تلقائياً بمجرد اكتمال تحميل عناصر الصفحة (DOM).
		 *
		 * -----------------------------------------------------------------------------
		 * ⚙️ آلية العمل:
		 *
		 * - يتم الاستماع لحدث DOMContentLoaded
		 * - عند اكتمال تحميل هيكل الصفحة (بدون انتظار الصور أو الملفات الثقيلة)
		 *   يتم استدعاء الدالة fetchOrders()
		 *
		 * -----------------------------------------------------------------------------
		 * 📌 لماذا نستخدم DOMContentLoaded بدلاً من window.onload؟
		 *
		 * ✔ DOMContentLoaded أسرع لأنه ينتظر اكتمال DOM فقط
		 * ✔ لا ينتظر تحميل الصور أو الفيديوهات أو الملفات الخارجية
		 * ✔ مناسب لبدء تحميل البيانات الديناميكية مبكراً
		 *
		 * -----------------------------------------------------------------------------
		 * ⚠️ ملاحظة مهمة:
		 *
		 * - يتم تمرير القيمة true إلى fetchOrders()
		 * - يجب التأكد أن الدالة تتعامل مع هذا النوع من البراميتر بشكل صحيح
		 *   لأن fetchOrders تتوقع Object أو null.
		 *
		 * -----------------------------------------------------------------------------
		 */
		fetchOrders(true);
		/*=========================================================
		 عرض بيانات اوامر البيع بالجدول
		=========================================================*/
		table = $('#tbl').DataTable({
				scrollX: true, // تفعيل التمرير الأفقي لتجنب ضغط الجدول
			    autoWidth: true, // تعطيل التحكم التلقائي في عرض الأعمدة
				
		        //scrollY : '70vh',
		        //scrollX : true,
		        //autoWidth: false,
		        data: orderslist,
				orderCellsTop: true, // يضمن أن الترتيب يعمل فقط على الصف العلوي
	    		ordering: true, // يسمح بالترتيب ولكن فقط على الهيدر الأول
		        processing: false,
		        serverSide: false,
				rowId : function(row){
					return 'row-' + row.oid;
				},
		        columns: [
		            { 
		                data: 'oid', 
		                type: 'num',
		                //title: 'رقم الطلب',
		                className: 'text-center',
	            		//width: '', 
		                render: (data, type, row) => {
		                    let badgcolor = " badge-light ";
		                    badgeColor = row['custstat'] === "FREEZE" ? "badge-red50" :
		                                    row['ORDER_TYPE_ID'] == 1 ? "badge-blue10" :
		                                    row['EXCHANGE'] ? "badge-warning" : "";
		
		                    let orderEx = row['EXCHANGE'] ? 
		                        `<span class="text-danger badge badge-warning" style="position: absolute;top: 16px;right: -5px;">
		                        استبدال للأمر: ${row['EXCHANGE_SUPID']}</span>` : "";
		
		                    return `
		                    <div class="badge ${badgcolor} text-left"  style="font-size: 1.2rem;width:100%;height:auto">
					            <div class="d-flex justify-content-between m-2" style ="position: relative;">
			                        <div class="text-primary"> ${row['subOid']}</div>
		                        	${orderEx}
		                        </div>
		                        <hr class="mb-1 mt-1">
					            <div class="d-flex justify-content-between m-2">
		                        	<div class="badge"><i class="fa-regular fa-user"></i> ${row['BUTTONS'][1]}</div>
		                        </div>
					            <hr class="mb-1 mt-1">
					            <div class="d-flex justify-content-between m-2">
		                        	<div class="badge"><i class="far fa-calendar-plus"></i> ${row['DATE_CREATED']}</div>
		                        </div>
		                    </div>`;
		                }
		            },
		            { 
		                data: 'STA_NAME', 
		               // title: 'حالة الطلب',
		                className: 'text-center',
		                render: (data, type, row) => {
		                	let badgcolor = " badge-light ";
		                	badgeColor = row['custstat'] === "FREEZE" ? "badge-red50" :
		                                    row['ORDER_TYPE_ID'] == 1 ? "badge-blue10" :
		                                    row['EXCHANGE'] ? "badge-warning" : "";
		                                    
		                    return `
		                    <div class="badge ${badgcolor} text-left"  style="font-size: 1.2rem;width:100%;height:auto">
					            <div class="d-flex justify-content-between m-2" style ="position: relative;">
		                        	<div class="badge badge-${row["COLOR_CLASS"]} w-100"><i class="fa-regular fa-clock"></i> ${data}</div>
		                        </div>
		                        <hr class="mb-1 mt-1">
		                        <div class="d-flex justify-content-between m-2" style ="position: relative;">
		                        	<div class="badge badge-light w-100"><i class="fa-solid fa-display"></i> ${row['EXISTS_NAME']}</div>
		                        </div>
		                        <hr class="mb-1 mt-1">
		                        <div class="d-flex justify-content-between m-2" style ="position: relative;">
		                        	<div class="badge badge-light w-100"><i class="fa-solid fa-gears"></i> ${row['SUCCESS_STAT']}</div>
		                    	</div>
		                    </div>`;
		                }
		            },
		            { 
		                data: 'CLIENT_NAME', 
		                //title: 'معلومات العميل',
		                className: 'text-left',
		                render: (data, type, row) => {
		                	let badgcolor = " badge-light ";
		                	badgeColor = row['custstat'] === "FREEZE" ? "badge-red50" :
		                                    row['ORDER_TYPE_ID'] == 1 ? "badge-blue10" :
		                                    row['EXCHANGE'] ? "badge-warning" : "";
		                                    
		                    return `
		                    <div class="badge ${badgcolor} text-left"  style="font-size: 1.2rem;width:100%;height:auto">
					            <div class="d-flex justify-content-between m-2" style ="position: relative;">
									<div class="badge"><i class="fas fa-user"></i> ${data}</div>
		                            <div class="badge"><i class="fas fa-mobile-alt"></i><a href="tel:${row['TELEPHONE']}"> ${row['TELEPHONE']}</a></div>
		                        </div>
		                        <hr class="mb-1 mt-1">
		                        <div class="d-flex justify-content-between m-2" style ="position: relative;">
		                        	<div class="badge"><i class="fas fa-shopping-cart"></i> المنتجات ${row['PRODUCTS_COUNT']} | الكمية ${row['PRO_QTY']} | التكلفة ${row['PRICE']}</div>
		                        </div>
		                        <hr class="mb-1 mt-1">
		                        <div class="d-flex justify-content-between m-2">
		                            <div class="badge"><i class="fas fa-truck-fast"></i> ${row['ZName']}</div>
		                            <div class="badge"><i class="fa fa-map-location-dot"></i><span class="text-primary"> ${row["GOV_NAME"]} - ${row["CI_NAME"]} </span></div>
		                        </div>
		                    </div>`;
		                }
		            },
		            { 
		                data: 'DELIVERY_DATE', 
		                //title: 'تواريخ الشحن والتسليم',
		                className: 'text-center',
		                render: (data, type, row) => {
		                	let badgcolor = " badge-light ";
		                	badgeColor = row['custstat'] === "FREEZE" ? "badge-red50" :
		                                    row['ORDER_TYPE_ID'] == 1 ? "badge-blue10" :
		                                    row['EXCHANGE'] ? "badge-warning" : "";
		                                    
		                    return `
		                    <div class="badge ${badgcolor} text-left"  style="font-size: 1.2rem;width:100%;height:auto">
					            <div class="d-flex justify-content-between m-2" style ="position: relative;">
		                        	<div class="badge badge-light w-100"><i class="fa-solid fa-truck-fast"></i> ${data}</div>
		                        </div>
		                        <hr class="mb-1 mt-1">
		                        <div class="d-flex justify-content-between m-2" style ="position: relative;">
		                        	<div class="badge badge-light w-100"><i class="fa-solid fa-right-from-bracket"></i> ${row['SHIPPING_DATE']}</div>
		                        </div>
		                        <hr class="mb-1 mt-1">
		                        <div class="d-flex justify-content-between m-2" style ="position: relative;">
		                        	<div class="badge badge-light w-100"><i class="fa-solid fa-handshake"></i> ${row['DELIVERED_DATE']}</div>
		                        </div>
		                    </div>`;
		                }
		            },
		            {
		                data : 'BUTTONS',
		                //title: 'تحرير',
		                orderable: false,
		                className: 'text-center text-nowrap',
		                render: (data, type, row) => {
		                		/*return [
									'oid'       => $use == true ? $orderData["ORDER_ID"] : "",
									'oclass'    => $use == true ? "text-primary border" : "text-secondary border",
									'available' => $use == true ? "" : "disabled",
									'elid'      => 'btnOview',
									'icon'      => 'fa-solid fa-eye',
									'msgnotification' => ""
								];*/
							let adminsupport = "",
								msgnotification = "",
								clasRowSpan = "",
								return_BTNs = "";
							if(row['OSMsgc']>0){
					            msgnotification = row['OSMsgc'];
					        }else if(row['OSid']>0 && row['OSStatu']==0 && row['OSMsgc'] ==0){
					            $msgnotification = '<i class="fa-regular fa-envelope"></i>';
					        }
					        
							if(ptype == 1){ 
					            adminsupport =`<td class="bg-white " colspan="2" rowspan="2">
							                            <a id="AdminSupport" data-id="${row['oid']}" class="btn btn-app-tools btn-light badge-ya text-5 bg-gradient mt-2" style="margin-left:-5px;margin-right:-5px;width:50px;">
							                                <span class="badge bg-danger">${msgnotification}</span>
							                                <i class="fas fa-headset"></i>الدعم
							                            </a>
							                    	</td>`;
					    	}
							if( row['BUTTONS'][0][2] == "" && row['BUTTONS'][0][3] == "" && (row['BUTTONS'][0][1] != "" || row['BUTTONS'][0][0] == "" ) ||
					        	row['BUTTONS'][0][0] == "" && row['BUTTONS'][0][1] == "" && (row['BUTTONS'][0][3] != "" || row['BUTTONS'][0][2] == "" ) 
					        ){
					        	clasRowSpan = 'rowspan="2"';
					        }
					        return_BTNs = `
					        	<table class="table OLCBtn table-borderless" >
					        		<tbody class="badge card-body mt-2 ml-2 mt-r tableAllBtn" style="font-size:1.5rem;">
					                    <tr class="p-1">
						                    ${adminsupport}
						                    <td class="p-1 badge-light btnTR" ${clasRowSpan}>
							                    <button id="${row['BUTTONS'][0][0]['elid']}" data-id="${row['BUTTONS'][0][0]['oid']}" class="btn btn-app-tools btn-xs ${row['BUTTONS'][0][0]['elid']} ovbtn w-100 h-100 ${row['BUTTONS'][0][0]['oclass']}">
											            <i class="${row['BUTTONS'][0][0]['icon']}"></i>
											            <a style="font-size:14px; font-weight: bold;">${row['BUTTONS'][0][0]['name']}</a>
											    </button>
							                </td>`;
					        if(row['BUTTONS'][0][1] != ""){
						        return_BTNs += `<td class="p-1 badge-light btnTL " ${clasRowSpan}>
						        					<button id="${row['BUTTONS'][0][1]['elid']}" data-id="${row['BUTTONS'][0][1]['oid']}" class="btn btn-app-tools btn-xs ${row['BUTTONS'][0][1]['elid']} ovbtn w-100 h-100 ${row['BUTTONS'][0][1]['oclass']}">
											            <i class="${row['BUTTONS'][0][1]['icon']}"></i>
											            <a style="font-size:14px; font-weight: bold;">${row['BUTTONS'][0][1]['name']}</a>
											        </button>
						        				</td>`;
					        }
							return_BTNs += `</tr><tr class="p-1">`;
					        if(row['BUTTONS'][0][2] != ""){
						    	return_BTNs += `<td class="p-1 badge-light btnBR " ${clasRowSpan}>
						    						<button id="${row['BUTTONS'][0][2]['elid']}" data-id="${row['BUTTONS'][0][2]['oid']}" class="btn btn-app-tools btn-xs ${row['BUTTONS'][0][2]['elid']} ovbtn w-100 h-100 ${row['BUTTONS'][0][2]['oclass']}">
											         	<span class="badge bg-danger">${adminsupport != "" ? "" : msgnotification}</span>
											            <i class="${row['BUTTONS'][0][2]['icon']}"></i>
											            <a style="font-size:14px; font-weight: bold;">${row['BUTTONS'][0][2]['name']}</a>
											         </button>
						    					</td>`;
					        }
					        if(row['BUTTONS'][0][3] != ""){
								 return_BTNs += `<td class="p-1 badge-light btnBL" ${clasRowSpan}>
								 					<button id="${row['BUTTONS'][0][3]['elid']}" data-id="${row['BUTTONS'][0][3]['oid']}" class="btn btn-app-tools btn-xs ${row['BUTTONS'][0][3]['elid']} ovbtn w-100 h-100 ${row['BUTTONS'][0][3]['oclass']}">
											            <span class="badge bg-danger spantimer"></span>
											            <i class="${row['BUTTONS'][0][3]['icon']}"></i>
											            <a style="font-size:14px; font-weight: bold;">${row['BUTTONS'][0][3]['name']}</a>
											         </button>
								 				</td>`;
							}
						         return_BTNs += `</tr>
						                     </tbody>
						                     </table>`;
		                     
		                	 return return_BTNs;
		                }
		            },
		            {data:'SHIPPING_DATE', visible:false, searchable: true}, //5
					{data:'DELIVERED_DATE', visible:false, searchable: true},//6
					{data:'DATE_CREATED', visible:false, searchable: true},//7
					{data:'DELIVERY_DATE', visible:false, searchable: true},//8
					{data:'Zid', visible:false, searchable: true},
					{data:'SHIPPING_TYPE', visible:false, searchable: true},
					{data:'ORDER_TYPE_ID', visible:false, searchable: true},
					//{data:'PRODUCT_IDS', visible:false, searchable: true},
					
		        ],
		       // fixedHeader: true, // تثبيت الهيدر أثناء التمرير
			    columnDefs: [
			        { targets: '_all', className: 'text-center' }
			    ],
			    initComplete: function () {
					console.log("INIT DONE");
			        this.api().columns.adjust().draw(); // ضبط الأعمدة بعد تحميل البيانات
					
			        
			    },
	    
		        buttons: [
			        {
			            text: 'تحديث البيانات',
			            action: function ( e, dt, node, config ) {
			                $('.table-overlay1').fadeIn(200); 
			                updateOrders();
			            }
			        }
			    ],
		        
		        pageLength: pageLength,
		       
		                    
		}).on('draw', function() {
			const oid = localStorage.getItem('viewOrderNum');
			const row = $('#row-' + oid);

			if (row.length) {
				row.find('#btnOview').trigger('click');
				localStorage.removeItem('viewOrderNum');
			}
			let val = $("#SelectUserDelivery").val();
			let count = 0;
			let intervalId = setInterval(() => {
				if (Go == 'OrdersCustody' && $('.OLCBtn .btnTL')) {
				if(val){
				    $('.OLCBtn .btnTL').show();
					$('.OLCBtn .btnBL').show();
				}else{
				    $('.OLCBtn .btnTL').hide();
					$('.OLCBtn .btnBL').hide();
				}
			    clearInterval(intervalId); // إيقاف التكرار بعد  ٣ مرات
			  }
			}, 1000); // كل ثانية
			
			$('.btnShipConfirmation').each(function () {
		        const btn = $(this);
		        const oId = btn.data('id');
		        const remaining = getCookie(`ShipConfirmRecall_${oId}`);
		        if (remaining > 0) {
		            startCountdown(btn, remaining);
		        }
		    });
			
			//$('.table-overlay1').fadeOut(200); 
			// هذه سيتم تشغيله بعد اكتمال عرض البيانات
			$('#hid').show();
			$('#ovarlay').hide();
			$('#contentbody').removeClass('overlay-wrapper');
			$('#contentbody').addClass('wrapper');
			if(Go === "OrdersCustody"){
				$('.OLCBtn .btnTL').hide();
				$('.OLCBtn .btnBL').hide();
				if(!$('#Olhbtn li a.active.show').length){
					$('#OLAll').addClass('active show');
				}
			}
			setTimeout(() => {
		        var selectedIcon = $('#selectedIcon'); // جلب الأيقونة الحالية
		        let filterCookie = getFilterDataFromCookie();
		        const [startDate, endDate] = filterCookie['dateRange']['id'].split(' - ');
		        let columnIndex = filterCookie['dateType']['id'] ?? 5;
				
				if (columnIndex == 7) {
			        selectedIcon.attr('class','fas fa-calendar-plus');      // البحث في العمود الأول عند اختيار "تاريخ الإنشاء"
				}else if (columnIndex == 8) {
			    	selectedIcon.attr('class','fas fa-truck-fast');         // البحث في العمود الأول عند اختيار "تاريخ الشحن"
				}else if (columnIndex == 5) {
			        selectedIcon.attr('class','fas fa-right-from-bracket'); // البحث في العمود الأول عند اختيار "تاريخ الخروج"
				}else if (columnIndex == 6) {
			        selectedIcon.attr('class','fas fa-handshake');          // البحث في العمود الأول عند اختيار "تاريخ التسليم"
				}
				$('#daterange').val(filterCookie['dateRange']['id']);
				$('.clear-daterange').show();
				if(startDate && columnIndex != null){
					$.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
					    var cellNode = table.cell(dataIndex, columnIndex).node();
					    var dateText = $(cellNode).text().trim(); // جلب النص مباشرة من الخلية
					    console.log(1,endDate);
					    // التحقق من صلاحية التاريخ وتصفية البيانات في النطاق المحدد
					    if (dateText !== "غير متاح" && dateText !== "1970/01/01" && moment(dateText, 'YYYY/MM/DD').isValid()) {
					        var rowDate = moment(dateText, 'YYYY/MM/DD');
					        return rowDate.isBetween(startDate, endDate, null, '[]'); // يشمل الحدين
					    }
					    return false; // استبعاد الصف إذا لم يكن التاريخ صالحًا
					});
				}
				
			}, 500); // كل ثاني
			//custodyTotal(orderslist);
			if(odata.length){
				//odata = odata
			}
		});
		
		/*=========================================================
		 البحث فى اعمدة جدول اوامر البيع
		=========================================================*/
		
	    // **البحث داخل كل عمود عند الكتابة في الحقل**
		$('#tbl thead tr:eq(1) th .column-search, #tbl thead tr:eq(1) th .select-search').on('keyup change', function () {
		    clearTimeout(searchTimeout);
		    let inputField = $(this);
		    let columnIndex = inputField.closest('th').index();
			
		    let isSelect = inputField.is('select');
			
		    // إظهار التأثير على tbody
		    $('.table-overlay1').fadeIn(200); 
		
		    searchTimeout = setTimeout(function () {
		        let searchValue = inputField.val().trim();
		        
		        table.column(columnIndex)
		            .search(searchValue ? `(?=.*${searchValue})` : '', true, false)
		            .draw();
		
		        // إخفاء التأثير بعد انتهاء البحث
		        $('.table-overlay1').fadeOut(200);
		    }, isSelect ? 0 : 300); // تأخير البحث في input، وتنفيذ فوري في select
		});
	    // عند اختيار نطاق تاريخ معين
		$('#daterange').on('apply.daterangepicker', function (ev, picker) {
		    var columnIndex  = 7; // تأكد من اختيار العمود الصحيح حسب الحاجة
		    var columnName   = 'تاريخ الانشاء';
		    var selectedIcon = $('#selectedIcon').attr('class'); // جلب الأيقونة الحالية
		    
		    var startDate = picker.startDate.format('YYYY/MM/DD',true);
		    var endDate   = picker.endDate.format('YYYY/MM/DD',true);
		    
		    if (selectedIcon.includes('fa-calendar-plus')) {
		        columnIndex = 7; // البحث في العمود الأول عند اختيار "تاريخ الإنشاء"
		        columnName = 'تاريخ الانشاء';
			}else if (selectedIcon.includes('fa-truck-fast')) {
		        columnIndex = 8; // البحث في العمود الأول عند اختيار "تاريخ الشحن"
		    	columnName ='تاريخ الشحن';
			}else if (selectedIcon.includes('fa-right-from-bracket')) {
		        columnIndex = 5; // البحث في العمود الأول عند اختيار "تاريخ الخروج"
		        columnName = 'تاريخ الخروج';
			}else if (selectedIcon.includes('fa-handshake')) {
		        columnIndex = 6; // البحث في العمود الأول عند اختيار "تاريخ التسليم"
		        columnName = 'تاريخ التسليم';
			}
			
		    $.fn.dataTable.ext.search = []; // إعادة تعيين أي فلترة سابقة
		
		    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
			    var cellNode = table.cell(dataIndex, columnIndex).node();
			    var dateText = $(cellNode).text().trim(); // جلب النص مباشرة من الخلية
			    
			    // التحقق من صلاحية التاريخ وتصفية البيانات في النطاق المحدد
			    if (dateText !== "غير متاح" && dateText !== "1970/01/01" && moment(dateText, 'YYYY/MM/DD').isValid()) {
			        var rowDate = moment(dateText, 'YYYY/MM/DD');
			        return rowDate.isBetween(startDate, endDate, null, '[]'); // يشمل الحدين
			    }
			
			    return false; // استبعاد الصف إذا لم يكن التاريخ صالحًا
			});
			
			filterCookie = getFilterDataFromCookie();
		    filterCookie['dateType']['id'] = columnIndex;
		    filterCookie['dateType']['val'] = columnName;
		    filterCookie['dateRange']['id'] = ''+startDate+' - '+endDate+'';
			updateFilterDataInCookie(filterCookie);
			filterOrderlist(false);
			// إعادة رسم الجدول لتطبيق الفلترة
		    table.draw();
		});
		
			    
		// عند مسح التاريخ، يتم إلغاء الفلترة
	    $('.clear-daterange').on('click', function () {
		    $.fn.dataTable.ext.search = []; // إزالة جميع الفلاتر المخصصة
		    filterCookie = getFilterDataFromCookie();
		    filterCookie['dateType']['id'] = '';
		    filterCookie['dateType']['val'] = '';
		    filterCookie['dateRange']['id'] = '';
			updateFilterDataInCookie(filterCookie);
			filterOrderlist(false);
		    
		    table.draw(); // إعادة رسم الجدول لإظهار جميع البيانات
		});
	    
		$('#SelectUserDelivery').select2({
			placeholder:'اختار المندوب',
			allowClear: true,
			language: 'ar',
			selectionCssClass: 'form-control',	
			dir:"rtl",
		});
		
		if ($("#SelectUserDelivery").length) {
			shipmentUser = sendRequestGET(`${urlApi}?CONTROL=UserListForType&utype=delivery`);
			let options = '<option value="">عرض الكل</option>';
			for (let key in shipmentUser) {
				options += `<option value="${shipmentUser[key]['ID']}">${shipmentUser[key]['NAME']}</option>`;
			}
			$("#SelectUserDelivery").html(options);
		}
		$("#SelectUserDelivery").on('change',function(){
			setTimeout(function() {
				updateOrders();
			}, 500);
			
		})
		btnhead();
		
		filterOrderlist(); 
	}else{
		$('.progress-container').hide();
		$('#progressBar').hide();
		$('#tbl #searchStatus').html(`
		    <option value="">الكل</option>
			<option data-type="2" value="11">بيع مباشر</option>
			<option data-type="2" value="1">قيد التأكيد</option>
			<option data-type="2" value="2">مؤجل</option>
		    <option data-type="2" value="3">غير متوفر</option>
			<option data-type="2" value="4">العميل لا يستجيب</option>
			<option data-type="2" value="5">ملغى</option>
			<option data-type="2" value="7">قيد التجهيز</option>
			<option data-type="2" value="8">تم التجهيز</option>
			
			<option data-type="1" value="2">جارى الشحن</option>
			<option data-type="1" value="3">تم التسليم</option>
			<option data-type="1" value="4">تسليم جزئى</option>
			<option data-type="1" value="5">مرفوض</option>
		`);
		//$('.table-overlay1').fadeOut(50);
		table = $('#tbl').DataTable({
				scrollX: true, // تفعيل التمرير الأفقي لتجنب ضغط الجدول
			    autoWidth: true, // تعطيل التحكم التلقائي في عرض الأعمدة
				orderCellsTop: true, // يضمن أن الترتيب يعمل فقط على الصف العلوي
	    		ordering: true, // يسمح بالترتيب ولكن فقط على الهيدر الأول
		        processing: true,
		        serverSide: true,
		        ajax: {
		        	type:'post',
		        	url: urlvo,
					data: function (d){
							d.CONTROL = Go;
							d.ex = Exists;
							d.ptype = ptype;
							d.st = ost;
							d.url = wls;
							d.sto_available = store_available;
							d.did = $('#SelectUserDelivery').val();
							d.columnFilters = getColumnFilters();
				            // فلترة التاريخ
				            d.dateFilter = getDateFilter();
				            // فلترة المندوب
				            d.deliveryUser = vieworderlistPram['did'];
							d.cofi = vieworderlistPram['cofi']
							console.log("🔥 request sent",d);
					},
					dataSrc: function (response) {

				        // هنا تمسك الداتا
				        let cuTotalValue = response.cuTotal;
				        custodyTotal(response);
				
				        // مهم جدًا ترجع الداتا للجدول
				        return response.data;
				    }
		        },
		        rowId : function(row){
					return 'row-' + row.oid;
				},
		        columns: [
		            { 
		                data: 'oid', 
		                type: 'num',
		                //title: 'رقم الطلب',
		                className: 'text-center',
	            		//width: '', 
		                render: (data, type, row) => {
		                    
		                    let badgcolor = " badge-light ";
		                    badgeColor = row['custstat'] === "FREEZE" ? "badge-red50" :
		                                    row['ORDER_TYPE_ID'] == 1 ? "badge-blue10" :
		                                    row['EXCHANGE'] ? "badge-warning" : "";
		
		                    let orderEx = row['EXCHANGE'] ? 
		                        `<span class="text-danger badge badge-warning" style="position: absolute;top: 16px;right: -5px;">
		                        استبدال للأمر: ${row['EXCHANGE_SUPID']}</span>` : "";
		
		                    return `
		                    <div class="badge ${badgcolor} text-left"  style="font-size: 1.2rem;width:100%;height:auto">
					            <div class="d-flex justify-content-between m-2" style ="position: relative;">
			                        <div class="text-primary"> ${row['subOid']}</div>
		                        	${orderEx}
		                        </div>
		                        <hr class="mb-1 mt-1">
					            <div class="d-flex justify-content-between m-2">
		                        	<div class="badge"><i class="fa-regular fa-user"></i> ${row['BUTTONS'][1]}</div>
		                        </div>
					            <hr class="mb-1 mt-1">
					            <div class="d-flex justify-content-between m-2">
		                        	<div class="badge"><i class="far fa-calendar-plus"></i> ${row['DATE_CREATED']}</div>
		                        </div>
		                    </div>`;
		                }
		            },
		            { 
		                data: 'STA_NAME', 
		               // title: 'حالة الطلب',
		                className: 'text-center',
		                render: (data, type, row) => {
		                	let badgcolor = " badge-light ";
		                	badgeColor = row['custstat'] === "FREEZE" ? "badge-red50" :
		                                    row['ORDER_TYPE_ID'] == 1 ? "badge-blue10" :
		                                    row['EXCHANGE'] ? "badge-warning" : "";
		                                    
		                    return `
		                    <div class="badge ${badgcolor} text-left"  style="font-size: 1.2rem;width:100%;height:auto">
					            <div class="d-flex justify-content-between m-2" style ="position: relative;">
		                        	<div class="badge badge-${row["COLOR_CLASS"]} w-100"><i class="fa-regular fa-clock"></i> ${data}</div>
		                        </div>
		                        <hr class="mb-1 mt-1">
		                        <div class="d-flex justify-content-between m-2" style ="position: relative;">
		                        	<div class="badge badge-light w-100"><i class="fa-solid fa-display"></i> ${row['EXISTS_NAME']}</div>
		                        </div>
		                        <hr class="mb-1 mt-1">
		                        <div class="d-flex justify-content-between m-2" style ="position: relative;">
		                        	<div class="badge badge-light w-100"><i class="fa-solid fa-gears"></i> ${row['SUCCESS_STAT']}</div>
		                    	</div>
		                    </div>`;
		                }
		            },
		            { 
		                data: 'CLIENT_NAME', 
		                //title: 'معلومات العميل',
		                className: 'text-left',
		                render: (data, type, row) => {
		                	let badgcolor = " badge-light ";
		                	badgeColor = row['custstat'] === "FREEZE" ? "badge-red50" :
		                                    row['ORDER_TYPE_ID'] == 1 ? "badge-blue10" :
		                                    row['EXCHANGE'] ? "badge-warning" : "";
		                                    
		                    return `
		                    <div class="badge ${badgcolor} text-left"  style="font-size: 1.2rem;width:100%;height:auto">
					            <div class="d-flex justify-content-between m-2" style ="position: relative;">
									<div class="badge"><i class="fas fa-user"></i> ${data}</div>
		                            <div class="badge"><i class="fas fa-mobile-alt"></i><a href="tel:${row['TELEPHONE']}"> ${row['TELEPHONE']}</a></div>
		                        </div>
		                        <hr class="mb-1 mt-1">
		                        <div class="d-flex justify-content-between m-2" style ="position: relative;">
		                        	<div class="badge"><i class="fas fa-shopping-cart"></i> المنتجات ${row['PRODUCTS_COUNT']} | الكمية ${row['PRO_QTY']} | التكلفة ${row['PRICE']}</div>
		                        </div>
		                        <hr class="mb-1 mt-1">
		                        <div class="d-flex justify-content-between m-2">
		                            <div class="badge"><i class="fas fa-truck-fast"></i> ${row['ZName']}</div>
		                            <div class="badge"><i class="fa fa-map-location-dot"></i><span class="text-primary"> ${row["GOV_NAME"]} - ${row["CI_NAME"]} </span></div>
		                        </div>
		                    </div>`;
		                }
		            },
		            { 
		                data: 'DELIVERY_DATE', 
		                //title: 'تواريخ الشحن والتسليم',
		                className: 'text-center',
		                render: (data, type, row) => {
		                	let badgcolor = " badge-light ";
		                	badgeColor = row['custstat'] === "FREEZE" ? "badge-red50" :
		                                    row['ORDER_TYPE_ID'] == 1 ? "badge-blue10" :
		                                    row['EXCHANGE'] ? "badge-warning" : "";
		                                    
		                    return `
		                    <div class="badge ${badgcolor} text-left"  style="font-size: 1.2rem;width:100%;height:auto">
					            <div class="d-flex justify-content-between m-2" style ="position: relative;">
		                        	<div class="badge badge-light w-100"><i class="fa-solid fa-truck-fast"></i> ${data}</div>
		                        </div>
		                        <hr class="mb-1 mt-1">
		                        <div class="d-flex justify-content-between m-2" style ="position: relative;">
		                        	<div class="badge badge-light w-100"><i class="fa-solid fa-right-from-bracket"></i> ${row['SHIPPING_DATE']}</div>
		                        </div>
		                        <hr class="mb-1 mt-1">
		                        <div class="d-flex justify-content-between m-2" style ="position: relative;">
		                        	<div class="badge badge-light w-100"><i class="fa-solid fa-handshake"></i> ${row['DELIVERED_DATE']}</div>
		                        </div>
		                    </div>`;
		                }
		            },
		            {
		                data : 'BUTTONS',
		                //title: 'تحرير',
		                orderable: false,
		                className: 'text-center text-nowrap',
		                render: (data, type, row) => {
		                		/*return [
									'oid'       => $use == true ? $orderData["ORDER_ID"] : "",
									'oclass'    => $use == true ? "text-primary border" : "text-secondary border",
									'available' => $use == true ? "" : "disabled",
									'elid'      => 'btnOview',
									'icon'      => 'fa-solid fa-eye',
									'msgnotification' => ""
								];*/
							let adminsupport = "",
								msgnotification = "",
								clasRowSpan = "",
								return_BTNs = "";
					if((row['OSid'] > 0 && row['OSStatu'] == 0) || row['OSMsgc'] > 0){
								if(row['OSMsgc']>0){
									msgnotification = row['OSMsgc'];
								}else {
									msgnotification = '<i class="fa-regular fa-envelope"></i>';
								}
							}
					        
							if(ptype == 1){ 
					            adminsupport =`<td class="bg-white " colspan="2" rowspan="2">
							                            <a id="AdminSupport" data-id="${row['oid']}" class="btn btn-app-tools btn-light badge-ya text-5 bg-gradient mt-2" style="margin-left:-5px;margin-right:-5px;width:50px;">
							                                <span class="badge bg-danger">${msgnotification}</span>
							                                <i class="fas fa-headset"></i>الدعم
							                            </a>
							                    	</td>`;
					    	}
							if( row['BUTTONS'][0][2] == "" && row['BUTTONS'][0][3] == "" && (row['BUTTONS'][0][1] != "" || row['BUTTONS'][0][0] == "" ) ||
					        	row['BUTTONS'][0][0] == "" && row['BUTTONS'][0][1] == "" && (row['BUTTONS'][0][3] != "" || row['BUTTONS'][0][2] == "" ) 
					        ){
					        	clasRowSpan = 'rowspan="2"';
					        }
					        return_BTNs = `
					        	<table class="table OLCBtn table-borderless" >
					        		<tbody class="badge card-body mt-2 ml-2 mt-r tableAllBtn" style="font-size:1.5rem;">
					                    <tr class="p-1">
						                    ${adminsupport}
						                    <td class="p-1 badge-light btnTR" ${clasRowSpan}>
							                    <button id="${row['BUTTONS'][0][0]['elid']}" data-id="${row['BUTTONS'][0][0]['oid']}" class="btn btn-app-tools btn-xs ${row['BUTTONS'][0][0]['elid']} ovbtn w-100 h-100 ${row['BUTTONS'][0][0]['oclass']}">
											            <i class="${row['BUTTONS'][0][0]['icon']}"></i>
											            <a style="font-size:14px; font-weight: bold;">${row['BUTTONS'][0][0]['name']}</a>
											    </button>
							                </td>`;
					        if(row['BUTTONS'][0][1] != ""){
						        return_BTNs += `<td class="p-1 badge-light btnTL " ${clasRowSpan}>
						        					<button id="${row['BUTTONS'][0][1]['elid']}" data-id="${row['BUTTONS'][0][1]['oid']}" class="btn btn-app-tools btn-xs ${row['BUTTONS'][0][1]['elid']} ovbtn w-100 h-100 ${row['BUTTONS'][0][1]['oclass']}">
											            <i class="${row['BUTTONS'][0][1]['icon']}"></i>
											            <a style="font-size:14px; font-weight: bold;">${row['BUTTONS'][0][1]['name']}</a>
											        </button>
						        				</td>`;
					        }
							return_BTNs += `</tr><tr class="p-1">`;
					        if(row['BUTTONS'][0][2] != ""){
						    	return_BTNs += `<td class="p-1 badge-light btnBR " ${clasRowSpan}>
						    						<button id="${row['BUTTONS'][0][2]['elid']}" data-id="${row['BUTTONS'][0][2]['oid']}" class="btn btn-app-tools btn-xs ${row['BUTTONS'][0][2]['elid']} ovbtn w-100 h-100 ${row['BUTTONS'][0][2]['oclass']}">
											         	<span class="badge bg-danger spantimer">${adminsupport != "" ? "" : msgnotification}</span>
											            <i class="${row['BUTTONS'][0][2]['icon']}"></i>
											            <a style="font-size:14px; font-weight: bold;">${row['BUTTONS'][0][2]['name']}</a>
											         </button>
						    					</td>`;
					        }
					        if(row['BUTTONS'][0][3] != ""){
								 return_BTNs += `<td class="p-1 badge-light btnBL" ${clasRowSpan}>
								 					<button id="${row['BUTTONS'][0][3]['elid']}" data-id="${row['BUTTONS'][0][3]['oid']}" class="btn btn-app-tools btn-xs ${row['BUTTONS'][0][3]['elid']} ovbtn w-100 h-100 ${row['BUTTONS'][0][3]['oclass']}">
											            <span class="badge bg-danger spantimer"></span>
											            <i class="${row['BUTTONS'][0][3]['icon']}"></i>
											            <a style="font-size:14px; font-weight: bold;">${row['BUTTONS'][0][3]['name']}</a>
											         </button>
								 				</td>`;
							}
						         return_BTNs += `</tr>
						                     </tbody>
						                     </table>`;
		                     
		                	 return return_BTNs;
		                }
		            },
		            {data:'SHIPPING_DATE', visible:false, searchable: true}, //5
					{data:'DELIVERED_DATE', visible:false, searchable: true},//6
					{data:'DATE_CREATED', visible:false, searchable: true},//7
					{data:'DELIVERY_DATE', visible:false, searchable: true},//8
					{data:'Zid', visible:false, searchable: true},
					{data:'SHIPPING_TYPE', visible:false, searchable: true},
					{data:'ORDER_TYPE_ID', visible:false, searchable: true},
					//{data:'PRODUCT_IDS', visible:false, searchable: true},
					
		        ],
		       // fixedHeader: true, // تثبيت الهيدر أثناء التمرير
			    columnDefs: [
			        { targets: '_all', className: 'text-center' }
			    ],
			    initComplete: function () {
			        this.api().columns.adjust(); // ضبط الأعمدة بعد تحميل البيانات
			    },
	    
		        buttons: [
			        {
			            text: 'تحديث البيانات',
			            action: function ( e, dt, node, config ) {
			                $('.table-overlay1').fadeIn(200); 
			                //store_available = 0;
			                updateOrders();
			            }
			        },
			        {
			        	text: 'متوفر بالمخزن',
			            action: function ( e, dt, node, config ) {
			                $('.table-overlay1').fadeIn(200); 
			                store_available = 1;
			                dataTableReload();
			            }
			        },
			        {
			        	text: 'عرض الكل',
			            action: function ( e, dt, node, config ) {
			                $('.table-overlay1').fadeIn(200); 
			                store_available = 0;
			                dataTableReload();
			            }
			        }
			        
			    ],
			    createdRow: function (row, data, dataIndex) {
						console.log(data)
			        if (data.STORE_IS_AVAILABLE == 1) {
			            $(row).addClass('badge-green10');
			        }
			
			    },
		        
		        pageLength: pageLength,
		        order: [[0, 'desc']],          // ترتيب افتراضي
		                    
		}).on('draw', function() {
			const oid = localStorage.getItem('viewOrderNum');
			const row = $('#row-' + oid);

			// استخدم jQuery لإخفاء الزر مباشرة
			$('.dt-buttons .btn:contains("عرض الكل")').hide();
			$('.dt-buttons .btn:contains("متوفر بالمخزن")').hide();
			//table.button(2).hide();
			
			// إظهارها عند تحقق الشرط
			if (Exists >0 && Exists<6 && ptype == 1) {
				$('.dt-buttons .btn:contains("عرض الكل")').show();
				$('.dt-buttons .btn:contains("متوفر بالمخزن")').show();
			}
			if (row.length) {
				row.find('#btnOview').trigger('click');
				localStorage.removeItem('viewOrderNum');
			}
			
			let ordersList = table.rows().data().toArray();
			
			odata = convertOrdersListToOData(ordersList);
			
			$('.table-overlay1').fadeOut(50);
			let val = $("#SelectUserDelivery").val();
			let count = 0;
			
			let intervalId = setInterval(() => {
				if (Go == 'OrdersCustody' && $('.OLCBtn .btnTL')) {
					if(val){
						$('.OLCBtn .btnTL').show();
						$('.OLCBtn .btnBL').show();
					}else{
						$('.OLCBtn .btnTL').hide();
						$('.OLCBtn .btnBL').hide();
					}
					clearInterval(intervalId); // إيقاف التكرار بعد  ٣ مرات
				 }
			}, 100); // كل ثانية
			
			$('.btnShipConfirmation').each(function () {
		        const btn = $(this);
		        const oId = btn.data('id');
		        const remaining = getCookie(`ShipConfirmRecall_${oId}`);
		        if (remaining > 0) {
		            startCountdown(btn, remaining);
		        }
		    });
			
			//$('.table-overlay1').fadeOut(200); 
			// هذه سيتم تشغيله بعد اكتمال عرض البيانات
			$('#hid').show();
			$('#ovarlay').hide();
			$('#contentbody').removeClass('overlay-wrapper');
			$('#contentbody').addClass('wrapper');
			if(Go === "OrdersCustody"){
				$('.OLCBtn .btnTL').hide();
				$('.OLCBtn .btnBL').hide();
				if(!$('#Olhbtn li a.active.show').length){
					$('#OLAll').addClass('active show');
				}
			}
			if(Go === "ShippingConfirmation"){
				if(!$('#Olhbtn2 li a.active.show').length){
					$('#UnderConfirmation').addClass('active show');
				}
			}
			
		});
		
		function getColumnFilters() {
		    let filters = {};
		
		    $('#tbl thead tr:eq(1) th').each(function (index) {
		        let input = $(this).find('.column-search, .select-search');
		        if (input.length && input.val()) {
		            filters[index] = input.val().trim();
		        }
		    });
		
		    return filters;
		}
		function getDateFilter() {
			
	    	let picker = $('#daterange').data('daterangepicker');
		    if (!picker) return null;
			
		    let selectedIcon = $('#selectedIcon').attr('class');
		    let columnIndex = "DATE_CREATED";
			
		    if (selectedIcon.includes('fa-truck-fast')) columnIndex = 'DELIVERY_DATE';
		    if (selectedIcon.includes('fa-right-from-bracket')) columnIndex = 'SHIPPING_DATE';
		    if (selectedIcon.includes('fa-handshake')) columnIndex = 'DELIVERED_DATE';
			filterCookie = getFilterDataFromCookie();
		    const [startDate, endDate] = filterCookie['dateRange']['id'].split(' - ');
		    const dateType = filterCookie['dateType']['id'];
		    if(startDate!=null && endDate != null && dateType != null){
			    picker.setStartDate(moment(startDate, 'YYYY/MM/DD'));
		        picker.setEndDate(moment(endDate, 'YYYY/MM/DD'));
		        $('#daterange').val(startDate +' - '+ endDate);
        		filterOrderlist(false);
        		$('.clear-daterange').show();
		    }
		    // ❌ لو مفيش اختيار فعلي
		    if (!picker.startDate || !picker.endDate || !$('#daterange').val()) {
		        return null; // مهم جدًا: متبعتش فلتر
		    }
		
		    return {
		        column: columnIndex,
		        start: picker.startDate.format('YYYY-MM-DD'),
		        end: picker.endDate.format('YYYY-MM-DD')
		    };
		}
		
		// **البحث داخل كل عمود عند الكتابة في الحقل**
		$('#tbl thead').on('keyup change', '.column-search, .select-search', function () {
		
		    clearTimeout(searchTimeout);
		
		    let columnIndex = $(this).closest('th').index();
		    let value = $(this).val();
		
		    searchTimeout = setTimeout(() => {
				if($(this).is('select')){
		    		let value = [
					    $(this).val(),
					    $(this).find(':selected').data('type')
					];
					table.column(columnIndex)
		             .search(value || '', false, false)
		             .draw(false); // يحافظ على الصفحة
				}else{
		        	table.column(columnIndex)
		             .search(value || '', false, false)
		             .draw(false); // يحافظ على الصفحة
				}
		    }, $(this).is('select') ? 0 : 1500);
		
		});	    
		function clearDateRange() {
		    $('.clear-daterange').click();
		};
		$('#daterange').on('apply.daterangepicker', function (ev, picker) {
			let startDate = picker.startDate.format('YYYY-MM-DD')?? '';
			let endDate   = picker.endDate.format('YYYY-MM-DD')?? '';
			let selectedIcon = $('#selectedIcon').attr('class'); // جلب الأيقونة الحالية
			let columnIndex = "DATE_CREATED";
			let columnName  = "تاريخ الانشاء";
		    if (selectedIcon.includes('fa-truck-fast')){
		    	columnIndex = 'DELIVERY_DATE';
		    	columnName  = "تاريخ الشحن";
		    }
		    if (selectedIcon.includes('fa-right-from-bracket')){
		    	columnIndex = 'SHIPPING_DATE';
		    	columnName  = 'تاريخ الخروج'
		    } 
		    if (selectedIcon.includes('fa-handshake')){
		    	columnIndex = 'DELIVERED_DATE';
		    	columnName  = 'تاريخ التسليم'
			}
			
			filterCookie = getFilterDataFromCookie();
			filterCookie['dateType']['id'] = columnIndex;
			filterCookie['dateType']['val'] = columnName;
			filterCookie['dateRange']['id'] =  startDate +' - '+ endDate;
			updateFilterDataInCookie(filterCookie);
			
			//let data = getDateFilter();
			filterOrderlist(false);
			setTimeout(function (){
				$('#dateRange .badge-danger').each(function () {
					this.setAttribute('onclick',"$('.clear-daterange').click();");
				    
				});
			},400);
			setTimeout(function(){
				updateOrders();
			},500);
		});
		

		
		
		// عند مسح التاريخ، يتم إلغاء الفلترة
		$('.clear-daterange').on('click', function () {
		    $.fn.dataTable.ext.search = []; // إزالة جميع الفلاتر المخصصة
		    filterCookie = getFilterDataFromCookie();
		    filterCookie['dateType']['id'] = '';
		    filterCookie['dateType']['val'] = '';
		    filterCookie['dateRange']['id'] = '';
			updateFilterDataInCookie(filterCookie);
			filterOrderlist(false);
		    setTimeout(function(){
				updateOrders();
			},500);
		
		});
		
	    $('#SelectUserDelivery').on('change', function () {
		
		    table.column(2) // غير الرقم حسب عمود المندوب
		         .search(this.value || '', false, false)
		         .draw(false);
		
		});
		
		
		$('#SelectUserDelivery').select2({
			placeholder:'اختار المندوب',
			allowClear: true,
			language: 'ar',
			selectionCssClass: 'form-control',	
			dir:"rtl",
		});
		
		if ($("#SelectUserDelivery").length) {
			shipmentUser = sendRequestGET(`${urlApi}?CONTROL=UserListForType&utype=delivery`);
			let options = '<option value="">عرض الكل</option>';
			for (let key in shipmentUser) {
				options += `<option value="${shipmentUser[key]['ID']}">${shipmentUser[key]['NAME']}</option>`;
			}
			$("#SelectUserDelivery").html(options);
		}
		try {
			btnhead();
			filterOrderlist(); 
		} catch (error) {
			
		}
		
	}
	$('#tbl').parent().css('position', 'relative') // لضمان ظهور الـ overlay فوق الجدول
	$('#tbl tbody').before('<div class="table-overlay" style="display: none;"><i class="fa fa-spinner fa-spin spinner"></i></div>');
	

	
});//end document ready