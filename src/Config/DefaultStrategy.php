<?php

declare(strict_types=1);

namespace SavinMikhail\AddNamedArgumentsRector\Config;

use PhpParser\Node;
use PhpParser\Node\Identifier;

final readonly class DefaultStrategy implements ConfigStrategy
{
    public static function shouldApply(Node $node, array $parameters): bool
    {
        foreach ($node->args as $index => $arg) {
            if (! isset($parameters[$index])) {
                return false;
            }

            // Skip variadic parameters (...$param)
            if ($parameters[$index]->isVariadic()) {
                return false;
            }

            // Skip unpacking arguments (...$var)
            if ($arg instanceof Node\Arg && $arg->unpack) {
                return false;
            }

            if ($arg instanceof Node\VariadicPlaceholder) {
                return false;
            }

            if ($arg->name !== null) {
                return false;
            }
        }

        return true;
    }
}
