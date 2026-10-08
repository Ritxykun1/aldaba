<?php

declare(strict_types=1);

namespace Aldaba\Tests\Unit;

use Aldaba\Cache\FileCache;
use PHPUnit\Framework\TestCase;

final class FileCacheTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/aldaba-cache-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    public function test_it_returns_what_was_stored(): void
    {
        $cache = new FileCache($this->dir, 60);
        $cache->set('k', ['a' => 1]);

        self::assertSame(['a' => 1], $cache->get('k'));
    }

    public function test_missing_key_is_null(): void
    {
        self::assertNull((new FileCache($this->dir, 60))->get('nope'));
    }

    public function test_expired_entries_are_null(): void
    {
        $cache = new FileCache($this->dir, 0);
        $cache->set('k', ['a' => 1]);

        self::assertNull($cache->get('k'));
    }
}
