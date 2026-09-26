<?php
declare(strict_types=1);

$contract = require __DIR__ . '/analytics_dto_contract.php';
$service = file_get_contents(dirname(__DIR__, 2) . '/app/Domain/Reports/ScopedAnalyticsService.php');
$openapi = file_get_contents(dirname(__DIR__, 2) . '/public/api-docs/openapi.yaml');
foreach ($contract['dashboard'] as $group => $fields) {
    foreach ($fields as $field) {
        if (!str_contains($service, $field) || !str_contains($openapi, $field)) {
            throw new RuntimeException("Dashboard contract field missing: $group.$field");
        }
    }
}
foreach ($contract['reports'] as $report => $fields) {
    if (!str_contains($service, "'$report'")) throw new RuntimeException("Report adapter missing: $report");
    foreach ($fields as $field) {
        if (!str_contains($service, $field) || !str_contains($openapi, $field)) {
            throw new RuntimeException("Report contract field missing: $report.$field");
        }
    }
}
echo "Analytics DTO contract inventory is present.\n";
