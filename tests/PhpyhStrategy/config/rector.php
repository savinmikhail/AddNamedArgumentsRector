<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use SavinMikhail\AddNamedArgumentsRector\AddNamedArgumentsRector;
use SavinMikhail\AddNamedArgumentsRector\Config\PhpyhStrategy;

return RectorConfig::configure()
        ->withConfiguredRule(AddNamedArgumentsRector::class, [PhpyhStrategy::class]);
