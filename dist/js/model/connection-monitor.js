const responseTimeout = 20000;
const REQUEST_DELAY = 5000; // 5 ثواني
let pingInProgress = false;
let lastStatus = null;



/**
* دالة لعرض رسالة خطأ اسفل navbar.
* @param {string} title - عنوان الرسالة.
* @param {string} text - نص الرسالة.
* 
*/
const displayError = function (type = 'w', title, text) {
    const $msg = $('#notificationMSG');

    // إزالة الكلاسات القديمة
    $msg.removeClass('bg-warning bg-danger bg-success');

    $msg.find('.notification-icon').removeClass('fa-times fa-check fa-exclamation-triangle');

    // إضافة النوع
    if (type === 'e') {
        $msg.addClass('bg-danger');
        $msg.find('.notification-icon').addClass('fa-times');
    } else if (type === 'w') {
        $msg.addClass('bg-warning');
        $msg.find('.notification-icon').addClass('fa-exclamation-triangle');
    } else if (type === 's') {
        $msg.addClass('bg-success');
        $msg.find('.notification-icon').addClass('fa-check');
    }

    // تحديث النص
    $msg.find('.notification-title').html(title);
    $msg.find('.notification-message').html(text);

    // إذا مش ظاهر بالفعل، نعمل fadeIn
    if (!$msg.is(':visible')) {
        $msg.stop(true, true).fadeIn();
    }
    // نلغي أي fadeOut سابق
    clearTimeout(window.__notifyTimer);

    // نخلي الرسالة تبقى موجودة **ولا تختفي إلا لو تم تغيير الحالة**
    window.__notifyTimer = setTimeout(() => {
        // هنا ممكن نخلي fadeOut اختياري فقط لو type == 's' (success)
        if(type === 's') {
            $msg.fadeOut();
        }
    }, responseTimeout); // أو خلي responseTimeout = كبير جدًا للخطأ
};

if (!window.__networkMonitorInstalled) {
    window.__networkMonitorInstalled = true;
	
	async function pingServer(url) {
		try {
			const delta = await ping(url);
			return delta; // تعيد النتيجة
		} catch (error) {
			console.log(String(error)); // تعرض رسالة خطأ
			return null; // يمكن إعادة قيمة افتراضية عند حدوث خطأ
		}
	}
	
	// ======= دالة ping محسّنة =======
	function pingHost(url, timeout = 30000) {
	    return new Promise((resolve) => {
	        if (!url) return resolve(null);
	
	        const p = new Ping();
	        let finished = false;
	
	        // مهلة عامة
	        const timer = setTimeout(() => {
	            if (!finished) {
	                finished = true;
	                resolve(null);
	            }
	        }, timeout);
	
	        try {
	            p.ping(url, function (err, data) {
	                if (finished) return;
	
	                clearTimeout(timer);
	                finished = true;
	
	                if (err || data === undefined || data === null) {
	                    resolve(null);
	                } else {
	                    resolve(data); // الوقت بالـ ms
	                }
	            });
	        } catch (err) {
	            clearTimeout(timer);
	            if (!finished) resolve(null);
	        }
	    });
	}
	
	async function checkFullNetworkStatus() {
	    if (pingInProgress) return; // منع تداخل الفحوص
	    pingInProgress = true;
	
	    try {
	        if (navigator.onLine) {
	            if (
	                lastStatus !== "no-internet" &&
	                lastStatus !== "server-down" &&
	                lastStatus !== "slow-intrnet" &&
	                lastStatus !== "slow-server"
	            ) {
	                displayError('w', 'جارٍ فحص الشبكة...', 'الرجاء الانتظار');
	            }
	
	            const [serverPing] = await Promise.all([
	                pingHost('https://just-m.net', 10000),
	               
	            ]);
	            const [googlePing] = await Promise.all([
	                
	                pingHost('https://www.google.com', 10000)
	            ]);
	
	            console.log('انترنت:', googlePing, '| الهوست:', serverPing);
	
	            if (googlePing === null) {
	                lastStatus = "no-internet";
	                displayError('e', 'لا يوجد إنترنت', 'أنت متصل بالشبكة لكن لا يوجد اتصال بالإنترنت');
	            } else if (serverPing === null) {
	                lastStatus = "server-down";
	                displayError('w', 'السيرفر غير متاح', 'الإنترنت يعمل لكن لا يمكن الوصول للسيرفر');
	            } else if (googlePing > 800 && serverPing > 1500) {
	                lastStatus = "slow-intrnet";
	                displayError('w', 'الاتصال بطيء', `Ping: ${Math.round(googlePing)}ms`);
	            } else if (googlePing < 800 && serverPing > 1500) {
	                lastStatus = "slow-server";
	                displayError('w', 'الاتصال بطيء', `Ping: ${Math.round(serverPing)}ms`);
	            } else {
	                lastStatus = 'good';
	                displayError('s', 'الاتصال مستقر', `Ping: ${Math.round(serverPing)}ms`);
	            }
	        }
	    } catch (err) {
	        console.error('Error in network check:', err);
	    } finally {
	        pingInProgress = false;
	        // ✅ استدعاء نفسه بعد انتهاء الفحص
	        if(lastStatus != 'good') setTimeout(checkFullNetworkStatus, REQUEST_DELAY);
	    }
	}

    // =========================
    // ✅ fetch
    // =========================
    const originalFetch = window.fetch;

    window.fetch = function (...args) {
        let triggered = false;

        const timer = setTimeout(() => {
            triggered = true;
            checkFullNetworkStatus();
        }, REQUEST_DELAY);

        return originalFetch.apply(this, args)
            .finally(() => {
                clearTimeout(timer);
            });
    };

    // =========================
    // ✅ XMLHttpRequest (jQuery / DataTables)
    // =========================
    // أولاً، نحتفظ بالـ URL لكل XHR
	const originalOpen = XMLHttpRequest.prototype.open;
	XMLHttpRequest.prototype.open = function(method, url, ...rest) {
	    this._url = url;
	    return originalOpen.apply(this, [method, url, ...rest]);
	};
	
	// ثم نعدل send
	const originalSend = XMLHttpRequest.prototype.send;
	XMLHttpRequest.prototype.send = function (...args) {
	    const xhr = this;
		const timer1 = setTimeout(() => {
	            triggered = true;
	            checkFullNetworkStatus();
	        }, 10000);
	
	        this.addEventListener('loadend', function () {
	            clearTimeout(timer1);
	        });
	    const timer = setTimeout(() => {
	        xhr.abort();
	        console.warn('XHR request aborted due to timeout:', xhr._url);
	    }, 40000); // 40 ثانية
	
	    xhr.addEventListener('loadend', () => clearTimeout(timer));
	
	    return originalSend.apply(this, args);
	};
	
	window.addEventListener('offline', () => {
	displayError('e', 'لا يوجد اتصال', 'تحقق من الاتصال بالانترنت');
	});
		
	window.addEventListener('online', () => {
		checkFullNetworkStatus(); // يعمل فحص حقيقي
	});

}
