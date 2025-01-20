<?php

declare(strict_types=1);

namespace SavinMikhail\AddNamedArgumentsRector\Config;

use PhpParser\Node;
use PHPStan\Reflection\ExtendedParameterReflection;

interface ConfigStrategy
{
    /**
     * @param ExtendedParameterReflection[] $parameters
     */
    public static function shouldApply(Node $node, array $parameters): bool;
}
