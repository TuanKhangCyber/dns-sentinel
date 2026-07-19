<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\TestingDatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    protected $seed = true;

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        TestingDatabaseGuard::assertApplicationIsSafe($app);

        return $app;
    }
}
