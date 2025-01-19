# AddNamedArgumentsRector

In two words this feature doing this:

```diff
- (new DateTimeImmutable())->format('Y-m-d');
+ (new DateTimeImmutable())->format(format: 'Y-m-d');
```
+ Also for functions, static methods, constructors

It seems for me and my teammates that code with named arguments less prone to errors and more readable

I made this rule standalone, due to [discussion](https://github.com/rectorphp/rector-src/pull/6678)

### Installation

```bash
composer require --dev savinmikhail/add_named_arguments_rector
```
