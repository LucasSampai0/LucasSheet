<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_dashboard_redirects_to_login_without_token(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_login_with_configured_token(): void
    {
        config(['lucassheet.access_token' => 'secret-token']);

        $response = $this->post(route('login.store'), [
            'token' => 'secret-token',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertTrue(session()->get('access_token_authenticated'));
    }

    public function test_dashboard_loads_after_token_login(): void
    {
        $response = $this
            ->withSession(['access_token_authenticated' => true])
            ->get('/');

        $response->assertStatus(200);
    }
}
