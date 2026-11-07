<?php

namespace Tests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Roles must exist before users can be given one (applies with RefreshDatabase). */
    protected bool $seed = true;

    protected string $seeder = RoleSeeder::class;
}
