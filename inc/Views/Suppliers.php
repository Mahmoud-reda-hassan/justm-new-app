<?php
?>
<!-- add client form -->
    <div id="addSuppliresform" style="display:none">
        <div class="card">
            <div class="card-body">
                <form onsubmit="return false" method="POST">
                    <div class='row'>
                        <div id="coCli" class='col-sm-6'>
                            <div class="form-group">
                                <label for='COM_NAME'>اسم المورد</label>
                                <input type='text' class='form-control cli req' id="cli_name" name="cliname" placeholder='ادخل اسم المورد'>
                            </div>
                            <div class="form-group">
                                <label for='comEmail'>بريد الكترونى</label>
                                <input type='email' class='form-control email cli' id='cli_email' name="cliemail" placeholder='أدخل البريد الإلكترونى'>
                            </div>
                        
                            <div id="cliTel"></div>
                        </div>
                        <div id="coAd" class='col-sm-6'></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
<!-- edit client data form -->
    <div id="editSupplierstData" style="display:none">
    	
    </div>

<!-- client data view form -->

<div id="SuppliersDataTables" class ="card-body"  style="display:none">
<section>
	<table id="tbl" class="table  table-hover table-striped text-center  ">
	</table>
</section>
</div>
