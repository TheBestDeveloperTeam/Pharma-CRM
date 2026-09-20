<?php

declare(strict_types=1);

namespace App\Core;

/**
 * EventBus — Lightweight synchronous in-process event dispatcher.
 */
class EventBus
{
    private array $listeners = [];

    public function subscribe(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    public function dispatch(string $event, mixed $payload = null): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener($payload);
        }
    }
}
