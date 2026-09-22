<?php

namespace Tests\Feature;

use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_placeholder_dashboard_renders(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Management dashboard');
    }
}
