<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>إنشاء أمر بيع | Just-M</title>
<style>
:root{--ink:#17233b;--muted:#718096;--line:#e7ebf2;--canvas:#f4f6fb;--brand:#3157d5;--red:#c43d4d;--green:#16865b}*{box-sizing:border-box}body{margin:0;background:var(--canvas);color:var(--ink);font:14px/1.7 Tahoma,Arial,sans-serif}.shell{max-width:1200px;margin:auto;padding:22px}.head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px}.head h1{margin:0;font-size:25px}.muted{color:var(--muted);font-size:12px}.panel{background:#fff;border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:14px}.fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.field{display:flex;flex-direction:column;gap:5px}.field label{font-weight:bold;font-size:12px;color:#53617a}.field input,.field select,.field textarea{width:100%;border:1px solid #dce2ed;border-radius:8px;padding:10px;background:#fff;font:inherit;color:var(--ink)}.field textarea{min-height:70px}.span2{grid-column:span 2}.btn{border:1px solid var(--line);border-radius:9px;padding:10px 15px;background:#fff;color:var(--ink);font-weight:bold;cursor:pointer;text-decoration:none}.primary{background:var(--brand);border-color:var(--brand);color:#fff}.soft{background:#eef2ff;color:var(--brand)}.danger{color:var(--red)}.actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:16px}.alert{padding:11px 14px;border-radius:9px;margin-bottom:14px;background:#fff0f1;color:#982d3a}.step{display:none}.step.active{display:block}.product-picker{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:9px;align-items:end}.product-picker select,.product-picker input{width:100%;border:1px solid #dce2ed;border-radius:8px;padding:10px;background:#fff;font:inherit}.hint{font-size:11px;color:var(--muted);margin-top:5px}.table-wrap{overflow:auto;margin-top:14px}table{width:100%;border-collapse:collapse;min-width:650px}th,td{text-align:right;padding:10px;border-bottom:1px solid var(--line)}th{font-size:11px;color:var(--muted);background:#fafbff}.totals{display:flex;justify-content:flex-end;gap:20px;flex-wrap:wrap;margin-top:14px;font-weight:bold}.badge{display:inline-block;padding:3px 9px;border-radius:20px;background:#eef2ff;color:#3157d5;font-size:11px}.top-actions{display:flex;gap:8px;flex-wrap:wrap}@media(max-width:700px){.shell{padding:12px}.head{align-items:flex-start;flex-direction:column}.fields{grid-template-columns:1fr}.span2{grid-column:auto}.product-picker{grid-template-columns:1fr 1fr}.product-picker .wide{grid-column:span 2}.actions .btn{flex:1}.panel{padding:13px}}
</style></head><body><main class="shell">
<header class="head"><div><h1>إنشاء أمر بيع</h1><div class="muted">العميل ثم المنتجات ثم مراجعة الطلب قبل الحفظ.</div></div><div class="top-actions"><a class="btn" href="{{ route('admin.sales.orders.index') }}">العودة للأوامر</a></div></header>
@if($errors->any())<div class="alert"><strong>راجع البيانات التالية:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="post" action="{{ route('admin.sales.orders.store') }}" id="order-form">@csrf
<section class="panel step active" id="step-customer"><h2>1. بيانات العميل</h2><p class="muted">اختر العميل وحدد عنوان التوصيل. رسوم الشحن تُدخل يدويًا ولا يتم احتسابها تلقائيًا في هذه المرحلة.</p>
<div class="fields"><div class="field span2"><label>العميل</label><select name="customer_id" id="customer_id" required><option value="">اختر العميل</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" data-address="{{ e($customer->address ?? '') }}">{{ $customer->name }} — {{ $customer->mobile ?: $customer->phone }}</option>@endforeach</select></div><div class="field"><label>رسوم الشحن (اختياري)</label><input type="number" name="shipping_fee" id="shipping_fee" min="0" step="0.01" value="0"></div><div class="field span2"><label>عنوان التوصيل</label><textarea name="shipping_address" id="shipping_address" maxlength="2000"></textarea></div></div>
<div class="actions"><button type="button" class="btn primary" id="to-products">التالي: اختيار المنتجات</button></div></section>
<section class="panel step" id="step-products"><h2>2. اختيار المنتجات</h2><p class="muted">اختر كود المنتج والكمية وسعر البيع. السعر لا يقل عن الحد الأدنى ولا يزيد عن سعر القائمة.</p>
<div class="product-picker"><div class="field wide"><label>المنتج / الكود</label><select id="variant-select"><option value="">اختر كود المنتج</option>@foreach($variants as $variant)<option value="{{ $variant->id }}" data-name="{{ e($variant->product->name) }}" data-sku="{{ e($variant->sku) }}" data-list="{{ $variant->list_price }}" data-min="{{ $variant->minimum_sale_price }}">{{ $variant->product->name }} — {{ $variant->variant_name ?: $variant->sku }} @foreach($variant->attributeValues as $value) / {{ $value->value }} @endforeach</option>@endforeach</select></div><div class="field"><label>الكمية</label><input id="item-quantity" type="number" min="0.001" step="0.001" value="1"></div><div class="field"><label>سعر البيع</label><input id="item-price" type="number" min="0" step="0.01"></div><button type="button" class="btn primary" id="add-item">إضافة المنتج</button></div><div class="hint" id="price-hint">اختر كود المنتج لعرض نطاق السعر.</div>
<div class="actions"><button type="button" class="btn" id="back-customer">رجوع</button><button type="button" class="btn primary" id="to-review">مراجعة الطلب</button></div></section>
<section class="panel step" id="step-review"><h2>3. مراجعة الطلب</h2><p class="muted">راجع تفاصيل البنود والأسعار والخصومات ورسوم الشحن قبل الحفظ.</p>
<div class="table-wrap"><table><thead><tr><th>المنتج</th><th>الكمية</th><th>سعر القائمة</th><th>سعر البيع</th><th>الخصم</th><th>الإجمالي</th><th></th></tr></thead><tbody id="review-items"></tbody></table></div>
<div class="totals"><span>قيمة البنود: <b id="subtotal">0.00</b></span><span>الخصم: <b id="discount-total">0.00</b></span><span>الشحن: <b id="shipping-total">0.00</b></span><span>الإجمالي: <b id="grand-total">0.00</b></span></div>
<div class="field" style="margin-top:15px"><label>ملاحظات</label><textarea name="notes" maxlength="5000"></textarea></div>
<div class="actions"><button type="button" class="btn" id="back-products">رجوع للمنتجات</button><button type="submit" class="btn primary" id="save-order">حفظ أمر البيع كمسودة</button></div></section>
</form></main>
<script>
(() => {
 const $ = id => document.getElementById(id);
 const steps = ['step-customer','step-products','step-review'];
 const items = [];
 const money = value => (Number(value)||0).toFixed(2);
 const show = id => steps.forEach(step => $(step).classList.toggle('active', step === id));
 const selected = () => $('variant-select').selectedOptions[0];
 const renderHint = () => {
   const option = selected();
   if (!option || !option.value) { $('price-hint').textContent='اختر كود المنتج لعرض نطاق السعر.'; $('item-price').value=''; return; }
   $('item-price').value=option.dataset.list;
   $('price-hint').textContent='الحد الأدنى: '+money(option.dataset.min)+' — سعر القائمة: '+money(option.dataset.list);
 };
 $('customer_id').addEventListener('change', () => { const option=$('customer_id').selectedOptions[0]; $('shipping_address').value=option?.dataset.address || ''; });
 $('variant-select').addEventListener('change', renderHint);
 $('to-products').addEventListener('click', () => { if (!$('customer_id').value) { alert('اختر العميل أولًا.'); return; } show('step-products'); });
 $('back-customer').addEventListener('click', () => show('step-customer'));
 $('back-products').addEventListener('click', () => show('step-products'));
 $('add-item').addEventListener('click', () => {
   const option=selected(), quantity=Number($('item-quantity').value), price=Number($('item-price').value);
   if (!option?.value) { alert('اختر المنتج أولًا.'); return; }
   if (!(quantity>0) || !Number.isFinite(quantity)) { alert('أدخل كمية صحيحة.'); return; }
   if (!Number.isFinite(price) || price<Number(option.dataset.min) || price>Number(option.dataset.list)) { alert('سعر البيع خارج النطاق المسموح.'); return; }
   const existing=items.find(item=>item.id===option.value);
   if (existing) { existing.quantity += quantity; existing.price=price; }
   else items.push({id:option.value,name:option.dataset.name,sku:option.dataset.sku,list:Number(option.dataset.list),min:Number(option.dataset.min),quantity,price});
   $('variant-select').value=''; $('item-quantity').value='1'; renderHint();
   $('price-hint').textContent='تمت إضافة المنتج. يمكنك اختيار منتج آخر.';
 });
 $('to-review').addEventListener('click', () => {
   if (!items.length) { alert('أضف منتجًا واحدًا على الأقل.'); return; }
   const body=$('review-items'); body.innerHTML='';
   let subtotal=0,discountTotal=0;
   items.forEach((item,index) => {
     const discount=(item.list-item.price)*item.quantity, line=item.price*item.quantity;
     subtotal+=line; discountTotal+=discount;
     const tr=document.createElement('tr');
     tr.innerHTML='<td><strong></strong><br><small></small></td><td></td><td></td><td></td><td></td><td></td><td><button type="button" class="btn danger">حذف</button></td>';
     tr.children[0].querySelector('strong').textContent=item.name;
     tr.children[0].querySelector('small').textContent=item.sku;
     [item.quantity,money(item.list),money(item.price),money(discount),money(line)].forEach((value,i)=>tr.children[i+1].textContent=value);
     tr.querySelector('button').addEventListener('click',()=>{items.splice(index,1);$('to-review').click();});
     body.appendChild(tr);
   });
   const shipping=Number($('shipping_fee').value)||0;
   $('subtotal').textContent=money(subtotal);$('discount-total').textContent=money(discountTotal);$('shipping-total').textContent=money(shipping);$('grand-total').textContent=money(subtotal+shipping);
   document.querySelectorAll('#order-form input[name^="items["]').forEach(input=>input.remove());
   items.forEach((item,index)=>{
     [['product_variant_id',item.id],['quantity',item.quantity],['sale_price',item.price]].forEach(([key,value])=>{
       const input=document.createElement('input');input.type='hidden';input.name='items['+index+']['+key+']';input.value=value;$('order-form').appendChild(input);
     });
   });
   show('step-review');
 });
 $('order-form').addEventListener('submit', event => {
   if (!items.length) { event.preventDefault(); alert('أضف منتجًا واحدًا على الأقل.'); }
 });
})();
</script></body></html>
