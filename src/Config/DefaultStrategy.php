<?php

declare(strict_types=1);

namespace SavinMikhail\AddNamedArgumentsRector\Config;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Reflection\ClassReflection;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;

final readonly class DefaultStrategy implements ConfigStrategy
{
    public static function shouldApply(
        FuncCall|StaticCall|MethodCall|New_ $node,
        array $parameters,
        ?ClassReflection $classReflection = null,
    ): bool {
        foreach ($node->args as $index => $arg) {
            if (!isset($parameters[$index])) {
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

        // Check if the class has @no-named-arguments annotation
        if ($classReflection !== null) {
            $docComment = $classReflection->getNativeReflection()->getDocComment();
            if ($docComment !== false && str_contains($docComment, '@no-named-arguments')) {
                return false;
            }
        }

        // Check if the function/method being called has @no-named-arguments annotation
        $functionReflection = self::getFunctionReflection($node, $classReflection);
        if ($functionReflection === null) {
            return false; // 🚨 Stop rule if method doesn't exist (likely a @method annotation)
        }

        $docComment = $functionReflection->getDocComment();
        if ($docComment !== false && str_contains($docComment, '@no-named-arguments')) {
            return false;
        }

        if ($classReflection !== null && $classReflection->isInterface()) {
            return false; // 🚨 Stop rule, cuz in runtime might be resolved any implementation of the interface, and the names of arguments might differ
        }

        return true;
    }

    private static function getFunctionReflection(
        FuncCall|StaticCall|MethodCall|New_ $node,
        ?ClassReflection $classReflection,
    ): ReflectionFunctionAbstract|false|null {
        if ($node instanceof FuncCall) {
            if ($node->name instanceof Node\Name) {
                try {
                    return new ReflectionFunction((string) $node->name);
                } catch (ReflectionException) {
                    return null;
                }
            }
        }

        if (
            ($node instanceof MethodCall || $node instanceof StaticCall)
            && $classReflection !== null
            && $node->name instanceof Node\Identifier
        ) {
            try {
                $methodName = $node->name->name;
                $reflection = $classReflection->getNativeReflection();

                if (!$reflection->hasMethod($methodName)) {
                    return null; // 🚨 Indicate method does not exist
                }

                return $reflection->getMethod($methodName);
            } catch (ReflectionException) {
                return null;
            }
        }

        if ($node instanceof New_ && $classReflection !== null) {
            try {
                return $classReflection->getNativeReflection()->getConstructor();
            } catch (ReflectionException) {
                return null;
            }
        }

        return null;
    }
}
