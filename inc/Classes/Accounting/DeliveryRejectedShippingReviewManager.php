<?php
declare(strict_types=1);
require_once __DIR__ . '/JournalManager.php';
require_once __DIR__ . '/FinancialEventManager.php';

/** قائمة مراجعة لتعويضات رفض الطلبات التي لم يتحصل فيها الشحن. */
final class DeliveryRejectedShippingReviewManager
{
 private PDO $db; private JournalManager $journal; private FinancialEventManager $events;
 public function __construct(PDO $db) { $this->db=$db; $this->journal=new JournalManager($db); $this->events=new FinancialEventManager($db); }

 /** يحفظ المحاولات غير المحصلة للمراجعة فقط، دون قيد مالي. */
 public function recordUnpaidAttempts(string $sessionId,int $branchId,int $actorId): array
 {
  if(!$this->db->inTransaction()) throw new RuntimeException('يجب تسجيل تسوية الشحن داخل معاملة تأكيد الشحنة.');
  if(trim($sessionId)===''||$branchId<=0||$actorId<=0) throw new InvalidArgumentException('بيانات تسوية الشحن غير مكتملة.');
  $q=$this->db->prepare("SELECT sm.shipmov_id,sm.shipment_orders order_id,sm.delivery_id,o.ORDER_TYPE_ID,o.SHIPPING_VAL,s.BRANCH_ID order_branch_id,ud.PERCENT delivery_percent
   FROM shipping_movement sm JOIN orders o ON o.ORDER_ID=sm.shipment_orders
   LEFT JOIN stores s ON s.STO_ID=o.STO_ID LEFT JOIN users_delivery ud ON ud.USER_ID=sm.delivery_id
   WHERE sm.UNIQUE_ID=? AND sm.movement_type=5
   AND sm.shipmov_id=(SELECT MIN(sm2.shipmov_id) FROM shipping_movement sm2
    WHERE sm2.UNIQUE_ID=sm.UNIQUE_ID AND sm2.movement_type=sm.movement_type
      AND sm2.shipment_orders=sm.shipment_orders AND sm2.delivery_id=sm.delivery_id)
   ORDER BY sm.shipmov_id FOR UPDATE");
  $q->execute([$sessionId]); $rows=$q->fetchAll(PDO::FETCH_ASSOC);
  $ins=$this->db->prepare("INSERT INTO delivery_rejected_shipping_reviews
   (shipmov_id,order_id,delivery_user_id,branch_id,shipping_amount_snapshot,commission_percent_snapshot,estimated_compensation,status,created_by)
   VALUES (?,?,?,?,?,?,?,'PENDING',?) ON DUPLICATE KEY UPDATE shipmov_id=VALUES(shipmov_id)");
  $queued=0;
  foreach($rows as $r){
   if((int)$r['ORDER_TYPE_ID']===5) continue;
   $order=(int)$r['order_id']; $delivery=(int)$r['delivery_id']; $orderBranch=(int)($r['order_branch_id']??0);
   if($order<=0||$delivery<=0||$orderBranch!==$branchId) throw new RuntimeException('بيانات الطلب أو المندوب غير متطابقة لتسوية الشحن.');
   $shipping=round((float)$r['SHIPPING_VAL'],2); $percent=round((float)$r['delivery_percent'],4);
   if(!is_finite($shipping)||$shipping<0||!is_finite($percent)||$percent<0||$percent>100) throw new RuntimeException('قيمة الشحن أو نسبة العمولة غير صالحة للطلب '.$order.'.');
   $ins->execute([(int)$r['shipmov_id'],$order,$delivery,$orderBranch,$shipping,$percent,round($shipping*$percent/100,2),$actorId]);
   if($ins->rowCount()===1) $queued++;
  }
  return ['processed'=>count($rows),'queued'=>$queued];
 }

 public function listPending(int $branchId,?int $deliveryId=null): array
 {
  if($branchId<=0) throw new InvalidArgumentException('الفرع الحالي غير محدد.');
  $sql="SELECT r.id,r.shipmov_id,r.order_id,o.SUB_ORDER_ID,r.delivery_user_id,
    r.shipping_amount_snapshot,
    r.commission_percent_snapshot,r.estimated_compensation,r.status,r.created_at
   FROM delivery_rejected_shipping_reviews r JOIN orders o ON o.ORDER_ID=r.order_id
   WHERE r.branch_id=? AND r.status='PENDING'";
  $p=[$branchId]; if($deliveryId!==null&&$deliveryId>0){$sql.=" AND r.delivery_user_id=?";$p[]=$deliveryId;}
  $sql.=" ORDER BY r.created_at,r.id"; $s=$this->db->prepare($sql);$s->execute($p);return $s->fetchAll(PDO::FETCH_ASSOC);
 }

 /** اعتماد كامل/جزئي أو رفض؛ القيد لا ينشأ إلا عند الاعتماد. */
 public function decide(int $id,int $branchId,int $actorId,bool $approve,float $amount=0,string $note=''): array
 {
  if($id<=0||$branchId<=0||$actorId<=0) throw new InvalidArgumentException('بيانات القرار غير مكتملة.');
  $note=trim($note); if(mb_strlen($note,'UTF-8')>500) throw new InvalidArgumentException('الملاحظة لا تتجاوز 500 حرف.');
  $amount=$approve?round($amount,2):0;
  if($approve&&(!is_finite($amount)||$amount<=0)) throw new InvalidArgumentException('أدخل تعويضًا أكبر من صفر.');
  $owns=!$this->db->inTransaction(); if($owns)$this->db->beginTransaction();
  try{
   $s=$this->db->prepare("SELECT * FROM delivery_rejected_shipping_reviews WHERE id=? AND branch_id=? FOR UPDATE");
   $s->execute([$id,$branchId]);$r=$s->fetch(PDO::FETCH_ASSOC);
   if(!$r)throw new RuntimeException('طلب التسوية غير موجود.'); if($r['status']!=='PENDING')throw new RuntimeException('تم اتخاذ قرار سابق لهذا الطلب.');
   if($approve&&$amount>(float)$r['estimated_compensation']+0.009)throw new InvalidArgumentException('التعويض لا يجوز أن يتجاوز العمولة التقديرية.');
   $eventId=null;$journalId=null;$status=$approve?'APPROVED':'REJECTED';
   if($approve){
    $settings=$this->db->query("SELECT courier_commission_expense_account_id,courier_commission_payable_account_id FROM delivery_accounting_settings WHERE id=1 FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
    if(!$settings||(int)$settings['courier_commission_expense_account_id']<=0||(int)$settings['courier_commission_payable_account_id']<=0)throw new RuntimeException('اضبط حساب مصروف العمولة وحساب العمولة المستحقة أولًا.');
    $event=$this->events->post([
     'idempotency_key'=>'DELIVERY_REJECTED_SHIPPING_COMPENSATION:'.(int)$r['id'],
     'event_type'=>'COMMISSION_COMPENSATION_APPROVED','source_type'=>'DELIVERY_REJECTED_SHIPPING_REVIEW',
     'source_id'=>(int)$r['id'],'branch_id'=>$branchId,'event_date'=>date('Y-m-d H:i:s'),'created_by'=>$actorId,
     'payload'=>['review_id'=>(int)$r['id'],'shipmov_id'=>(int)$r['shipmov_id'],'order_id'=>(int)$r['order_id'],
      'delivery_id'=>(int)$r['delivery_user_id'],'estimated_compensation'=>number_format((float)$r['estimated_compensation'],2,'.',''),
      'approved_compensation'=>number_format($amount,2,'.',''),'decision_note'=>$note]
    ],function()use($r,$branchId,$actorId,$amount,$settings):int{
     $this->journal->reset()->create(['branch_id'=>$branchId,'entry_date'=>date('Y-m-d H:i:s'),
      'reference_type'=>'DELIVERY_REJECTION_COMPENSATION','reference_id'=>(int)$r['id'],
      'description'=>'تعويض مندوب عن شحن غير محصل للطلب '.(string)$r['order_id'],'created_by'=>$actorId]);
     $this->journal->addLine(['account_id'=>(int)$settings['courier_commission_expense_account_id'],'branch_id'=>$branchId,
      'debit'=>$amount,'reference_type'=>'DELIVERY_REJECTION_COMPENSATION','reference_id'=>(int)$r['id'],
      'note'=>'مصروف تعويض رفض طلب غير محصل الشحن']);
     $this->journal->addLine(['account_id'=>(int)$settings['courier_commission_payable_account_id'],'branch_id'=>$branchId,
      'partner_type'=>'DELIVERY','partner_id'=>(int)$r['delivery_user_id'],'credit'=>$amount,
      'reference_type'=>'DELIVERY_REJECTION_COMPENSATION','reference_id'=>(int)$r['id'],
      'note'=>'تعويض معتمد للمندوب عن رفض طلب غير محصل الشحن']);
     return $this->journal->post();
    });
    $eventId=(int)$event['event_id'];$journalId=(int)$event['journal_entry_id'];
   }
   $u=$this->db->prepare("UPDATE delivery_rejected_shipping_reviews SET status=?,approved_compensation=?,accounting_event_id=?,journal_entry_id=?,decision_note=?,decided_by=?,decided_at=NOW() WHERE id=? AND status='PENDING'");
   $u->execute([$status,$amount,$eventId,$journalId,$note,$actorId,$id]);if($u->rowCount()!==1)throw new RuntimeException('تعذر حفظ القرار.');
   if($owns)$this->db->commit();
   return ['success'=>true,'status'=>$status,'approved_compensation'=>$amount,'accounting_event_id'=>$eventId,'journal_entry_id'=>$journalId];
  }catch(Throwable $e){if($owns&&$this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
}
