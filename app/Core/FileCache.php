<?php
declare(strict_types=1);
namespace App\Core;

final class FileCache
{
    private string $cacheDir;

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = rtrim($cacheDir, '/\\');
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Get a cached value. Returns null if not found or expired.
     */
    public function get(string $key): mixed
    {
        $file = $this->path($key);

        if (!file_exists($file)) {
            return null;
        }

        $fp = fopen($file, 'r');
        if (!$fp) return null;

        if (!flock($fp, LOCK_SH)) {
            fclose($fp);
            return null;
        }

        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($content === false || $content === '') return null;

        $data = json_decode($content, true);
        if (!is_array($data)) return null;

        // Check expiry
        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            @unlink($file); // Stale file, clean up
            return null;
        }

        return $data['value'];
    }

    /**
     * Set a cached value with TTL in seconds (0 = never expires).
     */
    public function set(string $key, mixed $value, int $ttl = 300): void
    {
        $payload = json_encode([
            'value'      => $value,
            'expires_at' => $ttl > 0 ? time() + $ttl : PHP_INT_MAX,
            'created_at' => time(),
        ], JSON_UNESCAPED_UNICODE);

        $file = $this->path($key);
        $tmp  = $file . '.tmp.' . getmypid();

        // Write to temp file
        file_put_contents($tmp, $payload, LOCK_EX);

        // Atomic rename
        rename($tmp, $file);
    }

    /**
     * Delete a cached value.
     */
    public function delete(string $key): void
    {
        $file = $this->path($key);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Get or compute. If not cached, calls $compute(), caches result, returns it.
     */
    public function remember(string $key, int $ttl, callable $compute): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) return $cached;

        $value = $compute();
        $this->set($key, $value, $ttl);
        return $value;
    }

    /**
     * Clear all expired cache files. Called by daily scheduler.
     */
    public function purgeExpired(): int
    {
        $count = 0;
        foreach (glob($this->cacheDir . '/*.cache') as $file) {
            $content = @file_get_contents($file);
            if ($content === false) continue;
            $data = json_decode($content, true);
            if (!is_array($data) || ($data['expires_at'] ?? PHP_INT_MAX) < time()) {
                @unlink($file);
                $count++;
            }
        }
        return $count;
    }

    private function path(string $key): string
    {
        // Use sha256 of key as filename (safe for all key strings)
        return $this->cacheDir . '/' . hash('sha256', $key) . '.cache';
    }
}
