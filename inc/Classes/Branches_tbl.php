<?php
/*================================================
**  Branches Database Table
**==============================================*/
include_once(__dir__.'/Stores_tbl.php');

class Branches_tbl {
    private $branchesTbl = 'BRANCHES';
    public $br_id;
    public $companie_id;
    public $city_id;
    public $br_name;
    public $br_main;
    public $br_address;
    private $conn;
    public function __construct($db) {
        $this->conn = $db;
    }
    /*============================================
    function listCountries
    --------------------------------
    dataTables parameters:
        1 - $_POST["search"]["value"]
        2 - $_POST["order"]
        3 - $_POST["length"]
        4 - $_POST['start']
===========================================*/
    public function listBranches() {
        $sqlQuery = 'SELECT A.BR_ID,
					        A.BR_NAME,
							A.BR_MAIN,
							A.COMPANIE_ID,
							B.COM_NAME,
							E.CO_ID,
							E.CO_NAME,
							E.CO_NAME_EN,
							D.GOV_ID,
							D.GOV_NAME,
							D.GOV_NAME_EN,
							A.CITY_ID,
							C.CI_NAME,
							C.CI_NAME_EN,
							A.BR_ADDRESS,
							COUNT(F.BRANCH_ID) AS COUNT_STORES,
							COUNT(G.BRANCH_ID) AS COUNT_USERS
					FROM ' . $this->branchesTbl . ' AS A
						LEFT JOIN COMPANIES AS B
							ON COMPANIE_ID = B.COM_ID
						LEFT JOIN CITY AS C
							ON A.CITY_ID = C.CI_ID
						LEFT JOIN GOV AS D
							ON C.GOV_ID = D.GOV_ID
						LEFT JOIN COUNTRIES AS E
							ON D.COUNTRY_ID = E.CO_ID
                        LEFT JOIN STORES AS F
                            ON A.BR_ID = F.BRANCH_ID
                        LEFT JOIN USERS AS G
                            ON A.BR_ID = G.BRANCH_ID ';
        if (!empty($_GET["search"]["value"])) {
            $sqlQuery .= ' where (A.BR_ID LIKE "%'.$_GET["search"]["value"].'%" ';
            $sqlQuery .= ' OR A.BR_NAME LIKE "%'.$_GET["search"]["value"].'%" ';
            $sqlQuery .= ' OR B.COM_NAME LIKE "%'.$_GET["search"]["value"].'%" ';
            $sqlQuery .= ' OR E.CO_NAME LIKE "%'.$_GET["search"]["value"].'%" ';
            $sqlQuery .= ' OR D.GOV_NAME LIKE "%'.$_GET["search"]["value"].'%" ';
            $sqlQuery .= ' OR C.CI_NAME LIKE "%'.$_GET["search"]["value"].'%") ';
        }
        // Group by sqlQuery
        $sqlQuery .= ' GROUP BY A.BR_ID,
					            A.BR_NAME,
							    A.BR_MAIN,
							    A.COMPANIE_ID,
							    B.COM_NAME,
							    E.CO_ID,
							    E.CO_NAME,
							    E.CO_NAME_EN,
							    D.GOV_ID,
							    D.GOV_NAME,
							    D.GOV_NAME_EN,
							    A.CITY_ID,
							    C.CI_NAME,
							    C.CI_NAME_EN,
							    A.BR_ADDRESS ';
        // Order by sqlQuery
        $columnsArray = ['A.BR_ID', 'A.BR_NAME', 'B.COM_NAME', 'A.BR_ADDRESS' ];
        if (!empty($_GET["order"])) {
        	
            $sqlQuery .= ' ORDER BY '.$columnsArray[$_GET['order']['0']['column']].' '.$_GET['order']['0']['dir'].' ';
        } else {
            $sqlQuery .= ' ORDER BY BR_ID DESC ';
        }
        // LIMIT data sqlQuery
        if (!empty($_GET["length"]) && $_GET["length"] != -1) {
            $sqlQuery .= ' LIMIT ' . $_GET['start'] . ', ' . $_GET['length'] . ' ';
        }
        $stmt = $this->conn->prepare($sqlQuery);
        $stmt->execute();
        $result = $stmt;

        $stmtTotal = $this->conn->prepare("SELECT * FROM " . $this->branchesTbl);
        $stmtTotal->execute();
        $allResult = $stmtTotal;
        $allRecords = $allResult->rowCount();

        $displayRecords = $result->rowCount();
        $records = array();
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $rows = array();
            if ($record['COUNT_STORES'] <= 0 && $record['COUNT_USERS'] <= 0) {
                $rows[] = '<input id="'.$record['BR_ID'].'" type="checkbox">';
            } else {
                $rows[] = '#';
            }
            if ($record['BR_MAIN'] == 1) {
                $rows[] = $record['BR_NAME'] . ' - المقر الرئيسى';
            } else {
                $rows[] = $record['BR_NAME'];
            }
            $rows[] = $record['COM_NAME'];
            $rows[] = $record['BR_ADDRESS'] . ' - ' . $record['CI_NAME'] . ' - ' . $record['GOV_NAME'] .' - '. $record['CO_NAME'];

            $rows[] = "<button id='btnNew' type='button' data-id='".$record["BR_ID"]."' data-name='".$record["BR_NAME"]."' data-br_main='".$record["BR_MAIN"]."' data-companie_id='".$record["COMPANIE_ID"]."' data-co_id='".$record["CO_ID"]."' data-gov_id='".$record["GOV_ID"]."' data-city_id='".$record["CITY_ID"]."' data-br_address='".$record["BR_ADDRESS"]."' class='btn btn-primary btn-xs btnEdit'><i class='fas fa-align-center fa-edit'></i></button>";

            $records[] = $rows;
        }

        $output = array(
            "draw" => intval($_GET["draw"]),
            "iTotalRecords" => $displayRecords,
            "iTotalDisplayRecords" => $allRecords,
            "data" => $records
        );
        echo json_encode($output);
    }
    public function updateBranches() {
        if (!empty($this->br_id) &&
            !empty($this->companie_id) &&
            !empty($this->city_id) &&
            !empty($this->br_name) &&
            !empty($this->br_address)) {
            //!empty($this->br_main)) {
            if ($this->br_main > 0) {
                $sqlQuery1 = " UPDATE ".$this->branchesTbl." SET BR_MAIN = 0 WHERE BR_MAIN > 0 ; ";
            } else {
                $sqlQuery1 = " UPDATE ".$this->branchesTbl." SET BR_MAIN = BR_MAIN WHERE BR_MAIN > 0 ; ";
            }
            $sqlQuery2 = " UPDATE ".$this->branchesTbl." SET COMPANIE_ID= ?, CITY_ID = ?, BR_NAME = ?, BR_MAIN = ?,BR_ADDRESS = ?  WHERE BR_ID = ? ;";
            $this->br_id = htmlspecialchars(strip_tags(trim($this->br_id, " ")));
            $this->companie_id = htmlspecialchars(strip_tags(trim($this->companie_id, " ")));
            $this->city_id = htmlspecialchars(strip_tags(trim($this->city_id, " ")));
            $this->br_name = htmlspecialchars(strip_tags(trim($this->br_name, " ")));
            $this->br_main = htmlspecialchars(strip_tags(trim($this->br_main, " ")));
            $this->br_address = htmlspecialchars(strip_tags(trim($this->br_address, " ")));
            try {
                $this->conn->beginTransaction();
                $stmt1 = $this->conn->prepare($sqlQuery1);
                if ($stmt1->execute()) {
                    $stmt2 = $this->conn->prepare($sqlQuery2);
                    $stmt2->bindValue(1, $this->companie_id, PDO::PARAM_STR);
                    $stmt2->bindValue(2, $this->city_id, PDO::PARAM_STR);
                    $stmt2->bindValue(3, $this->br_name, PDO::PARAM_STR);
                    $stmt2->bindValue(4, $this->br_main, PDO::PARAM_STR);
                    $stmt2->bindValue(5, $this->br_address, PDO::PARAM_STR);
                    $stmt2->bindValue(6, $this->br_id, PDO::PARAM_STR);
                    if ($stmt2->execute()) {
                        echo json_encode(array('s','تم تعديل البيانات بنجاح')) ;
                        $this->conn->commit();
                    } else {
                        echo json_encode(array('e','لم يتم الحفظ .. حدث خطاء غير معروف'));
                        $this->conn->rollback();
                    }
                } else {
                    echo json_encode(array('e','لم يتم الحفظ .. حدث خطاء غير معروف'));
                    $this->conn->rollback();
                }

            } catch (PDOException $e) {
                echo json_encode(array('e','حدث خطاء  - رسالة الخطاء: '.$e->getMessage(),$e->getCode())); 
            }
        }
    }
    public function addBranches() {
        if (!empty($this->br_name) &&
            !empty($this->companie_id) &&
            !empty($this->city_id) &&
            !empty($this->br_address)) {
            if ($this->br_main > 0) {
                $sqlQuery1 = " UPDATE ".$this->branchesTbl." SET BR_MAIN = 0 WHERE BR_MAIN > 0 ; ";
            } else {
                $sqlQuery1 = " UPDATE ".$this->branchesTbl." SET BR_MAIN = BR_MAIN WHERE BR_MAIN > 0 ; ";
            }
            $sqlQuery2 = "INSERT INTO " . $this->branchesTbl . " (`COMPANIE_ID`, `CITY_ID`, `BR_NAME`, `BR_MAIN`, `BR_ADDRESS`) VALUES (? , ? , ? , ? , ?)";
            $this->companie_id = htmlspecialchars(strip_tags(trim($this->companie_id, " ")));
            $this->city_id = htmlspecialchars(strip_tags(trim($this->city_id, " ")));
            $this->br_name = htmlspecialchars(strip_tags(trim($this->br_name, " ")));
            $this->br_main = htmlspecialchars(strip_tags(trim($this->br_main, " ")));
            $this->br_address = htmlspecialchars(strip_tags(trim($this->br_address, " ")));
            try {
                $this->conn->beginTransaction();
                $stmt1 = $this->conn->prepare($sqlQuery1);
                if ($stmt1->execute()) {
                    $stmt2 = $this->conn->prepare($sqlQuery2);
                    $stmt2->bindValue(1, $this->companie_id, PDO::PARAM_STR);
                    $stmt2->bindValue(2, $this->city_id, PDO::PARAM_STR);
                    $stmt2->bindValue(3, $this->br_name, PDO::PARAM_STR);
                    $stmt2->bindValue(4, $this->br_main, PDO::PARAM_STR);
                    $stmt2->bindValue(5, $this->br_address, PDO::PARAM_STR);
                    if ($stmt2->execute()) {
                    	$BranchId = $this->conn->lastInsertId();
                    	
                    	
                    	
                    	
                    	$sqlQuery2 = "INSERT INTO STORES(`STO_NAME`, `BRANCH_ID`, `STTY_ID`) VALUES (? , ? , ? )";
						$sqlQuery3 = "INSERT INTO `STORES_PRODUCT`(`STO_ID`, `PRO_CODE`, `STO_QTY`) SELECT A.STO_ID, A.PRO_CODE, A.STO_QTY FROM  (SELECT B.STO_ID,A.PRO_CODE, 0 AS STO_QTY FROM  PRODUCT_CODES A JOIN STORES B) A LEFT JOIN STORES_PRODUCT B ON A.STO_ID = B.STO_ID  AND A.PRO_CODE = B.PRO_CODE WHERE B.PRO_CODE IS NULL";

						$sttid   = 2;
						$stoMame = 'مخزن فرع - '.$this->br_name;
						$stmt2 = $this->conn->prepare( "INSERT INTO STORES(`STO_NAME`, `BRANCH_ID`, `STTY_ID`) VALUES (? , ? , ? )");
						$stmt2->bindValue(1,$stoMame, 	PDO::PARAM_STR);
						$stmt2->bindValue(2,$BranchId, 	PDO::PARAM_INT);
						$stmt2->bindValue(3,$sttid, 	PDO::PARAM_INT);
						if($stmt2->execute()) {
								$stmt3 = $this->conn->prepare("INSERT INTO `STORES_PRODUCT`(`STO_ID`, `PRO_CODE`, `STO_QTY`) SELECT A.STO_ID, A.PRO_CODE, A.STO_QTY FROM  (SELECT B.STO_ID,A.PRO_CODE, 0 AS STO_QTY FROM  PRODUCT_CODES A JOIN STORES B) A LEFT JOIN STORES_PRODUCT B ON A.STO_ID = B.STO_ID  AND A.PRO_CODE = B.PRO_CODE WHERE B.PRO_CODE IS NULL");
								if ($stmt3->execute()) {
									if($stmt3->rowCount()> 0){
										$this->conn->commit();
										echo json_encode(array('s','تم حفظ البيانات بنجاح'));
									} else {
										echo json_encode(array('e','حدث خطاء لم يتم تأكيد حفظ البيانات من الخادم'));
										$this->conn->rollback();
									}
								} else {
									echo json_encode(array('e','حدث خطاء لم يتم تأكيد حفظ البيانات من الخادم'));
									$this->conn->rollback();
								}
						}else{
								echo json_encode(array('e','حدث خطاء لم يتم تأكيد حفظ البيانات من الخادم'));
								$this->conn->rollback();
						}	
		
                    } else {
                        echo json_encode(array('e','لم يتم الحفظ .. حدث خطاء غير معروف'));
                        $this->conn->rollback();
                    }
                } else {
                    echo json_encode(array('e','لم يتم الحفظ .. حدث خطاء غير معروف'));
                    $this->conn->rollback();
                }
            } catch (PDOException $e) {
                echo json_encode(array('e','حدث خطاء  - رسالة الخطاء: '.$e->getMessage(),$e->getCode())); 
                $this->conn->rollback();
            }
        }
    }
    public function deleteBranches() {
        if (!empty($this->br_id)) {
            $sqlQuery1 = " SELECT * FROM " . $this->branchesTbl . " WHERE BR_MAIN = 1 AND FIND_IN_SET (BR_ID , ? ); ";
            $sqlQuery2 = " DELETE FROM ".$this->branchesTbl." WHERE FIND_IN_SET (BR_ID , ? ); ";
            $this->br_id = htmlspecialchars(strip_tags($this->br_id));
            try {
                $this->conn->beginTransaction();
                $stmt1 = $this->conn->prepare($sqlQuery1);
                $stmt1->bindValue(1, $this->br_id, PDO::PARAM_STR);
                if ($stmt1->execute()) {
                    if ($stmt1->rowCount() == 0) {
                        $stmt2 = $this->conn->prepare($sqlQuery2);
                        $stmt2->bindValue(1, $this->br_id, PDO::PARAM_STR);
                        if ($stmt2->execute()) {
                            echo json_encode(array('s','تم حذف البيانات بنجاح'));
                            $this->conn->commit();
                        } else {
                            echo json_encode(array('e','لم يتم الحفظ .. حدث خطاء غير معروف'));
                            $this->conn->rollback();
                        }
                    } else {
                        echo json_encode(array('w','لايمكن حذف البيانات المحددة لانها تحتوى على فرع رئيسى - من فضلك قم بتعين فرع اخر كفرع رئيسي واعد محاولة الحذف'));
                        $this->conn->rollback();
                    }
                } else {
                    echo json_encode(array('e','لم يتم الحفظ .. حدث خطاء غير معروف'));
                    $this->conn->rollback();
                }

            } catch (PDOException $e) {
                echo json_encode(array('e','حدث خطاء  - رسالة الخطاء: '.$e->getMessage(),$e->getCode())); 
                $this->conn->rollback();
            }
        }
    }
    public function getdropBranches() {
        $sqlQuery = "SELECT * FROM ".$this-> branchesTbl;
		if(!empty($_GET['ID'])){
			$sqlQuery .= " WHERE FIND_IN_SET (BR_ID , ? );";
		}
        $this->br_id = htmlspecialchars(strip_tags($this->br_id));
        $stmt = $this->conn->prepare($sqlQuery);
		if(!empty($_GET['ID'])){
			$stmt->bindValue(1, $this->br_id, PDO::PARAM_STR);
		}
        $stmt->execute();
        $result = $stmt;
        $records = array();
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $rows = array();
            $rows[] = $record['BR_ID'];
            $rows[] = $record['COMPANIE_ID'];
            $rows[] = $record['BR_NAME'];
            $records[] = $rows;
        }
        echo json_encode($records);
    }
}
?>