<?php
declare(strict_types=1);

/**
 * cPanel Scheduler CLI Runner
 * Usage:
 *   php cli/scheduler.php minute
 *   php cli/scheduler.php five-minute
 *   php cli/scheduler.php hourly
 *   php cli/scheduler.php daily
 */

require_once __DIR__ . '/../bootstrap/app.php';

$action = $argv[1] ?? 'minute';
$container = \App\Core\Container::getInstance();

echo "[" . date('Y-m-d H:i:s') . "] Starting scheduler tick: {$action}\n";

switch ($action) {
    case 'minute':
        // Run queued background jobs with a 50 second budget
        $runner = $container->make(\App\Core\JobRunner::class);
        $start = time();
        $processedTotal = 0;
        while ((time() - $start) < 45) {
            $count = $runner->runNext(10);
            $processedTotal += $count;
            if ($count === 0) {
                break;
            }
            usleep(250000); // 250ms delay between batches
        }
        echo "  [minute] Processed {$processedTotal} background job(s).\n";
        break;

    case 'five-minute':
        // Webhook sweeps or retry checks
        $runner = $container->make(\App\Core\JobRunner::class);
        $count = $runner->runNext(20);
        echo "  [five-minute] Swept and executed {$count} pending/retry job(s).\n";
        break;

    case 'hourly':
        // Scanner duties: follow-up reminders & expired reservation releases
        $scanner = $container->make(\App\Domain\Jobs\ReminderScannerService::class);
        $fCount = $scanner->scanDueFollowUps();
        $rCount = $scanner->releaseExpiredReservations();
        echo "  [hourly] Enqueued {$fCount} follow-up reminder(s), released {$rCount} expired reservation(s).\n";
        break;

    case 'daily':
        // Near-expiry scans, overdue invoices, log rotation
        $scanner = $container->make(\App\Domain\Jobs\ReminderScannerService::class);
        $pCount = $scanner->scanOverdueInvoices();
        $eCount = $scanner->scanNearExpiryBatches();
        echo "  [daily] Scanned {$pCount} overdue payment reminder(s), {$eCount} near-expiry batch reminder(s).\n";
        break;

    default:
        echo "Unknown scheduler tick: {$action}. Available: minute, five-minute, hourly, daily.\n";
        exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Scheduler tick {$action} completed.\n";
exit(0);
