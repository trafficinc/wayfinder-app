<?php

declare(strict_types=1);

use Wayfinder\Foundation\AppKernel;

$container = require __DIR__ . '/container.php';

return $container->get(AppKernel::class);
