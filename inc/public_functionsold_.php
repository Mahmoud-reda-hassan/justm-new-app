<?php
session_start();

$loginType;
$pageType;
$path1 = "";
//include ltrim(preg_replace("/[^\/]+/","..",dirname($_SERVER['PHP_SELF']).'/'),'/')."inc/init.php";	
if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'){  
    $path = "https://".$_SERVER['HTTP_HOST'];
}else{  
    $path = "http://".$_SERVER['HTTP_HOST']; 
}// Append the host(domain name, ip) to the URL.   
$dirArrlink = ltrim(preg_replace("/[^\/]+/","..",dirname($_SERVER['PHP_SELF']).'/'),'/');
//require_once $dirArrlink."inc/init.php";
//include "". $dirArrlink . "Languages/AR/SidebarMenu.lang.php";
/*==================================================
    * get_searchfile( File name and extension )  :  This function is used to search for a file or all files
	  or files with a specified extension  and return the entire file directory with the name and  type 
	  of the file in an array  
    *Note: You can use the * symbol instead of the file name, file extension, or both
    *Example: - get_searchfile(index.php)    Find the file index.php .
			  - get_searchfile(*.php)		Find all files with the extension php .
			  - get_searchfile(index.*)		Find all the files named index .
			  - get_searchfile(*.*)			Find all the files
 ===================================================*/
	function get_searchfile($pattern, $flags=0) {
		$files = glob($pattern, $flags);
		foreach (glob(dirname($pattern).'/*', GLOB_NOESCAPE|GLOB_NOSORT) as $dir) {
			$files = array_merge($files, get_searchfile($dir . '/*' . basename($pattern), $flags));
		}
		return $files;
	}
	
	
/*==================================================
    *This function creates an array containing all project files and writes them to the init.php file.
  ===================================================*/
	function set_dirs_files(){
		$initfile = fopen(ltrim(preg_replace("/[^\/]+/","..",dirname($_SERVER['PHP_SELF']).'/'),'/').'inc/init.php','w+');
		$x = '<?php' . "\n\t" . '$dirArr = array();' . "\n\t\t";
		foreach (get_searchfile(ltrim(preg_replace("/[^\/]+/","..",dirname($_SERVER['PHP_SELF']).'/'),'/').'*.*') as $path){
			$path = str_replace(ltrim(preg_replace("/[^\/]+/","..",dirname($_SERVER['PHP_SELF']).'/'),'/'),'',$path);
			$x = $x . '$dirArr[' . '"'. basename($path)  .'"' . '] = ' . '"' . str_replace('./','',$path) . '";' . "\n\t\t";
		}
		fwrite($initfile,$x);
		fclose($initfile);
	}
/*=================================================
   The function returns the requested file link from an array (init)
  ================================================*/
	function get_dir_init($file_name){
		$initfile = $_SERVER["DOCUMENT_ROOT"]. "/inc/init.php";
		if(!file_exists($initfile)|| filesize($initfile) == 0){	//التأكد من وجود ملف init ومساحته
			set_dirs_files();//أعادت تهيئة ملف init
			
		} 
		$path = $_SERVER["DOCUMENT_ROOT"]."/";
		include ''.$initfile;
		$checkFileArr = $dirArr[$file_name];
		$returnx="";
		$returnx = $path;
		if($dirArr[$file_name]!=''){
			$returnx .= $dirArr[$file_name];
		} else {
			set_dirs_files(); //أعادت تهيئة ملف init
 
			if($checkArr == 1  && $checkFileArr!==''){
				$returnx .= $dirArr[$file_name];
			} else {
				$returnx =  "#";
			}
		}
		return $returnx;
	}
/*=================================================
    get_css(css_files) => This function returns links for a batch of files, provided that they are of type css
  =================================================*/
	function get_css($css_array){ 
		$x = "";
		foreach ($css_array as $file_css){
			$dirArrcss = get_dir_init($file_css);
			if(strstr($file_css,".css")==".css"){
				if(strlen($x)==0){
					$x .= "\t";
				} else {
					$x .= "\n\t";
				} 
				$x .= '<link rel="stylesheet" href="' .$path1. $dirArrcss ;
				if ($dirArrcss !=='#') {
					$x .= '?v=';
					$x .= filemtime($dirArrcss);
				}
				$x .= '">';
			}
		}
		return $x;
	}
/*=================================================
   get_js() => This function returns links for a batch of files, provided that they are of type js
   ===============================================*/
	function get_js($js_array){
    $x = "";
    if (isset($js_array)){
		foreach ($js_array as $file_js){
			$dirArrjs = get_dir_init($file_js);
			if(strstr($file_js,".js")==".js"){
				if(strlen($x)==0){
					  $x .= "\t";
					} else {
						$x .= "\n\t";
					} 
				$x .= '<script src="' . $dirArrjs ; 
				if ( $dirArrjs !=='#') {
					$x .= '?v=';
					$x .= filemtime($dirArrjs);
				}
				$x .= '"></script>';
			}
		}
		return  $x;
    } else {
        echo "Error load js files";
    }
  }
/*===================================================
    function generate_code() => Returns with a 5-digit code
    =================================================*/
   function generate_code(){
        return rand(10,99999);
        
    }
/*===================================================
    
   ==================================================*/
// Centralized SMTP mailer; credentials are supplied through server environment settings.
require_once __DIR__ . '/Classes/Mailer.php';
/*==================================================
    *Public Error Page Function
  ===================================================*/
function public_Error($error){
    header("location: ?PublicError=" . $error);
}
/*===================================================
**  set $language
**==================================================*/
 