<?php
return [
    'name'  => 'agent-jobs',
    'scope' => 'jobs',
    'group' => 'crm',
    'steps' => [
        // 1. Dispatch async job
        function() {
            $dispatcher = \App\Core\Container::getInstance()->make(\App\Domain\Jobs\JobDispatcher::class);
            $jobRef = $dispatcher->dispatch(
                'ProcessWebhookEvent',
                ['test' => true],
                'ORG-PLATFORM0000000001',
                'FRN-MUMBAI000000000001',
                'test_dedupe_' . bin2hex(random_bytes(4))
            );

            \Tests\Support\Assert::true(!empty($jobRef), 'Job successfully enqueued');
        },
        // 2. Dedupe key prevents duplicate job insertion
        function() {
            $dispatcher = \App\Core\Container::getInstance()->make(\App\Domain\Jobs\JobDispatcher::class);
            $key = 'dedupe_sample_key_01';

            $job1 = $dispatcher->dispatch('ProcessWebhookEvent', ['run' => 1], null, null, $key);
            $job2 = $dispatcher->dispatch('ProcessWebhookEvent', ['run' => 2], null, null, $key);

            \Tests\Support\Assert::true($job2 === null, 'Duplicate job not inserted due to unique dedupe key');
        },
        // 3. Worker executes jobs
        function() {
            $runner = \App\Core\Container::getInstance()->make(\App\Core\JobRunner::class);
            $count = $runner->runNext(5);
            \Tests\Support\Assert::true($count >= 0, 'Worker executed without fatal error');
        },
    ]
];
