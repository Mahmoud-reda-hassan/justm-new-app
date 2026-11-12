<?php

?>
<footer class="main-footer" <?php if ($perm->Pagelink === "404") { echo 'style="margin-right:0px"'; } ?>>
	<strong>Copyright &copy; 2019-<?php echo date("Y"); ?> <?php echo '<a href="'.$_SERVER['HTTP_HOST'].'">'. $_SERVER["SERVER_NAME"] . '</a>.'; ?></strong>
	All rights reserved.
	<div class="float-right d-none d-sm-inline-block">
		<b>Version</b> 3.0.0-rc.1
	</div>
</footer>
<?php
// REQUIRED SCRIPTS

?>