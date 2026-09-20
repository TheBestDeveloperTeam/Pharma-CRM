<?php

declare(strict_types=1);

echo "============================================\n";
echo "    Running Pharma CRM Test Suite           \n";
echo "============================================\n\n";

$php = 'E:\xampp\php\php.exe';

echo ">> Running Auth & Users API Tests...\n";
system("$php cli/api_test_bot.php", $returnAuth);

echo "\n>> Running Masters API Tests...\n";
system("$php cli/masters_test_bot.php", $returnMasters);

echo "\n>> Running DCR API Tests...\n";
system("$php cli/dcr_test_bot.php", $returnDcr);

echo "\n>> Running Orders API Tests...\n";
system("$php cli/orders_test_bot.php", $returnOrders);

echo "\n============================================\n";
if ($returnAuth === 0 && $returnMasters === 0 && $returnDcr === 0 && $returnOrders === 0) {
    echo "✅ ALL TEST SUITES PASSED!\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED.\n";
    exit(1);
}
