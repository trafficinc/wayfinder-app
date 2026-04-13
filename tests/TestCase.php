<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Wayfinder\Contracts\Container;
use Wayfinder\Testing\TestClient;

abstract class TestCase extends BaseTestCase
{
    protected TestClient $client;
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Container $container */
        $container = require __DIR__ . '/../bootstrap/container.php';
        $this->container = $container;

        /** @var TestClient $client */
        $client = require __DIR__ . '/../bootstrap/testing.php';
        $this->client = $client;

        $this->purgeDirectory(__DIR__ . '/../storage/mail', ['.gitignore']);
        $this->purgeDirectory(__DIR__ . '/../storage/framework/cache', ['.gitignore']);
        $this->purgeDirectory(__DIR__ . '/../storage/framework/queue', ['.gitignore']);
        $this->purgeDirectory(__DIR__ . '/../storage/framework/queue/pending', ['.gitignore']);
        $this->purgeDirectory(__DIR__ . '/../storage/framework/queue/processing', ['.gitignore']);
        $this->purgeDirectory(__DIR__ . '/../storage/framework/queue/failed', ['.gitignore']);
        $this->purgeDirectory(__DIR__ . '/../storage/framework/sessions', ['.gitignore']);
    }

    /**
     * @param list<string> $preserve
     */
    protected function purgeDirectory(string $directory, array $preserve = []): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, $preserve, true)) {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
