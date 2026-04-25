<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_returns_ok_payload(): void
    {
        $this->get('/healthz')
            ->assertOk()
            ->assertJsonStructure(['ok', 'app', 'time'])
            ->assertJson(['ok' => true]);
    }
}
