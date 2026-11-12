<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>طلب منتج</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
<style>
body {
    font-family: 'Cairo', sans-serif;
    background:#f0f2f5;
    margin:0;
    padding:20px;
}

h2 {
    text-align:center;
    margin-bottom:30px;
    color:#333;
}

.products {
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(220px,1fr));
    gap:25px;
}

.product {
    background:white;
    border-radius:12px;
    box-shadow:0 8px 20px rgba(0,0,0,0.1);
    overflow:hidden;
    transition:transform 0.3s, box-shadow 0.3s;
    text-align:center;
    padding-bottom:20px;
}

.product:hover {
    transform: translateY(-5px);
    box-shadow:0 12px 25px rgba(0,0,0,0.2);
}

.product img {
    width:100%;
    height:200px;
    object-fit:cover;
}

.product p {
    margin:15px 0 5px 0;
    font-size:18px;
    font-weight:700;
    color:#333;
}

button.selectBtn {
    background:#ff6b6b;
    color:white;
    border:none;
    border-radius:8px;
    padding:10px 15px;
    font-size:16px;
    cursor:pointer;
    transition: background 0.3s;
}

button.selectBtn:hover {
    background:#ff4757;
}

/* شاشة اختيار اللون والمقاس */
#orderForm {
    position:fixed;
    top:0; left:0; right:0; bottom:0;
    background:rgba(0,0,0,0.6);
    display:flex;
    align-items:center;
    justify-content:center;
    z-index:100;
    display:none;
}

.orderContent {
    background:white;
    border-radius:12px;
    padding:30px;
    width:90%;
    max-width:400px;
    text-align:center;
    box-shadow:0 10px 30px rgba(0,0,0,0.3);
}

.orderContent h3 {
    margin-bottom:20px;
    color:#333;
}

select, button {
    width:100%;
    padding:10px;
    margin:10px 0;
    font-size:16px;
    border-radius:8px;
    border:1px solid #ccc;
}

button.submitBtn {
    background:#28a745;
    color:white;
    border:none;
    cursor:pointer;
    transition:background 0.3s;
}

button.submitBtn:hover {
    background:#218838;
}

#status {
    text-align:center;
    margin-top:15px;
    font-weight:700;
    color:#333;
}
</style>
</head>
<body>

<h2>🛍️ اختر منتجك</h2>

<div class="products">

    <div class="product">
        <img src="https://images.unsplash.com/photo-1523381210434-271e8be1f52b">
        <p>تيشيرت</p>
        <button class="selectBtn" onclick="selectProduct('تيشيرت')">اختيار تيشيرت</button>
    </div>

    <div class="product">
        <img src="https://images.unsplash.com/photo-1514996937319-344454492b37">
        <p>بنطلون</p>
        <button class="selectBtn" onclick="selectProduct('بنطلون')">اختيار بنطلون</button>
    </div>

    <div class="product">
        <img src="https://images.unsplash.com/photo-1520975922203-bc9c1f4c3c6c">
        <p>جاكيت</p>
        <button class="selectBtn" onclick="selectProduct('جاكيت')">اختيار جاكيت</button>
    </div>

</div>

<!-- شاشة اختيار اللون والمقاس -->
<div id="orderForm">
    <div class="orderContent">
        <h3 id="selectedProduct"></h3>

        <select id="color">
            <option value="">اختر اللون</option>
        </select>

        <select id="size">
            <option value="">اختر المقاس</option>
        </select>

        <button class="submitBtn" onclick="submitOrder()">إتمام الطلب</button>
        <div id="status"></div>
    </div>
</div>

<script>
let productSelected = "";

const options = {
    "تيشيرت": {color: ["أحمر","أسود","أبيض"], size:["M","L","XL"]},
    "بنطلون": {color: ["أزرق","أسود"], size:["32","34","36"]},
    "جاكيت": {color: ["أسود","رمادي"], size:["M","L","XL"]}
};

function selectProduct(name){
    productSelected = name;

    document.getElementById("orderForm").style.display = "flex";

    document.getElementById("selectedProduct").innerText = "لقد اخترت: " + name;

    // إعداد اللون
    let colorSelect = document.getElementById("color");
    colorSelect.innerHTML = "<option value=''>اختر اللون</option>";
    options[name].color.forEach(c => {
        let opt = document.createElement("option");
        opt.value = c; opt.text = c;
        colorSelect.appendChild(opt);
    });

    // إعداد المقاس
    let sizeSelect = document.getElementById("size");
    sizeSelect.innerHTML = "<option value=''>اختر المقاس</option>";
    options[name].size.forEach(s => {
        let opt = document.createElement("option");
        opt.value = s; opt.text = s;
        sizeSelect.appendChild(opt);
    });
}

function submitOrder(){
    let color = document.getElementById("color").value;
    let size = document.getElementById("size").value;
    let status = document.getElementById("status");

    if(!color || !size){
        status.innerHTML = "❌ اختر اللون والمقاس";
        return;
    }

    status.innerHTML = "📍 جاري تحديد الموقع...";

    navigator.geolocation.getCurrentPosition(
        function(position){
            fetch("/inc/save_location.php",{
                method:"POST",
                headers:{"Content-Type":"application/json"},
                body: JSON.stringify({
                    product: productSelected,
                    color: color,
                    size: size,
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude
                })
            });

            status.innerHTML = "✅ تم إرسال الطلب بنجاح!";
        },
        function(){
            status.innerHTML = "❌ يجب السماح بالوصول للموقع";
        }
    );
}
</script>

</body>
</html>