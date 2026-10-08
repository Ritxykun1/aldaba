<?php

declare(strict_types=1);

namespace Aldaba\Controller;

use Aldaba\Http\Response;

final class DocsController
{
    public function __construct(private readonly string $docsDirectory)
    {
    }

    /**
     * GET /docs
     */
    public function ui(): Response
    {
        return $this->file('index.html', 'text/html; charset=utf-8');
    }

    /**
     * GET /openapi.yaml
     */
    public function spec(): Response
    {
        return $this->file('openapi.yaml', 'application/yaml');
    }

    private function file(string $name, string $contentType): Response
    {
        return new Response(
            (string) file_get_contents($this->docsDirectory . '/' . $name),
            200,
            ['Content-Type' => $contentType],
        );
    }
}
