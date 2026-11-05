<?php
	include $_SERVER["DOCUMENT_ROOT"].'/inc/Forms/ElementHtml.php';
    //array_push($perm->js_page,"Countries.js");
	$geturl = trim($_GET['Go']);
    $swl_js="$('.select2').select2({
			    selectionCssClass:'form-control',
                width:'100%',
                dir:'rtl'
             });";
    $perm->privetjs .= "
//-----------------COUNTRY PAGE--------------
	$(document).ready(function () {
        Columns = ".$elementHtml[$_GET['Go']]['Columns']."
	tblGET(urlApi+'?CONTROL=getlist". $geturl ."')
	});";
?>
<div class="card">
    <div class="card-body">
        <div>
            <table id="tbl" class="cell-border table table-striped table-bordered text-center text-nowrap display" style="width:100%">
            </table>
        </div>
    </div>

</div>
  