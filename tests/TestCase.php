<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $connection = config('database.default');

        if ($connection !== 'sqlite') {
            throw new \RuntimeException(
                "CRITICAL SAFETY INTERCEPT: Database connection is '{$connection}'. " .
                "Tests are strictly forbidden from running on MySQL to protect real data."
            );
        }

        parent::setUp();
    }
}
