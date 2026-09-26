<?php
declare(strict_types=1);

/* Source-level production-call-path contract, runnable when PHP is installed. */
$root = dirname(__DIR__, 2);
$billing = file_get_contents($root . '/app/Domain/Billing/BillingService.php');
$repository = file_get_contents($root . '/app/Repositories/Sql/SqlInvoiceRepository.php');
$openapi = file_get_contents($root . '/public/api-docs/openapi.yaml');
$calculator = file_get_contents($root . '/app/Domain/Billing/GstCalculator.php');
$required = [
    [$billing, 'SUPPLIER_STATE_REQUIRED'], [$billing, 'PLACE_OF_SUPPLY_REQUIRED'], [$billing, 'GstCalculator::INTRASTATE'], [$billing, 'GstCalculator::INTERSTATE'], [$billing, '$this->gst->calculate'],
    [$billing, "'cgst_percent'"], [$billing, "'sgst_percent'"], [$billing, "'igst_percent'"], [$billing, "'total_tax'"], [$billing, "'line_total'"],
    [$repository, 'cgst_percent'], [$repository, 'sgst_percent'], [$repository, 'igst_percent'], [$repository, 'cgst_amount'], [$repository, 'sgst_amount'], [$repository, 'igst_amount'], [$repository, 'taxable_amount'], [$repository, 'total_tax'], [$repository, 'line_total'],
    [$calculator, 'Money::fromDecimal'], [$calculator, 'INTRASTATE'], [$calculator, 'INTERSTATE'],
    [$openapi, 'supplier_state_ref'], [$openapi, 'place_of_supply_state_ref'], [$openapi, 'cgst_percent'], [$openapi, 'sgst_percent'], [$openapi, 'igst_percent'],
];
foreach ($required as [$source, $needle]) if (!str_contains($source, $needle)) throw new RuntimeException("Missing invoice/GST production contract evidence: $needle");
if (str_contains($billing, 'GST_POLICY_PENDING') || str_contains($billing, 'consumeOrderStock')) throw new RuntimeException('Billing retains a stale GST block or consumes reserved stock.');
echo "Invoice/GST production contract is present.\n";
