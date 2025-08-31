<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use SavinMikhail\AddNamedArgumentsRector\AddNamedArgumentsRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src/AddNamedArgumentsRector.php',
    ])
    ->withRules([
        AddNamedArgumentsRector::class
    ]);
