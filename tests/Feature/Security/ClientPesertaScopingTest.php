<?php

namespace Tests\Feature\Security;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-TEST 04 — Scoping data panel /klien (FASE 2 / Opsi A).
 *
 * Regresi yang dijaga: klien hanya boleh melihat & mengedit peserta yang
 * ia daftarkan sendiri (users.created_by = id dirinya).
 */
class ClientPesertaScopingTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email, string $type = 'client', ?int $createdBy = null): User
    {
        return User::create([
            'name' => 'Nama ' . $email,
            'email' => $email,
            'password' => bcrypt('Secret#12345'),
            'user_type' => $type,
            'status' => UserStatus::Published->value,
            'created_by' => $createdBy,
        ]);
    }

    public function test_klien_hanya_melihat_peserta_miliknya_sendiri(): void
    {
        $klienA = $this->makeUser('klien.a@example.test');
        $klienB = $this->makeUser('klien.b@example.test');

        $this->makeUser('peserta.milik.a@example.test', 'client', $klienA->id);
        $this->makeUser('peserta.milik.b@example.test', 'client', $klienB->id);

        $response = $this->actingAs($klienA)->get('/klien/pesertas');

        $response->assertOk();
        $response->assertSee('peserta.milik.a@example.test');
        $response->assertDontSee('peserta.milik.b@example.test');
    }

    public function test_klien_tidak_bisa_membuka_edit_peserta_klien_lain(): void
    {
        $klienA = $this->makeUser('klien.a@example.test');
        $klienB = $this->makeUser('klien.b@example.test');
        $pesertaB = $this->makeUser('peserta.milik.b@example.test', 'client', $klienB->id);

        $this->actingAs($klienA)
            ->get('/klien/pesertas/' . $pesertaB->id . '/edit')
            ->assertNotFound();
    }

    public function test_klien_bisa_membuka_edit_peserta_miliknya(): void
    {
        $klienA = $this->makeUser('klien.a@example.test');
        $pesertaA = $this->makeUser('peserta.milik.a@example.test', 'client', $klienA->id);

        $this->actingAs($klienA)
            ->get('/klien/pesertas/' . $pesertaA->id . '/edit')
            ->assertOk();
    }

    public function test_peserta_tanpa_pemilik_tidak_bocor_ke_panel_klien(): void
    {
        $klienA = $this->makeUser('klien.a@example.test');
        $orphan = $this->makeUser('peserta.tanpa.pemilik@example.test');

        $this->actingAs($klienA)
            ->get('/klien/pesertas')
            ->assertOk()
            ->assertDontSee('peserta.tanpa.pemilik@example.test');

        $this->actingAs($klienA)
            ->get('/klien/pesertas/' . $orphan->id . '/edit')
            ->assertNotFound();
    }
}
