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
        // Check if the class has @no-named-arguments annotation
        if ($classReflection !== null) {
            $docComment = $classReflection->getNativeReflection()->getDocComment();
            if ($docComment !== false && str_contains($docComment, '@no-named-arguments')) {
                return false;
            }
        }

        // Check if the function/method being called has @no-named-arguments annotation
        $functionReflection = self::getFunctionReflection($node, $classReflection);
        if ($functionReflection !== null) {
            $docComment = $functionReflection->getDocComment();
            if ($docComment !== false && str_contains($docComment, '@no-named-arguments')) {
                return false;
            }
        }

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

    private static function getFunctionReflection(
        FuncCall|StaticCall|MethodCall|New_ $node,
        ?ClassReflection $classReflection,
    ): ?ReflectionFunctionAbstract {
        if ($node instanceof FuncCall) {
            if ($node->name instanceof Node\Name) {
                try {
                    return new ReflectionFunction((string) $node->name);
                } catch (ReflectionException) {
                    return null;
                }
            }
        }
        if ($node instanceof MethodCall && $classReflection !== null) {
            if ($node->name instanceof Node\Identifier) {
                try {
                    return $classReflection->getNativeReflection()->getMethod($node->name->name);
                } catch (ReflectionException) {
                    return null;
                }
            }
        }
        if ($node instanceof StaticCall && $classReflection !== null) {
            if ($node->name instanceof Node\Identifier) {
                try {
                    return $classReflection->getNativeReflection()->getMethod($node->name->name);
                } catch (ReflectionException) {
                    return null;
                }
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
