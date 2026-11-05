const characters_random ='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',
    queryString    = window.location.search,
	urlParams      = new URLSearchParams(queryString),
	Go             = urlParams.get('Go'),
	ptype          = urlParams.get('ty'),
	ordernum       = urlParams.get('on'),
	Exists         = urlParams.get('ex'),
	oService       = urlParams.get('os'),
	ordertype      = ptype,
	logout         = urlParams.get('logout'),
	urlControl     = '../../rest-api/index.php',
	urlApi 	       = urlControl,/////////////
	date           = new Date(),
	userlogin_data = sendRequestGET(`${urlApi}?CONTROL=valSess`),
	addressOpti    = sendRequestGET(`${urlApi}?CONTROL=getSelectOptionCountries`),
	rqof           = sendRequestGET(`${urlApi}?CONTROL=filterOrder`),
    rqFil          = rqof ,/////////////
    orderfilter    = rqof[0],
    existsview     = rqof[1],
	filobj         ={'pType'    :ptype,
                		'orderType'     :{'id': '','val': ''},
                		'orderType'		:{'id': '','val': ''},
                		'shippingType'	:{'id': '','val': ''},
                		'exists'		:{'id': '','val': ''},
                		'statuse'		:{'id': '','val': ''},
                		'category'		:{'id': '','val': ''},
                		'products'		:{'id': '','val': ''},
                		'dateType'		:{'id': '','val': ''},
                		'dateRange'		:{'id': '','val': ''}
                	},
	option1        = {
                		weekday: 'long',
                		year: 'numeric',
                		month: 'long',
                		day: 'numeric'
	                },
	option2        = {
                		year: 'numeric',
                		month: 'numeric',
                		day: 'numeric'
            	    },
	Toast          = Swal.mixin({
                    	  toast: true,
                    	  position: 'top-start',
                    	  iconColor: 'white',
                    	  customClass: {
                    		popup: 'colored-toast '
                    	  },
                    	  didOpen: (toast) => {
                    		toast.addEventListener('mouseenter', Swal.stopTimer)
                    		toast.addEventListener('mouseleave', Swal.resumeTimer)
                    	  },
                    	  showConfirmButton: false,
                    	  timer: 3500,
                    	  timerProgressBar: true,
                    	  showCloseButton: false
	                }),
	wls         = queryString.replace(/&/g,`_`).replace(/=/g,'_').replace('?','_'); 
module.exports={characters_random,
	urlParams ,
	Go,
	ptype,
	ordernum ,
	Exists,
	oService ,
	ordertype ,
	logout ,
	urlControl,
	urlApi ,
	date ,
	userlogin_data ,
	addressOpti ,
	rqof ,
    rqFil ,
    orderfilter ,
    existsview  ,
    filobj,
    option1,
    option2,
    Toast,
    wls
};
