<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use SavinMikhail\AddNamedArgumentsRector\AddNamedArgumentsRector;
use SavinMikhail\AddNamedArgumentsRector\Config\DefaultStrategy;

return RectorConfig::configure()
        ->withConfiguredRule(AddNamedArgumentsRector::class, [
            AddNamedArgumentsRector::STRATEGY => DefaultStrategy::class,
            AddNamedArgumentsRector::ALLOW_NAMED_VARIADIC_ARGUMENTS => false,
        ]);
