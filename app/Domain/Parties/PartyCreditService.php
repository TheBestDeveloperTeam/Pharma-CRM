<?php
declare(strict_types=1);
namespace App\Domain\Parties;
use App\Core\Database;
use App\Core\Exceptions\ValidationException;
use App\Support\Money;

/** Authoritative credit exposure; posted invoices and uninvoiced confirmed orders never overlap. */
final class PartyCreditService {
 public function __construct(private Database $db){}
 public function check(string $franchiseRef,string $partyRef,int|float|string $proposedExposure=0):array{return $this->snapshot($franchiseRef,$partyRef,(string)$proposedExposure,false);}
 /** Called only from the order-confirmation transaction to serialize competing confirmations for one party. */
 public function checkForConfirmation(string $franchiseRef,string $partyRef,int|float|string $proposedExposure):array{return $this->snapshot($franchiseRef,$partyRef,(string)$proposedExposure,true);}
 private function snapshot(string $franchiseRef,string $partyRef,string $proposed,bool $lockParty):array{
  $suffix=$lockParty?' FOR UPDATE':'';$party=$this->db->fetchOne("SELECT party_ref,credit_limit,opening_outstanding FROM parties WHERE franchise_ref=:f AND party_ref=:p AND status='ACTIVE' LIMIT 1$suffix",[':f'=>$franchiseRef,':p'=>$partyRef]);if(!$party)throw new ValidationException('PARTY_NOT_FOUND','Active party was not found.');
  $opening=Money::fromDecimal((string)($party['opening_outstanding']??'0'));
  $invoice=Money::fromDecimal((string)$this->db->fetchColumn("SELECT COALESCE(SUM(grand_total-paid_total),0) FROM invoices WHERE franchise_ref=:f AND party_ref=:p AND status='POSTED'",[':f'=>$franchiseRef,':p'=>$partyRef]));
  $orders=Money::fromDecimal((string)$this->db->fetchColumn("SELECT COALESCE(SUM(o.grand_total),0) FROM orders o WHERE o.franchise_ref=:f AND o.party_ref=:p AND o.status IN ('CONFIRMED','PROCESSING','DISPATCHED','DELIVERED') AND NOT EXISTS(SELECT 1 FROM invoices i WHERE i.franchise_ref=o.franchise_ref AND i.order_ref=o.order_ref AND i.status='POSTED')",[':f'=>$franchiseRef,':p'=>$partyRef]));
  $advance=Money::fromDecimal((string)$this->db->fetchColumn("SELECT COALESCE(SUM(pay.amount-pay.allocated_amount),0) FROM payments pay WHERE pay.franchise_ref=:f AND pay.party_ref=:p AND pay.status IN ('RECORDED','PARTIALLY_ALLOCATED') AND (pay.mode<>'PDC' OR EXISTS(SELECT 1 FROM pdcs d WHERE d.franchise_ref=pay.franchise_ref AND d.payment_ref=pay.payment_ref AND d.status='REALIZED'))",[':f'=>$franchiseRef,':p'=>$partyRef]));
  $current=max(0,$opening+$invoice+$orders-$advance);$proposal=Money::fromDecimal($proposed);if($proposal<0)throw new ValidationException('INVALID_CREDIT_EXPOSURE','Proposed exposure cannot be negative.');$projected=$current+$proposal;$limit=Money::fromDecimal((string)($party['credit_limit']??'0'));
  return ['party_ref'=>$partyRef,'credit_limit'=>Money::toDecimal($limit),'opening_outstanding'=>Money::toDecimal($opening),'unpaid_invoice_outstanding'=>Money::toDecimal($invoice),'uninvoiced_confirmed_order_exposure'=>Money::toDecimal($orders),'realized_unallocated_advance'=>Money::toDecimal($advance),'current_exposure'=>Money::toDecimal($current),'proposed_exposure'=>Money::toDecimal($proposal),'projected_exposure'=>Money::toDecimal($projected),'available_credit'=>Money::toDecimal($limit-$current),'credit_breached'=>$projected>$limit];
 }
}
