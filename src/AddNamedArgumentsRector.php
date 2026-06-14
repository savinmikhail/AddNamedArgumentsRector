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

use function array_key_exists;
use function constant;
use function count;
use function defined;
use function is_bool;
use function is_array;
use function is_string;
use function preg_match;
use function preg_quote;
use function str_contains;

/**
 * @see AddNamedArgumentsRectorTest
 */
final class AddNamedArgumentsRector extends AbstractRector implements MinPhpVersionInterface, ConfigurableRectorInterface
{
    public const STRATEGY = 0;
    public const ALLOW_NAMED_VARIADIC_ARGUMENTS = 1;
    public const SKIP_CALLS = 'skip_calls';

    private string $configStrategy = DefaultStrategy::class;

    private bool $allowNamedVariadicArguments = true;

    /**
     * @var string[]
     */
    private array $skipCalls = [];

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

        if ($this->shouldSkipCall($node, $classReflection)) {
            return null;
        }

        $hasVariadicArguments = $this->hasVariadicArguments($node, $parameters);
        if (! $this->allowNamedVariadicArguments && $hasVariadicArguments) {
            return null;
        }

        $functionReflection = Reflection::getFunctionReflection(node: $node, classReflection: $classReflection);
        if ($hasVariadicArguments && ($functionReflection?->isInternal() ?? false)) {
            return null;
        }

        $hasChanges = $this->addNamesToArgs(
            node: $node,
            parameters: $parameters,
        );

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
        $variadicArgCounters = [];
        $hasChanges = false;
        foreach ($node->args as $index => $arg) {
            $parameter = $this->resolveParameterForArgumentIndex(parameters: $parameters, index: $index);
            if ($parameter === null) {
                $namedArgs[] = $arg;

                continue;
            }

            if ($arg->name !== null) {
                $namedArgs[] = $arg;

                continue;
            }

            if (! $parameter->isVariadic() && $this->shouldSkipArg($arg, $parameter)) {
                $hasChanges = true;

                continue;
            }

            if ($parameter->isVariadic()) {
                $variadicParameterName = $parameter->getName();
                $variadicIndex = ($variadicArgCounters[$variadicParameterName] ?? 0) + 1;
                $variadicArgCounters[$variadicParameterName] = $variadicIndex;

                $arg->name = new Identifier(name: $variadicParameterName . $variadicIndex);
                $namedArgs[] = $arg;
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

    /**
     * @param ExtendedParameterReflection[] $parameters
     */
    private function hasVariadicArguments(
        FuncCall|StaticCall|MethodCall|New_ $node,
        array $parameters,
    ): bool {
        foreach ($node->args as $index => $arg) {
            if ($arg instanceof Arg && $arg->unpack) {
                continue;
            }

            $parameter = $this->resolveParameterForArgumentIndex(parameters: $parameters, index: $index);
            if ($parameter !== null && $parameter->isVariadic()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ExtendedParameterReflection[] $parameters
     */
    private function resolveParameterForArgumentIndex(array $parameters, int $index): ?ExtendedParameterReflection
    {
        if (isset($parameters[$index])) {
            return $parameters[$index];
        }

        if ($parameters === []) {
            return null;
        }

        $lastParameter = $parameters[array_key_last($parameters)];

        if (! $lastParameter->isVariadic()) {
            return null;
        }

        return $lastParameter;
    }

    private function shouldSkipCall(
        FuncCall|StaticCall|MethodCall|New_ $node,
        ?\PHPStan\Reflection\ClassReflection $classReflection,
    ): bool {
        if ($this->skipCalls === []) {
            return false;
        }

        $callSignature = $this->resolveCallSignature($node, $classReflection);
        if ($callSignature === null) {
            return false;
        }

        foreach ($this->skipCalls as $pattern) {
            if ($this->matchesSkipPattern($pattern, $callSignature)) {
                return true;
            }
        }

        return false;
    }

    private function matchesSkipPattern(string $pattern, string $callSignature): bool
    {
        if (! str_contains($pattern, '*')) {
            return $pattern === $callSignature;
        }

        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';

        return preg_match($regex, $callSignature) === 1;
    }

    private function resolveCallSignature(
        FuncCall|StaticCall|MethodCall|New_ $node,
        ?\PHPStan\Reflection\ClassReflection $classReflection,
    ): ?string {
        if ($node instanceof FuncCall) {
            return $this->getName($node->name);
        }

        if ($node instanceof New_ && $classReflection !== null) {
            return $classReflection->getName() . '::__construct';
        }

        if (($node instanceof MethodCall || $node instanceof StaticCall) && $classReflection !== null && $node->name instanceof Identifier) {
            return $classReflection->getName() . '::' . $node->name->toString();
        }

        return null;
    }

    public function provideMinPhpVersion(): int
    {
        return PhpVersion::PHP_80;
    }

    public function configure(array $configuration): void
    {
        Assert::lessThan(value: count(value: $configuration), limit: 4, message: 'You can pass only 1 strategy, 1 variadic option, and 1 skip list');
        if ($configuration === []) {
            return;
        }

        $strategyClass = $configuration[self::STRATEGY] ?? null;
        if (is_bool($strategyClass)) {
            $this->allowNamedVariadicArguments = $strategyClass;

            return;
        }

        if ($strategyClass !== null) {
            if (! is_string($strategyClass) || ! class_exists(class: $strategyClass)) {
                throw new InvalidArgumentException(message: "Class {$strategyClass} does not exist.");
            }

            $strategy = new $strategyClass();

            Assert::isInstanceOf(value: $strategy, class: ConfigStrategy::class, message: 'Your strategy must implement ConfigStrategy interface');

            $this->configStrategy = $strategyClass;
        }

        if (array_key_exists(self::ALLOW_NAMED_VARIADIC_ARGUMENTS, $configuration)) {
            Assert::boolean(value: $configuration[self::ALLOW_NAMED_VARIADIC_ARGUMENTS], message: 'Variadic option must be boolean');
            $this->allowNamedVariadicArguments = $configuration[self::ALLOW_NAMED_VARIADIC_ARGUMENTS];
        }

        if (array_key_exists(self::SKIP_CALLS, $configuration)) {
            Assert::true(value: is_array($configuration[self::SKIP_CALLS]), message: 'Skip calls option must be array');
            Assert::allString(value: $configuration[self::SKIP_CALLS], message: 'Skip calls option must contain only strings');
            $this->skipCalls = $configuration[self::SKIP_CALLS];
        }
    }
}
