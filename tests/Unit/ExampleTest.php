<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * That true is true.
     */
    public function test_that_true_is_true()
    {
        $this->assertTrue(true);
    }
}
