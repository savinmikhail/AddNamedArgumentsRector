<?php

declare(strict_types=1);

namespace SavinMikhail\AddNamedArgumentsRector\Config;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionClass;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionEnum;
use PHPStan\Reflection\ClassReflection;
use ReflectionFunctionAbstract;
use SavinMikhail\AddNamedArgumentsRector\Reflection\ReflectionService;

final readonly class DefaultStrategy implements ConfigStrategy
{
    public static function shouldApply(
        FuncCall|StaticCall|MethodCall|New_ $node,
        array $parameters,
        ?ClassReflection $classReflection = null,
    ): bool {
        if (!self::areArgumentsSuitable($node->args, $parameters)) {
            return false;
        }

        if ($classReflection !== null && !self::classAllowsNamedArguments($classReflection)) {
            return false;
        }

        if (!self::functionAllowsNamedArguments($node, $classReflection)) {
            return false;
        }

        return true;
    }

    private static function classAllowsNamedArguments(ClassReflection $classReflection): bool
    {
        $reflectionClass = $classReflection->getNativeReflection();

        while ($reflectionClass) {
            if (self::hasNoNamedArgumentsTag($reflectionClass)) {
                return false;
            }
            // Check if the class has @no-named-arguments annotation, even in the parent classes
            $reflectionClass = $reflectionClass->getParentClass();
        }

        if ($classReflection->isInterface()) {
            // 🚨 Stop rule, cuz in runtime might be resolved any implementation of the interface, and the names of arguments might differ
            return false;
        }

        return true;
    }

    private static function areArgumentsSuitable(array $args, array $parameters): bool
    {
        foreach ($args as $index => $arg) {
            if (!isset($parameters[$index])) {
                return false;
            }

            // Skip variadic parameters (...$param)
            if ($parameters[$index]->isVariadic()) {
                return false;
            }

            if ($arg instanceof Node\VariadicPlaceholder) {
                return false;
            }

            // Skip unpacking arguments (...$var)
            if ($arg instanceof Node\Arg && $arg->unpack) {
                return false;
            }

            // skip already named arguments
            if ($arg->name !== null) {
                return false;
            }
        }

        return true;
    }

    private static function hasNoNamedArgumentsTag(ReflectionFunctionAbstract|ReflectionClass|ReflectionEnum $reflection): bool
    {
        $docComment = $reflection->getDocComment();

        return $docComment !== false && str_contains(haystack: $docComment, needle: '@no-named-arguments');
    }

    private static function functionAllowsNamedArguments(
        FuncCall|StaticCall|MethodCall|New_ $node,
        ?ClassReflection $classReflection = null,
    ): bool {
        $functionReflection = ReflectionService::getFunctionReflection(node: $node, classReflection: $classReflection);
        if ($functionReflection === null) {
            return false; // 🚨 Stop rule if method doesn't exist (likely a @method annotation)
        }

        if (self::hasNoNamedArgumentsTag($functionReflection)) {
            return false;
        }

        return true;
    }
}
