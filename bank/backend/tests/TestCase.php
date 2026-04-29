<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pretend every request comes from the SPA so Sanctum's stateful
        // middleware kicks in and session cookies are issued. Matches the
        // default `SANCTUM_STATEFUL_DOMAINS=localhost:3000`.
        $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
        ]);
    }
}
