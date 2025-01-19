<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use SavinMikhail\AddNamedArgumentsRector\AddNamedArgumentsRector;

return RectorConfig::configure()
        ->withRules([
            AddNamedArgumentsRector::class,
        ]);
