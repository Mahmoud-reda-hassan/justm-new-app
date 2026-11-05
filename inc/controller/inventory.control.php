<?php
session_start();

include_once(__dir__.'/../../config.php');
include_once(ABSPATH.'/inc/Databases/dbconn.php');
include_once(ABSPATH.'/inc/controller/BackEnd/inventory_BE.php');
$DBconn        	= new dbconn();
$db            	= $DBconn->getConnection(DB_HOST,DB_PORT,DB_USER,DB_PASSWORD,DB_NAME,DB_CHARSET);
$backEnd     = new inventory_BE($db);
$http_RQM = $_SERVER['REQUEST_METHOD'];
if($http_RQM === "GET") {
	if(isset($_GET) && isset($_GET['CONTROL'])){
		switch ($_GET['CONTROL']) {
			case 'ProAttributes':
				http_response_code(200);
				echo json_encode($backEnd->ProAttributes($_GET['pid']));
				$db = null;
				unset($backEnd,$db,$http_RQM,$DBconn,$_GET);
			    break;
			    
			case 'Productlist':
				http_response_code(200);
				echo json_encode($backEnd->Productlist());
				$db = null;
				unset($backEnd,$db,$http_RQM,$DBconn,$_GET);
			    break;
		}
	}
}else if($http_RQM === "POST") {
	if(isset($_POST) && isset($_POST['CONTROL'])){
		switch ($_POST['CONTROL']) {
			case 'AddStockAndPurchises':
				try{
					http_response_code(200);
					echo json_encode($backEnd->AddStock($_POST['SUPPLIER_ID'], $_POST['REFERENCE'], $_POST['COMMENT'],$_POST['PRODUCTS'],$_POST['STOID']));
				} catch (Exception $e) {
	                http_response_code(500);
	                echo json_encode(['error' => $e->getMessage()]);
	            } finally {$db = null;}
				unset($backEnd,$db,$http_RQM,$DBconn,$_POST);
			    break;
			case 'AddPurchises':
				try{
					http_response_code(200);
					echo json_encode($backEnd->AddStock($_POST['SUPPLIER_ID'], $_POST['REFERENCE'], $_POST['COMMENT'],$_POST['PRODUCTS'],false));
				} catch (Exception $e) {
	                http_response_code(500);
	                echo json_encode(['error' => $e->getMessage()]);
	            } finally {$db = null;}
				unset($backEnd,$db,$http_RQM,$DBconn,$_POST);
			    break;
			case 'SuppliedStock':
				try{
					
					http_response_code(200);
					echo json_encode($backEnd->SuppliedStock($_POST['ORDER_ID'], '', $_POST['SUPPLIER_ID'], $_POST['PRODUCTS']));
				}catch (Exception $e) {
	                http_response_code(500);
	                echo json_encode(['error' => $e->getMessage()]);
	            } finally {
	            	//$backEnd->releaseGlobalInventoryLock();
	            	$db = null;
	            }
				unset($backEnd,$db,$http_RQM,$DBconn,$_POST);
			    break;
			case 'StockAudit':
				try{
					http_response_code(200);
					
					$stoId = isset($_POST['stoid']) ? $_POST['stoid']: null;
					$catId = isset($_POST['catid']) ? $_POST['catid']: null;
					$proId = isset($_POST['proid']) ? $_POST['proid']: null;
					echo json_encode($backEnd->inventoryReconciliationList($stoId,$catId,$proId));
				}catch (Exception $e) {
	                http_response_code(500);
	                echo json_encode(['error' => $e->getMessage()]);
	            } finally {$db = null;}
				unset($backEnd,$db,$http_RQM,$DBconn,$_POST);
			    break;
			    
			case 'Productlist_transfer':  
				try{
					http_response_code(200);
					$stoId = isset($_POST['from_stoid']) ? $_POST['from_stoid']: null;
					//$tostoId = isset($_POST['to_stoid']) ? $_POST['to_stoid']: null;
					$catId = isset($_POST['catid']) ? $_POST['catid']: null;
					$proId = isset($_POST['proid']) ? $_POST['proid']: null;
					echo json_encode($backEnd->inventoryReconciliationList($stoId,$catId,$proId));
				}catch (Exception $e) {
	                http_response_code(500);
	                echo json_encode(['error' => $e->getMessage()]);
	            } finally {$db = null;}
				unset($backEnd,$db,$http_RQM,$DBconn,$_POST);
			    break;
			    
			case 'setStockAudit':
				try{
					
		    		$stoid = $_POST['STO_ID'];
			        $ProData = $_POST['PRODUCTS'];
					http_response_code(200);
					echo json_encode($backEnd->setStockAudit ($ProData, $stoid));
				}catch (Exception $e) {
	                http_response_code(500);
	                echo json_encode(['error' => $e->getMessage()]);
	            } finally {$db = null;}
				unset($backEnd,$db,$http_RQM,$DBconn,$_POST);
			    break;
			case 'refreshStoreCountingLock':
				try{
					$stoid = $_POST['store_id'];
					http_response_code(200);
					echo json_encode($backEnd->refreshStoreCountingLock($stoid));
				}catch (Exception $e) {
	                http_response_code(500);
	                echo json_encode(['error' => $e->getMessage()]);
	            } finally {$db = null;}
				unset($backEnd,$db,$http_RQM,$DBconn,$_POST);
				break;
			case 'unlockStore':
   				try{
					$stoid = $_POST['store_id'];
					http_response_code(200);
					echo json_encode($backEnd->unlockStoreAfterCounting($stoid));
				}catch (Exception $e) {
	                http_response_code(500);
	                echo json_encode(['error' => $e->getMessage()]);
	            } finally {$db = null;}
				unset($backEnd,$db,$http_RQM,$DBconn,$_POST);
				break;
			case 'setInTransfer':
				try{
		    		$stoid = $_POST['FROM_STO'];
		    		$tostoId = isset($_POST['TO_STO']) ? $_POST['TO_STO']: null;
			        $ProData = $_POST['PRODUCTS'];
					$backEnd->comment = $_POST['COMMENT'];
					http_response_code(200);
					echo json_encode($backEnd->setInTransfer ($ProData, $stoid,$tostoId));
				}catch (Exception $e) {
	                http_response_code(500);
	                echo json_encode(['error' => $e->getMessage()]);
	            } finally {$db = null;}
				unset($backEnd,$db,$http_RQM,$DBconn,$_POST);
			    break;
			    
			case 'addScrapPro':
				try{
					http_response_code(200);
					echo json_encode($backEnd->addScrap($_POST['from_stoid'], $_POST['COMMENT'], $_POST['PRODUCTS']));
				}catch (Exception $e) {
	                http_response_code(500);
	                echo json_encode(['error' => $e->getMessage()]);
	            } finally {$db = null;}
				unset($backEnd,$db,$http_RQM,$DBconn,$_POST);
			    break;
			    
			case 'setReturnOrderPurchises':
				try{
		    		$stoid   = $_POST['STO_ID'];
		    		$supid   = $_POST['SUPPLIER_ID'];
		    		$oid     = $_POST['ORDER_ID'];
			        $ProData = $_POST['PRODUCTS'];
					http_response_code(200);
					//echo json_encode(['success','test','titele']);
					//returnPurchisesFormStock($stoid ,$supplierId, $reference, $comment, $products , $receivedProduct = true) {
						
					echo json_encode($backEnd->returnPurchisesFormStock ($stoid, $supid, $oid,'مرتجع امر شراء الى المورد',$ProData));
				}catch (Exception $e) {
	                http_response_code(500);
	                echo json_encode(['error' => $e->getMessage()]);
	            } finally {$db = null;}
				unset($backEnd,$db,$http_RQM,$DBconn,$_POST);
			    break;
		}
	}
}


?>