<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Every Feature test gets a clean Postgres schema via RefreshDatabase. We
 * intentionally keep the real Postgres dialect instead of swapping to SQLite
 * because our migrations lean on `gen_random_uuid()`, `JSONB`, `INET`, and
 * `audit_log` immutability triggers — none of which sqlite can express.
 */
uses(Tests\TestCase::class, RefreshDatabase::class)->in('Feature');

uses(Tests\TestCase::class)->in('Unit');
