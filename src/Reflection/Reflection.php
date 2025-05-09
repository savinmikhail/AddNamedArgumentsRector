<?php

declare(strict_types=1);

namespace SavinMikhail\AddNamedArgumentsRector\Reflection;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedParameterReflection;
use PHPStan\Reflection\ReflectionProvider;
use Rector\NodeNameResolver\NodeNameResolver;
use Rector\NodeTypeResolver\NodeTypeResolver;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;

final readonly class Reflection
{
    private ParameterReflection $parameterReflection;

    public function __construct(
        private ReflectionProvider $reflectionProvider,
        protected NodeNameResolver $nodeNameResolver,
        protected NodeTypeResolver $nodeTypeResolver,
        ?ParameterReflection $parameterReflection = null,
    ) {
        if ($parameterReflection === null) {
            $parameterReflection = new ParameterReflection(
                reflectionProvider: $reflectionProvider,
                nodeNameResolver: $nodeNameResolver,
                nodeTypeResolver: $nodeTypeResolver,
            );
        }
        $this->parameterReflection = $parameterReflection;
    }

    /**
     * @return ExtendedParameterReflection[]
     */
    public function getParameters(Node $node): array
    {
        return $this->parameterReflection->getParameters(node: $node);
    }

    /**
     * Resolve the reflection for a function, method/static call, or constructor.
     */
    public static function getFunctionReflection(
        FuncCall|StaticCall|MethodCall|New_ $node,
        ?ClassReflection $classReflection,
    ): null|ReflectionFunctionAbstract|false {
        if (self::isFuncCall($node)) {
            return self::resolveFunction($node);
        }

        if (self::isMethodOrStaticCall($node, $classReflection)) {
            return self::resolveMethod($node, $classReflection);
        }

        if (self::isConstructorCall($node, $classReflection)) {
            return self::resolveConstructor($classReflection);
        }

        return null;
    }

    private static function isFuncCall(Node $node): bool
    {
        return $node instanceof FuncCall && $node->name instanceof Name;
    }

    private static function isMethodOrStaticCall(Node $node, ?ClassReflection $classReflection): bool
    {
        return ($node instanceof MethodCall || $node instanceof StaticCall)
            && $classReflection !== null
            && $node->name instanceof Node\Identifier;
    }

    private static function isConstructorCall(Node $node, ?ClassReflection $classReflection): bool
    {
        return $node instanceof New_ && $classReflection !== null;
    }

    private static function resolveFunction(FuncCall $node): ?ReflectionFunctionAbstract
    {
        try {
            return new ReflectionFunction((string) $node->name);
        } catch (ReflectionException) {
            return null;
        }
    }

    private static function resolveMethod(
        MethodCall|StaticCall $node,
        ClassReflection $classReflection,
    ): null|ReflectionFunctionAbstract|false {
        $methodName = $node->name->name;

        try {
            $native = $classReflection->getNativeReflection();
            if (! $native->hasMethod($methodName)) {
                return null;
            }

            return $native->getMethod($methodName);
        } catch (ReflectionException) {
            return null;
        }
    }

    private static function resolveConstructor(ClassReflection $classReflection): ?ReflectionFunctionAbstract
    {
        try {
            return $classReflection->getNativeReflection()->getConstructor();
        } catch (ReflectionException) {
            return null;
        }
    }

    public function getClassReflection(FuncCall|StaticCall|MethodCall|New_ $node): ?ClassReflection
    {
        if ($node instanceof MethodCall) {
            $callerType = $this->nodeTypeResolver->getType(node: $node->var);
            $classReflections = $callerType->getObjectClassReflections();

            return $classReflections[0] ?? null;
        }

        if ($node instanceof StaticCall && $node->class instanceof Name) {
            return $this->fetchClass($node->class);
        }

        if ($node instanceof New_ && $node->class instanceof Name) {
            return $this->fetchClass($node->class);
        }

        return null;
    }

    private function fetchClass(Name $name): ?ClassReflection
    {
        $className = $this->nodeNameResolver->getName(node: $name);

        return $this->reflectionProvider->hasClass($className)
            ? $this->reflectionProvider->getClass($className)
            : null;
    }
}
