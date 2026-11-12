<?php
include $_SERVER["DOCUMENT_ROOT"].'/inc/Forms/addressOptionForm.php';
$elementHtml =  array(
	"Countries" => array(
		"Columns" => "[{targets:0,orderable:false,searchable:false,title:'<input id=select-all type=checkbox>',orderable: false },
						{targets:1,className:'fild f1',title:'الاسم'},
						{targets:2,className:'fild f2',title:'name'},
						{targets:3 ,title: 'تحرير', orderable: false}];",
		"HTML" => "<div class='form-group text-left'>
						<label class='text-left'>الاسم</label>
						<input id='CO_NAME' type='text' class='form-control required' value=$(this).attr('data-name) placeholder='من فضلك ادخل الاسم'>
					</div>
					<div class='form-group text-left'>
						<label class='text-left'>Name</label>
						<input id='CO_NAME_EN' type='text' class='form-control required' value=$(this).attr('data-nameen) placeholder='Please enter name'>
					</div>"
	),
	"Gov" => array(
		"Columns" => "[{targets:0,title:'<input id=select-all type=checkbox>',orderable: false},
						{targets:1,className:'fild f1',title:'الاسم'},
						{targets:2,className:'fild f2',title:'name'},
						{targets:3,className:'fild f3',title:'تابع لـ'},
						{targets:4,title: 'تحرير',orderable: false }];",
		"HTML" => "<div class='form-group text-left'>
						<label class='text-left'>فضلك ادخل الاسم</label>
						<input id='GOV_NAME' type='text' class='form-control required' value='' placeholder=''>
					</div>
					<div class='form-group text-left'>
						<label class='text-left'>Name</label>
						<input id='GOV_NAME_EN' type='text' class='form-control required' value='' placeholder='Please enter name'>
					</div>
					<div class='form-group text-left'>
						<label>تابع لـ</label>
						<select id='COUNTRY_ID' class='form-control select2 required'>
					</div>"
	),
	"City" => array(
		"Columns" => "[{targets:0,title:'<input id=select-all type=checkbox>',orderable: false},
						{targets:1,className:'fild f1',title:'الاسم'},
						{targets:2,className:'fild f2',title:'name'},
						{targets:3,className:'fild f3',title:'تابع لـ'},
						{targets:4,title: 'تحرير',orderable: false }]",
		"HTML" => "<div class='form-group text-left'>
						<label class='text-left'>الاسم</label>
						<input id='CI_NAME' type='text' class='form-control required' placeholder='من فضلك ادخل الاسم'>
					</div>
					<div class='form-group text-left'>
						<label class='text-leftName'></label>
						<input id='CI_NAME_EN' type='text' class='form-control required'  placeholder='Please enter name'>
					</div>
					<div class='form-group text-left'>
						<label>تابع لـ</label>
						<select id='GOV_ID' class='form-control select2 required'>
					</div>"
	),
	"LocalShipping"=> array(
		"Columns" => "[{targets:0,title:'<input id=select-all type=checkbox>',orderable: false},
						{targets:1,className:'fild f1',title:'الاسم'},
						{targets:2,className:'fild f2',title:'name'},
						{targets:3,className:'fild f3',title:'سعر الشحن'},
						{targets:4,className:'fild f4',title:'مهلة التنفيذ(باليوم)'},
						{targets:5,className:'fild f5',title:'عدد المدن / المناطق'},
						{targets:6,title: 'تحرير',orderable: false }]",
		"HTML" => "<div class='form-group text-left'>
						<label class='text-left'>الاسم</label>
						<input id='ZONE_NAME' type='text' class='form-control required' placeholder='من فضلك ادخل الاسم'>
					</div>
					<div class='form-group text-left'>
						<label class='text-left'>Name</label>
						<input id='ZONE_NAME_EN' type='text' class='form-control required' placeholder='Please enter name'>
					</div>
					<div class='form-group text-left'>
						<label class='text-left'>اقل سعر للشحن</label>
						<input id='SHIPPING_LOWEST' type='Number' class='form-control required' placeholder='0'>
					</div>
					<div class='form-group text-left'>
						<label class='text-left'>اعلى سعر للشحن</label>
						<input id='SHIPPING_RATE' type='Number' class='form-control required' placeholder='0'>
					</div>
					<div class='form-group text-left'>
						<label>المناطق</label>
						<select id='CI_ZONE_ID' class='form-control select2 required' multiple='' >
					</div>"
	),
	"Enterprise" => array(
		"Columns" => "",
		"HTML" => "<div class='row'>
						<div class='col-sm-6'>
							<div class='form-group'>
								<label for='COM_NAME'>اسم المؤسسة</label>
								<input type='text' class='form-control enpr required' id='COM_NAME' placeholder='أدخل اسم المؤسسة'>
							</div>
							".$addressOption_HTML."
							<div class='form-group'>
								<label for='comEmail'>العنوان</label>
								<textarea type='text' row='4' class='form-control enpr required' id='COM_ADDRESS' placeholder='Enter email'></textarea>
							</div>
						</div>
						<div class='col-sm-6'>
							<div class='form-group'>
								<label for='comEmail'>بريد الكترونى</label>
								<input type='email' class='form-control mail enpr required' id='COM_EMAIL' required placeholder='أدخل البريد الإلكترونى'>
							</div>
							<div class='form-group'>
								<label for='comEmail'>الهاتف</label>
								<input type='text' class='form-control phone enpr required' id='COM_TEL' placeholder='من فضلك ادخل رقم الهاتف الأرضى' required>
							</div>
							<div class='form-group'>
								<label for='comEmail'>فاكس</label>
								<input type='text' class='form-control fax enpr required' id='COM_FAX' placeholder='منمنمن فضلك أدخل رقم الفاكس'>
							</div>
							<div class='form-group'>
								<label for='comEmail'>محمول</label>
								<input type='text' class='form-control mobile enpr required' id='COM_MOBILE' placeholder='من فضلك أدخل رقم الهاتف المحمول'>
							</div>
						</div>
					</div>"
					)
		);
                        

    
?>