<?php
declare(strict_types=1);
namespace App\Domain\Payments;
use App\Core\{Database,RefGenerator};use App\Core\Exceptions\{ConflictException,ValidationException};

/** Full, immutable posted-payment reversal. */
final class PaymentReversalService {
 public function __construct(private Database $db){}
 public function reverse(string $franchiseRef,string $paymentRef,string $actorRef,string $reason,string $idempotencyKey):array{return $this->db->transaction(function()use($franchiseRef,$paymentRef,$actorRef,$reason,$idempotencyKey){
  if(trim($reason)==='')throw new ValidationException('REVERSAL_REASON_REQUIRED','A reversal reason is required.');
  $payment=$this->db->fetchOne('SELECT * FROM payments WHERE franchise_ref=? AND payment_ref=? FOR UPDATE',[$franchiseRef,$paymentRef]);if(!$payment)throw new ValidationException('PAYMENT_NOT_FOUND','Payment was not found.');
  $existing=$this->db->fetchOne('SELECT reversal_ref FROM payment_reversals WHERE franchise_ref=? AND (payment_ref=? OR idempotency_key=?) FOR UPDATE',[$franchiseRef,$paymentRef,$idempotencyKey]);if($existing)throw new ConflictException('PAYMENT_ALREADY_REVERSED','Payment reversal already exists.');
  if(in_array($payment['status'],['REVERSED','CANCELLED'],true))throw new ValidationException('PAYMENT_NOT_REVERSIBLE','Payment is not reversible.');
  $alloc=$this->db->fetchAll("SELECT * FROM payment_allocations WHERE franchise_ref=? AND payment_ref=? AND status='ACTIVE' FOR UPDATE",[$franchiseRef,$paymentRef]);
  foreach($alloc as$a){$this->db->prepare("UPDATE invoices SET paid_total=paid_total-? WHERE franchise_ref=? AND invoice_ref=? AND paid_total>=?")->execute([$a['allocated_amount'],$franchiseRef,$a['invoice_ref'],$a['allocated_amount']]);$this->db->prepare("UPDATE payment_allocations SET status='REVERSED',reversed_at=NOW(),reversed_by_ref=?,reversal_reason=? WHERE franchise_ref=? AND allocation_ref=? AND status='ACTIVE'")->execute([$actorRef,$reason,$franchiseRef,$a['allocation_ref']]);}
  $ref=RefGenerator::generate('prv');$this->db->insert('payment_reversals',['reversal_ref'=>$ref,'org_ref'=>$payment['org_ref'],'franchise_ref'=>$franchiseRef,'payment_ref'=>$paymentRef,'reason'=>$reason,'idempotency_key'=>$idempotencyKey,'reversed_by_ref'=>$actorRef]);$this->db->prepare("UPDATE payments SET status='REVERSED',allocated_amount=0.00 WHERE franchise_ref=? AND payment_ref=?")->execute([$franchiseRef,$paymentRef]);return['reversal_ref'=>$ref,'payment_ref'=>$paymentRef,'status'=>'REVERSED'];});}
}
