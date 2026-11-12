
/*$(document).ready(function () {
     $('.nav-link').click(function() { 
           $('.nav-link').removeClass('active'); 
            var $this = $(this); 
            if (!$this.hasClass('active')) { 
                $this.addClass('active'); 
                } //e.preventDefault(); 
        }); 
    });*/
$(document).ready(function () {
                 var x = $('#pagelink').html();
                if (x !== "cp_admin"){
                 $('.nav-link').removeClass('active'); 
                 $('a[href=?Go=" + x +"]').addClass('active');
              $("active").parent().addClass('active');
            alert(x + document.getElementsByClassName()            );
                }
              });