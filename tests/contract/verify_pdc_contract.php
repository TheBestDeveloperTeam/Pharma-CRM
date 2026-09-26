<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$service=file_get_contents($root.'/app/Domain/Payments/PdcService.php');$controller=file_get_contents($root.'/app/Http/Controllers/Api/V1/Admin/PdcsController.php');$routes=file_get_contents($root.'/bootstrap/routes.php');$openapi=file_get_contents($root.'/public/api-docs/openapi.yaml');
foreach(['register','realize','bounce','cancel','FOR UPDATE','payment_ref','INVALID_PDC_TRANSITION']as$n)if(!str_contains($service,$n))throw new RuntimeException("PDC lifecycle evidence missing: $n");
foreach(['TenantContext','PartyScopePredicate','scopedForUpdate','listScoped','findScoped']as$n)if(!str_contains($service,$n))throw new RuntimeException("PDC scope evidence missing: $n");
foreach(['requirePermission','PdcService','pdc.registered','pdc.realized','pdc.bounced']as$n)if(!str_contains($controller,$n))throw new RuntimeException("PDC controller evidence missing: $n");
foreach(['/api/v1/admin/pdc','/realize','/bounce','/cancel']as$n)if(!str_contains($routes,$n))throw new RuntimeException("PDC route missing: $n");if(!str_contains($openapi,'Pdc:'))throw new RuntimeException('PDC OpenAPI schema missing.');echo "PDC source contract is present.\n";
