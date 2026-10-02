<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\MahasiswaTa;
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

    public function test_hero_uses_new_headline_and_moves_old_headline_to_eyebrow(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('<div class="landing-eyebrow mb-6"><span class="landing-eyebrow-dot"></span><span>Bimbingan yang tertata, kemajuan yang terlihat.</span></div>', false)
            ->assertSee('<h1 id="hero-title" class="landing-display">Campus <span class="text-accent-blue">Logbook</span> <span class="text-accent-orange">Management</span></h1>', false)
            ->assertDontSee('Ruang kerja Tugas Akhir &amp; Kerja Praktik', false);
    }

    public function test_dashboard_previews_share_existing_assets_with_readme(): void
    {
        $response = $this->get(route('landing'))->assertOk();
        $readme = file_get_contents(base_path('README.md'));

        foreach (['mahasiswa', 'dosen'] as $role) {
            $image = 'images/readme-dashboard-'.$role.'.jpeg';

            $version = hash_file('sha256', public_path($image));
            $response->assertSee('src="'.asset($image).'?v='.$version.'"', false)
                ->assertSee('Dashboard '.$role);
            $this->assertFileExists(public_path($image));
            $this->assertStringContainsString('(public/'.$image.')', $readme);
        }

        $response->assertSee('data-dashboard-slider', false)
            ->assertSee('aria-roledescription="carousel"', false)
            ->assertSee('aria-controls="dashboard-slide-mahasiswa"', false)
            ->assertSee('aria-controls="dashboard-slide-dosen"', false)
            ->assertSee('data-slide-prev', false)
            ->assertSee('data-slide-next', false)
            ->assertSee('data-slide-play', false);
    }

    public function test_ta_journey_uses_application_phases_and_accessible_controls(): void
    {
        $response = $this->get(route('landing'))->assertOk();

        foreach (MahasiswaTa::FASES as $key => $label) {
            $response->assertSee($label)
                ->assertSee('id="ta-phase-detail-'.$key.'"', false)
                ->assertSee('aria-controls="ta-phase-detail-'.$key.'"', false);
        }

        $response->assertSee('data-ta-play', false)
            ->assertSee('prefers-reduced-motion: reduce', false)
            ->assertSee('ini ilustrasi alur, bukan progres akun Anda.')
            ->assertSee('Fase mahasiswa ditetapkan oleh dosen pembimbing.')
            ->assertSeeInOrder(['id="hero-title"', 'id="alur"', 'id="fitur"'], false);
    }

    public function test_landing_motion_has_phase_colors_and_progressive_enhancement(): void
    {
        $response = $this->get(route('landing'))->assertOk();

        foreach (array_keys(MahasiswaTa::FASES) as $index => $key) {
            $response->assertSee('data-ta-step="'.$index.'" data-phase-color="'.$index.'"', false);
        }

        $response->assertSee('IntersectionObserver', false)
            ->assertSee('is-reveal-pending', false)
            ->assertSee('pointercancel', false)
            ->assertSee('visibilitychange', false)
            ->assertSee('data-slider-controls hidden', false);
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

    public function test_capabilities_marquee_runs_without_pause_controls_and_has_hidden_duplicate(): void
    {
        $this->get(route('landing'))->assertOk()
            ->assertSee('data-capabilities-marquee', false)
            ->assertDontSee('data-marquee-toggle', false)
            ->assertDontSee('Jeda running text')
            ->assertSee('class="landing-marquee-group"  aria-hidden="true"', false)
            ->assertDontSee('Putar running text')
            ->assertSee("motion.addEventListener('change', sync)", false);
    }

    public function test_footer_has_current_year_copyright_and_social_links(): void
    {
        $this->get(route('landing'))->assertOk()
            ->assertSee('© '.now()->year.' Reloop Lab.')
            ->assertSee('href="https://github.com/relooplab/campus-logbook-management"', false)
            ->assertSee('href="https://www.linkedin.com/company/relooplab"', false)
            ->assertSee('aria-label="LinkedIn Reloop Lab (tab baru)"', false)
            ->assertSee('aria-label="GitHub Campus Logbook Management (tab baru)"', false);
    }

    public function test_landing_page_hides_technical_stack_and_license_blurb(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertDontSee('DIBANGUN DENGAN')
            ->assertDontSee('Laravel · Tailwind CSS · React · PDF.js · Reverb')
            ->assertDontSee('Dilisensikan dengan Business Source License 1.1.')
            ->assertSee('PERTANYAAN UMUM')
            ->assertSee('Kirim Masukan');
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