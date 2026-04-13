<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Wayfinder\Database\Migrator;

final class HomePageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->container->get(Migrator::class)->run();
    }

    public function test_home_page_renders(): void
    {
        $this->client
            ->get('/')
            ->assertStatus(200)
            ->assertSee('Build PHP apps with explicit structure')
            ->assertSee('Interactive Demo');
    }

    public function test_health_endpoint_returns_ok(): void
    {
        $this->client
            ->get('/health')
            ->assertStatus(200)
            ->assertSee('Wayfinder app is running.');
    }
}
