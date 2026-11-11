<?php

namespace Tests\Support\Absensi;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Base test case modul absensi.
 *
 * RefreshDatabase menangani DB utama (default connection), sedangkan koneksi
 * `semarak` memakai SQLite in-memory tersendiri yang dimigrasi & di-reset
 * datanya pada setiap test (lihat PreparesAbsensiDatabase).
 */
abstract class AbsensiTestCase extends TestCase
{
    use PreparesAbsensiDatabase;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareAbsensiDatabase();
    }
}
