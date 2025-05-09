<?php

declare(strict_types=1);

namespace SavinMikhail\AddNamedArgumentsRector\Service;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use Rector\NodeNameResolver\NodeNameResolver;
use Rector\NodeTypeResolver\NodeTypeResolver;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;

final readonly class ReflectionService
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        protected NodeNameResolver $nodeNameResolver,
        protected NodeTypeResolver $nodeTypeResolver,
    ) {}

    public static function getFunctionReflection(
        FuncCall|StaticCall|MethodCall|New_ $node,
        ?ClassReflection $classReflection,
    ): null|ReflectionFunctionAbstract|false {
        if ($node instanceof FuncCall) {
            if ($node->name instanceof Name) {
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

    public function getClassReflection(FuncCall|StaticCall|MethodCall|New_ $node): ?ClassReflection
    {
        if ($node instanceof MethodCall) {
            $callerType = $this->nodeTypeResolver->getType($node->var);
            $classReflections = $callerType->getObjectClassReflections();

            return $classReflections[0] ?? null;
        }

        if ($node instanceof StaticCall && $node->class instanceof Name) {
            $className = $this->nodeNameResolver->getName($node->class);

            return $this->reflectionProvider->hasClass($className)
                ? $this->reflectionProvider->getClass($className)
                : null;
        }

        if ($node instanceof New_ && $node->class instanceof Name) {
            $className = $this->nodeNameResolver->getName($node->class);

            return $this->reflectionProvider->hasClass($className)
                ? $this->reflectionProvider->getClass($className)
                : null;
        }

        return null;
    }
}
