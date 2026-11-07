<?php
declare(strict_types=1);

require_once __DIR__ . '/AccountSettings.php';
require_once __DIR__ . '/JournalManager.php';
require_once __DIR__ . '/FinancialEventManager.php';

/** GL postings for new supplier purchases, receipts, returns and payments. */
final class SupplierAccountingManager
{
    private PDO $db;
    private AccountSettings $accounts;
    private FinancialEventManager $events;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->accounts = new AccountSettings($db);
        $this->events = new FinancialEventManager($db);
    }

    private function postEvent(string $key, string $type, string $source, int $sourceId,
        int $branchId, int $userId, array $payload, callable $postJournal): array
    {
        return $this->events->post([
            'idempotency_key' => $key, 'event_type' => $type, 'source_type' => $source,
            'source_id' => $sourceId, 'branch_id' => $branchId,
            'event_date' => date('Y-m-d H:i:s'), 'created_by' => $userId, 'payload' => $payload,
        ], $postJournal);
    }

    public function recordPurchaseOrder(int $orderId, int $supplierId, int $branchId, int $userId): array
    {
        if ($orderId <= 0 || $supplierId <= 0 || $branchId <= 0 || $userId <= 0) {
            throw new InvalidArgumentException('بيانات أمر الشراء أو الفرع أو المستخدم غير مكتملة.');
        }
        $stmt = $this->db->prepare('SELECT SUPPLIER_ID,TOTAL_PRICE FROM purchases_orders WHERE ORDER_ID=? FOR UPDATE');
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int)$row['SUPPLIER_ID'] !== $supplierId) throw new RuntimeException('أمر الشراء لا يتبع المورد المحدد.');
        $amount = round((float)$row['TOTAL_PRICE'], 2);
        if ($amount <= 0) throw new RuntimeException('قيمة أمر الشراء يجب أن تكون أكبر من صفر.');

        return $this->postEvent('PURCHASE_ORDER:' . $orderId, 'PURCHASE_ORDER_RECOGNIZED',
            'PURCHASE_ORDER', $orderId, $branchId, $userId,
            ['supplier_id' => $supplierId, 'amount' => number_format($amount, 2, '.', '')],
            function () use ($orderId, $supplierId, $branchId, $userId, $amount): int {
                $journal = new JournalManager($this->db);
                $journal->create(['branch_id'=>$branchId,'entry_date'=>date('Y-m-d H:i:s'),
                    'reference_type'=>'PURCHASE_ORDER','reference_id'=>$orderId,
                    'description'=>'إثبات أمر شراء رقم '.$orderId,'created_by'=>$userId])
                    ->addLine(['account_id'=>$this->accounts->inventoryInTransit(),'branch_id'=>$branchId,
                        'debit'=>$amount,'reference_type'=>'PURCHASE_ORDER','reference_id'=>$orderId,
                        'note'=>'بضاعة مشتراة بالطريق - أمر شراء '.$orderId])
                    ->addLine(['account_id'=>$this->accounts->suppliers(),'branch_id'=>$branchId,
                        'partner_type'=>'SUPPLIER','partner_id'=>$supplierId,'credit'=>$amount,
                        'reference_type'=>'PURCHASE_ORDER','reference_id'=>$orderId,
                        'note'=>'مستحق للمورد عن أمر شراء '.$orderId]);
                return $journal->post();
            });
    }

    public function recordReceiptByMovementUniqueId(string $uniqueId): ?array
    {
        $stmt=$this->db->prepare("SELECT IM.IM_ID,IM.REFERENCE ORDER_ID,IM.IM_BRANCH,IM.STO_ID,IM.IM_USER,
            ROUND(COALESCE(SUM(PP.PURCHASE_UNIT_PRICE*IPM.IPM_QTY),0),2) AMOUNT
            FROM inventory_movement IM
            INNER JOIN inventory_product_movement IPM ON IPM.IM_ID=IM.IM_ID
            INNER JOIN purchases_products PP ON PP.ORDER_ID=IPM.ORDER_ID AND PP.PRO_CODE=IPM.IPM_CODE
            WHERE IM.UNIQUE_ID=? AND LOWER(IM.IM_MOVE_TYPE)='receiving' AND IPM.IPM_TYPE_MOV='IN'
            GROUP BY IM.IM_ID,IM.REFERENCE,IM.IM_BRANCH,IM.STO_ID,IM.IM_USER LIMIT 1");
        $stmt->execute([$uniqueId]); $m=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$m) throw new RuntimeException('تعذر العثور على حركة استلام المشتريات.');
        $amount=round((float)$m['AMOUNT'],2); if($amount<=0) return null;
        $mid=(int)$m['IM_ID']; $oid=(int)$m['ORDER_ID']; $branch=(int)$m['IM_BRANCH'];
        // أوامر الشراء القديمة لم تُرحّل إلى المخزون بالطريق في GL؛ لا ننشئ لها قيد إعادة تصنيف بلا أصل مقابل.
        $purchaseEvent = $this->db->prepare("SELECT id FROM accounting_events
            WHERE idempotency_key=? AND event_type='PURCHASE_ORDER_RECOGNIZED' AND status='POSTED' LIMIT 1");
        $purchaseEvent->execute(['PURCHASE_ORDER:' . $oid]);
        if (!$purchaseEvent->fetchColumn()) return null;
        $store=(int)$m['STO_ID']; $user=(int)$m['IM_USER'];
        return $this->postEvent('PURCHASE_RECEIPT_MOVEMENT:'.$mid,'PURCHASE_STOCK_RECEIVED',
            'INVENTORY_MOVEMENT',$mid,$branch,$user,
            ['order_id'=>$oid,'store_id'=>$store,'amount'=>number_format($amount,2,'.','')],
            function() use($mid,$oid,$branch,$store,$user,$amount): int {
                $j=new JournalManager($this->db);
                $j->create(['branch_id'=>$branch,'entry_date'=>date('Y-m-d H:i:s'),
                    'reference_type'=>'PURCHASE_RECEIPT','reference_id'=>$mid,
                    'description'=>'استلام مخزني لأمر الشراء رقم '.$oid,'created_by'=>$user])
                  ->addLine(['account_id'=>$this->accounts->inventory(),'branch_id'=>$branch,'store_id'=>$store,
                    'debit'=>$amount,'reference_type'=>'PURCHASE_ORDER','reference_id'=>$oid,
                    'note'=>'إضافة قيمة الكميات المستلمة للمخزون'])
                  ->addLine(['account_id'=>$this->accounts->inventoryInTransit(),'branch_id'=>$branch,
                    'credit'=>$amount,'reference_type'=>'PURCHASE_ORDER','reference_id'=>$oid,
                    'note'=>'تخفيض قيمة البضاعة المستلمة من المخزون بالطريق']);
                return $j->post();
            });
    }

    public function recordReturnByMovementUniqueId(string $uniqueId): array
    {
        $stmt=$this->db->prepare("SELECT IM.IM_ID,IM.REFERENCE RETURN_ORDER_ID,IM.IM_BRANCH,IM.STO_ID,IM.IM_USER,
            PRO.SUPPLIER_ID,PRO.REFERANC PURCHASE_ORDER_ID,
            ROUND(COALESCE(SUM(PRP.PURCHASE_UNIT_PRICE*IPM.IPM_QTY),0),2) AMOUNT
            FROM inventory_movement IM
            INNER JOIN inventory_product_movement IPM ON IPM.IM_ID=IM.IM_ID
            INNER JOIN purchases_return_products PRP ON PRP.ORDER_ID=IPM.ORDER_ID AND PRP.PRO_CODE=IPM.IPM_CODE
            INNER JOIN purchases_return_orders PRO ON PRO.ORDER_ID=IM.REFERENCE
            WHERE IM.UNIQUE_ID=? AND LOWER(IM.IM_MOVE_TYPE)='refundpurchises' AND IPM.IPM_TYPE_MOV='OUT'
            GROUP BY IM.IM_ID,IM.REFERENCE,IM.IM_BRANCH,IM.STO_ID,IM.IM_USER,PRO.SUPPLIER_ID,PRO.REFERANC LIMIT 1");
        $stmt->execute([$uniqueId]); $m=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$m) throw new RuntimeException('تعذر العثور على حركة صرف مرتجع المشتريات.');
        $amount=round((float)$m['AMOUNT'],2); if($amount<=0) throw new RuntimeException('قيمة مرتجع المشتريات غير صالحة.');
        $mid=(int)$m['IM_ID']; $rid=(int)$m['RETURN_ORDER_ID']; $purchaseOrderId=(int)$m['PURCHASE_ORDER_ID']; $supplier=(int)$m['SUPPLIER_ID'];
        $branch=(int)$m['IM_BRANCH']; $store=(int)$m['STO_ID']; $user=(int)$m['IM_USER'];
        return $this->postEvent('PURCHASE_RETURN_MOVEMENT:'.$mid,'PURCHASE_RETURN_RECOGNIZED',
            'INVENTORY_MOVEMENT',$mid,$branch,$user,
            ['return_order_id'=>$rid,'purchase_order_id'=>$purchaseOrderId,'supplier_id'=>$supplier,'store_id'=>$store,'amount'=>number_format($amount,2,'.','')],
            function() use($mid,$rid,$purchaseOrderId,$supplier,$branch,$store,$user,$amount): int {
                $j=new JournalManager($this->db);
                $j->create(['branch_id'=>$branch,'entry_date'=>date('Y-m-d H:i:s'),
                    'reference_type'=>'PURCHASE_RETURN','reference_id'=>$rid,
                    'description'=>'مرتجع مشتريات رقم '.$rid.' بعد الصرف المخزني','created_by'=>$user])
                  ->addLine(['account_id'=>$this->accounts->suppliers(),'branch_id'=>$branch,
                    'partner_type'=>'SUPPLIER','partner_id'=>$supplier,'debit'=>$amount,
                    'reference_type'=>'PURCHASE_ORDER','reference_id'=>$purchaseOrderId,
                    'note'=>'تخفيض مستحق المورد عن مرتجع مشتريات '.$rid.' لأمر الشراء '.$purchaseOrderId])
                  ->addLine(['account_id'=>$this->accounts->inventory(),'branch_id'=>$branch,'store_id'=>$store,
                    'credit'=>$amount,'reference_type'=>'PURCHASE_RETURN','reference_id'=>$rid,
                    'note'=>'صرف قيمة البضاعة المرتجعة من المخزون']);
                return $j->post();
            });
    }

    public function pay(int $supplierId,float $totalAmount,array $orders,int $paymentMethodId,
        int $branchId,int $userId,string $requestKey,string $note=''): array
    {
        $amount=round($totalAmount,2);
        if($supplierId<=0||$branchId<=0||$userId<=0||$paymentMethodId<=0) throw new InvalidArgumentException('بيانات المورد أو الفرع أو المستخدم أو طريقة الدفع غير مكتملة.');
        if(!is_finite($amount)||$amount<=0) throw new InvalidArgumentException('مبلغ صرف المورد يجب أن يكون أكبر من صفر.');
        if(!preg_match('/^[A-Za-z0-9._:-]{8,64}$/D',$requestKey)) throw new InvalidArgumentException('مفتاح طلب صرف المورد مطلوب لمنع تكرار القيد.');
        $allocations=[]; $allocated=0.0;
        foreach($orders as $item){
            if(!is_array($item)) throw new InvalidArgumentException('توزيع دفعة المورد غير صالح.');
            $oid=(int)($item['orderId']??$item['order_id']??$item['ORDER_ID']??0);
            $value=round((float)($item['amount']??$item['paid_amount']??$item['AMOUNT']??0),2);
            if($oid<=0||$value<=0||isset($allocations[$oid])) throw new InvalidArgumentException('بيانات توزيع دفعة المورد غير صحيحة أو مكررة.');
            $allocations[$oid]=$value; $allocated=round($allocated+$value,2);
        }
        ksort($allocations,SORT_NUMERIC);
        if($allocated-$amount>0.009) throw new InvalidArgumentException('إجمالي التوزيع أكبر من مبلغ الدفعة.');
        $advance=round($amount-$allocated,2);
        $payload=['supplier_id'=>$supplierId,'payment_method_id'=>$paymentMethodId,
            'amount'=>number_format($amount,2,'.',''),
            'allocations'=>array_map(static fn($id,$v)=>['order_id'=>$id,'amount'=>number_format($v,2,'.','')],array_keys($allocations),array_values($allocations)),
            'advance'=>number_format($advance,2,'.',''),'note'=>trim($note)];

        $event=$this->postEvent('SUPPLIER_PAYMENT:'.$requestKey,'SUPPLIER_PAYMENT_POSTED',
            'SUPPLIER_PAYMENT_REQUEST',$supplierId,$branchId,$userId,$payload,
            function() use($supplierId,$amount,$allocations,$advance,$paymentMethodId,$branchId,$userId,$requestKey,$note): int {
                $check=$this->db->prepare('SELECT SUP_ID FROM suppliers WHERE SUP_ID=? FOR UPDATE');
                $check->execute([$supplierId]); if(!$check->fetchColumn()) throw new RuntimeException('المورد المحدد غير موجود.');
                $rows=[]; $get=$this->db->prepare("SELECT PO.ORDER_ID,PO.SUPPLIER_ID,PO.TOTAL_PRICE,PO.TOTAL_PAYMENTS,
                    COALESCE((SELECT SUM(PR.TOTAL_PRICE-PR.TOTAL_PAYMENTS) FROM purchases_return_orders PR
                        WHERE PR.REFERANC=PO.ORDER_ID AND PR.STATUSE='DEFERRED'
                        AND EXISTS (SELECT 1 FROM inventory_movement IM
                            WHERE IM.REFERENCE=PR.ORDER_ID AND LOWER(IM.IM_MOVE_TYPE)='refundpurchises')),0) RETURN_TOTAL
                    FROM purchases_orders PO WHERE PO.ORDER_ID=? FOR UPDATE");
                foreach($allocations as $oid=>$value){
                    $get->execute([$oid]); $row=$get->fetch(PDO::FETCH_ASSOC);
                    if(!$row||(int)$row['SUPPLIER_ID']!==$supplierId) throw new RuntimeException('أمر الشراء رقم '.$oid.' لا يتبع المورد المحدد.');
                    $remain=round((float)$row['TOTAL_PRICE']-(float)$row['RETURN_TOTAL']-(float)$row['TOTAL_PAYMENTS'],2);
                    if($remain<=0||$value-$remain>0.009) throw new RuntimeException('المبلغ المخصص لأمر الشراء رقم '.$oid.' يتجاوز المتبقي المستحق.');
                    $rows[$oid]=$row;
                }
                $journal=new JournalManager($this->db);
                $journal->create(['branch_id'=>$branchId,'entry_date'=>date('Y-m-d H:i:s'),
                    'reference_type'=>'SUPPLIER_PAYMENT','reference_id'=>$supplierId,
                    'description'=>'صرف دفعة للمورد رقم '.$supplierId,'created_by'=>$userId]);
                foreach($allocations as $oid=>$value){
                    $journal->addLine(['account_id'=>$this->accounts->suppliers(),'branch_id'=>$branchId,
                        'partner_type'=>'SUPPLIER','partner_id'=>$supplierId,'reference_type'=>'PURCHASE_ORDER',
                        'reference_id'=>$oid,'debit'=>$value,'note'=>'سداد أمر شراء رقم '.$oid]);
                }
                if($advance>0) $journal->addLine(['account_id'=>$this->accounts->supplierAdvances(),'branch_id'=>$branchId,
                    'partner_type'=>'SUPPLIER','partner_id'=>$supplierId,'reference_type'=>'SUPPLIER_ADVANCE',
                    'reference_id'=>$supplierId,'debit'=>$advance,'note'=>'دفعة مقدمة غير مخصصة للمورد']);
                $journal->addLine(['account_id'=>$this->accounts->transactionAccount($paymentMethodId,$branchId),
                    'branch_id'=>$branchId,'credit'=>$amount,'reference_type'=>'SUPPLIER_PAYMENT',
                    'reference_id'=>$supplierId,'note'=>trim($note)?:'صرف نقدية للمورد']);
                $journalId=$journal->post();

                $insert=$this->db->prepare("INSERT INTO supplier_gl_payments
                    (request_key,supplier_id,branch_id,payment_method_id,amount,journal_entry_id,note,created_by)
                    VALUES (?,?,?,?,?,?,?,?)");
                $insert->execute([$requestKey,$supplierId,$branchId,$paymentMethodId,$amount,$journalId,trim($note),$userId]);
                $paymentId=(int)$this->db->lastInsertId();
                $paymentRow=$this->db->prepare('INSERT INTO purchases_payments (ORDER_ID,AMOUNT,PAYMENT_METHOD,NOTE,CREATED_BY,SUPPLIER_GL_PAYMENT_ID) VALUES (?,?,?,?,?,?)');
                $update=$this->db->prepare("UPDATE purchases_orders SET TOTAL_PAYMENTS=?,
                    STATUSE=CASE WHEN ?>=? THEN 'COMPLETED' ELSE 'DEFERRED' END WHERE ORDER_ID=? AND SUPPLIER_ID=?");
                $allocationInsert=$this->db->prepare("INSERT INTO supplier_gl_payment_allocations
                    (payment_id,supplier_id,purchase_order_id,allocation_type,amount,created_by) VALUES (?,?,?,?,?,?)");
                foreach($allocations as $oid=>$value){
                    $paymentRow->execute([$oid,$value,$paymentMethodId,trim($note)?:'سداد من دفعة المورد رقم '.$paymentId,$userId,$paymentId]);
                    $newPaid=round((float)$rows[$oid]['TOTAL_PAYMENTS']+$value,2);
                    $netTotal=round((float)$rows[$oid]['TOTAL_PRICE']-(float)$rows[$oid]['RETURN_TOTAL'],2);
                    $update->execute([$newPaid,$newPaid,$netTotal,$oid,$supplierId]);
                    if($update->rowCount()!==1) throw new RuntimeException('تعذر تحديث أمر الشراء رقم '.$oid.'.');
                    $allocationInsert->execute([$paymentId,$supplierId,$oid,'INVOICE',$value,$userId]);
                }
                if($advance>0) $allocationInsert->execute([$paymentId,$supplierId,null,'ADVANCE',$advance,$userId]);
                return $journalId;
            });
        $stmt=$this->db->prepare('SELECT id,journal_entry_id,amount,supplier_id FROM supplier_gl_payments WHERE request_key=? LIMIT 1');
        $stmt->execute([$requestKey]); $payment=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$payment) throw new RuntimeException('لم يتم العثور على سجل دفعة المورد بعد الترحيل.');
        return ['payment_id'=>(int)$payment['id'],'journal_entry_id'=>(int)$payment['journal_entry_id'],
            'supplier_id'=>(int)$payment['supplier_id'],'total_amount'=>(float)$payment['amount'],
            'allocated_amount'=>$allocated,'advance_amount'=>$advance,'already_posted'=>(bool)$event['already_posted']];
    }

    /**
     * تطبيق مقدمات المورد السابقة على أوامر الشراء، تلقائيًا FIFO أو بتوزيع يدوي.
     * لا ينشئ حركة نقدية جديدة؛ ينقل الرصيد من مقدمات المورد إلى مستحقات أوامر الشراء.
     */
    public function applyAdvances(int $supplierId, array $orders, string $mode, int $branchId,
        int $userId, string $requestKey): array
    {
        $mode = strtoupper(trim($mode));
        if (!in_array($mode, ['AUTO', 'MANUAL'], true)) throw new InvalidArgumentException('طريقة توزيع المقدم غير صحيحة.');
        if ($supplierId <= 0 || $branchId <= 0 || $userId <= 0) throw new InvalidArgumentException('بيانات المورد أو الفرع أو المستخدم غير مكتملة.');
        if (!preg_match('/^[A-Za-z0-9._:-]{8,64}$/D', $requestKey)) throw new InvalidArgumentException('مفتاح تطبيق المقدم مطلوب لمنع التكرار.');

        $requested = [];
        foreach ($orders as $item) {
            if (!is_array($item)) throw new InvalidArgumentException('توزيع المقدم غير صالح.');
            $orderId = (int)($item['orderId'] ?? $item['order_id'] ?? 0);
            $value = round((float)($item['amount'] ?? 0), 2);
            if ($orderId <= 0 || $value <= 0 || isset($requested[$orderId])) throw new InvalidArgumentException('أوامر أو مبالغ تطبيق المقدم غير صحيحة أو مكررة.');
            $requested[$orderId] = $value;
        }
        if ($mode === 'MANUAL' && !$requested) throw new InvalidArgumentException('حدد مبالغ المقدم على أوامر الشراء أولًا.');
        ksort($requested, SORT_NUMERIC);
        $payload = ['supplier_id'=>$supplierId, 'mode'=>$mode, 'orders'=>array_map(
            static fn($id,$amount)=>['order_id'=>$id,'amount'=>number_format($amount,2,'.','')],
            array_keys($requested), array_values($requested)
        )];

        $event = $this->postEvent('SUPPLIER_ADVANCE_APPLY:'.$requestKey, 'SUPPLIER_ADVANCE_APPLIED',
            'SUPPLIER_ADVANCE_REQUEST', $supplierId, $branchId, $userId, $payload,
            function () use ($supplierId,$requested,$mode,$branchId,$userId): int {
                $lock = $this->db->prepare('SELECT SUP_ID FROM suppliers WHERE SUP_ID=? FOR UPDATE');
                $lock->execute([$supplierId]);
                if (!$lock->fetchColumn()) throw new RuntimeException('المورد المحدد غير موجود.');

                $sourceSql = "SELECT A.id,A.payment_id,A.amount,P.created_at,
                    ROUND(A.amount-COALESCE((SELECT SUM(X.amount) FROM supplier_gl_advance_applications X
                        INNER JOIN journal_entries AXJ ON AXJ.id=X.journal_entry_id AND AXJ.status='POSTED'
                        WHERE X.source_advance_allocation_id=A.id),0),2) AS AVAILABLE
                    FROM supplier_gl_payment_allocations A
                    INNER JOIN supplier_gl_payments P ON P.id=A.payment_id AND P.reversal_journal_entry_id IS NULL
                    WHERE A.supplier_id=? AND A.allocation_type='ADVANCE'
                      AND A.amount>COALESCE((SELECT SUM(X.amount) FROM supplier_gl_advance_applications X
                        INNER JOIN journal_entries AXJ ON AXJ.id=X.journal_entry_id AND AXJ.status='POSTED'
                        WHERE X.source_advance_allocation_id=A.id),0)
                    ORDER BY P.created_at,A.id FOR UPDATE";
                $sourceStmt=$this->db->prepare($sourceSql);
                $sourceStmt->execute([$supplierId]);
                $sources=$sourceStmt->fetchAll(PDO::FETCH_ASSOC);
                $available=0.0;
                foreach($sources as $source) $available=round($available+(float)$source['AVAILABLE'],2);
                if($available<=0) throw new RuntimeException('لا يوجد مقدم متاح لهذا المورد.');

                $orderSql = "SELECT PO.ORDER_ID,PO.SUPPLIER_ID,PO.TOTAL_PRICE,PO.TOTAL_PAYMENTS,
                    COALESCE((SELECT SUM(PR.TOTAL_PRICE-PR.TOTAL_PAYMENTS) FROM purchases_return_orders PR
                        WHERE PR.REFERANC=PO.ORDER_ID AND PR.STATUSE='DEFERRED'
                        AND EXISTS (SELECT 1 FROM inventory_movement IM
                            WHERE IM.REFERENCE=PR.ORDER_ID AND LOWER(IM.IM_MOVE_TYPE)='refundpurchises')),0) RETURN_TOTAL
                    FROM purchases_orders PO WHERE PO.SUPPLIER_ID=? AND
                    (PO.TOTAL_PRICE-PO.TOTAL_PAYMENTS-COALESCE((SELECT SUM(PR.TOTAL_PRICE-PR.TOTAL_PAYMENTS)
                        FROM purchases_return_orders PR WHERE PR.REFERANC=PO.ORDER_ID AND PR.STATUSE='DEFERRED'
                        AND EXISTS (SELECT 1 FROM inventory_movement IM WHERE IM.REFERENCE=PR.ORDER_ID
                            AND LOWER(IM.IM_MOVE_TYPE)='refundpurchises')),0))>0
                    ORDER BY PO.ORDER_ID ASC FOR UPDATE";
                $orderStmt=$this->db->prepare($orderSql);
                $orderStmt->execute([$supplierId]);
                $openOrders=$orderStmt->fetchAll(PDO::FETCH_ASSOC);
                $openById=[];
                foreach($openOrders as $row) $openById[(int)$row['ORDER_ID']]=$row;

                $targets=[];
                if($mode==='AUTO'){
                    $remaining=$available;
                    foreach($openOrders as $row){
                        if($remaining<=0) break;
                        $oid=(int)$row['ORDER_ID'];
                        $due=round((float)$row['TOTAL_PRICE']-(float)$row['TOTAL_PAYMENTS']-(float)$row['RETURN_TOTAL'],2);
                        $take=min($remaining,$due);
                        if($take>0){$targets[$oid]=$take;$remaining=round($remaining-$take,2);}
                    }
                } else {
                    $targets=$requested;
                    foreach($targets as $oid=>$value){
                        if(!isset($openById[$oid])) throw new RuntimeException('أمر الشراء رقم '.$oid.' لا يتبع المورد أو لا يوجد عليه مستحق.');
                        $row=$openById[$oid];
                        $due=round((float)$row['TOTAL_PRICE']-(float)$row['TOTAL_PAYMENTS']-(float)$row['RETURN_TOTAL'],2);
                        if($value-$due>0.009) throw new RuntimeException('المبلغ المحدد لأمر الشراء رقم '.$oid.' يتجاوز المتبقي.');
                    }
                }
                $total=round(array_sum($targets),2);
                if($total<=0) throw new RuntimeException('لا توجد أوامر شراء مستحقة لتطبيق المقدم عليها.');
                if($total-$available>0.009) throw new RuntimeException('إجمالي التوزيع أكبر من مقدم المورد المتاح.');

                $journal=new JournalManager($this->db);
                $journal->create(['branch_id'=>$branchId,'entry_date'=>date('Y-m-d H:i:s'),
                    'reference_type'=>'SUPPLIER_ADVANCE_APPLICATION','reference_id'=>$supplierId,
                    'description'=>'تطبيق مقدمات المورد على أوامر الشراء رقم '.$supplierId,'created_by'=>$userId]);
                foreach($targets as $oid=>$value){
                    $journal->addLine(['account_id'=>$this->accounts->suppliers(),'branch_id'=>$branchId,
                        'partner_type'=>'SUPPLIER','partner_id'=>$supplierId,'reference_type'=>'PURCHASE_ORDER',
                        'reference_id'=>$oid,'debit'=>$value,'note'=>'تطبيق مقدم مورد على أمر شراء رقم '.$oid]);
                }
                $journal->addLine(['account_id'=>$this->accounts->supplierAdvances(),'branch_id'=>$branchId,
                    'partner_type'=>'SUPPLIER','partner_id'=>$supplierId,'reference_type'=>'SUPPLIER_ADVANCE',
                    'reference_id'=>$supplierId,'credit'=>$total,'note'=>'تسوية مقدم المورد على أوامر الشراء']);
                $journalId=$journal->post();

                $appInsert=$this->db->prepare("INSERT INTO supplier_gl_advance_applications
                    (supplier_id,source_advance_allocation_id,purchase_order_id,amount,journal_entry_id,created_by)
                    VALUES (?,?,?,?,?,?)");
                $sourceIndex=0;
                foreach($targets as $oid=>$targetAmount){
                    $need=$targetAmount;
                    while($need>0.009){
                        if(!isset($sources[$sourceIndex])) throw new RuntimeException('تعذر مطابقة المقدمات المتاحة مع التوزيع المطلوب.');
                        $source=$sources[$sourceIndex];
                        $sourceAvailable=round((float)$source['AVAILABLE'],2);
                        if($sourceAvailable<=0.009){$sourceIndex++;continue;}
                        $take=min($need,$sourceAvailable);
                        $appInsert->execute([$supplierId,(int)$source['id'],(int)$oid,$take,$journalId,$userId]);
                        $sources[$sourceIndex]['AVAILABLE']=round($sourceAvailable-$take,2);
                        $need=round($need-$take,2);
                    }
                }
                $update=$this->db->prepare("UPDATE purchases_orders SET TOTAL_PAYMENTS=?,
                    STATUSE=CASE WHEN ?>=? THEN 'COMPLETED' ELSE 'DEFERRED' END
                    WHERE ORDER_ID=? AND SUPPLIER_ID=?");
                foreach($targets as $oid=>$value){
                    $row=$openById[$oid];
                    $newPaid=round((float)$row['TOTAL_PAYMENTS']+$value,2);
                    $netTotal=round((float)$row['TOTAL_PRICE']-(float)$row['RETURN_TOTAL'],2);
                    $update->execute([$newPaid,$newPaid,$netTotal,$oid,$supplierId]);
                    if($update->rowCount()!==1) throw new RuntimeException('تعذر تحديث أمر الشراء رقم '.$oid.'.');
                }
                return $journalId;
            });
        $appliedStmt=$this->db->prepare('SELECT COALESCE(SUM(amount),0) FROM supplier_gl_advance_applications WHERE journal_entry_id=?');
        $appliedStmt->execute([(int)$event['journal_entry_id']]);
        $appliedAmount=round((float)$appliedStmt->fetchColumn(),2);
        return ['journal_entry_id'=>(int)$event['journal_entry_id'],'supplier_id'=>$supplierId,
            'applied_amount'=>$appliedAmount,'mode'=>$mode,'already_posted'=>(bool)$event['already_posted']];
    }

    /**
     * عكس تطبيق مقدم مورد مع إعادة فتح مستحقات أوامر الشراء.
     * يظل سجل التطبيق محفوظًا، ويُحتسب فقط ما زال قيده POSTED كمبلغ مطبق.
     */
    public function reverseAdvanceApplication(int $journalId, int $userId, string $reason = ''): array
    {
        if ($journalId <= 0 || $userId <= 0) throw new InvalidArgumentException('رقم القيد والمستخدم مطلوبان.');
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare("SELECT id,branch_id,reference_type,status FROM journal_entries WHERE id=? FOR UPDATE");
            $lock->execute([$journalId]);
            $journalRow = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$journalRow || $journalRow['reference_type'] !== 'SUPPLIER_ADVANCE_APPLICATION' || $journalRow['status'] !== 'POSTED') {
                throw new RuntimeException('قيد تطبيق المقدم غير موجود أو سبق عكسه.');
            }
            $apps = $this->db->prepare("SELECT supplier_id,purchase_order_id,SUM(amount) amount
                FROM supplier_gl_advance_applications WHERE journal_entry_id=?
                GROUP BY supplier_id,purchase_order_id ORDER BY purchase_order_id FOR UPDATE");
            $apps->execute([$journalId]);
            $rows = $apps->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) throw new RuntimeException('لا توجد تفاصيل تطبيق مرتبطة بهذا القيد.');
            $supplierId = (int)$rows[0]['supplier_id'];
            $supplierLock = $this->db->prepare('SELECT SUP_ID FROM suppliers WHERE SUP_ID=? FOR UPDATE');
            $supplierLock->execute([$supplierId]);
            if (!$supplierLock->fetchColumn()) throw new RuntimeException('المورد المرتبط بالتطبيق غير موجود.');
            $orderLock = $this->db->prepare('SELECT PO.ORDER_ID,PO.TOTAL_PRICE,PO.TOTAL_PAYMENTS,
                COALESCE((SELECT SUM(PR.TOTAL_PRICE-PR.TOTAL_PAYMENTS) FROM purchases_return_orders PR
                    WHERE PR.REFERANC=PO.ORDER_ID AND PR.STATUSE='DEFERRED'
                    AND EXISTS (SELECT 1 FROM inventory_movement IM WHERE IM.REFERENCE=PR.ORDER_ID
                        AND LOWER(IM.IM_MOVE_TYPE)='refundpurchises')),0) RETURN_TOTAL
                FROM purchases_orders PO WHERE PO.ORDER_ID=? AND PO.SUPPLIER_ID=? FOR UPDATE');
            $orderUpdate = $this->db->prepare("UPDATE purchases_orders SET TOTAL_PAYMENTS=?,
                STATUSE=CASE WHEN ?>=? THEN 'COMPLETED' ELSE 'DEFERRED' END
                WHERE ORDER_ID=? AND SUPPLIER_ID=?");
            foreach ($rows as $row) {
                $orderLock->execute([(int)$row['purchase_order_id'],$supplierId]);
                $order = $orderLock->fetch(PDO::FETCH_ASSOC);
                $amount = round((float)$row['amount'],2);
                if (!$order || (float)$order['TOTAL_PAYMENTS'] + 0.009 < $amount) {
                    throw new RuntimeException('لا يمكن عكس التطبيق لأن رصيد أمر الشراء لم يعد يسمح بإرجاع المبلغ.');
                }
                $newPaid = round((float)$order['TOTAL_PAYMENTS']-$amount,2);
                $netTotal = round((float)$order['TOTAL_PRICE']-(float)$order['RETURN_TOTAL'],2);
                $orderUpdate->execute([$newPaid,$newPaid,$netTotal,(int)$order['ORDER_ID'],$supplierId]);
                if ($orderUpdate->rowCount() !== 1) throw new RuntimeException('تعذر إعادة فتح أمر الشراء بعد عكس المقدم.');
            }
            $reversalId = (new JournalManager($this->db))->reverse($journalId, $reason ?: 'عكس تطبيق مقدم المورد', 'SUPPLIER_ADVANCE_APPLICATION');
            $this->db->commit();
            return ['journal_entry_id'=>$journalId,'reversal_journal_entry_id'=>$reversalId,'supplier_id'=>$supplierId];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * عكس دفعة المورد بعد التأكد من إلغاء كل تطبيقات المقدم الصادرة منها.
     * يعكس القيد، ويعيد أرصدة أوامر الشراء، ويربط سجلات الصرف التشغيلية بقيد العكس.
     */
    public function reversePayment(int $paymentId, int $userId, string $reason = ''): array
    {
        if ($paymentId <= 0 || $userId <= 0) throw new InvalidArgumentException('رقم الدفعة والمستخدم مطلوبان.');
        $this->db->beginTransaction();
        try {
            $paymentLock = $this->db->prepare("SELECT id,supplier_id,journal_entry_id,reversal_journal_entry_id
                FROM supplier_gl_payments WHERE id=? FOR UPDATE");
            $paymentLock->execute([$paymentId]);
            $payment = $paymentLock->fetch(PDO::FETCH_ASSOC);
            if (!$payment || !empty($payment['reversal_journal_entry_id'])) throw new RuntimeException('دفعة المورد غير موجودة أو سبق عكسها.');
            $supplierId = (int)$payment['supplier_id'];
            $supplierLock = $this->db->prepare('SELECT SUP_ID FROM suppliers WHERE SUP_ID=? FOR UPDATE');
            $supplierLock->execute([$supplierId]);
            if (!$supplierLock->fetchColumn()) throw new RuntimeException('المورد المرتبط بالدفعة غير موجود.');

            $activeApplications = $this->db->prepare("SELECT COUNT(*) FROM supplier_gl_advance_applications X
                INNER JOIN supplier_gl_payment_allocations A ON A.id=X.source_advance_allocation_id
                INNER JOIN journal_entries J ON J.id=X.journal_entry_id AND J.status='POSTED'
                WHERE A.payment_id=?");
            $activeApplications->execute([$paymentId]);
            if ((int)$activeApplications->fetchColumn() > 0) {
                throw new RuntimeException('يجب عكس تطبيقات المقدم المرتبطة بهذه الدفعة أولًا، ثم إعادة عكس الدفعة.');
            }

            $allocations = $this->db->prepare("SELECT purchase_order_id,SUM(amount) amount
                FROM supplier_gl_payment_allocations
                WHERE payment_id=? AND allocation_type='INVOICE'
                GROUP BY purchase_order_id ORDER BY purchase_order_id FOR UPDATE");
            $allocations->execute([$paymentId]);
            $rows = $allocations->fetchAll(PDO::FETCH_ASSOC);
            $orderLock = $this->db->prepare('SELECT PO.ORDER_ID,PO.TOTAL_PRICE,PO.TOTAL_PAYMENTS,
                COALESCE((SELECT SUM(PR.TOTAL_PRICE-PR.TOTAL_PAYMENTS) FROM purchases_return_orders PR
                    WHERE PR.REFERANC=PO.ORDER_ID AND PR.STATUSE='DEFERRED'
                    AND EXISTS (SELECT 1 FROM inventory_movement IM WHERE IM.REFERENCE=PR.ORDER_ID
                        AND LOWER(IM.IM_MOVE_TYPE)='refundpurchises')),0) RETURN_TOTAL
                FROM purchases_orders PO WHERE PO.ORDER_ID=? AND PO.SUPPLIER_ID=? FOR UPDATE');
            $orderUpdate = $this->db->prepare("UPDATE purchases_orders SET TOTAL_PAYMENTS=?,
                STATUSE=CASE WHEN ?>=? THEN 'COMPLETED' ELSE 'DEFERRED' END
                WHERE ORDER_ID=? AND SUPPLIER_ID=?");
            foreach ($rows as $row) {
                $oid = (int)$row['purchase_order_id'];
                $amount = round((float)$row['amount'],2);
                $orderLock->execute([$oid,$supplierId]);
                $order = $orderLock->fetch(PDO::FETCH_ASSOC);
                if (!$order || (float)$order['TOTAL_PAYMENTS'] + 0.009 < $amount) {
                    throw new RuntimeException('لا يمكن عكس الدفعة لأن رصيد أمر الشراء تغير بعد تسجيلها.');
                }
                $newPaid = round((float)$order['TOTAL_PAYMENTS']-$amount,2);
                $netTotal = round((float)$order['TOTAL_PRICE']-(float)$order['RETURN_TOTAL'],2);
                $orderUpdate->execute([$newPaid,$newPaid,$netTotal,$oid,$supplierId]);
                if ($orderUpdate->rowCount() !== 1) throw new RuntimeException('تعذر تحديث أمر الشراء أثناء عكس الدفعة.');
            }

            $reversalId = (new JournalManager($this->db))->reverse((int)$payment['journal_entry_id'],
                $reason ?: 'عكس دفعة المورد', 'SUPPLIER_PAYMENT');
            $markPayment = $this->db->prepare('UPDATE supplier_gl_payments SET reversal_journal_entry_id=? WHERE id=? AND reversal_journal_entry_id IS NULL');
            $markPayment->execute([$reversalId,$paymentId]);
            if ($markPayment->rowCount() !== 1) throw new RuntimeException('تعذر تسجيل عكس دفعة المورد.');
            $markOperational = $this->db->prepare('UPDATE purchases_payments SET reversal_journal_entry_id=? WHERE supplier_gl_payment_id=? AND reversal_journal_entry_id IS NULL');
            $markOperational->execute([$reversalId,$paymentId]);
            $this->db->commit();
            return ['payment_id'=>$paymentId,'supplier_id'=>$supplierId,'journal_entry_id'=>(int)$payment['journal_entry_id'],'reversal_journal_entry_id'=>$reversalId];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

}
