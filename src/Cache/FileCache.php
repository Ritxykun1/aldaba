<?php

declare(strict_types=1);

namespace Aldaba\Cache;

/**
 * Minimal file-based cache with a fixed TTL. Each key is stored as a JSON file.
 */
final class FileCache
{
    public function __construct(
        private readonly string $directory,
        private readonly int $ttlSeconds,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array
    {
        $file = $this->path($key);
        if (!is_file($file)) {
            return null;
        }

        $entry = json_decode((string) file_get_contents($file), true);
        if (!is_array($entry) || ($entry['expires_at'] ?? 0) <= time()) {
            @unlink($file);

            return null;
        }

        return $entry['value'];
    }

    /**
     * @param array<string, mixed> $value
     */
    public function set(string $key, array $value): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }

        $entry = ['expires_at' => time() + $this->ttlSeconds, 'value' => $value];

        file_put_contents($this->path($key), json_encode($entry, JSON_THROW_ON_ERROR), LOCK_EX);
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . sha1($key) . '.json';
    }
}
