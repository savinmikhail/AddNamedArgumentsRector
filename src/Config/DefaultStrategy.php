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
use PHPStan\Reflection\ExtendedParameterReflection;
use ReflectionFunctionAbstract;
use SavinMikhail\AddNamedArgumentsRector\Reflection\Reflection;

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

    /**
     * @param Node[] $args
     * @param ExtendedParameterReflection[] $parameters
     */
    private static function areArgumentsSuitable(array $args, array $parameters): bool
    {
        foreach ($args as $index => $arg) {
            $parameter = self::resolveParameterForArgumentIndex(parameters: $parameters, index: $index);
            if ($parameter === null) {
                return false;
            }

            if ($arg instanceof Node\VariadicPlaceholder) {
                return false;
            }

            // Skip unpacking arguments (...$var)
            if ($arg->unpack) {
                return false;
            }

            // Allow already named arguments as long as they reference the parameter in the same position
            if ($arg->name !== null) {
                if ($parameter->isVariadic()) {
                    continue;
                }

                $argName = $arg->name->toString();

                if ($argName !== $parameter->getName()) {
                    return false;
                }

                continue;
            }
        }

        return true;
    }

    /**
     * @param ExtendedParameterReflection[] $parameters
     */
    private static function resolveParameterForArgumentIndex(array $parameters, int $index): ?ExtendedParameterReflection
    {
        if (isset($parameters[$index])) {
            return $parameters[$index];
        }

        if ($parameters === []) {
            return null;
        }

        $lastParameter = $parameters[array_key_last($parameters)];

        if (! $lastParameter->isVariadic()) {
            return null;
        }

        return $lastParameter;
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
        $functionReflection = Reflection::getFunctionReflection(node: $node, classReflection: $classReflection);
        if ($functionReflection === null) {
            return false; // 🚨 Stop rule if method doesn't exist (likely a @method annotation)
        }

        if (self::hasNoNamedArgumentsTag($functionReflection)) {
            return false;
        }

        return true;
    }
}
