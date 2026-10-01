<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    public function test_guest_sees_public_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Kelola bimbingan TA &amp; KP dari logbook hingga finalisasi.', false)
            ->assertSee('Masuk ke Sistem')
            ->assertSee('Daftar sebagai Mahasiswa');
    }

    public function test_authenticated_user_is_redirected_to_dashboard(): void
    {
        $user = new User;
        $user->id = 999999;

        $this->actingAs($user)->get('/')
            ->assertRedirect(route('dashboard'));
    }

    public function test_hero_shows_product_screenshot(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('images/landing/showcase-mahasiswa.webp')
            ->assertSee('Dashboard Campus Logbook pada tampilan desktop');
    }

    public function test_only_the_four_core_features_are_listed(): void
    {
        $response = $this->get(route('home'))->assertOk();

        foreach (['Bimbingan Terstruktur', 'Review &amp; Revisi', 'Pantau Progres', 'Workspace Terpusat'] as $feature) {
            $response->assertSee($feature, false);
        }

        // Tidak boleh ada section yang dilarang (pricing, statistik, testimonial).
        foreach (['Pricing', 'Testimoni', 'mahasiswa terdaftar', 'Trusted by'] as $forbidden) {
            $response->assertDontSee($forbidden, false);
        }
    }

    public function test_login_and_register_ctas_use_real_routes(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="'.route('register').'"', false);
    }

    public function test_landing_page_has_metadata_for_public_sharing(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<title>Campus Logbook Management — Bimbingan TA &amp; KP Terstruktur</title>', false)
            ->assertSee('<link rel="canonical" href="'.route('home').'">', false)
            ->assertSee('<meta name="description"', false)
            ->assertSee('property="og:title"', false);
    }
}