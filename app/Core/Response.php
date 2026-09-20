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

    public static function json(
        int|array $statusOrData,
        int|array $dataOrMeta = [],
        array $meta = [],
        array $extraHeaders = []
    ): self {
        if (is_array($statusOrData)) {
            if (is_int($dataOrMeta)) {
                $status = $dataOrMeta;
                $data   = $statusOrData;
                $meta   = $meta;
            } else {
                $status = 200;
                $data   = $statusOrData;
                $meta   = $dataOrMeta;
            }
        } else {
            $status = $statusOrData;
            $data   = $dataOrMeta;
        }

        $envelope = [
            'success' => $status < 400,
            'data'    => $data,
            'meta'    => array_merge(['request_id' => RequestId::current()], $meta),
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
