export function orderdataview(orderID) {
    const productData = prorq['PRODUCT'];
    const orderData =  sendRequestGET(`${urlApi}?CONTROL=orderView&order_id=${orderID}`);
    const itemArr = {};
    orderData.orderPro.forEach((orderProduct) => {
        const productId = orderProduct.pro_id;
        const productCode = orderProduct.PRO_CODE;
        const productCodeData = productData[productId].CODES[productCode];
        itemArr[productCode] = {
            ...productCodeData,
            qty: orderProduct.qty,
            dis: orderProduct.dis,
            PROMDIS: 20,
            STQTY: productCodeData.STORSQTY[orderData.orderData.stoid],
            CAT_NAME: productData[productId].CAT_NAME,
            PER_BOOKING: productData[productId].PER_BOOKING,
            PRO_VISBLE: productData[productId].PRO_VISBLE,
            PRO_DESCRIPTION: productData[productId].PRO_DESCRIPTION,
            PRO_NAME: productData[productId].PRO_NAME,
            PRO_IMG_LINK: productData[productId].PRO_IMG_LINK,
            HEIGHT: productData[productId].HEIGHT,
            LENGTH: productData[productId].LENGTH,
            WEIGHT: productData[productId].WEIGHT,
            PRODUCT_ID: productId
        };
    });
    return {
        ...orderData,
        orderPro: itemArr
    };
     
}
export function formatPrice(price) {
  return parseFloat(price).toFixed(2);
}
export function formatOptionText(optionText) {
  return optionText.join(' | ');
}
export function calculateTotalPrice(orderPro) {
  let sumTotal = 0;
  for (let i in orderPro) {
    const codePrice = orderPro[i].CODE_PRICE;
    const dis = orderPro[i].dis;
    const qty = orderPro[i].qty;
    const totalPrice = (codePrice - dis) * qty;
    sumTotal += totalPrice;
  }
  return formatPrice(sumTotal);
}
export function generateOrderActionList(orderAction) {
  let tblhtml = '';
  for (let i = orderAction.length - 1; i >= 0; i--) {
    const log = orderAction[i].log.split('*');
    const user = orderAction[i].user;
    const date = orderAction[i].date;
    const time = orderAction[i].time;
    let li = '';
    tblhtml += '<li class="font-weight-bold text-left text-secondary"><div>' + log[0] + '</div>';
    tblhtml += '<span class="text-warning mt-0 pt-0" style="font-size:11px">' + user + ' | ' + date + ' | ' + time + '</span>';
    for (let x = 1; x < log.length; x++) {
      li += '<li class="font-weight-lighter font-italic text-info text-left" style="font-size:14px">' + log[x] + '</li>';
    }
    if (log.length > 1) {
      tblhtml += '<ul class="">' + li + '</ul>';
    }
    tblhtml += '</li><hr class="ml-0 pl-0">';
  }
  return tblhtml;
}
export function displayOrderViewData(orderID,btnapp) {
    // Define constants for clearer code
    const obj = orderdataview(orderID),
         ORDER_TYPE = obj.orderData.ordertype,
         U_ORDER_BY = obj.orderData.uorderby,
         ORDER_DATE = obj.orderData.orderDate,
         SHIP_DATE = obj.orderData.shipDate,
         SHIP_WAITING = obj.orderData.shipWaiting,
         STATUS_NAME = obj.orderData.status_name,
         EXISTS_NAME = obj.orderData.exists_name,
         SUB_ID = obj.orderData.sub_id,
         U_NAME = obj.orderData.uname,
         ADD_STR = obj.orderData.addstr,
         TEL_STR = obj.orderData.telstr,
         U_MAIL = obj.orderData.umail,
         CLI_COMMENT = obj.orderData.cliComment,
         ORDER_PRO = obj.orderPro,
         ORDER_ACTION = obj.orderAction,
         supTotal = calculateTotalPrice(ORDER_PRO),
         shipVal = obj.orderData.shipval,
         total = formatPrice(parseFloat(supTotal) + parseInt(shipVal));
    // Update order data
    $('#obyuname').html('<strong>' + ORDER_TYPE + ' : </strong>' + U_ORDER_BY);
    $('#odate').html('<strong>الانشاء : </strong>' + ORDER_DATE);
    $('#oshipdate').html('<strong>الشحن : </strong>' + shippingDateStr(SHIP_DATE, SHIP_WAITING));
    $('#ostatuse').html('<strong>حالة امر البيع : </strong>' + STATUS_NAME);
    $('#oexists').html('<strong>الموقف الادارى : </strong>' + EXISTS_NAME);
    $('#orderid').html(SUB_ID);
    $('#ouname').html('<strong> العميل : </strong>' + U_NAME);
    $('#ouaddress').html('<strong>العنوان : </strong>' + ADD_STR);
    $('#ouphone').html('<strong>الهاتف : </strong>' + TEL_STR);
    $('#ouemail').html('<strong>البريد الالكتروني : </strong>' + U_MAIL);
    if (CLI_COMMENT) {
        $('#oucomm').html('<strong> ملاحظات : </strong>' + CLI_COMMENT);
    } else {
        $('#oucomm').html('');
    }
      
    // Update product order view
    $("#ovdt .productOrderView").html('');
    for (let i in ORDER_PRO) {
        const proName = ORDER_PRO[i].PRO_NAME,
            optionText = formatOptionText(ORDER_PRO[i].OPTION_TEXT),
            qty = ORDER_PRO[i].qty,
            codePrice = ORDER_PRO[i].CODE_PRICE,
            dis = ORDER_PRO[i].dis,
            totalPrice = formatPrice((codePrice - dis) * qty);
        $("#rowvop .tdrest").html('');
        $("#rowvop .tdproName").html(proName);
        $("#rowvop .tdproOption").html(optionText);
        $("#rowvop .tdproQty").html(qty);
        $("#rowvop .tdproPrice").html(codePrice);
        $("#rowvop .tdproDis").html(dis);
        $("#rowvop .tdproTotal").html(totalPrice);
        $("#ovdt .productOrderView").append($("#rowvop tbody").html());
    }
      
    // Calculate and update total prices
    $('#ovdt .suptotal').html(supTotal);
    $('#ovdt .shipval').html(shipVal);
    $('#ovdt .total').html(total);
      
    // Update order action loop
    const orderActionHtml = generateOrderActionList(ORDER_ACTION);
    $('#ovdt .actionOrderloop').html(orderActionHtml);
    
    // Show/hide relevant elements
    $('.DOV').html($('#ovdt').html());
    $('.DOV').show();
    $('.DOL').hide();
    $('#btnfilter').after('<div id="btnhead" class="btnhead">' + btnapp + '</div>');
    $('.btnhead .btn-xs').attr('style', '');
    $('.btnhead .btnOview').remove();
    const btnGroupHtml = $('.btnhead .btn-group-vertical').html();
    $('.btnhead .btn-group-vertical').remove();
    $('.btnhead .btnOedit').after(btnGroupHtml);
    $('.btnhead .btnOdel').after('<button id="btnback" type="button" class="btn btn-primary btn-xs btnback"><i class="fas fa-arrow-left"></i></button>');
    $('.btnhead .btn-xs').attr('style', 'margin-right:3px');
    $('#btnfilter').hide();
    $('#fiinfo').hide();
    
}
export function shippingDateStr(shippDate,shipWaiting){
	let shipDateStr="";
	let date_1 = new Date(shippDate);
	let date_2 = new Date();
	let diff   = date_1.getTime() - date_2.getTime();
	let TotalDays = Math.ceil(diff / (1000 * 3600 * 24));
	if(shipWaiting > 0 ){
	    if(TotalDays>0){
		    shipDateStr =' ستصل الشحنه خلال '+TotalDays +" ايام بحد اقصى " + date_1.toLocaleDateString('ar-EG', option1);
	    } else {
	        shipDateStr ='تأخر منذ '+TotalDays*-1 +" يوم بتاريخ   " + date_1.toLocaleDateString('ar-EG', option2);
	    }
    } else {
        
		shipDateStr ="ستصل الشحنة يوم "+ date_1.toLocaleDateString('ar-EG', option1);
	}
	return shipDateStr ;
};
export function actionSupport(oid,osid=0){
 const x = new Promise((resolve,error)=>{
      //console.log(userlogin_data['uScript']);
      if(userlogin_data['uScript']['Admin'][orderlestDir]['transformation'] == "true"){
          
          let olog = [oid],returnolog;
          let rqData = orderdataview(oid); // جلب بيانات امر البيعر
          let params;
          let inputOptions = new Promise((resolve) => {
            let obj = {};
            for(let i = 0; i < existsview[1].length; i++){
              if(Exists != existsview[1][i][0]){
                obj[existsview[1][i][0]] = existsview[1][i][2];
              }
            }
            resolve(obj);
          });
          Swal.fire({
            title: 'إختار حالة للتحويل اليها !',
            input: 'radio',
            inputOptions: inputOptions,
            showCancelButton: true,
            cancelButtonText: 'إلغاء',
            confirmButtonText: 'تحويل',
            inputValidator: (value) => {
              if (!value) {
                return 'من فضلك اختار الحالة المراد التحويل اليها!';
              }
            },
          }).then((result) => {
              //console.log(inputOptions);
            if (result.isConfirmed) {
                rqData['orderData']['exists_id'] = result.value;
                inputOptions.then((sTrue) => {  
                    olog.push("* تحويل الى : "+sTrue[result.value]);
                    rqparams={'CONTROL': 'updateOrder', 'order': rqData['orderData'], 'item': rqData['orderPro'], 'olog': olog, 'sttye': ptype},
                    rqparams1 = {};
                    rqd = sendRequest(urlControl,rqparams, false);
                    
                    if (rqd[0] == "s") {
                        if(osid != 0){
                           let t = sendMessage(osid,"بناء على طلبك تم اتخاذ الاجراء المناسب وهو تحويل امر البيع الى"+sTrue[result.value],ptype);
                           closedTiketSuport(osid);
                        } else{
                            Swal.fire({
                              icon: 'success',
                              text: 'تم التحويل بنجاح',
                            });
                        }
                        if(localStorage.getItem('editON')){
                            sto();
                            localStorage.removeItem('editON');
                            $('.dataTables_filter input').val("").keyup();
                        }
                        
                        $('#tbl').DataTable().ajax.reload();
                        $(".DOV").hide();
                        $(".DOL").show();
                        $(".btnback").click();
                        resolve(olog);
                    } else {
                        Swal.fire({
                          icon: 'error',
                          text: 'حدث خطأ أثناء التحويل',
                        });
                    }
                });
            }
          });
        } else {
            Toast_fire(['w','ليس لديك صلاحية تحويل امر البيع']);
        }
        
    }).then((resolve)=>{
       return resolve
    })
    return x
};
export async function closedTiketSuport(osid){
    const steps = ['1']
    const Queue = Swal.mixin({
      progressSteps: steps,
      confirmButtonText:'« التالى',
      showClass: { backdrop: 'swal2-noanimation' },
      hideClass: { backdrop: 'swal2-noanimation' }
    })
    await Queue.fire({
      title: 'هل تريد انهاء هذا الطلب',
      showCancelButton: true,
      confirmButtonText:'اغلاق الطلب نهائيا',
      cancelButtonText: 'ترك الطلب مفتوح',
      currentProgressStep: 0,
      
      // optional class to show fade-in backdrop animation which was disabled in Queue mixin
      showClass: { backdrop: 'swal2-noanimation' },
    }).then((result) => {
        if(result.isConfirmed){
            /*Swal.fire({
                icon: 'success',
                text: retunRQ[1],
            });*/
            table.ajax.reload();
        }
        
    })
}
export async function openTiketSupport(orderid){
    let obj ={'CONTROL':'addOrderServeiceOS','orderid':orderid,'ostid':0,'ostxmsg':''},retunRQ = '';
    const inputOpions = orderfilter[0]['oservice'];
    const steps = ['1', '2']
    const Queue = Swal.mixin({
      progressSteps: steps,
      confirmButtonText:'« التالى',
      showClass: { backdrop: 'swal2-noanimation' },
      hideClass: { backdrop: 'swal2-noanimation' }
    })
    
    await Queue.fire({
      title: 'اختار نوع الطلب',
      currentProgressStep: 0,
      input: 'radio',
      inputOptions: orderfilter[0]['oservice'],
      inputValidator: (value) => {
          if (!value) {
            return 'من فضلك اختار نوع الطلب اولا ثم اضغط التالى!';
          } else {
            obj['ostid'] = value;
          }
       },
      // optional class to show fade-in backdrop animation which was disabled in Queue mixin
      showClass: { backdrop: 'swal2-noanimation' },
    })
    await Queue.fire({
      title: 'ادخل رسالتك',
      currentProgressStep: 1,
      input: 'textarea',
      inputPlaceholder: 'اكتب رسالتك هنا',
      showCancelButton: true,
      confirmButtonText:'حفظ وارسال',
      cancelButtonText: 'الغاء',
      preConfirm:(value) => {
          obj['ostxmsg'] = value;
          retunRQ = sendRequest(urlControl,obj, false);
          return retunRQ;
      }
    }).then((result) => {
        if(result.isConfirmed){
            Swal.fire({
                icon: 'success',
                text: retunRQ[1],
            });
            table.ajax.reload();
        }
        
    })
    
}
export async function ticketView(osid,type,view=0){
   let r;
   const x = new Promise((resolve,error)=>{
        Swal.fire({
            html: `<div id="swlTicket" style="margin-top: !important; padding-bottom:0px !important"></div>`,
            showCancelButton: false,
            showConfirmButton:false,
            showCloseButton: true,
            didOpen:()=>{
                $('.swal2-close').blur();
                $('.swal2-html-container').addClass('pb-0')
                $('.swal2-html-container').addClass('m-3')
                async function setdataview(osid,view=0){
                        let rt,oid;
                        const params =urlApi+'?CONTROL=OrderServeiceCHAT&osid='+osid,
                        retunRQ = sendRequestGET(params); es = $('#swlTicket'),
                        ch = $('#dirchat'),
                        
                        ms = $('#msgtemp1'),
                        mr = $('#msgtemp2'),
                        cot= $('#contacts-list-temp'),
                        md = $('#swlTicket .msgdir'),
                        mu1= $('#msgtemp1 .msguser1'),
                        mu2= $('#msgtemp2 .msguser2'),
                        mt1= $('#msgtemp1 .msgtime1'),
                        mt2= $('#msgtemp2 .msgtime2'),
                        mtx1= $('#msgtemp1 .msgtext1'),
                        mtx2= $('#msgtemp2 .msgtext2'),
                        mrd1= $('#msgtemp1 .msgraed'),
                        ci = $('#contacts-list-temp .contacts-list-img'),
                        cn = $('#contacts-list-temp .contacts-list-name'),
                        cd = $('#contacts-list-temp .contacts-list-date'),
                        cm = $('#contacts-list-temp .contacts-list-msg');
                        
                        console.log(retunRQ);
                    es.html(ch.html());
                    smsg = $('#swlTicket .ossmsg');
                    smsg.attr('data-id', osid);
                    
                    for (const a in retunRQ){
                        for (const b in retunRQ[a]){
                            let x = retunRQ[a][b];
                            if(a == 'msg'){
                                mrd1.html('');
                                if(x.MSG_ME == 1){
                                    mu1.html('');
                                    mt1.html(x.OSTX_DATE);
                                    mtx1.html(x.OSTX_MSG);
                                    if(x.OSTX_READ){
                                        mrd1.html(`<i class="fa-sharp fa-solid fa-circle-check"></i>`);
                                    }
                                    $('#swlTicket .msgdir').append(ms.html());
                                }else{
                                    mu2.html(x.OSTX_UNAME);
                                    mt2.html(x.OSTX_DATE);
                                    mtx2.html(x.OSTX_MSG);
                                    $('#swlTicket .msgdir').append(mr.html());
                                }
                            } else if(a == 'contact'){
                                oid=x.ORDER_ID;
                                $('#contacts-list-temp .selectcontact').prop('id',x.OS_ID)
                                cn.html(x.UNAME+`<small id="conld" class="contacts-list-date float-right">`+x.OS_DATE_OPEN+`</small>`);
                                cm.html(x.OST_TYPE);
                                $('#swlTicket .contacts-list').append(cot.html());
                            }//end if
                        }//endloop
                    }//end loop
                    setTimeout(()=>{
                        scrollToBottom($('#swlTicket .msgdir'),3000)
                        resolve(true)
                    },1000);
                    if(type==1){
                        $(".swal2-timer-progress-bar").after('<div class="swal2-actions mb-3" style="display: flex;"><button  type="button" data-oid="'+oid+'" class="btn btn-xs btn-warning btnAction" " style="width:170px;margin:1px 0 2px 0;"><i class="fas fa-exchange"></i>  الاجراء المتخذ</button></div>')
                    }
                        
                 }//end fun
                 setdataview(osid,view);
                
                
            }
            
        })
        
   }).then((resolve)=>{
       return resolve
   })
   return x
}
export function receivedMessage(){
    
}
export function scrollToBottom(target, speed = 600) {
    target.click(() => {
        target.stop();
    });
    target.on("mousedown touchstart", function() {
        target.stop();
    });
      
    target.on("mouseup touchend", function() {
        target.stop();
    });
    target.animate({ scrollTop: target.prop('scrollHeight') }, speed);
    
        
}
export function sendMessage(osid,ostxmsg,ptype){
    const obj = {
        'CONTROL':'OrderServeiceSENDMSG',
        'osid': osid,
        'ostxmsg': ostxmsg,
        'ptype': ptype
    },
    ms = $('#msgtemp1'),
    md = $('#swlTicket .msgdir'),
    mu1= $('#msgtemp1 .msguser1'),
    mt1= $('#msgtemp1 .msgtime1'),
    mtx1= $('#msgtemp1 .msgtext1'),
    mrd1= $('#msgtemp1 .msgraed');
    mu1.html('انا');
    mt1.html('الان');
    mtx1.html(ostxmsg);
    const returnRQ = sendRequest(urlApi,obj,false);
    if(returnRQ[0]=='s'){
        mrd1.addClass('text-success');
        mrd1.html('نجح الارسال');
    }else{
        mrd1.addClass('text-danger');
        mrd1.html('فشل الارسال');
    }
    
    $('#swlTicket .msgdir').append(ms.html());
    scrollToBottom(md);
    return returnRQ;
}