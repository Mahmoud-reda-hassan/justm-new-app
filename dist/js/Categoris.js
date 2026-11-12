//-----------------Product-Categories PAGE--------------
$(document).ready(function () {
function treeviewdata(treeData) {
		$('#tree').bstreeview({
				data: treeData,
                expandIcon: 'fa fa-angle-right fa-fw',
                collapseIcon: 'fa fa-angle-down fa-fw',
                indent: 2,
                parentsMarginLeft: '1.25rem',
                openNodeLinkOnNewTab: false
			});
			
		$('.bstreeview,.collapse').collapse();
		
	}
	let xtreeData;
	xtreeData =  sendRequestGET(''+urlApi+'?CONTROL=getlistProduct-Categories')
	treeviewdata(xtreeData);
	
//============= new and update ========================= 
    $(document).on('click','#btnNew', function (){
        if($('#tree input:checkbox:checked').length == 0){
			$('#tree input:checkbox').each(function() {
		        $(this).remove();
			});
		}
        let x =''
	    let mode;
        let SwalTitle;
        var data =$(this);
        //var up = $(this).attr('data-id');
        let obj={};
        var msg ='';
        let objreq;
        let ajaxpar ={};
        if(data.attr('data-id')  !== 'new') {
            SwalTitle = 'تحديث بيانات ال';
            ajaxpar['CONTROL']='update'+Go;
            ajaxpar['data_id'] = data.attr('data-id');
            msg = 'تم تغير ' +data.attr('data-namear') + ' ' +  data.attr('data-nameen') + ' إلى ';
        } else {
            SwalTitle = 'اضافة ';
            ajaxpar['CONTROL'] = 'add'+Go;
            msg = 'تم اضافة ';
        }
        SwalTitle += 'قسم';
      
        if (data.attr('data-id')  == 'new') {
            obj = {'el':'input','id':'CAT_NAME','req':'param required','lbl':'اسم القسم','place':'من فضلك ادخل الاسم','val': data.attr('data-name')};
            x   = htmlel(obj);
            obj = {el:'select',id:'CAT_PARENT',req:'param',lbl:'تابع لـ',val:0,params:Go};
            x  += htmlel(obj);
			swalinsert(x,0);
        } else {
            obj = {'el':'select','id':'CAT_ID','req':'param required','lbl':'إختارالقسم المراد تعديلة','val': -1,params:Go};
            let a = htmlel(obj);
            obj = {'el':'input','id':'CAT_NAME','req':' param required','lbl':'اسم القسم','place':'من فضلك ادخل الاسم','val': data.attr('data-name')};
            x   += htmlel(obj);
            obj = {'el':'select','id':'CAT_PARENT','req':' param','lbl':'تابع لـ','val': 0,params:Go};
            x  += htmlel(obj);
			swalupdate(a,x);
        }
		function swalupdate(a){
			Swal.fire({
				title		        : 'من فضلك اختار القسم',
				html		        : a ,
				//type		        : '',
				allowOutsideClick	: false,
				showCancelButton	: true,
				confirmButtonColor	: '#3085d6',
				cancelButtonColor	: '#d33',
				confirmButtonText	: 'التالى',
				cancelButtonText	: 'إلغاء',
				focusConfirm		: true,
				didOpen: ()=>{
					
					$('.form-group label').remove();
					$('#CAT_ID .val1').remove()
				}, 
				preConfirm: function () {
					let msgerr = '';
					let x=0;
					$('.required').each(function() {
						if(!$(this).val()){
							x++;
							$(this).addClass('is-invalid');
						} else {
							$(this).removeClass('is-invalid');
							msg += $(this).val() +' ';
							ajaxpar[$(this).attr('id')] ='' + $(this).val();
						}
					});
					if (x>0){
						msgerr += 'برجاء اختيار القسم';
					} else {
						//console.log(ajaxpar);
					}
					if(msgerr!== ''){
						Swal.showValidationMessage(msgerr);
					}
					let datareturn = sendRequestGET(urlApi+'?CONTROL=getlistProduct-Categories&CAT_ID='+ajaxpar['CAT_ID'])
					//console.log(datareturn[0]);
				return datareturn;
				
				}
			}).then((result) => {
				$('td :checkbox').checked = false;
				console.log(result.value[0]);
				if(result.value[0]['CAT_ID'] > 0){
					swalinsert(x,
								result.value[0]['CAT_ID'],
								result.value[0]['parent_id']
					);
					
				};
			});
		}
		function swalinsert(a=0,b=0,c=1){
			Swal.fire({
				title		        : SwalTitle,
				html		        : a ,
				//type		        : '',
				allowOutsideClick	: false,
				showCancelButton	: true,
				confirmButtonColor	: '#3085d6',
				cancelButtonColor	: '#d33',
				confirmButtonText	: 'حفظ',
				cancelButtonText	: 'إغلاق',
				focusConfirm		: true,
				didOpen: ()=>{
					ajaxpar['CAT_ID']= b;
					$('#CAT_PARENT').val(b);
					$('#CAT_NAME').val($('#CAT_PARENT option:selected').text());
					if(b <= 1 && data.attr('data-id') ==-1){
						$('#CAT_PARENT').hide();
						$('.form-group label').hide();
						ajaxpar['CAT_PARENT']= c;
					}
					let h=$('#CAT_PARENT').html();
					$('#CAT_PARENT').val(c).change();
					
				}, 
				preConfirm: function () {
					let msgerr = '';
					let x=0;
					$('.required').each(function() {
						if(!$(this).val()){
							x++;
							$(this).addClass('is-invalid');
						} else {
							$(this).removeClass('is-invalid');
							msg += $(this).val() +' ';
						}
					});
					$('.param').each(function() {
							ajaxpar[$(this).attr('id')] ='' + $(this).val();
					});
					if (x>0){
						msgerr += 'جميع الحقول مطلوبة برجاء ملئ جميع الحقول';
					} else {
					    if(ajaxpar['CAT_ID'] != ajaxpar['CAT_PARENT']){
					    	
					        objreq =  sendRequest(urlControl,ajaxpar);
						    
						    if(objreq === 'error23000'){
							    $('.required').addClass('border-danger');
							    msgerr += 'البيانات التى تم  ادخلها مسجله مسبقا بقواعد البيانات';
						    } 
					    } else {
					        msgerr += 'غير مسموح اختيار نفس القسم فى الحقل تابع ل';
					    }
					}
					if(msgerr!== ''){
						Swal.showValidationMessage(msgerr);
					}
					//$('#CAT_PARENT').hide();
					//$('.form-group label').hide();
				return objreq;
				}
			}).then((result) => {
				$('td :checkbox').checked = false;
				if(result.value > 0){
					$('#tree').attr('class','col-12'); 
					$('#tree').html('');
					location.reload();
				};
			});
		}
	});//end btn new and update
//================ delete ============================================================
    /*$(document).on('click','#btnDelete', function (){	
        
        if($('#tree input:checkbox').length == 0 ){
			alert
			$('.list-group-item').each(function() {
				if($(this).attr('id') !=1){
				    let c=$(this).attr('id'),
					    v='data-name='+$(this).text().replace(' ','_');
				    $(this).append('<input id='+ c +' '+ v +' type =checkbox >');
				}
				    
		    });
			Swal.fire('يمكن الان اختيار قسم او اكثر للحذف ثم قم بالضغط مره أخرى على زر الحذف')
			
        } else if($('#tree input:checkbox:checked').length == 0){
			$('#tree input:checkbox').each(function() {
		        $(this).remove();
			});
			
		}
		let data_return;
        let id = [];
        var el='';
		var el1='';
		let elfiell='';
        $('#tree input:checkbox:checked').each(function () {
			
            if($(this).is(':checked')){
				let check_pro_cat = [];
				    check_pro_cat = sendRequest(urlControl,{CONTROL:'getdropProCat',ID:$(this).attr('id')});
				if(check_pro_cat.length === 0 ){//&& check_pro_cat.indexOf(1)){
					id.push($(this).attr('id'));
					el += '<li class=text-left> سيتم حذف ('
					el += $(this).attr('data-name').replace('_',' ') + ')</li>';
				} else {
					console.log(check_pro_cat);
					let elfiell='<ul>';
					for(let i=0 ; i < check_pro_cat.length; i++){
						elfiell += '<li class="text-left text-danger">'+ check_pro_cat[i][1]+ ' </li>';
					}
					elfiell +='</ul>';
					el1 += '<li class="text-left text-danger"> لايمكن حذف ('
					el1 += $(this).attr('data-name').replace('_',' ') + ') لإحتوائة على الاصناف التالية :- ' + elfiell +'</li>';
				 
				}
                
				
            };
			
        });
		if (el1 !== ''){
				let el2 =''; 
				el2 ='<li><a class="text-center">لايمكن حذف الاقسام التالية</a><ul>';
				el2 += el1;
				if(el !== ''){
					el2+= '</ul></li>';
					el2+= '<a class="text-center"> وسوف يتم حذف الاقسام التالية </a>';
					el2+= '<li><a class="text-center"> وسوف يتم حذف الاقسام التالية </a><ul>';
				}
					el = el1 + el + '</ul></li>';
		}
        var delParams = {}
        delParams['CONTROL']     = 'delete'+Go;
        delParams[Go+'_id']      = id.join();
        
        Swal.fire({
            title: 'هل انت واثق من حذف البيانات التالية؟',
            html: '<div class=card-body><ul>' + el + '</ul></div><div class=swal2-validation-message id=swal2-validation-message style=font-size:14px; display: flex; margin-left: -20px; margin-right: -20px;> فى حال تأكيد الحذف لم يتم استعادة البيانات مرة اخرى</div>',
            type: 'warning',
            allowOutsideClick: false,
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'تأكيد الحذف',
            cancelButtonText: 'إغلاق',
            footer: ''

        }).then((result) => {
                if (result.value){
                   let data_return = sendRequest(urlControl,delParams);
                   if(data_return > 0 ){
                        
                        Toast.fire({
                            position: 'top-end',
                            type: 'success',
                            title: '<spen class=bg-warning>نجح تنفيذ الاجراء</spen>',
                            html : '<spen class=text-white6>تم حذف البيانات المحدده </spen>',
                            customClass: {
                                //popup:' text-white',
                                text: 'text-black'
                            },
                            showConfirmButton: false,
                            timer: 000
                        });
                        location.reload();
                   } else {
                       Toast.fire({
                            position: 'top-end',
                            type: 'erorr',
                            title: '<spen class=text-white>حدث خطاء<spen>',
                            html : '<spen class=text-white>لم يتم الحذف ERROR : '+data_return+'<spen>',
                          //  text : 'لم ننجح فى تنفيذ اجراء حذف البيانات كود الخطاء : '+ data_return,
                            customClass: {
                                popup:'bg-danger bg-gradient text-white',
                                text: 'text-white'
                            },
                            showConfirmButton: false,
                            timer: 7500
                        });
                   }
                } else {
                    Toast.fire({
                        position: 'top-end', 
                            title: '<spen class=text-white>تم الإلغاء<spen>',
                            html : '<spen class=text-white>جميع البيانات امنه<spen>',
                            customClass: {
                                popup:'bg-warning bg-gradient text-white'
                            },
                            showConfirmButton: false,
                            timer: 7500
                    })
                } 
            })
        $('tr :checkbox').each(function() {
                this.checked = false;
                $('#btnDelete').prop( 'disabled', true );
        });
        if($('#tree input:checkbox').length > 0 ){
			$('#tree input:checkbox').each(function() {
		        $(this).remove();
			});
			
        } 
	});*/ //end btn delete

	$(document).on('click', '#btnDelete', function () {
	    const treeCheckboxes = $('#tree input:checkbox');
	    
	    if (treeCheckboxes.length === 0) {
	        $('.list-group-item').each(function () {
	            const $item = $(this);
	            const id = $item.attr('id');
	
	            if (id != 1) {
	                const dataName = $item.text().trim().replace(' ', '_');
	                const checkbox = `<input id="${id}" data-name="${dataName}" type="checkbox">`;
	                $item.append(checkbox);
	            }
	        });
	
	        Swal.fire('يمكن الآن اختيار قسم أو أكثر للحذف ثم الضغط مرة أخرى على زر الحذف');
	        return;
	    }
	
	    if ($('#tree input:checkbox:checked').length === 0) {
	        treeCheckboxes.remove();
	        return;
	    }
	
	    let idsToDelete = [];
	    let removableHtml = '';
	    let nonRemovableHtml = '';
	
	    $('#tree input:checkbox:checked').each(function () {
	        const $checkbox = $(this);
	        const id = $checkbox.attr('id');
	        const dataName = $checkbox.attr('data-name').replace('_', ' ');
	
	        const associatedCategories = sendRequest(urlControl, { CONTROL: 'getdropProCat', ID: id });
	
	        if (associatedCategories.length === 0) {
	            idsToDelete.push(id);
	            removableHtml += `<li class="text-left">سيتم حذف (${dataName})</li>`;
	        } else {
	            let associatedItemsHtml = '<ul>';
	            associatedCategories.forEach(category => {
	                associatedItemsHtml += `<li class="text-left text-danger">${category[1]}</li>`;
	            });
	            associatedItemsHtml += '</ul>';
	
	            nonRemovableHtml += `
	                <li class="text-left text-danger">
	                    لا يمكن حذف (${dataName}) لإحتوائه على الأصناف التالية: ${associatedItemsHtml}
	                </li>`;
	        }
	    });
	
	    let confirmationHtml = '';
	    if (nonRemovableHtml) {
	        confirmationHtml += `
	            <li>
	                <a class="text-center">لا يمكن حذف الأقسام التالية</a>
	                <ul>${nonRemovableHtml}</ul>
	            </li>`;
	    }
	    if (removableHtml) {
	        confirmationHtml += `
	            <li>
	                <a class="text-center">سيتم حذف الأقسام التالية</a>
	                <ul>${removableHtml}</ul>
	            </li>`;
	    }
	
	    const deleteParams = {
	        CONTROL: `delete${Go}`,
	        [`${Go}_id`]: idsToDelete.join(',')
	    };
	
	    Swal.fire({
	        title: 'هل أنت واثق من حذف البيانات التالية؟',
	        html: `
	            <div class="card-body">
	                <ul>${confirmationHtml}</ul>
	            </div>
	            <div class="swal2-validation-message" style="font-size:14px; margin-left: -20px; margin-right: -20px;">
	                في حال تأكيد الحذف لن يتم استعادة البيانات مرة أخرى
	            </div>`,
	        icon: 'warning',
	        allowOutsideClick: false,
	        showCancelButton: true,
	        confirmButtonColor: '#3085d6',
	        cancelButtonColor: '#d33',
	        confirmButtonText: 'تأكيد الحذف',
	        cancelButtonText: 'إغلاق',
	    }).then(result => {
	        if (result.isConfirmed) {
	            const dataReturn = sendRequest(urlControl, deleteParams);
	
	            if (dataReturn > 0) {
	                Toast.fire({
	                    position: 'top-end',
	                    icon: 'success',
	                    title: '<span class="bg-warning">نجح تنفيذ الإجراء</span>',
	                    html: '<span>تم حذف البيانات المحددة</span>',
	                    showConfirmButton: false,
	                    timer: 3000
	                });
	                location.reload();
	            } else {
	                Toast.fire({
	                    position: 'top-end',
	                    icon: 'error',
	                    title: '<span>حدث خطأ</span>',
	                    html: `<span>لم يتم الحذف. الخطأ: ${dataReturn}</span>`,
	                    customClass: {
	                        popup: 'bg-danger bg-gradient text-white'
	                    },
	                    showConfirmButton: false,
	                    timer: 7500
	                });
	            }
	        } else {
	            Toast.fire({
	                position: 'top-end',
	                title: '<span>تم الإلغاء</span>',
	                html: '<span>جميع البيانات آمنة</span>',
	                customClass: {
	                    popup: 'bg-warning bg-gradient text-white'
	                },
	                showConfirmButton: false,
	                timer: 7500
	            });
	        }
	    });
	
	    // تنظيف جميع مربعات الاختيار بعد الإجراء
	    $('tr :checkbox').prop('checked', false);
	    $('#btnDelete').prop('disabled', true);
	
	    if (treeCheckboxes.length > 0) {
	        treeCheckboxes.remove();
	    }
	});
});