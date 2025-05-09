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
use PHPStan\Reflection\ExtendedParameterReflection;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\NodeNameResolver\NodeNameResolver;
use Rector\NodeTypeResolver\NodeTypeResolver;
use Rector\Rector\AbstractRector;
use Rector\ValueObject\PhpVersion;
use Rector\VersionBonding\Contract\MinPhpVersionInterface;
use SavinMikhail\AddNamedArgumentsRector\Config\ConfigStrategy;
use SavinMikhail\AddNamedArgumentsRector\Config\DefaultStrategy;
use SavinMikhail\AddNamedArgumentsRector\Reflection\ReflectionService;
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

    private readonly ReflectionService $reflectionService;

    public function __construct(
        ReflectionProvider $reflectionProvider,
        NodeNameResolver $nodeNameResolver,
        NodeTypeResolver $nodeTypeResolver,
        ?ReflectionService $reflectionService = null,
    ) {
        if ($reflectionService === null) {
            $reflectionService = new ReflectionService(
                reflectionProvider: $reflectionProvider,
                nodeNameResolver: $nodeNameResolver,
                nodeTypeResolver: $nodeTypeResolver,
            );
        }
        $this->reflectionService = $reflectionService;
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

        $this->addNamesToArgs(node: $node, parameters: $parameters);

        return $node;
    }

    /**
     * @param ExtendedParameterReflection[] $parameters
     */
    private function addNamesToArgs(
        FuncCall|StaticCall|MethodCall|New_ $node,
        array $parameters,
    ): void {
        $argNames = [];
        foreach ($node->args as $index => $arg) {
            $argNames[$index] = new Identifier(name: $parameters[$index]->getName());
        }

        foreach ($node->args as $index => $arg) {
            $arg->name = $argNames[$index];
        }
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
