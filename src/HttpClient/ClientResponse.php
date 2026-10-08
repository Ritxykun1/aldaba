<?php

declare(strict_types=1);

namespace Aldaba\HttpClient;

final class ClientResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
    ) {
    }
}
