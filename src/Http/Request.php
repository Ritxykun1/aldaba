<?php

declare(strict_types=1);

namespace Aldaba\Http;

final class Request
{
    /**
     * @param array<string, mixed> $query
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), $path, $_GET);
    }
}
