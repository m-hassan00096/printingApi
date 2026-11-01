<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    private const SPA_ORIGIN = 'https://masria.elmotechsoft.online';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cors.allowed_origins', [self::SPA_ORIGIN]);
        config()->set('cors.allowed_origins_patterns', []);
        config()->set('cors.supports_credentials', true);
    }

    public function test_trusted_spa_can_preflight_login_with_credentials(): void
    {
        $this->withHeaders([
            'Origin' => self::SPA_ORIGIN,
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type,x-xsrf-token,x-requested-with',
        ])->options('/api/v1/auth/login')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', self::SPA_ORIGIN)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_trusted_spa_can_preflight_sanctum_csrf_cookie(): void
    {
        $this->withHeaders([
            'Origin' => self::SPA_ORIGIN,
            'Access-Control-Request-Method' => 'GET',
        ])->options('/sanctum/csrf-cookie')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', self::SPA_ORIGIN)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_untrusted_origin_is_not_allowed(): void
    {
        $untrusted = 'https://untrusted.example';
        $response = $this->withHeaders([
            'Origin' => $untrusted,
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/v1/auth/login');

        // Fruitcake CORS deliberately emits the fixed allowed origin when the
        // allowlist has one entry. Browsers deny access because it does not
        // match the requesting origin, even when credentials are supported.
        $response->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', self::SPA_ORIGIN);
        $this->assertNotSame($untrusted, $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_multiple_allowed_origins_never_reflect_an_untrusted_origin(): void
    {
        config()->set('cors.allowed_origins', [self::SPA_ORIGIN, 'http://localhost:5173']);
        $response = $this->withHeaders([
            'Origin' => 'https://untrusted.example',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/v1/auth/login');

        $response->assertNoContent();
        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertFalse($response->headers->has('Access-Control-Allow-Credentials'));
    }

    public function test_multiple_allowed_origins_reflect_the_trusted_origin(): void
    {
        config()->set('cors.allowed_origins', [self::SPA_ORIGIN, 'http://localhost:5173']);
        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/v1/auth/login')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_unauthenticated_api_responses_include_trusted_spa_cors_headers(): void
    {
        $this->withHeaders([
            'Origin' => self::SPA_ORIGIN,
            'Accept' => 'application/json',
        ])->get('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertHeader('Access-Control-Allow-Origin', self::SPA_ORIGIN)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }
}
