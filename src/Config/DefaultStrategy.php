<?php

declare(strict_types=1);

namespace SavinMikhail\AddNamedArgumentsRector\Config;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Reflection\ClassReflection;
use SavinMikhail\AddNamedArgumentsRector\Reflection\ReflectionService;

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

        // Check if the class has @no-named-arguments annotation, even in parent classes
        if ($classReflection !== null) {
            $reflectionClass = $classReflection->getNativeReflection();

            while ($reflectionClass) {
                $docComment = $reflectionClass->getDocComment();
                if ($docComment !== false && str_contains($docComment, '@no-named-arguments')) {
                    return false;
                }
                $reflectionClass = $reflectionClass->getParentClass();
            }
        }

        // Check if the function/method being called has @no-named-arguments annotation
        $functionReflection = ReflectionService::getFunctionReflection($node, $classReflection);
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
}
