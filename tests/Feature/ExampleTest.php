<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify home route and API state endpoint respond with 200 OK and security headers.
     */
    public function test_the_application_returns_a_successful_response_with_security_headers(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $apiResponse = $this->getJson('/api/tournaments');
        $apiResponse->assertOk()
            ->assertJsonPath('total_count', 6);
    }
}
