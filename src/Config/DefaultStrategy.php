<?php

declare(strict_types=1);

namespace SavinMikhail\AddNamedArgumentsRector\Config;

use PhpParser\Node;

final readonly class DefaultStrategy implements ConfigStrategy
{
    public static function shouldApply(Node $node, array $parameters): bool
    {
        return true;
    }
}
