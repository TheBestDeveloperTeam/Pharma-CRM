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

        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? 'req_unknown';

        // Validation error — include field-level errors
        if ($e instanceof ValidationException) {
            return Response::json([
                'success' => false,
                'error'   => [
                    'code'    => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                    'fields'  => $e->getFieldErrors(),
                ],
                'meta' => ['request_id' => $requestId],
            ], 422);
        }

        // Known application exceptions
        if ($e instanceof AppException) {
            return Response::json([
                'success' => false,
                'error'   => [
                    'code'    => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ],
                'meta' => ['request_id' => $requestId],
            ], $e->getHttpStatus());
        }

        // Unknown / system errors — never expose internals
        $message = env('APP_DEBUG') === true
            ? $e->getMessage()
            : 'An unexpected error occurred. Please try again.';

        return Response::json([
            'success' => false,
            'error'   => [
                'code'    => 'INTERNAL_ERROR',
                'message' => $message,
            ],
            'meta' => ['request_id' => $requestId],
        ], 500);
    }
}
