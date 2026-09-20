<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cache — File-based cache adapter.
 * Extendable to APCu or Memcached by swapping the driver.
 */
class Cache
{
    public function __construct(
        private readonly string $driver,
        private readonly string $path,
        private readonly string $prefix,
        private readonly int    $ttl,
    ) {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->filePath($key);
        if (!file_exists($file)) return $default;

        $data = unserialize(file_get_contents($file));
        if ($data['expires_at'] !== 0 && $data['expires_at'] < time()) {
            unlink($file);
            return $default;
        }
        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $data = [
            'expires_at' => $ttl > 0 ? time() + $ttl : ($this->ttl > 0 ? time() + $this->ttl : 0),
            'value'      => $value,
        ];
        return (bool) file_put_contents($this->filePath($key), serialize($data), LOCK_EX);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function forget(string $key): bool
    {
        $file = $this->filePath($key);
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) return $cached;
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    public function flush(): void
    {
        $files = glob($this->path . '/' . $this->prefix . '*');
        foreach ($files as $file) {
            if (is_file($file)) unlink($file);
        }
    }

    private function filePath(string $key): string
    {
        return $this->path . '/' . $this->prefix . md5($key) . '.cache';
    }
}
