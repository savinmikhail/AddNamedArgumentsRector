<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\StringableForToStringRector;
use SavinMikhail\AddNamedArgumentsRector\AddNamedArgumentsRector;

return RectorConfig::configure()
    ->withPaths(paths: [
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __FILE__
    ])
    ->withParallel()
    ->withCache(cacheDirectory: __DIR__ . '/var/rector')
    ->withPhpSets(php82: true)
    ->withRules(rules: [
        AddNamedArgumentsRector::class,
    ])
    ->withSkip(skip: [
        StringableForToStringRector::class,
    ]);
