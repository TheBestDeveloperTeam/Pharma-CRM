<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use App\Core\Request;
use App\Core\Response;

/**
 * Exception Handler — Converts any Throwable to a clean JSON response.
 * Never exposes stack traces to clients.
 */
class Handler
{
    public function __construct(private readonly \App\Core\Container $container) {}

    public function render(\Throwable $e): Response
    {
        // Log the exception
        try {
            $logger = $this->container->make(\App\Core\Logger::class);
            $logger->error($e->getMessage(), [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => env('APP_DEBUG') === true ? $e->getTraceAsString() : '[hidden]',
            ]);
        } catch (\Throwable) {
            // Logger unavailable — fail silently
        }

        if ($e instanceof AppException) {
            return Response::error($e->statusCode(), $e->errorCode(), $e->getMessage(), $e->fields());
        }

        // Unknown / system errors — never expose internals
        $message = env('APP_DEBUG') === true
            ? $e->getMessage()
            : 'An unexpected error occurred. Please try again.';

        return Response::error(500, 'INTERNAL_ERROR', $message);
    }
}
