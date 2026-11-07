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
                $paymentRow=$this->db->prepare('INSERT INTO purchases_payments (ORDER_ID,AMOUNT,PAYMENT_METHOD,NOTE,CREATED_BY) VALUES (?,?,?,?,?)');
                $update=$this->db->prepare("UPDATE purchases_orders SET TOTAL_PAYMENTS=?,
                    STATUSE=CASE WHEN ?>=? THEN 'COMPLETED' ELSE 'DEFERRED' END WHERE ORDER_ID=? AND SUPPLIER_ID=?");
                $allocationInsert=$this->db->prepare("INSERT INTO supplier_gl_payment_allocations
                    (payment_id,supplier_id,purchase_order_id,allocation_type,amount,created_by) VALUES (?,?,?,?,?,?)");
                foreach($allocations as $oid=>$value){
                    $paymentRow->execute([$oid,$value,$paymentMethodId,trim($note)?:'سداد من دفعة المورد رقم '.$paymentId,$userId]);
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
}
