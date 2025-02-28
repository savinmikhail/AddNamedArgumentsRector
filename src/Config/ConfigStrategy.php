<?php

declare(strict_types=1);

namespace SavinMikhail\AddNamedArgumentsRector\Config;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedParameterReflection;

interface ConfigStrategy
{
    /**
     * @param ExtendedParameterReflection[] $parameters
     */
    public static function shouldApply(
        FuncCall|StaticCall|MethodCall|New_ $node,
        array $parameters,
        ?ClassReflection $classReflection = null,
    ): bool;
}
