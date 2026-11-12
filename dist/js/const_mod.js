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





In JavaScript, you can include one file inside another file using the `import` statement or the `require` function, depending on the environment you are working in.

If you are working in a Node.js environment, you can use the `require` function to include a file inside another file. For example, let's say you have two files, `file1.js` and `file2.js`. To include the contents of `file1.js` inside `file2.js`, you can use the `require` function like this:

```
// file1.js
const message = "Hello, World!";
module.exports = message;
```

```
// file2.js
const message = require('./file1');
console.log(message);
```

In this example, we use the `require` function to include the contents of `file1.js` inside `file2.js`. We assign the exported value of `file1.js` to a variable called `message` in `file2.js`.

If you are working in a web environment, you can use the `import` statement to include a JavaScript file inside another JavaScript file. For example, let's say you have two files, `file1.js` and `file2.js`. To include the contents of `file1.js` inside `file2.js`, you can use the `import` statement like this:

```
// file1.js
export const message = "Hello, World!";
```

```
// file2.js
import { message } from './file1.js';
console.log(message);
```

In this example, we use the `import` statement to include the contents of `file1.js` inside `file2.js`. We use the curly braces syntax to import the `message` variable from `file1.js`, and we specify the relative path to `file1.js`.