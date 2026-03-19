<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use SavinMikhail\AddNamedArgumentsRector\AddNamedArgumentsRector;

require_once __DIR__ . '/../FixtureFunctions.php';

return RectorConfig::configure()
        ->withRules(rules: [
            AddNamedArgumentsRector::class,
        ]);
