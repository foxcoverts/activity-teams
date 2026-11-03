<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_serves_the_public_home_page(): void
    {
        $this->get('/')->assertOk();
    }
}
