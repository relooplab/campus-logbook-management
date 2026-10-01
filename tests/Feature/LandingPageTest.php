<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    public function test_guest_can_visit_public_landing_page(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('Bimbingan yang tertata')
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="'.route('register').'"', false);
    }

    public function test_authenticated_user_stays_on_landing_page_and_sees_dashboard_cta(): void
    {
        $user = new User;
        $user->id = 999999;

        $this->actingAs($user)->get(route('landing'))
            ->assertOk()
            ->assertSee('Ke Dashboard')
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertDontSee('Buat akun gratis');
    }

    public function test_landing_page_has_metadata_for_public_sharing(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('landing').'">', false)
            ->assertSee('<meta name="description"', false)
            ->assertSee('property="og:title"', false);
    }
}