<?php

declare(strict_types=1);

namespace SavinMikhail\AddNamedArgumentsRector\Config;

use PhpParser\Node;

use function count;

final readonly class PhpyhStrategy implements ConfigStrategy
{
    public static function shouldApply(Node $node, array $parameters): bool
    {
        // Skip if there's only 1 argument
        if (count($parameters) === 1) {
            return false;
        }

        // Skip if there are 2 arguments with different types
        if (
            count($parameters) === 2
            && !$parameters[0]->getType()->equals($parameters[1]->getType())
        ) {
            return false;
        }

        // Skip if there's a @no-named-argument annotation
        $docComment = $node->getDocComment();
        if ($docComment !== null && str_contains($docComment->getText(), '@no-named-argument')) {
            return false;
        }

        return true;
    }
}
