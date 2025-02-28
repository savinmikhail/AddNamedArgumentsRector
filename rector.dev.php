<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\StringableForToStringRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use SavinMikhail\AddNamedArgumentsRector\AddNamedArgumentsRector;
use SavinMikhail\AddNamedArgumentsRector\Config\PhpyhStrategy;

return RectorConfig::configure()
->withPaths([
//    __DIR__ . '/.task',
    __DIR__ . '/src/AddNamedArgumentsRector.php',
    ])
    ->withRules([
        AddNamedArgumentsRector::class
    ]);
