<?php

declare(strict_types=1);

namespace SavinMikhail\AddNamedArgumentsRector\Reflection;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ExtendedParameterReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\ShouldNotHappenException;
use Rector\NodeNameResolver\NodeNameResolver;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\NodeTypeResolver\NodeTypeResolver;

final readonly class ParameterReflection
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private NodeNameResolver $nodeNameResolver,
        private NodeTypeResolver $nodeTypeResolver,
    ) {}

    /**
     * @return ExtendedParameterReflection[]
     */
    public function getParameters(Node $node): array
    {
        $parameters = [];

        if ($node instanceof New_) {
            $parameters = $this->getConstructorArgs(node: $node);
        } elseif ($node instanceof MethodCall) {
            $parameters = $this->getMethodArgs(node: $node);
        } elseif ($node instanceof StaticCall) {
            $parameters = $this->getStaticMethodArgs(node: $node);
        } elseif ($node instanceof FuncCall) {
            $parameters = $this->getFuncArgs(node: $node);
        }

        return $parameters;
    }

    /**
     * @return ExtendedParameterReflection[]
     */
    private function getStaticMethodArgs(StaticCall $node): array
    {
        if (! $node->class instanceof Name) {
            return [];
        }

        $scope = $node->getAttribute(key: AttributeKey::SCOPE);
        if (! $scope instanceof Scope) {
            return [];
        }

        $classReflection = $this->resolveClassReflectionFromName(name: $node->class, scope: $scope);
        if ($classReflection === null) {
            return [];
        }

        if ($node->name instanceof Identifier) {
            $methodName = $node->name->name;
        } elseif ($node->name instanceof Name) {
            $methodName = (string) $node->name;
        } else {
            return [];
        }

        if (! $classReflection->hasMethod(methodName: $methodName)) {
            return [];
        }

        $reflection = $classReflection->getMethod(methodName: $methodName, scope: $scope);

        try {
            return $reflection
                ->getOnlyVariant()
                ->getParameters();
        } catch (ShouldNotHappenException) {
            // for example in interface argument being called "$className" and in child class it being called "$entityName",
            // we have no idea what will be resolved in a runtime, so just skip
            return [];
        }
    }

    /**
     * @return ExtendedParameterReflection[]
     */
    private function getMethodArgs(MethodCall $node): array
    {
        $callerType = $this->nodeTypeResolver->getType(node: $node->var);

        $name = $node->name;
        if ($name instanceof Node\Expr) {
            return [];
        }
        $methodName = $name->name;
        if (! $callerType->hasMethod($methodName)->yes()) {
            return [];
        }

        $scope = $node->getAttribute(key: AttributeKey::SCOPE);
        $reflection = $callerType->getMethod($methodName, $scope);

        try {
            return $reflection
                ->getOnlyVariant()
                ->getParameters();
        } catch (ShouldNotHappenException) {
            // for example in interface argument being called "$className" and in child class it being called "$entityName",
            // we have no idea what will be resolved in a runtime, so just skip
            return [];
        }
    }

    /**
     * @return ExtendedParameterReflection[]
     */
    private function getConstructorArgs(New_ $node): array
    {
        $calledName = $this->resolveCalledName(node: $node);
        if ($calledName === null) {
            return [];
        }

        if (! $this->reflectionProvider->hasClass($calledName)) {
            return [];
        }
        $classReflection = $this->reflectionProvider->getClass($calledName);

        if (! $classReflection->hasConstructor()) {
            return [];
        }

        $reflection = $classReflection->getConstructor();

        try {
            return $reflection
                ->getOnlyVariant()
                ->getParameters();
        } catch (ShouldNotHappenException) {
            // for example in interface argument being called "$className" and in child class it being called "$entityName",
            // we have no idea what will be resolved in a runtime, so just skip
            return [];
        }
    }

    /**
     * @return ExtendedParameterReflection[]
     */
    private function getFuncArgs(FuncCall $node): array
    {
        $calledName = $this->resolveCalledName(node: $node);
        if ($calledName === null) {
            return [];
        }

        $scope = $node->getAttribute(key: AttributeKey::SCOPE);

        if (! $this->reflectionProvider->hasFunction(new Name(name: $calledName), $scope)) {
            return [];
        }
        $reflection = $this->reflectionProvider->getFunction(new Name(name: $calledName), $scope);

        try {
            return $reflection
                ->getOnlyVariant()
                ->getParameters();
        } catch (ShouldNotHappenException) {
            // for example in interface argument being called "$className" and in child class it being called "$entityName",
            // we have no idea what will be resolved in a runtime, so just skip
            return [];
        }
    }

    private function resolveCalledName(Node $node): ?string
    {
        if ($node instanceof FuncCall && $node->name instanceof Name) {
            return (string) $node->name;
        }

        if ($node instanceof MethodCall && $node->name instanceof Identifier) {
            return (string) $node->name;
        }

        if ($node instanceof StaticCall && $node->name instanceof Identifier) {
            return (string) $node->name;
        }

        if ($node instanceof New_ && $node->class instanceof Name) {
            return (string) $node->class;
        }

        return null;
    }

    private function resolveClassReflectionFromName(Name $name, Scope $scope): ?\PHPStan\Reflection\ClassReflection
    {
        $className = $this->nodeNameResolver->getName(node: $name);

        if ($className === null) {
            return null;
        }

        $lowerClassName = strtolower($className);

        if (in_array($lowerClassName, ['self', 'static'], true)) {
            return $scope->getClassReflection();
        }

        if ($lowerClassName === 'parent') {
            return $scope->getClassReflection()?->getParentClass();
        }

        if (! $this->reflectionProvider->hasClass($className)) {
            return null;
        }

        return $this->reflectionProvider->getClass($className);
    }
}
