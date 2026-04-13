<?php

declare(strict_types=1);

use Wayfinder\Foundation\AppKernel;
use Wayfinder\Testing\TestClient;

$container = require __DIR__ . '/container.php';

return new TestClient(
    $container->get(AppKernel::class),
    $container,
);
