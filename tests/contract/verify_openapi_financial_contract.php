<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$routes=file_get_contents($root.'/bootstrap/routes.php');$openapi=file_get_contents($root.'/public/api-docs/openapi.yaml');
foreach(['/api/v1/admin/outstanding','/admin/outstanding','OutstandingInvoice:','PaymentOutstandingRow:','payment-outstanding','CREDIT_LIMIT_EXCEEDED']as$needle){$source=str_starts_with($needle,'/api')?$routes:$openapi;if(!str_contains($source,$needle))throw new RuntimeException("OpenAPI/route consistency evidence missing: $needle");}
foreach(['invoiceNumber','partyName','dueDate','balance','bucket','status','NOT_DUE','91-120','120+']as$field)if(!str_contains($openapi,$field))throw new RuntimeException("Financial DTO field missing from OpenAPI: $field");
echo "Financial OpenAPI route/DTO contract is present.\n";
