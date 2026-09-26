<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$files=['pdc'=>"$root/app/Domain/Payments/PdcService.php",'allocation'=>"$root/app/Domain/Payments/AllocationService.php",'outstanding'=>"$root/app/Domain/Payments/OutstandingService.php",'credit'=>"$root/app/Domain/Parties/PartyCreditService.php",'orders'=>"$root/app/Domain/Orders/OrderService.php",'analytics'=>"$root/app/Domain/Reports/ScopedAnalyticsService.php",'controller'=>"$root/app/Http/Controllers/Api/V1/Admin/OutstandingController.php"];
foreach($files as$key=>$path){$source=file_get_contents($path);if($source===false)throw new RuntimeException("Missing financial source: $key");$files[$key]=$source;}
$required=[
 'pdc'=>['TenantContext','PartyScopePredicate','scopedForUpdate','FOR UPDATE'],
 'allocation'=>['TenantContext','PartyScopePredicate','FOR UPDATE','PAYMENT_OVER_ALLOCATION','INVOICE_OVER_ALLOCATION','REVERSED'],
 'outstanding'=>['rowsForContext','PartyScopePredicate',"a.status='ACTIVE'","pay.status NOT IN ('REVERSED','CANCELLED')",'NOT_DUE',"'0-30'","'31-60'","'61-90'","'91-120'","'120+'"],
 'credit'=>['checkForConfirmation','FOR UPDATE','unpaid_invoice_outstanding','uninvoiced_confirmed_order_exposure','realized_unallocated_advance','NOT EXISTS','REALIZED','$projected>$limit'],
 'orders'=>['checkForConfirmation','CREDIT_LIMIT_EXCEEDED'],
 'analytics'=>['payment-outstanding','paymentOutstanding','OutstandingService','dashboardForContext'],
 'controller'=>['requirePermission','pageForContext'],
];
foreach($required as$key=>$need)foreach($need as$needle)if(!str_contains($files[$key],$needle))throw new RuntimeException("Financial contract evidence missing: $key::$needle");
echo "Financial closure source contract is present.\n";
