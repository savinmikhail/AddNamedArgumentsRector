<?php

declare(strict_types=1);

namespace SavinMikhail\AddNamedArgumentsRector\Service;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Reflection\ClassMemberAccessAnswerer;
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
        protected NodeNameResolver $nodeNameResolver,
        protected NodeTypeResolver $nodeTypeResolver,
    ) {}

    /**
     * @return ExtendedParameterReflection[]
     */
    public function getParameters(Node $node): array
    {
        $parameters = [];

        if ($node instanceof New_) {
            $parameters = $this->getConstructorArgs($node);
        } elseif ($node instanceof MethodCall) {
            $parameters = $this->getMethodArgs($node);
        } elseif ($node instanceof StaticCall) {
            $parameters = $this->getStaticMethodArgs($node);
        } elseif ($node instanceof FuncCall) {
            $parameters = $this->getFuncArgs($node);
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

        $className = $this->nodeNameResolver->getName($node->class);
        if (! $this->reflectionProvider->hasClass($className)) {
            return [];
        }

        $classReflection = $this->reflectionProvider->getClass($className);

        if ($node->name instanceof Identifier) {
            $methodName = $node->name->name;
        } elseif ($node->name instanceof Name) {
            $methodName = (string) $node->name;
        } else {
            return [];
        }

        if (! $classReflection->hasMethod($methodName)) {
            return [];
        }

        /** @var ClassMemberAccessAnswerer $scope */
        $scope = $node->getAttribute(AttributeKey::SCOPE);
        $reflection = $classReflection->getMethod($methodName, $scope);

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
        $callerType = $this->nodeTypeResolver->getType($node->var);

        $name = $node->name;
        if ($name instanceof Node\Expr) {
            return [];
        }
        $methodName = $name->name;
        if (! $callerType->hasMethod($methodName)->yes()) {
            return [];
        }

        /** @var ClassMemberAccessAnswerer $scope */
        $scope = $node->getAttribute(AttributeKey::SCOPE);
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
        $calledName = $this->resolveCalledName($node);
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
        $calledName = $this->resolveCalledName($node);
        if ($calledName === null) {
            return [];
        }

        $scope = $node->getAttribute(AttributeKey::SCOPE);

        if (! $this->reflectionProvider->hasFunction(new Name($calledName), $scope)) {
            return [];
        }
        $reflection = $this->reflectionProvider->getFunction(new Name($calledName), $scope);

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
}
