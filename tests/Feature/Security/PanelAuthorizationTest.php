<?php

namespace Tests\Feature\Security;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-TEST 03 — Pemisahan panel Admin vs Klien (broken access control).
 * Regresi yang dijaga: klien tidak boleh masuk /admin, admin tidak masuk /klien.
 */
class PanelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $type): User
    {
        return User::create([
            'name' => ucfirst($type) . ' User',
            'email' => $type . '.panel@example.test',
            'password' => bcrypt('Secret#12345'),
            'user_type' => $type,
            'status' => UserStatus::Published->value,
        ]);
    }

    public function test_tamu_diarahkan_ke_login_bukan_ke_dashboard(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/klien')->assertRedirect('/klien/login');
    }

    public function test_klien_tidak_bisa_membuka_panel_admin(): void
    {
        $client = $this->makeUser('client');

        $this->actingAs($client)->get('/admin')->assertForbidden();
        $this->actingAs($client)->get('/admin/users')->assertForbidden();
    }

    public function test_admin_tidak_bisa_membuka_panel_klien(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->get('/klien')->assertForbidden();
    }

    public function test_klien_bisa_membuka_panel_klien(): void
    {
        $client = $this->makeUser('client');

        $this->actingAs($client)->get('/klien')->assertOk();
    }
}
