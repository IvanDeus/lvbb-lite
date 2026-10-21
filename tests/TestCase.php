<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // The test suite runs against the same Neon database as the app, and that database also
    // holds djbb-lite's tables, so nothing here may migrate or wipe. Each test rolls back.
    use DatabaseTransactions;
}
