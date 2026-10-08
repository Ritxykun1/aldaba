<?php

declare(strict_types=1);

namespace Aldaba\Recipe;

final class RecipeNotFound extends \RuntimeException
{
    public function __construct(string $name)
    {
        parent::__construct("No recipe found for '$name'.");
    }
}
