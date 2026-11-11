<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-TEST 02 — Header keamanan wajib ada pada response HTML
 * (mitigasi clickjacking, MIME sniffing, referrer leak).
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_publik_mengirim_security_headers(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_halaman_login_juga_mengirim_security_headers(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader(
                'Content-Security-Policy',
                "frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action 'self'"
            );
    }

    public function test_tidak_membocorkan_header_versi_php(): void
    {
        $response = $this->get('/');

        $this->assertFalse(
            $response->headers->has('X-Powered-By'),
            'Header X-Powered-By membocorkan teknologi server; matikan expose_php.'
        );
    }
}
