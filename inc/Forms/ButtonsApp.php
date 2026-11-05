<?php
    
    
   
/* 
    
    if(isset($_GET["Go"]) && $_GET['Go'] === 'Countries' || 
       isset($_GET["Go"]) && $_GET['Go'] === 'Gov' ||
       isset($_GET["Go"]) && $_GET['Go'] === 'City' ||
       isset($_GET["Go"]) && $_GET['Go'] === 'LocalShipping' ||
	   isset($_GET["Go"]) && $_GET['Go'] === 'Branches' ||
	   isset($_GET["Go"]) && $_GET['Go'] === 'Warehouse' || 
	   isset($_GET["Go"]) && $_GET['Go'] === 'ProductOption' || 
	   isset($_GET["Go"]) && $_GET['Go'] === 'Administrative-Structure'
	  ){
       echo '
                <button id="btnNew" data-id="" class="btn btn-app-tools text-success" data-id="new">
                    <i class="fas fa-plus"></i> إضافة
                </button>
                <button id="btnDelete" class="btn btn-app-tools text-danger" disabled>
                    <i class="fas fa-trash"></i> حذف
                </button>
            ';
	
	} else if(isset($_GET["Go"]) && (
			   $_GET['Go'] === 'Enterprise' || 
			   $_GET['Go'] === 'NewOrder5' || 
			   $_GET['Go'] === 'EditOrder5')) {
        echo '
                <button id="btnSave" data-id="new" class="btn btn-app-tools text-success" disabled >
                    <i class="fas fa-save"></i>حفظ
                </button>
                <button id="btnEdit" class="btn btn-app-tools text-info ">
                    <i class="fas fa-edit"></i>تعديل
                </button>
				<button id="btnCancel" class="btn btn-app-tools text-danger " disabled >
                    <i class="fas fa-times"></i>إلغاء
                </button>
            ';
    } else if(isset($_GET["Go"]) && $_GET['Go'] === 'Product-Categories' || 
              isset($_GET["Go"]) && $_GET['Go'] === 'ProductOption' ) {
        echo '
                <button id="btnNew" data-id="" class="btn btn-app-tools text-success" data-id="new">
                    <i class="fas fa-save"></i>إضافة
                </button>
                <button id="btnNew" data-id="-1" class="btn btn-app-tools text-primary">
                    <i class="fas fa-edit"></i>تعديل
                </button>
				<button id="btnDelete" class="btn btn-app-tools text-danger">
                    <i class="fas fa-trash"></i>حذف
                </button>
            ';
    } else if(isset($_GET["Go"]) && $_GET['Go'] === 'Products' || isset($_GET["Go"]) && $_GET['Go'] === 'Users'){
        echo '
                <button id="btnSave" data-id="" class="btn btn-app-tools text-success" data-id="new">
                    <i class="fas fa-save"></i> حفظ
                </button>
                <button id="btnNew" data-id="" class="btn btn-app-tools text-info new" data-id="new">
                    <i class="fas fa-plus"></i> إضافة
                </button>
                <button id="btnDelete" class="btn btn-app-tools text-danger " disabled>
                    <i class="fas fa-trash"></i> حذف
                </button>
                <button id="btnCancel" class="btn btn-app-tools text-warning " style="display:none;" >
                    <i class="fas fa-times"></i> إلغاء
                </button>
            ';
    }
    echo '
        <button id="btnSave" data-id="" class="btn btn-app-tools text-success" style="display:none;">
             <i class="fas fa-save"></i> حفظ
        </button>
        <button id="btnNew" data-id="" class="btn btn-app-tools text-info new" style="display:none;">
            <i class="fas fa-plus"></i> إضافة
        </button>
        <button id="btnDelete" class="btn btn-app-tools text-danger " style="display:none;" disabled>
            <i class="fas fa-trash"></i> حذف
        </button>
        <button id="btnCancel" class="btn btn-app-tools text-warning " style="display:none;" >
            <i class="fas fa-times"></i> إلغاء
        </button>
        <button id="btnShipp" data-id="" class="btn btn-app-tools btnh text-dark" data-id="new" style="display:none;" >
            <i class="fas fa-truck-fast"></i> 
            <span class="btnname">تسليم</span>
            <span class="badge badge-danger navbar-badge mr-2" ></span>
        </button>
        <button id="btnExchange" data-id="" class="btn btn-app-tools btnh text-dark" data-id="new" style="display:none;" >
            <i class="fas fa-exchange"></i> 
            <span class="btnname">تحويل</span>
            <span class="badge badge-danger navbar-badge mr-2" ></span>
        </button>
        <button id="btnPrint" data-id="" class="btn btn-app-tools btnh text-dark" data-id="new" style="display:none;" >
            <i class="fas fa-print"></i>
            <span class="btnname">طباعة</span>
            <span class="badge badge-danger navbar-badge mr-2" ></span>
        </button>
        <button id="btnQrcode" data-id="" class="btn btn-app-tools btnh text-dark" data-id="new" style="display:none;" >
            <i class="fas fa-barcode"></i>
            <span>باركود</span>
        </button>
        <button id="btnSettlement" data-id="" class="btn btn-app-tools btnh text-dark" data-id="new" style="display:none;" >
            <i class="fas fa-vote-yea"></i>
            <span>تسوية</span>
        </button>
        <button id="btnfilter" data-id="" class="btn btn-app-tools btnh text-dark" data-id="new" style="display:none;" >
            <i class="fas fa-filter"></i>
            <span>فرز</span>
        </button>
        ';
        
        
        
        
        <?php*/
// تعريف المجموعات المختلفة لقيم Go
$group1 = ['Countries', 'Gov', 'City', 'LocalShipping', 'Branches', 'Warehouse', 'ProductOption', 'Administrative-Structure'];
$group2 = ['Enterprise', 'NewOrder', 'EditOrder'];
$group3 = ['Product-Categories', 'ProductOption'];
$group4 = ['Products', 'Users','Clients','Suppliers'];
$group5 = ['ListOrdersMarketer','SupportOrders', 'ListOrdersDelivery', 'ListOrdersDelivered'];
$group6 = ['Orderlist'];
$group7 = ['OrdersCustody','ListOrdersDelivery'];
// دالة لإنشاء الأزرار
function renderButtons($buttons){
    foreach ($buttons as $button) {
        echo sprintf(
            '<button id="%s" class="btn btn-app-tools %s" %s style="%s">
                <i class="%s"></i> 
                <span>%s</span>
                <span class="badge badge-danger navbar-badge mr-2" ></span>
            </button>',
            $button['id'],
            $button['class'],
            $button['attributes'],
            $button['style'],
            $button['icon'],
            $button['label']
        );
    }
}

// التحقق من وجود قيمة Go
if (isset($_GET["Go"])) {
    $go = $_GET["Go"];

    if (in_array($go, $group1)) {
        renderButtons([
            ['id' => 'btnNew', 'class' => 'text-success', 'attributes' => 'data-id="new"', 'icon' => 'fas fa-plus', 'style' =>'', 'label' => 'إضافة'],
            ['id' => 'btnDelete', 'class' => 'text-danger', 'attributes' => 'disabled', 'icon' => 'fas fa-trash','style' =>'', 'label' => 'حذف'],
        ]);
    } elseif (in_array($go, $group2)) {
        renderButtons([
            ['id' => 'btnSave', 'class' => 'text-success btnSave', 'attributes' => 'data-id="new" disabled', 'icon' => 'fas fa-save', 'style'=>'display:none','label' => 'حفظ'],
            ['id' => 'btnEdit', 'class' => 'text-info', 'attributes' => '', 'icon' => 'fas fa-edit', 'style'=>'display:none','label' => 'تعديل'],
            ['id' => 'btnCancel', 'class' => 'text-danger', 'attributes' => 'disabled', 'icon' => 'fas fa-times','style'=>'display:none', 'label' => 'إلغاء'],
        ]);
    } elseif (in_array($go, $group3)) {
        renderButtons([
            ['id' => 'btnNew', 'class' => 'text-success', 'attributes' => 'data-id="new"', 'icon' => 'fas fa-save','style' =>'', 'label' => 'إضافة'],
            ['id' => 'btnNew', 'class' => 'text-primary', 'attributes' => 'data-id="-1"', 'icon' => 'fas fa-edit','style' =>'', 'label' => 'تعديل'],
            ['id' => 'btnDelete', 'class' => 'text-danger', 'attributes' => '', 'icon' => 'fas fa-trash','style' =>'', 'label' => 'حذف'],
        ]);
    } elseif (in_array($go, $group4)) {
        renderButtons([
            ['id' => 'btnSave', 'class' => 'text-success', 'attributes' => 'data-id="new"', 'icon' => 'fas fa-save','style' =>'', 'label' => 'حفظ'],
            ['id' => 'btnNew', 'class' => 'text-info new', 'attributes' => 'data-id="new"', 'icon' => 'fas fa-plus', 'style' =>'','label' => 'إضافة'],
            ['id' => 'btnDelete', 'class' => 'text-danger', 'attributes' => 'disabled', 'icon' => 'fas fa-trash', 'style' =>'','label' => 'حذف'],
            ['id' => 'btnCancel', 'class' => 'text-warning', 'attributes' => 'style="display:none;"', 'icon' => 'fas fa-times', 'style' =>'','label' => 'إلغاء'],
        ]);
    } elseif (in_array($go, $group5)) {
    	renderButtons([
    		['id' => 'btnfilter', 'class' => 'btnh text-dark',   'attributes' => 'data-id="new"', 'icon' => 'fas fa-filter', 'style' =>'','label' => 'فرز']
        ]);
    } elseif (in_array($go, $group6)) {
    	if(isset($_GET["ex"]) && isset($_GET["ty"])){
    		$btnarr=[];
    		$ex = $_GET["ex"]; 
			$ty = $_GET["ty"];
	    	if($ty == 1){
	    		if($ex == 7){
	    			
						$btnarr[] = ["id" => "btnExchange", 	"class" => "btnh text-dark", 'attributes' => 'data-id="new"', 'icon' => 'fas fa-exchange', 'label' => 'تحويل', 'style' => "display:none1;"];
						$btnarr[] = ['id' => "btnPrint", 		'class' => "btnh text-dark", 'attributes' => 'data-id="new"', 'icon' => 'fas fa-print', 'style'=>"display:none1;", 'label' => 'طباعة'];
			        	//	['id' => "btnfilter", 		'class' => "btnh text-dark", 'attributes' => 'data-id="new"', 'icon' => 'fas fa-filter', 'label' => 'فرز', 'style'=>"display:none1;"],
			        
	    		}else if($ex == 8){
	    			
			    		$btnarr[] = ['id' => "btnShipp", 		'class' => "btnh text-dark", 'attributes' => 'data-id="new"', 'icon' => 'fas fa-truck-fast', 'label' => 'تسليم', 'style' => "display:none1;"];
			    		$btnarr[] = ['id' => "btnPrint", 		'class' => "btnh text-dark", 'attributes' => 'data-id="new"', 'icon' => 'fas fa-print', 'style'=>"display:none1;", 'label' => 'طباعة'];
						//	['id' => "btnfilter", 		'class' => "btnh text-dark", 'attributes' => 'data-id="new"', 'icon' => 'fas fa-filter', 'label' => 'فرز', 'style'=>"display:none1;"],
			        
	    		}
	    		
	    	}
	    	$btnarr[]=['id' => "btnfilter", 		'class' => "btnh text-dark", 'attributes' => 'data-id="new"', 'icon' => 'fas fa-filter', 'label' => 'فرز', 'style'=>"display:none1;"];
	    	renderButtons($btnarr);
    	}
    } elseif (in_array($go, $group7)) {
    	renderButtons([
			['id' => "btnQrcode", 		'class' => "btnh text-dark", 'attributes' => 'data-id="new"', 'icon' => 'fas fa-barcode', 'label'=>'باركود', 'style'=>"display:none1;"],
			['id' => "btnfilter", 		'class' => "btnh text-dark", 'attributes' => 'data-id="new"', 'icon' => 'fas fa-filter', 'label' => 'فرز', 'style'=>"display:none1;"],
		]);
    }
    
}
?>