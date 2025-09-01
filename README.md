[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/savinmikhail/AddNamedArgumentsRector/badges/quality-score.png?b=main)](https://scrutinizer-ci.com/g/savinmikhail/AddNamedArgumentsRector/?branch=main)
[![Code Coverage](https://scrutinizer-ci.com/g/savinmikhail/AddNamedArgumentsRector/badges/coverage.png?b=main)](https://scrutinizer-ci.com/g/savinmikhail/AddNamedArgumentsRector/?branch=main)
<a href="https://packagist.org/packages/savinmikhail/add_named_arguments_rector"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>

# AddNamedArgumentsRector

The **AddNamedArgumentsRector** rule enhances your code by converting function, method, or constructor calls to use named arguments where possible. Named arguments improve readability and reduce errors by explicitly naming the parameters being passed.

### Example

```diff
- str_contains('foo', 'bar');
+ str_contains(haystack: 'foo', needle: 'bar');
```

This feature works for:
- Functions
- Static methods
- Instance methods
- Constructors

---

### Installation

You can install the package via Composer:

```bash
composer require --dev savinmikhail/add_named_arguments_rector
```

---

### Usage

To enable the rule, add it to your Rector configuration (`rector.php`):

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use SavinMikhail\AddNamedArgumentsRector\AddNamedArgumentsRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(AddNamedArgumentsRector::class);
};
```

---

### Customization

By default, the rule applies named arguments universally where possible. However, if you want more control over when the rule applies, you can use a **strategy**:

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use SavinMikhail\AddNamedArgumentsRector\AddNamedArgumentsRector;
use SavinMikhail\AddNamedArgumentsRector\Config\PhpyhStrategy;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->ruleWithConfiguration(
        AddNamedArgumentsRector::class,
        [PhpyhStrategy::class]
    );
};
```

#### Implementing Your Own Strategy

See `PhpyhStrategy` as example, you can create your own strategy by implementing the `ConfigStrategy` interface. For example:

```php
<?php

declare(strict_types=1);

namespace YourNamespace;

use PhpParser\Node;
use SavinMikhail\AddNamedArgumentsRector\Config\ConfigStrategy;

class CustomStrategy implements ConfigStrategy
{
    public static function shouldApply(Node $node, array $parameters): bool
    {
        // Add your custom logic here
        return true;
    }
}
```

Then, configure it in your `rector.php`:

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use SavinMikhail\AddNamedArgumentsRector\AddNamedArgumentsRector;
use YourNamespace\CustomStrategy;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->ruleWithConfiguration(
        AddNamedArgumentsRector::class,
        [CustomStrategy::class]
    );
};
```

---

### Tests as Documentation

The package includes tests that demonstrate how the rule behaves in various scenarios. Feel free to explore the tests for examples and usage details.

---

### Related Discussion

This rule was developed as a standalone feature following [this discussion](https://github.com/rectorphp/rector-src/pull/6678).

---

### Contributing

Contributions, feedback, and suggestions are welcome! Feel free to open issues or submit pull requests.

### Credits

Thanks to [Valentin](https://github.com/vudaltsov) for inspiration and advices
