
function justdoc (passpages){
  
  let x =document.getElementById('justcontent');
    if (x!==''){
      x.innerHTML=''
  }
  
  $('#justcontent').load('pages/'+ passpages + '.just',function(res,stat,xhr){
    if(stat == "error"){
        $('#justcontent').load('pages/err404.just');
    };
  }); 
};


$(function justmenu (){
  let x =document.getElementById('justmenu').innerHTML;
    if (x.toString !== ""){
  
 
  $('#justmenu').load('pages/menus.just',function(res,stat,xhr){
    if(stat == "error"){
        $('#justmenu').load('pages/err404.just');
    };
  
  }); 
    };
});
function rqpost(){
	alert("send post")
};

