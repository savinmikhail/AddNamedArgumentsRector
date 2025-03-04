<?php

declare(strict_types=1);

namespace SavinMikhail\AddNamedArgumentsRector;

use InvalidArgumentException;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Reflection\ClassMemberAccessAnswerer;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedParameterReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\ShouldNotHappenException;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Rector\ValueObject\PhpVersion;
use Rector\VersionBonding\Contract\MinPhpVersionInterface;
use SavinMikhail\AddNamedArgumentsRector\Config\ConfigStrategy;
use SavinMikhail\AddNamedArgumentsRector\Config\DefaultStrategy;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Webmozart\Assert\Assert;

use function count;

/**
 * @see AddNamedArgumentsRectorTest
 */
final class AddNamedArgumentsRector extends AbstractRector implements MinPhpVersionInterface, ConfigurableRectorInterface
{
    private string $configStrategy = DefaultStrategy::class;

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Convert all arguments to named arguments', codeSamples: [
            new CodeSample(
                badCode: '$user->setPassword("123456");',
                goodCode: '$user->changePassword(password: "123456");',
            ),
        ]);
    }

    public function getNodeTypes(): array
    {
        return [FuncCall::class, StaticCall::class, MethodCall::class, New_::class];
    }

    public function refactor(Node $node): ?Node
    {
        /** @var FuncCall|StaticCall|MethodCall|New_ $node */
        $parameters = $this->getParameters($node);
        $classReflection = $this->getClassReflection($node);

        if (!$this->configStrategy::shouldApply($node, $parameters, $classReflection)) {
            return null;
        }

        $hasChanged = $this->addNamesToArgs($node, $parameters);

        return $hasChanged ? $node : null;
    }

    private function getClassReflection(FuncCall|StaticCall|MethodCall|New_ $node): ?ClassReflection
    {
        if ($node instanceof MethodCall) {
            $callerType = $this->nodeTypeResolver->getType($node->var);
            $classReflections = $callerType->getObjectClassReflections();

            return $classReflections[0] ?? null;
        }

        if ($node instanceof StaticCall && $node->class instanceof Name) {
            $className = $this->getName($node->class);

            return $this->reflectionProvider->hasClass($className)
                ? $this->reflectionProvider->getClass($className)
                : null;
        }

        if ($node instanceof New_ && $node->class instanceof Name) {
            $className = $this->getName($node->class);

            return $this->reflectionProvider->hasClass($className)
                ? $this->reflectionProvider->getClass($className)
                : null;
        }

        return null;
    }

    /**
     * @return ExtendedParameterReflection[]
     */
    private function getParameters(Node $node): array
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

        $className = $this->getName($node->class);
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

    /**
     * @param ExtendedParameterReflection[] $parameters
     */
    private function addNamesToArgs(
        FuncCall|StaticCall|MethodCall|New_ $node,
        array $parameters,
    ): bool {
        $argNames = [];
        foreach ($node->args as $index => $arg) {
            $argNames[$index] = new Identifier($parameters[$index]->getName());
        }

        foreach ($node->args as $index => $arg) {
            $arg->name = $argNames[$index];
        }

        return true;
    }

    public function provideMinPhpVersion(): int
    {
        return PhpVersion::PHP_80;
    }

    public function configure(array $configuration): void
    {
        Assert::lessThan(count($configuration), 2, 'You can pass only 1 strategy');
        if ($configuration === []) {
            return;
        }
        $strategyClass = $configuration[0];

        if (!class_exists($strategyClass)) {
            throw new InvalidArgumentException("Class {$strategyClass} does not exist.");
        }

        $strategy = new $strategyClass();

        Assert::isInstanceOf($strategy, ConfigStrategy::class, 'Your strategy must implement ConfigStrategy interface');

        $this->configStrategy = $strategyClass;
    }
}
