<?php

declare(strict_types=1);

namespace Aldaba\Spoonacular;

/**
 * Daily quota used up (HTTP 402) or too many requests per second (HTTP 429).
 */
final class SpoonacularLimitExceeded extends SpoonacularException
{
}
