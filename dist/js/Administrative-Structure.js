$(document).ready(function () {
    //عرض بيانات المنتجات بالجدول
    table = $('#tbl').DataTable({
        scrollY : '70vh',
        scrollX : true,
        processing: true,
        serverSide: true,
        columns: [
            {targets: 0,orderable: false,searchable: false,title: '<input id=select-all type=checkbox>'},
            {targets: 1,className:'fild f1',title: 'اسم الوظيفة'},
            {targets: 2,title: 'تحرير',orderable: false}
        ],
        columnDefs: [
            {
                targets: ['_all'],
                className: 'mdc-data-table__cell text-nowarp text-center',
            },
        ],
        ajax: {url:`${urlApi}?CONTROL=getlistUserGroup`,
            type: 'GET',
            async: true,
        },
	    
        
    });
    //tblGET(urlApi+'?CONTROL=getlistUserGroup');
    $(document).on('click',
        '#btnNew',
        function () {
            let title="",
				html="",
				ajaxpar = {"CONTROL":"","data-id":$(this).attr('data-id'),"uname":$(this).attr('data-name'),"script" :{'Admin':{}}},
				permArr = sendRequest(urlControl,params={'CONTROL':'permissionArray','data_id':$(this).attr('data-id')}),
				name="";
            if($(this).attr('data-id')=="new"){
                title="اضافة وظيفة جديده";
                ajaxpar["CONTROL"] = "AddUserGroup";
            } else {
                title= "تعديل وظيفة";
                name = $(this).attr('data-name');
                ajaxpar["CONTROL"] = "updateUserGroup";
            }
          
			html = `<div class="form-group text-left">
                        <label class="text-left">من فضلك ادخل الاسم الوظيفة</label>
        			    <input id="USER_GRUOP_NAME" type="text" class="form-control req" value="${name}" placeholder="اسم الوظيفة">
        			</div>
        			<div class='form-group text-left'>
                        <label class='text-left'>من فضلك قم بتحديد الصلاحيات :</label>
        			    <div>${permArr}</div>
        			</div>`;
			Swal.fire({
				title		        : title ,
				html		        : html ,
				//type		        : '',
				allowOutsideClick	: false,
				showCancelButton	: true,
			//	confirmButtonColor	: '#3085d6',
			//	cancelButtonColor	: '#d33',
				confirmButtonText	: 'حفظ',
				cancelButtonText	: 'إلغاء',
				scrollbarPadding    : true,
				showLoaderOnConfirm : true,
				focusConfirm		: true,
				didOpen: () => {
					$("#USER_GRUOP_NAME").change(function(){
						if(! $(this).val()){
							$(this).addClass('is-invalid');
						} else {
							$(this).addClass('is-valid');
						}
					});
					
				}, 
				preConfirm: function (){
		            let ti=0;
		            $('#ulPerm li .q').each(function(){
		                
					    let e = $(this),
		                    k1=e.attr('data-k1'),
		                    k2=e.attr('data-k2');
						if(!ajaxpar['script']['Admin'][k1]){
							ajaxpar['script']['Admin'][k1]={};
						}
		                if(e.is(':checked')){
		                    ajaxpar['script']['Admin'][k1][k2]=true;
							ti++;
		                } else {
		                    ajaxpar['script']['Admin'][k1][k2]=false;
		                }
		            });
					ajaxpar['uname'] = $("#USER_GRUOP_NAME").val();
                    let msg=""
					if(ajaxpar['uname'] === ""){
					    msg+="<li>من فضلك قم بإدخال اسم الوظيفة .</li>";
					}
					if(ti===0){
					    msg+="<li>من فضلك قم بتحديد صلاحية واحده على الاقل</li>";
					}
					if(msg!==""){
					    Swal.showValidationMessage("<ul>"+msg+"</ul>");
					}
					let datareturn = sendRequest(urlControl,ajaxpar)
					if(datareturn[2]=="23000"){
					    Swal.showValidationMessage("هناك وظيفة اخرى مسجله بنفس الاسم");
					}
					return datareturn;
				}
			}).then((result) => {
				if(result.value){
					table.ajax.reload();
                    Toast_fire(result.value);
				};
			});
    });
    $(document).on('click',
        '#btnDelete',
        function () {
            //check_permission();
            let id = [];
            var el = '';
            $('tbody :checked').each(function () {
                if ($(this).is(':checked') && $(this).attr('id') !== 'select-all') {
                    id.push($(this).attr('id'));
                    el += '<li class=text-left>' +$(this).parents('tr').children().eq(1).text() +'</li>';
                };
            });
            var delParams = {}
            delParams['CONTROL'] = 'deleteUserGroup';
            delParams['data-id'] = id.join();
            Swal.fire({
                title: 'هل انت واثق من حذف البيانات التالية؟',
                html: '<div class=card-body><ul>' + el + '</ul></div><div class="swal2-validation-message"  style="font-size:14px; display: flex; margin: 0 -30px 0 -20px;"> فى حال تأكيد الحذف لم يتم استعادة البيانات مرة اخرى</div>',
                icon: 'question',
                allowOutsideClick: false,
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'تأكيد الحذف',
                cancelButtonText: 'إغلاق',
                focusConfirm		: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    let x = sendRequest(urlControl, delParams)
                    if (x[0] === "s") {
                        table.ajax.reload();
                        Toast_fire(x);
                    } else if (x[0] == 'e' && x[2] == "23000") {
                        Toast_fire(['e','خطاء الوظيفة مرتبطه بمستخدمين ولا يمكن حذفها']);
                    } else {
                        Toast_fire(x);
                    }
                }
            });
            $('tr :checkbox').each(function() {
                this.checked = false;
                $('#btnDelete').prop('disabled', true);
            });
			
			
    });
});