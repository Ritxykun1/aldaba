<?php

declare(strict_types=1);

namespace Aldaba\Controller;

use Aldaba\Http\HttpException;
use Aldaba\Http\Request;
use Aldaba\Http\Response;
use Aldaba\Recipe\RecipeService;

final class RecipeController
{
    private const MAX_NAME_LENGTH = 100;

    public function __construct(private readonly RecipeService $recipes)
    {
    }

    /**
     * GET /api/recipes?name={name}
     */
    public function search(Request $request): Response
    {
        $name = self::validName($request->query['name'] ?? null);

        return Response::json(['data' => $this->recipes->findByName($name)]);
    }

    private static function validName(mixed $name): string
    {
        if (!is_string($name) || !mb_check_encoding($name, 'UTF-8')) {
            throw new HttpException(422, "The 'name' query parameter is required and must be a string.");
        }

        $name = trim($name);

        return match (true) {
            $name === '' => throw new HttpException(422, "The 'name' query parameter must not be empty."),
            mb_strlen($name) > self::MAX_NAME_LENGTH => throw new HttpException(
                422,
                "The 'name' query parameter must be at most " . self::MAX_NAME_LENGTH . ' characters.',
            ),
            !preg_match('/[\p{L}\p{N}]/u', $name) => throw new HttpException(
                422,
                "The 'name' query parameter must contain letters or numbers.",
            ),
            default => $name,
        };
    }
}
