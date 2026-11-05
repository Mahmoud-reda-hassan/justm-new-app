

function swal({title = '', type = '', html = '', allowOutside = false} = {}){
Swal.fire({
            title	        	: SwalTitle,
            html	        	: x,
            type	        	: '',
            allowOutsideClick	: !!allowOutside,
            showCancelButton	: true,
            confirmButtonColor	: '#3085d6',
            cancelButtonColor	: '#d33',
            confirmButtonText	: 'حفظ',
            cancelButtonText	: 'إغلاق',
            focusConfirm        : true,
            onOpen: function(){
                //Run JavaScript when rendering for sweetalert2
            }, 
            preConfirm: function () {
                //Execution of JavaScript codes when pressing the save button
            }
        }).then((result) => {
            //Final crown after executing the save code
        });
}