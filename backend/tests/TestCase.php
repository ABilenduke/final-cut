<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        if ($testDb = env('DB_TEST_DATABASE')) {
            $app['config']->set('database.connections.pgsql.database', $testDb);
        }

        return $app;
    }
}
