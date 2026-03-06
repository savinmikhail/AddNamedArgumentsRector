# Issue #15 investigation: variadic parameters

## Context

Issue #15 requests support for converting positional arguments that map to a variadic parameter into named arguments. The idea is to allow names like `foo1`, `foo2`, etc. for a variadic parameter named `foo`, and make this behavior configurable.

## Current behavior in the codebase

- `DefaultStrategy::areArgumentsSuitable()` currently rejects any call where the matched parameter is variadic (`$parameters[$index]->isVariadic()`), so variadic calls are skipped before refactoring starts.
- The existing fixture `tests/DefaultStrategy/Fixture/variadic.php.inc` documents this: a `sprintf()` call remains unchanged.
- `AddNamedArgumentsRector::addNamesToArgs()` assumes a 1:1 index mapping between arguments and parameters, and for arguments past the parameter list keeps them unchanged (`$parameters[$index] ?? null`). This is another reason variadic tails are not transformed.

## Proposed implementation plan

1. **Add explicit variadic naming mode to configuration**
   - Extend rector configuration to accept a second option (or a small options object/array), e.g. `allowNamedVariadicArguments: bool`.
   - Keep default `false` to preserve current behavior and avoid unexpected changes.

2. **Refactor argument-to-parameter matching for variadics**
   - Introduce a helper that resolves the *effective parameter* for each argument index:
     - normal parameters map by index;
     - any argument index beyond the last parameter maps to the last parameter only if it is variadic.
   - Use this helper in both suitability checks and argument renaming to avoid divergent logic.

3. **Update strategy validation logic**
   - In `DefaultStrategy::areArgumentsSuitable()`, keep rejecting variadics when the new option is disabled.
   - When enabled, allow variadic-mapped arguments and still keep existing safety checks:
     - reject unpacked args (`...$x`);
     - reject mismatched pre-existing named arguments.

4. **Define deterministic naming for variadic args**
   - For the first variadic value use `<variadicParamName>1`, then increment (`foo1`, `foo2`, ...).
   - Never rename an argument that is already explicitly named.
   - Apply naming only when the variadic option is enabled.

5. **Preserve skip-default logic only for non-variadics**
   - `shouldSkipArg()` should continue to operate for optional non-variadic parameters.
   - Do not apply default-value skipping to variadic values (variadic parameters do not have a comparable default-value semantic for each collected item).

6. **Add comprehensive fixtures/tests**
   - Keep current fixture asserting default behavior (variadics unchanged).
   - Add new fixture(s) with the option enabled covering:
     - pure variadic function (`sprintf`-like examples as allowed by PHP);
     - mixed signature (`function f($a, ...$rest)`) where only variadic tail gets `rest1`, `rest2`;
     - partial pre-named args and stability of existing names;
     - unpacking remains skipped.

7. **Document behavior and limitations in README**
   - Explain that variadic argument naming is opt-in.
   - Mention that names for variadic arguments are synthetic keys and chosen for deterministic output.

## Risks and edge cases to validate

- Calls that already contain named variadic arguments with arbitrary names should remain valid and stable.
- Interaction with mixed positional + named arguments ordering rules in PHP 8+.
- Reflection differences between internal and userland functions when detecting variadic parameters.

## Suggested rollout

- Implement behind opt-in config, ship with tests first.
- Collect feedback before considering changing defaults.
