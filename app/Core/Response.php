<?php
declare(strict_types=1);
namespace App\Core;

final class Response
{
    private bool $sent = false;

    private function __construct(
        private readonly int    $status,
        private readonly string $body,
        private readonly array  $headers,
    ) {}

    // ── Factories ──────────────────────────────────────────────────────────

    public static function json(int|array $statusOrData, int|array $dataOrMeta = [], array $meta = [], array $extraHeaders = []): self
    {
        $status = is_int($statusOrData) ? $statusOrData : (is_int($dataOrMeta) ? $dataOrMeta : 200);
        $payload = is_int($statusOrData) ? $dataOrMeta : $statusOrData;
        $providedMeta = is_int($statusOrData) ? $meta : (is_int($dataOrMeta) ? $meta : $dataOrMeta);

        if (is_array($payload) && array_key_exists('success', $payload)) {
            if ($payload['success'] === false) {
                $error = is_array($payload['error'] ?? null) ? $payload['error'] : [];
                return self::error($status, (string)($error['code'] ?? ApiErrorCodes::INTERNAL_ERROR), (string)($error['message'] ?? 'Request failed.'), (array)($error['fields'] ?? []), $extraHeaders);
            }
            $data = $payload['data'] ?? null;
            $providedMeta = array_merge($providedMeta, is_array($payload['meta'] ?? null) ? $payload['meta'] : []);
        } else {
            $data = is_array($payload) && array_key_exists('data', $payload) ? $payload['data'] : $payload;
            if (is_array($payload) && array_key_exists('meta', $payload) && is_array($payload['meta'])) {
                $providedMeta = array_merge($providedMeta, $payload['meta']);
            }
            // Several existing list controllers pass a repository result as
            // ['data' => ['data' => $items, 'meta' => $pagination]]. Unwrap
            // that historical shape centrally so the public contract remains
            // one envelope without a broad controller rewrite.
            if (is_array($data) && array_key_exists('data', $data) && array_key_exists('meta', $data) && is_array($data['meta'])) {
                $providedMeta = array_merge($providedMeta, $data['meta']);
                $data = $data['data'];
            }
        }

        $envelope = [
            'success' => $status < 400,
            'data'    => $data,
            'meta'    => array_merge(['request_id' => RequestId::current()], $providedMeta),
        ];

        return new self(
            $status,
            (string)json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            array_merge(['Content-Type' => 'application/json; charset=UTF-8'], $extraHeaders)
        );
    }

    public static function error(
        int    $status,
        string $code,
        string $message,
        array  $fields = [],
        array  $extraHeaders = []
    ): self {
        $envelope = [
            'success' => false,
            'error'   => array_filter([
                'code'    => $code,
                'message' => $message,
                'fields'  => $fields ?: null,
            ]),
            'meta'    => ['request_id' => RequestId::current()],
        ];

        return new self(
            $status,
            (string)json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            array_merge(['Content-Type' => 'application/json; charset=UTF-8'], $extraHeaders)
        );
    }

    public static function html(int $status, string $html, array $extraHeaders = []): self
    {
        return new self(
            $status,
            $html,
            array_merge(['Content-Type' => 'text/html; charset=UTF-8'], $extraHeaders)
        );
    }

    public static function empty(int $status = 204): self
    {
        return new self($status, '', ['Content-Length' => '0']);
    }

    public static function replay(int $status, string $jsonBody): self
    {
        return new self(
            $status,
            $jsonBody,
            [
                'Content-Type'      => 'application/json; charset=UTF-8',
                'Idempotent-Replay' => 'true',
            ]
        );
    }

    // ── Accessors ──────────────────────────────────────────────────────────

    public function status(): int   { return $this->status; }
    public function body(): string  { return $this->body; }
    public function headers(): array { return $this->headers; }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->status, $this->body, array_merge($this->headers, [$name => $value]));
    }

    // ── Send ──────────────────────────────────────────────────────────────

    public function send(): void
    {
        if ($this->sent) return;
        $this->sent = true;

        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("$name: $value", replace: true);
        }

        if ($this->body !== '') {
            echo $this->body;
        }
    }
}
