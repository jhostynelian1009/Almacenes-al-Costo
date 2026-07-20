<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_home_renders_with_its_named_route(): void
    {
        $this->assertSame(url('/'), route('home'));
        $this->get(route('home'))->assertOk()->assertSee('Nueva experiencia digital');
    }

    public function test_admin_dashboard_renders_with_its_named_route(): void
    {
        $user = User::factory()->create();

        $this->assertSame(url('/admin'), route('admin.dashboard'));
        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Los valores mostrados son únicamente demostrativos');
    }
}
