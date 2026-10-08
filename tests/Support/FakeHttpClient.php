<?php

declare(strict_types=1);

namespace Aldaba\Tests\Support;

use Aldaba\HttpClient\ClientResponse;
use Aldaba\HttpClient\HttpClient;
use Aldaba\HttpClient\TransportException;

/**
 * Replays canned responses instead of going to the network, and records what was requested.
 */
final class FakeHttpClient implements HttpClient
{
    /** @var list<array{url: string, headers: array<string, string>}> */
    public array $requests = [];

    /**
     * @param list<ClientResponse|TransportException> $responses returned (or thrown) in order
     */
    public function __construct(private array $responses = [])
    {
    }

    public function get(string $url, array $headers = []): ClientResponse
    {
        $this->requests[] = ['url' => $url, 'headers' => $headers];

        $next = array_shift($this->responses) ?? throw new \LogicException("Unexpected request to $url");
        if ($next instanceof TransportException) {
            throw $next;
        }

        return $next;
    }

    public static function json(mixed $data, int $status = 200): ClientResponse
    {
        return new ClientResponse($status, json_encode($data, JSON_THROW_ON_ERROR));
    }
}
