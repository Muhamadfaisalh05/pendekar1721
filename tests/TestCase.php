<?php

namespace Tests;

use App\Support\SchemaGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Skema bisa berubah antar test (mis. tes yang melepas kolom Fase 2),
        // sedangkan SchemaGuard menyimpan hasil pengecekan secara static
        // (cache per request). Reset di sini supaya setiap test mulai bersih.
        SchemaGuard::reset();
    }
}
