<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Core\Container;
use App\Core\JobRunner;

$runner = Container::getInstance()->make(JobRunner::class);
$limit = isset($argv[1]) ? (int)$argv[1] : 10;

$count = $runner->runNext($limit);
echo "Worker completed. Processed {$count} jobs.\n";
