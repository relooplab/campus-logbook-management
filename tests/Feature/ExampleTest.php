<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_shows_public_homepage_to_guests(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Bimbingan yang tertata');
    }
}
