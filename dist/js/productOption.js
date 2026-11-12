//-----------------ProductOption PAGE--------------
$(document).ready(function () {
	Columns = [{targets:0,orderable:false,searchable:false,title:'<input id=select-all type=checkbox>',orderable: false },
		{targets:1,className:'col-6 fild f1',title:'الصفة'},
		{targets:2,className:'col-6 fild f2',title:'القيمة'},
		{targets:3 ,title: 'تحرير', orderable: false}];
	tblGET('../../inc/Api.php?CONTROL=getlist' + Go);
//============= new and update =======================================================
    $(document).on('click','#btnNew', function (){
        let x ='',
            v,
            selectoption,
            mode,
            SwalTitle,
            objreq,
            obj={};
        var data =$(this),
            msg ='';
        
        let ajaxpar ={};
        let swalhtml = '';
        if(data.attr('data-id')  !== 'new') {
            SwalTitle = 'تحديث بيانات ';
            ajaxpar['CONTROL']='update'+Go;
            ajaxpar['data_id'] = data.attr('data-id');
            msg = 'تم تغير ' +data.attr('data-namear') + ' ' +  data.attr('data-nameen') + ' إلى ';
            if(data.attr('data-option_value_id') !== ''){
                selectoption = {'1': SwalTitle + ' صفة ',
                                '2': SwalTitle + 'قيمة لصفة مسجلة مسبقا'}
            } else {
                selectoption = {'1': SwalTitle + ' صفة '}
            }
        } else {
            SwalTitle = 'اضافة ';
            ajaxpar['CONTROL'] = 'add'+Go;
            msg = 'تم اضافة ';
            selectoption = {'1': SwalTitle + ' صفة ',
                            '2': SwalTitle + 'قيمة لصفة مسجلة مسبقا'}
        };
		(async () => {
			var Queue_val_array ={};
			const steps = ['1', '2']
			const Queue = Swal.mixin({
			  progressSteps: steps,
			  confirmButtonText: 'التالى',
			  reverseButtons: true,
			  showCloseButton: true,
              focusConfirm: true,
              allowOutsideClick: false,
			  // optional classes to avoid backdrop blinking between steps
			  showClass: { backdrop: 'swal2-noanimation' },
			  hideClass: { backdrop: 'swal2-noanimation' }
			})
			await Queue.fire({
				title: 'إختار نوع الاجراء',
				currentProgressStep: 0,
				input: 'select',
				inputOptions: selectoption ,
				inputPlaceholder: 'من فضلك إختار نوع الإجراء', 
				inputValidator: (value) => {
					return new Promise((resolve) => {
					  if (value !== '') {
						if(value == 1){
						    x    =`<div class='form-group text-left'><label>نوع الصفة</label>`;
						    x   +=`<select id='OPTION_TYPE' class='form-control required'><option value='1' selected>قائمة منسدلة</option></select></div>`;
						    obj   = {'el':'input','id':'OPTION_NAME','lbl':'ادخل اسم الصفة','val':data.attr('data-option_name')};
                            x    += htmlel(obj);
							ajaxpar['CONTROL']+= 'Group';
						} else if(value == 2){
						    obj  = {'el':'select','id':'OPTION_ID','lbl':'تابع لـ','place':'','val':data.attr('data-id'),params:'ProductOptionGroup'};
						    x   += htmlel(obj);
                            obj  = {'el':'input','id':'OPTION_VALUE','lbl':'ادخل قيمة الصفة','val':data.attr('data-option_value')};
                            x   += htmlel(obj);
							ajaxpar['CONTROL'] += 'Value';
							ajaxpar['OPTION_VALUE_ID'] = data.attr('data-option_value_id');
						}
						resolve()
					  } else {
						resolve('من فضلك إختار نوع الاجراء المراد تنفيذه :)')
					  }
					})
				 },
				// optional class to show fade-in backdrop animation which was disabled in Queue mixin
				showClass: { backdrop: 'swal2-noanimation' },
			})
			await Queue.fire({
			    didOpen: ()=>{
				    if(x==''){
				        swal.close();
				    } else {
				        if( data.attr('data-id') != 'new' ){
							$('#OPTION_ID').val(data.attr('data-id'));
							$('#OPTION_ID').attr('disabled',true);
						}
				    }
				},
				currentProgressStep: 1 ,
				confirmButtonText: 'حفظ',
				html: x,
				preConfirm: function () {
					let msgerr = '';
					let v = 0;
					$('.required').each(function() {
						if(!$(this).val()){
							v++;
							$(this).addClass('is-invalid');
						} else {
							$(this).removeClass('is-invalid');
							ajaxpar[$(this).attr('id')] ='' + $(this).val();
							msg += $(this).val() +' ';
						}
					});
					if (v>0){
						msgerr += 'جميع الحقول مطلوبة برجاء ملئ جميع الحقول';
					} else {
						//console.log(ajaxpar);
						objreq =  sendRequest(urlControl,ajaxpar);
						if(objreq === 'error23000'){
							$('.required').addClass('border-danger');
							msgerr += 'البيانات التى تم  ادخلها مسجله مسبقا بقواعد البيانات';
						}
					}
					if(msgerr!== ''){
						Swal.showValidationMessage(msgerr);
					}
					return objreq;
				} 
			}).then((result) => {
				$('td :checkbox').checked = false;
				if(typeof result.value =='number' && result.value > 0){
					table.ajax.reload();
                    Swal.fire('تم حفظ البيانات بنجاح!', '', 'success')
                } else if (typeof result.value =='number' && result.value==0) {
                    Swal.fire('انتبه','لم تقم بتغير اى بيانات لحفظها', 'info')
                } else if(typeof result.value =='string') {
                    Swal.fire('حدث خطاء',result.value,'warning');
                } else {
                    
                }
			})
        })();
	});//end btn new and update
//================ delete ============================================================
    $(document).on('click','#btnDelete', function (){	

		let data_return,
			group_id = [],
			option_value_id = [],			
			selectoption = {'1':'حذف الصفات',
                            '2':'حذف القيم'};
		var el_option='',
			el_value='',
			el ='',
			delParams = {};
        $('input:checkbox:checked').each(function () {
            if( $(this).is(':checked') && $(this).attr('id') !== 'select-all' ){
				group_id.push($(this).attr('id'));
				el_option += '<li class=text-left>' +$(this).parents('tr').children().eq(1).text();
				if( $(this).attr('option_value_id') != 0){
					option_value_id.push($(this).attr('option_value_id'));
					el_value  += '<li class=text-left>' + $(this).parents('tr').children().eq(2).text() + '</li>';
				} 
            };
        }); 
        
        
		(async () => {
			const steps = ['1', '2']
			const Queue = Swal.mixin({
			  progressSteps: steps,
			  confirmButtonText: 'التالى',
			  reverseButtons: true,
			  showCloseButton: true,
              focusConfirm: true,
              allowOutsideClick: false,
			  // optional classes to avoid backdrop blinking between steps
			  showClass: { backdrop: 'swal2-noanimation' },
			  hideClass: { backdrop: 'swal2-noanimation' }
			})
			await Queue.fire({
				title: 'إختار نوع الاجراء',
				currentProgressStep: 0,
				input: 'select',
				inputOptions: selectoption ,
				inputPlaceholder: 'من فضلك إختار نوع الإجراء', 
				inputValidator: (value) => {
					return new Promise((resolve) => {
					  if (value !== '') {
						if(value == 1){
							delParams['CONTROL']     = 'delete'+Go+'Group';
							delParams[Go+'_id']      = group_id.join();
							el = el_option;
						} else if (value == 2) {
							delParams['CONTROL']     = 'delete'+Go+'Value';
							delParams[Go+'_id']      = option_value_id.join();
							el = el_value;
						}
						resolve()
					  } else {
						resolve('من فضلك إختار نوع الاجراء المراد تنفيذه :)')
					  }
					})
				 },
				// optional class to show fade-in backdrop animation which was disabled in Queue mixin
				showClass: { backdrop: 'swal2-noanimation' },
			})
			await Queue.fire({
				title: 'هل انت واثق من حذف البيانات التالية؟',
				currentProgressStep: 1,
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
						console.log(delParams);
					   let data_return = sendRequest(urlControl,delParams);
					   if(data_return > 0 ){
							table.ajax.reload();
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
								timer: 5000
							});
					   } else {
						   Toast.fire({
								position: 'top-end',
								type: 'warning',
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
			})()
        $('tr :checkbox').each(function() {
                this.checked = false;
                $('#btnDelete').prop( 'disabled', true );
        });
	}); 
})