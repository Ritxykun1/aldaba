<?php

declare(strict_types=1);

namespace Aldaba\HttpClient;

final class CurlHttpClient implements HttpClient
{
    public function __construct(private readonly int $timeoutSeconds)
    {
    }

    public function get(string $url, array $headers = []): ClientResponse
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->timeoutSeconds,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => array_map(
                static fn (string $name, string $value): string => "$name: $value",
                array_keys($headers),
                $headers,
            ),
        ]);

        $body = curl_exec($curl);
        if ($body === false) {
            throw new TransportException(curl_error($curl), curl_errno($curl) === CURLE_OPERATION_TIMEDOUT);
        }

        return new ClientResponse(curl_getinfo($curl, CURLINFO_RESPONSE_CODE), (string) $body);
    }
}
