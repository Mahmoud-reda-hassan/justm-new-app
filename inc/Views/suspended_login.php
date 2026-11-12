<?php
?>
<div class="error-page m-5"style="">
				<h2 class="headline text-danger">STOP</h2>
				<div class="error-content m-5">
					<h3><i class="fas fa-exclamation-triangle text-danger"></i> تم ايقاف حسابك مؤقتاً .</h3>
					<p>
				 تم ايقاف حسابك الشخصى لاسباب امنيه .
                 <br>
                 من فضلك قم بالتواصل مع الادارة لمعرفة اسباب الايقاف .. وفى حال تفعيل حسابك 
					<a onclick="logout()" href=""> 
					قم بتسجيل الخروج من هنا
					</a> 
					وحاول تسجيل الدخول مرة اخرى
					</p>
					<form class="search-form">
						<div class="input-group">
							<div class="input-group-append">
								<button type="submit" name="submit" class="btn btn-warning"><i class="fas fa-search"></i>
								</button>
							</div>
							<input type="text" name="search" class="form-control" placeholder="Search">
						</div>
					</form>
				</div>

			</div>
			<script>
			        function logout() {
                        $.get('', {logout:''});
                    }
			</script>