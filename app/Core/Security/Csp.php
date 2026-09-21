<?php
declare(strict_types=1);
namespace App\Core\Security;

/**
 * Content Security Policy builder.
 * Constructs CSP header string from config directives.
 * No third-party dependencies (R10).
 */
final class Csp
{
    /** @var array<string, string[]> */
    private array $directives;

    public function __construct(array $directives = [])
    {
        $this->directives = $directives;
    }

    /**
     * Create from config array.
     */
    public static function fromConfig(): self
    {
        $config = require dirname(__DIR__, 2) . '/Config/security.php';
        return new self($config['csp'] ?? []);
    }

    /**
     * Override or add a directive at runtime.
     */
    public function set(string $directive, array $values): self
    {
        $clone = clone $this;
        $clone->directives[$directive] = $values;
        return $clone;
    }

    /**
     * Append values to an existing directive.
     */
    public function append(string $directive, string ...$values): self
    {
        $clone = clone $this;
        $existing = $clone->directives[$directive] ?? [];
        $clone->directives[$directive] = array_merge($existing, $values);
        return $clone;
    }

    /**
     * Remove a directive entirely.
     */
    public function remove(string $directive): self
    {
        $clone = clone $this;
        unset($clone->directives[$directive]);
        return $clone;
    }

    /**
     * Add a nonce source to script-src and style-src.
     */
    public function withNonce(string $nonce): self
    {
        $nonceValue = "'nonce-{$nonce}'";
        $clone = clone $this;
        $clone->directives['script-src'] = array_merge(
            $clone->directives['script-src'] ?? ["'self'"],
            [$nonceValue]
        );
        $clone->directives['style-src'] = array_merge(
            $clone->directives['style-src'] ?? ["'self'"],
            [$nonceValue]
        );
        return $clone;
    }

    /**
     * Build the CSP header value string.
     */
    public function build(): string
    {
        $parts = [];
        foreach ($this->directives as $directive => $values) {
            if (empty($values)) {
                continue;
            }
            $parts[] = $directive . ' ' . implode(' ', array_unique($values));
        }
        return implode('; ', $parts);
    }

    /**
     * Send the CSP header directly.
     */
    public function send(): void
    {
        $header = $this->build();
        if ($header !== '') {
            header('Content-Security-Policy: ' . $header);
        }
    }

    public function __toString(): string
    {
        return $this->build();
    }
}
