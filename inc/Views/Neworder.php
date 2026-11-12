<?php include  $_SERVER["DOCUMENT_ROOT"].'/inc/Forms/addressOptionForm.php'; ?>
<!-- products list Template -->
<div id="productTemplate" style="display:none;">
	<div id="protemp">
		teeffyyttggg
	</div>
</div>
<!-- Main content -->
<section class="content">
    <!-- sreach form -->
    <div id="sreachform">
        <form id="fchcil" onsubmit="return false" method="POST">
            <div class="">
                <div class="col-md-10 offset-md-1">
                    <div class="form-group">
                        <div class="input-group input-group-lg">
                        	<input id="clisreach" type="text" class="form-control form-control-lg sreach"  inputmode="numeric" pattern="\d*" placeholder="رقم هاتف العميل"  value="" required>
                            <!--<input id="clisreach" type="number" class="form-control form-control-lg sreach" value="" placeholder="من فضلك ادخل رقم هاتف العميل" required>-->
                            <div class="input-group-append">
                                <button id="btnSearchClient" type="submit" class="btn btn-lg btn-default">
                                    <i class="fa fa-search"></i>
                                </button>
                                <button id="btnAddClient" type="submit" class="btn btn-lg btn-default " style="display: block;">
                                    <i class="fa fa-user-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        
        <div id="ClintOrdersList" style="display:none;">
		    <table id="tblClintOrders" class="col-12 tbl table table-hover table-striped٦ m-0 text-table"style="width:100%;">
		    </table>
		    
		<hr>
	    </div>
	    <div class="alert-box pt-0 pb-0" style="display:none">
	    	<div class="alertanma m-2">
		        <i class="fas fa-exclamation-triangle alert-icon"></i>
		        <span class="">تنبيه : العميل له
		        <span class='ordercount'>3</span>
		        اوامر مسجله مسبقا اضغط للعرض
		    	</span>
	    	</div>
	    </div>
        <div id="result" style="display:none">
        </div>
    </div>
<!-- add client form -->
    <div id="addClientform" style="display:none">
        <div class="card mb-5">
            <div class="card-body">
                <form onsubmit="return false" method="POST">
                    <div class='row'>
                        <div id="coCli" class='col-sm-6'>
                            <div class="form-group">
                                <label for='COM_NAME'>اسم العميل</label>
                                <input type='text' class='form-control cli req' id="cli_name" name="cliname" placeholder='ادخل اسم العميل'>
                            </div>
                            <div class="form-group">
                                <label for='comEmail'>بريد الكترونى</label>
                                <input type='email' class='form-control email cli' id='cli_email' name="cliemail" placeholder='أدخل البريد الإلكترونى'>
                            </div>
                        
                            <div id="cliTel"></div>
                        </div>
                        <div id="coAd" class='col-sm-6'></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div id="editClientData" style="display:none">
    </div>
    <div id="addorderform" style="display:none">
		<div class="overlay-wrapper"  >
				<div id="orderOv"class="overlay orderOv" style="display:none">
					<i class="fas fa-3x fa-sync-alt fa-spin"></i>
					<div class="text-bold pt-2">جارى حفظ امر البيع....</div>
				</div>
               <div class="invoice p-3 mb-3">
              <!-- title row -->
              <div class="row">
                <div class="col-12">
                  <h4>
                    <i class="fas fa-globe"></i> <span id='orderHeadr'>امر بيع جديد.</span>
                    <small id="dateNaw" class="float-right"></small>
                  </h4>
                </div>
                <!-- /.col -->
              </div>
              <!-- info row -->
              <div class="row invoice-info">
                <!-- <div class="col-sm-6 invoice-col">
                  <b>رقم امر البيع:</b> 4F3S8J<br>
                </div>
                <!-- /.col -->
                <div class="col-sm-12 invoice-col">
                    <div class="dropdown-divider"></div>
					<div class="row">
                        <div class="col-6"><b>بيانات العميل</b></div>
                        <div class="col-6 text-right">
                            <button id="#editclientfromOrder" class="btn btn-app editcliOrder" style="font-size: 9px; min-width: 30px; height: 30px !important;">
                                <i class="fa fa-edit "></i>
                            </button>
                        </div>
                    </div>
                  <address class="text-info">
                    <div id="clientStatuse" class="">
                         
                    </div>
                  </address>
                  <div class="dropdown-divider"></div>
                    <div class="row">
			            <div id="shippingdate" class="col-9 text-primary">
                        </div>
                        <div class="col-3 text-right">
                            <button id="edit_shippingDate" class="btn btn-app edit_shippingDate" style="font-size: 9px; min-width: 30px; height: 30px !important;">
                                <i class="fa fa-edit "></i>
                            </button>
                        </div>
                    </div>
                  </address>
                  <div class="dropdown-divider"></div>
				  <div class="row">
                        <div class="col-6"><b>ملاحظات</b></div>
                        <div class="col-6 text-right">
                            <button id="del_clientComment" class="btn btn-app del_clientComment" style="font-size: 9px; min-width: 30px; height: 30px !important;">
                                <i class="fa fa-close "></i>
                            </button>
							<button id="edit_clientComment" class="btn btn-app edit_clientComment" style="font-size: 9px; min-width: 30px; height: 30px !important;">
                                <i class="fa fa-edit"></i>
                            </button>
							
                        </div>
                  </div>
                  <address class="text-info">
                        <div id="clientComment" class="">
                          <span class="text-secondary">لايوجد ملاحظات حتى الان ....</span>
                        </div>
                  </address>
                 </div>
                <!-- /.col -->
              </div>
              <!-- /.row -->
              <!-- Table row -->
              <div class="row">
                <div class=" table-responsive-sm col-sm-12">
                  <table class="table table-hover table-bordered table-striped">
                    <thead class="bg-primary border">
                    <tr class ="text-nowarp">
                      <th class="col-sm-1 text-center">كود</th>
                      <th class="">الصنف</th>
                      <th class="col-sm-1 text-center">الكمية</th>
                      <th class="col-sm-1 text-center">السعر</th>
                      <th class="col-sm-1 text-center">الخصم</th>
                      <th class="col-sm-1 text-center">الاجمالى</th>
                      <th class="col-sm-1 text-center">تحرير</th>
                      <th class="col-sm-1 text-center">حذف</th>
                    </tr>
                    </thead>
                    <tbody id = "itemorder">
                    <tr id="addPorduct">
                      <td colspan="8" class="text-primary text-center"><label>إضافة صنف جديد</label></td>
                    </tr>
                    </tbody>
                  </table>
                </div>
                <!-- /.col -->

              </div>
              <!-- /.row -->

              <div class="row">
                <div class="col-sm-6"></div>
                <div class="col-sm-6">
                  <p class="lead">ملخص الحساب</p>
                  <div class="table-responsive">
                    <table class="table">
                      <tr>
                        <th style="width:50%">قيمة الفاتورة:</th>
                        <td id="orderprice" colspan="2" class="te">0</td>
                      </tr>
                      <tr>
                        <th>قيمة الشحن:</th>
                        <td id="shipping_rate" class="text-left"></td>
                        <td class="text-right"><button id="edit_shipping" class="btn btn-app edit_shipping" style="font-size: 9px; min-width: 30px; height: 30px !important;"><i class="fa fa-edit "></i></button></td>
                      </tr>
                      <tr>
                        <th>اجمالى:</th>
                        <td id="ordertotal" colspan="2"></td>
                      </tr>
                    </table>
                  </div>
                </div>
                <!-- /.col -->
              </div>
              <!-- /.row -->

              <!-- this row will not appear when printing -->
            <div class="row text-center no-print">
                <div class="col-12 text-center no-print">
                    <div class="order-foot mt-0 mb-5"> 

                    </div>
                </div>
            </div>
            <!-- /.invoice -->
			
        
    </div>
	</div>
	</div>
	<div id="DOL" class="card DOL" style="display: none;">
		<div class="card-body p-4">
	    	<table id="tbl" class="col-12 tbl table table-hover table-striped m-0 text-table"style="width:100%;">
	        </table>
        </div>
    </div>
	
	
</section>
<!--<script src="https://cdn.jsdelivr.net/npm/fuse.js@6.4.6/dist/fuse.min.js"></script>-->
<script>
document.addEventListener("DOMContentLoaded", () => {
    let lastClipboardContent = ""; // تخزين آخر بيانات الحافظة لمنع التكرار
    let inputField = document.getElementById("clisreach"); // حقل البحث
    let resultDiv = document.getElementById("result"); // الحاوية لعرض البيانات
	let clientData = "";
	inputField.addEventListener("paste",  async (event) => {	
		let clipboardText = await navigator.clipboard.readText();
	    clientData = await extractData(clipboardText); // هنا يجب استخدام await
		
	    if (clientData.phones.length === 0) {
	    	inputField.value ="";
	    	alert("البيانات التى ادخلتها لاتحتوى على رقم هاتف");
	    	return
	    }
	
	    lastClipboardContent = clipboardText;
	
	    Swal.fire({
	        title: "استخدام البيانات من الحافظة؟",
	        html: `<b>الاسم:</b> ${clientData.name || ""}<br>
	               <b>الدولة:</b> ${clientData.country || ""}<br>
	       		   <b>المحافظة:</b> ${clientData.governorate || ""}<br>
	               <b>المدينه:</b> ${clientData.city || ""}<br>
	               <b>العنوان:</b> ${clientData.address || "غير محدد"}<br>
	               <b>رقم الهاتف:</b> ${clientData.phones.join(" - ")}`,
	        icon: "question",
	        showCancelButton: true,
	        confirmButtonText: "نعم، استخدم البيانات",
	        cancelButtonText: "لا",
	        didOpen : ()=>{
	        	inputField.value ="";
	        }
	        
	    }).then(async (result) => {
	        if (result.isConfirmed) {
	            let foundPhone = await searchClient(clientData.phones);
				if (foundPhone) {
					console.log(foundPhone)
		            inputField.value = foundPhone || clientData.phones[0] || "";
		            inputField.dataset.clientData = JSON.stringify(clientData);
		        	
		        	inputField.blur(foundPhone);
		        	searchClientData()
				    console.log('تم العثور على الرقم:', foundPhone);
				} else {
					$('#btnAddClient').click();
				    console.log('لم يتم العثور على رقم');
				}
				
	        }else{
	        	inputField.value =""; 
	        }
	    })
	    
	});
	$(document).on("click","#btnAddClient",function (){
		if(clientData){
			$("#cli_name").val(clientData.name || "");
			$("#cli_tel").val(clientData.phones[0] || "");
			$("#addressCity").val(clientData.city || "");
			$("#cli_address").val(clientData.address || "");
		}
	});
	//جلب بيانات المدن والمحافظات لاستخدمها فى تحليل النصوص
	async function loadDatabase() {
	    return new Promise((resolve, reject) => {
	        let request = indexedDB.open("addressDB", 2);
	
	        request.onupgradeneeded = function (event) {
	            let db = event.target.result;
	
	            // التحقق من وجود الجداول (objectStores) وإنشاؤها عند الحاجة
	            if (!db.objectStoreNames.contains("countries")) {
	                let store = db.createObjectStore("countries", { keyPath: "CO_ID" });
	                store.createIndex("CO_NAME", "CO_NAME", { unique: false });
	            }
	
	            if (!db.objectStoreNames.contains("governorates")) {
	                let store = db.createObjectStore("governorates", { keyPath: "GOV_ID" });
	                store.createIndex("GOV_NAME", "GOV_NAME", { unique: false });
	            }
	
	            if (!db.objectStoreNames.contains("cities")) {
	                let store = db.createObjectStore("cities", { keyPath: "CI_ID" });
	                store.createIndex("CI_NAME", "CI_NAME", { unique: false });
	            }
	
	            console.log("📌 تم إنشاء/تحديث قاعدة البيانات في IndexedDB");
	        };
	
	        request.onsuccess = function (event) {
	            let db = event.target.result;
	            let transaction = db.transaction(["countries", "governorates", "cities"], "readonly");
	
	            let countriesStore = transaction.objectStore("countries");
	            let governoratesStore = transaction.objectStore("governorates");
	            let citiesStore = transaction.objectStore("cities");
	
	            let countriesRequest = countriesStore.getAll();
	            let governoratesRequest = governoratesStore.getAll();
	            let citiesRequest = citiesStore.getAll();
	
	            Promise.all([countriesRequest, governoratesRequest, citiesRequest].map(req => {
	                return new Promise((res, rej) => {
	                    req.onsuccess = () => res(req.result);
	                    req.onerror = () => rej("❌ خطأ أثناء استرجاع البيانات من IndexedDB");
	                });
	            }))
	            .then(([countries, governorates, cities]) => {
	                if (countries.length > 0 && governorates.length > 0 && cities.length > 0) {
	                    console.log("✅ تم تحميل البيانات من IndexedDB", { countries, governorates, cities });
	                    resolve({ countries, governorates, cities });
	                } else {
	                    console.log("⚠️ لا توجد بيانات، يتم جلبها من API...");
	                    fetch("../../inc/Api.php?CONTROL=getAddressDataUse")
	                        .then(response => response.json())
	                        .then(data => {
	                            let writeTransaction = db.transaction(["countries", "governorates", "cities"], "readwrite");
	                            let countriesStore = writeTransaction.objectStore("countries");
	                            let governoratesStore = writeTransaction.objectStore("governorates");
	                            let citiesStore = writeTransaction.objectStore("cities");
	
	                            data.countries.forEach(country => countriesStore.put(country));
	                            data.governorates.forEach(gov => governoratesStore.put(gov));
	                            data.cities.forEach(city => citiesStore.put(city));
	
	                            console.log("✅ تم حفظ البيانات في IndexedDB");
	                            resolve(data);
	                        })
	                        .catch(error => {
	                            console.error("❌ خطأ أثناء جلب البيانات من API:", error);
	                            reject(error);
	                        });
	                }
	            })
	            .catch(error => reject(error));
	        };
	
	        request.onerror = function () {
	            reject("❌ فشل الاتصال بـ IndexedDB");
	        };
	    });
	}
	//تحليل النص لاستخراج البيانات
	async function extractData(text) {
    let data = {
        name: "",
        country: "",
        governorate: "",
        city: "",
        address: "",
        phones: new Set()
    };

    let database = await loadDatabase(); // تحميل بيانات الدول، المحافظات، والمدن

    let countries = database.countries.map(item => ({ CO_ID: item.CO_ID, CO_NAME: item.CO_NAME }));
    let governors = database.governorates.map(item => ({ GOV_ID: item.GOV_ID, GOV_NAME: item.GOV_NAME, CO_ID: item.COUNTRY_ID }));
    let cities = database.cities.map(item => ({ CI_ID: item.CI_ID, CI_NAME: item.CI_NAME, GOV_ID: item.GOV_ID }));

    // ✅ تهيئة Fuse.js للبحث عن المدن والمحافظات والدول
    let fuseCity = new Fuse(cities, { keys: ["CI_NAME"], threshold: 0.2 });
    let fuseGov = new Fuse(governors, { keys: ["GOV_NAME"], threshold: 0.2 });
    let fuseCountry = new Fuse(countries, { keys: ["CO_NAME"], threshold: 0.2 });

    let lines = text.split("\n").map(line => line.trim()).filter(line => line);

    let addressParts = []; // لتخزين العنوان  
    let foundCity = null;
    let foundGovernorate = null;

    lines.forEach(line => {
        // 📌 استخراج أرقام الهواتف
        let phoneMatches = line.match(/01\d{9}/g);
        if (phoneMatches) {
            phoneMatches.forEach(phone => data.phones.add(phone));
        }

        // 📌 البحث عن المدينة باستخدام Fuse.js
        if (!foundCity) {
            let cityResult = fuseCity.search(line);
            if (cityResult.length > 0) {
                foundCity = cityResult[0].item;
                data.city = foundCity.CI_NAME;

                // البحث عن المحافظة بناءً على المدينة
                foundGovernorate = governors.find(gov => gov.GOV_ID === foundCity.GOV_ID);
                if (foundGovernorate) {
                    data.governorate = foundGovernorate.GOV_NAME;

                    // البحث عن الدولة بناءً على المحافظة
                    let foundCountry = countries.find(country => country.CO_ID === foundGovernorate.CO_ID);
                    if (foundCountry) {
                        data.country = foundCountry.CO_NAME;
                    }
                }
            }
        }

        // 📌 البحث عن المحافظة إذا لم يتم العثور على مدينة
        if (!foundGovernorate) {
            let govResult = fuseGov.search(line);
            if (govResult.length > 0) {
                foundGovernorate = govResult[0].item;
                data.governorate = foundGovernorate.GOV_NAME;

                // البحث عن الدولة بناءً على المحافظة
                let foundCountry = countries.find(country => country.CO_ID === foundGovernorate.CO_ID);
                if (foundCountry) {
                    data.country = foundCountry.CO_NAME;
                }
            }
        }

        // 📌 استخراج العنوان بناءً على كلمات دلالية
        if (line.match(/(العنوان|شارع|بجوار|محافظة|مدينة|حي|شقة|الدور|منطقة|قسم|مدخل|متفرع|امام|فوق)/)) {
            addressParts.push(line);
        }

        // 📌 استخراج الاسم عند وجود "بأسم" أو "الاسم"
        if (line.includes("بأسم") || line.includes("الاسم")) {
            data.name = line.replace(/(بأسم|الاسم)/, "").trim();
        }

        // 📌 استخراج الاسم إذا لم يتم استخراجه مسبقًا وكان السطر لا يحتوي على أرقام أو عنوان
        if (!data.name && !line.match(/\d/) && !line.match(/(العنوان|شارع|بجوار|محافظة|مدينة|حي|شقة|الدور|منطقة|قسم|مدخل|متفرع|امام|فوق)/)) {
            data.name = line;
        }
    });

    // ✅ تجميع العنوان الكامل
    data.address = addressParts.join(" ").trim();

    // 🔄 تحويل Set إلى Array للأرقام الفريدة
    data.phones = Array.from(data.phones);

    return data;
}
	// البحث عن العميل باستخدام أرقام الهاتف
	async function searchClient(phones) {
	        if (!phones.length) return null; // إذا لم يكن هناك أرقام هاتف
	
	        for (let phone of phones) {
	            let x = await getClientData(0, 0, 0, phone); // البحث عن العميل
	            $("#result").html('<div class="centered text-primary lds-roller"><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div>');
	
	            return new Promise((resolve) => {
	                setTimeout(() => {
	                    $("#result").html("");
	                    if (x !== undefined && x !== "") {
	                        $("#result").html(x);
	                        resolve(phone);
	                    } else {
	                        $("#result").html('<div class="centered text-center">لا يوجد بيانات مسجلة لهذا الرقم</div>');
	                        resolve(null);
	                    }
	                }, 1000);
	            });
	        }
	        return null;
	}
	
	async function requestClipboardPermission() {
		    try {
		        // فحص حالة إذن الوصول للحافظة
		        let permissionStatus = await navigator.permissions.query({ name: "clipboard-read" });
		
		        if (permissionStatus.state === "granted") {
		            return true; // لديك الإذن بالفعل
		        } else if (permissionStatus.state === "prompt") {
		            // المستخدم بحاجة إلى الموافقة
		            alert("يرجى الضغط على زر السماح عند طلب الإذن.");
		            return false;
		        } else {
		            alert("تم رفض الإذن مسبقًا. يرجى السماح بقراءة الحافظة من إعدادات المتصفح.");
		            return false;
		        }
		    } catch (err) {
		        console.error("خطأ أثناء طلب الإذن:", err);
		        return false;
		    }
		}
	
	async function readClipboardData() {
	    let hasPermission = await requestClipboardPermission();
	    if (!hasPermission) return;
	
	    try {
	        let clipboardText = await navigator.clipboard.readText();
	        if (!clipboardText) {
	            alert("الحافظة فارغة!");
	            return;
	        }
	
	        let extractedData = extractData(clipboardText); // استدعاء دالة التحليل
	        console.log("📋 البيانات المستخرجة:", extractedData);
	        alert("تم استخراج البيانات بنجاح! تحقق من الكونسول.");
	
	    } catch (err) {
	        console.error("خطأ أثناء قراءة الحافظة:", err);
	        alert("تعذر قراءة البيانات من الحافظة.");
	    }
	}
	
});
</script>
                            