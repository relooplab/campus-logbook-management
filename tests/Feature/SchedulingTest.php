<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SchedulingTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    public function test_dosen_dengan_link_eksternal_muncul_di_halaman_jadwal(): void
    {
        $dosen = User::create([
            'name' => 'Dosen Link',
            'email' => 'dosen-link@test.com',
            'password' => bcrypt('password'),
            'registration_status' => 'approved',
            'jadwal_bimbingan_url' => 'https://cal.com/dosen-link',
        ]);
        $dosen->syncRoles(['dosen']);

        $response = $this->actingAs($this->mhs)->get(route('scheduling.index'));

        $response->assertOk();
        $response->assertSee('Dosen Link');
        $response->assertSee('https://cal.com/dosen-link');
    }

    public function test_dosen_dengan_optin_whatsapp_muncul_di_halaman_jadwal(): void
    {
        $dosen = User::create([
            'name' => 'Dosen WA',
            'email' => 'dosen-wa@test.com',
            'password' => bcrypt('password'),
            'registration_status' => 'approved',
            'whatsapp' => '6281234567890',
            'bimbingan_via_whatsapp' => true,
        ]);
        $dosen->syncRoles(['dosen']);

        $response = $this->actingAs($this->mhs)->get(route('scheduling.index'));

        $response->assertOk();
        $response->assertSee('Dosen WA');
        $response->assertSee('https://wa.me/6281234567890');
    }

    public function test_dosen_dengan_optin_telegram_muncul_di_halaman_jadwal(): void
    {
        $dosen = User::create([
            'name' => 'Dosen TG',
            'email' => 'dosen-tg@test.com',
            'password' => bcrypt('password'),
            'registration_status' => 'approved',
            'telegram' => '@dosen_tg',
            'bimbingan_via_telegram' => true,
        ]);
        $dosen->syncRoles(['dosen']);

        $response = $this->actingAs($this->mhs)->get(route('scheduling.index'));

        $response->assertOk();
        $response->assertSee('Dosen TG');
        $response->assertSee('https://t.me/dosen_tg');
    }

    public function test_dosen_tanpa_jalur_apapun_tidak_muncul_di_halaman_jadwal(): void
    {
        $dosen = User::create([
            'name' => 'Dosen Tanpa Jalur',
            'email' => 'dosen-tanpa@test.com',
            'password' => bcrypt('password'),
            'registration_status' => 'approved',
        ]);
        $dosen->syncRoles(['dosen']);

        $response = $this->actingAs($this->mhs)->get(route('scheduling.index'));

        $response->assertOk();
        $response->assertDontSee('Dosen Tanpa Jalur');
    }

    public function test_dosen_dengan_whatsapp_tapi_tidak_optin_tidak_muncul(): void
    {
        $dosen = User::create([
            'name' => 'Dosen WA Nonaktif',
            'email' => 'dosen-wa-nonaktif@test.com',
            'password' => bcrypt('password'),
            'registration_status' => 'approved',
            'whatsapp' => '6281234567890',
            'bimbingan_via_whatsapp' => false,
        ]);
        $dosen->syncRoles(['dosen']);

        $response = $this->actingAs($this->mhs)->get(route('scheduling.index'));

        $response->assertOk();
        $response->assertDontSee('Dosen WA Nonaktif');
    }
}