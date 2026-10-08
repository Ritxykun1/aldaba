<?php

declare(strict_types=1);

namespace Aldaba\HttpClient;

/**
 * Outgoing HTTP requests. Lets tests replace the real network with a fake.
 */
interface HttpClient
{
    /**
     * @param array<string, string> $headers
     * @throws TransportException when no HTTP response is received (DNS, connection, timeout...)
     */
    public function get(string $url, array $headers = []): ClientResponse;
}
