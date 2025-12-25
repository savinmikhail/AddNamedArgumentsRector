<?php

declare(strict_types=1);

namespace SavinMikhail\AddNamedArgumentsRector;

use InvalidArgumentException;
use PhpParser\ConstExprEvaluator;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Reflection\ExtendedParameterReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ConstantScalarType;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\NodeNameResolver\NodeNameResolver;
use Rector\NodeTypeResolver\NodeTypeResolver;
use Rector\Rector\AbstractRector;
use Rector\ValueObject\PhpVersion;
use Rector\VersionBonding\Contract\MinPhpVersionInterface;
use RuntimeException;
use SavinMikhail\AddNamedArgumentsRector\Config\ConfigStrategy;
use SavinMikhail\AddNamedArgumentsRector\Config\DefaultStrategy;
use SavinMikhail\AddNamedArgumentsRector\Reflection\Reflection;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Throwable;
use Webmozart\Assert\Assert;

use function constant;
use function count;
use function defined;

/**
 * @see AddNamedArgumentsRectorTest
 */
final class AddNamedArgumentsRector extends AbstractRector implements MinPhpVersionInterface, ConfigurableRectorInterface
{
    private string $configStrategy = DefaultStrategy::class;

    private readonly Reflection $reflectionService;

    private readonly ConstExprEvaluator $constExprEvaluator;

    public function __construct(
        ReflectionProvider $reflectionProvider,
        NodeNameResolver $nodeNameResolver,
        NodeTypeResolver $nodeTypeResolver,
        ?Reflection $reflectionService = null,
        ?ConstExprEvaluator $constExprEvaluator = null,
    ) {
        if ($reflectionService === null) {
            $reflectionService = new Reflection(
                reflectionProvider: $reflectionProvider,
                nodeNameResolver: $nodeNameResolver,
                nodeTypeResolver: $nodeTypeResolver,
            );
        }
        $this->reflectionService = $reflectionService;
        $this->constExprEvaluator = $constExprEvaluator ?? new ConstExprEvaluator(static function (string $name) {
            if (defined($name)) {
                return constant($name);
            }

            throw new RuntimeException("Undefined constant: {$name}");
        });
    }

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
        $parameters = $this->reflectionService->getParameters(node: $node);
        $classReflection = $this->reflectionService->getClassReflection(node: $node);

        if (!$this->configStrategy::shouldApply($node, $parameters, $classReflection)) {
            return null;
        }

        $hasChanges = $this->addNamesToArgs(node: $node, parameters: $parameters);

        if (! $hasChanges) {
            return null;
        }

        return $node;
    }

    /**
     * @param ExtendedParameterReflection[] $parameters
     */
    private function addNamesToArgs(
        FuncCall|StaticCall|MethodCall|New_ $node,
        array $parameters,
    ): bool {
        $namedArgs = [];
        $hasChanges = false;
        foreach ($node->args as $index => $arg) {
            $parameter = $parameters[$index] ?? null;
            if ($parameter === null) {
                $namedArgs[] = $arg;

                continue;
            }

            if ($arg->name !== null) {
                $namedArgs[] = $arg;

                continue;
            }

            if ($this->shouldSkipArg($arg, $parameter)) {
                $hasChanges = true;

                continue;
            }

            $arg->name = new Identifier(name: $parameter->getName());
            $namedArgs[] = $arg;
            $hasChanges = true;
        }

        if (! $hasChanges) {
            return false;
        }

        $node->args = $namedArgs;

        return true;
    }

    private function shouldSkipArg(Arg $arg, ExtendedParameterReflection $parameter): bool
    {
        if (! $parameter->isOptional()) {
            return false;
        }

        try {
            $defaultValue = $parameter->getDefaultValue();
        } catch (Throwable) {
            return false;
        }

        try {
            $argValue = $this->constExprEvaluator->evaluateDirectly($arg->value);
        } catch (Throwable) {
            return false;
        }

        if ($defaultValue instanceof ConstantScalarType) {
            $defaultValue = $defaultValue->getValue();
        }

        return $argValue === $defaultValue;
    }

    public function provideMinPhpVersion(): int
    {
        return PhpVersion::PHP_80;
    }

    public function configure(array $configuration): void
    {
        Assert::lessThan(value: count(value: $configuration), limit: 2, message: 'You can pass only 1 strategy');
        if ($configuration === []) {
            return;
        }
        $strategyClass = $configuration[0];

        if (!class_exists(class: $strategyClass)) {
            throw new InvalidArgumentException(message: "Class {$strategyClass} does not exist.");
        }

        $strategy = new $strategyClass();

        Assert::isInstanceOf(value: $strategy, class: ConfigStrategy::class, message: 'Your strategy must implement ConfigStrategy interface');

        $this->configStrategy = $strategyClass;
    }
}
