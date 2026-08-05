<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\MahasiswaTa;
use App\Models\User;
use App\Notifications\WeeklyDigestNotification;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;

/**
 * Verifikasi resolusi institusi per-konteks (bukan singleton global):
 * - Admin institusi B tidak boleh menimpa setting institusi A.
 * - Limit upload mengikuti institusi user yang login.
 * - Notifikasi queued pakai mail config institusi penerima.
 * - Dokumen rekap tampilkan institusi pembimbing_1.
 * - Deployment 1-institusi tidak berubah perilaku.
 */
class InstitutionResolutionTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    private Institution $institutionA;
    private Institution $institutionB;
    private User $adminA;
    private User $adminB;
    private User $dosenA;
    private User $dosenB;

    protected function setUp(): void
    {
        parent::setUp();

        // Paksa mode institusi.
        config(['app.mode' => 'institution']);

        // Buat 2 institusi dengan setting berbeda.
        $this->institutionA = Institution::create([
            'app_name' => 'Institusi A',
            'institution_name' => 'Universitas A',
            'email' => 'a@test.com',
            'max_upload_size_mb' => 5,
            'allowed_file_types' => 'pdf',
            'mail_mailer' => 'log',
            'mail_from_address' => 'a@test.com',
            'mail_from_name' => 'Institusi A',
        ]);
        $this->institutionB = Institution::create([
            'app_name' => 'Institusi B',
            'institution_name' => 'Universitas B',
            'email' => 'b@test.com',
            'max_upload_size_mb' => 20,
            'allowed_file_types' => 'pdf,docx',
            'mail_mailer' => 'log',
            'mail_from_address' => 'b@test.com',
            'mail_from_name' => 'Institusi B',
        ]);

        // Admin A & B.
        $this->adminA = User::create([
            'name' => 'Admin A',
            'email' => 'admin-res-a@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institutionA->id,
        ]);
        $this->adminA->assignRole('admin');

        $this->adminB = User::create([
            'name' => 'Admin B',
            'email' => 'admin-res-b@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institutionB->id,
        ]);
        $this->adminB->assignRole('admin');

        // Dosen A & B.
        $this->dosenA = User::create([
            'name' => 'Dosen A',
            'email' => 'dosen-res-a@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institutionA->id,
        ]);
        $this->dosenA->assignRole('dosen');

        $this->dosenB = User::create([
            'name' => 'Dosen B',
            'email' => 'dosen-res-b@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institutionB->id,
        ]);
        $this->dosenB->assignRole('dosen');
    }

    protected function tearDown(): void
    {
        config(['app.mode' => 'individual']);
        parent::tearDown();
    }

    public function test_admin_b_update_institution_does_not_change_institution_a(): void
    {
        // Admin B update "Pengaturan Institusi".
        $this->actingAs($this->adminB)->post(route('admin.institution.update'), [
            'app_name' => 'Institusi B Updated',
            'institution_name' => 'Universitas B Updated',
            'max_upload_size_mb' => 30,
            'allowed_file_types' => 'pdf,docx,xlsx',
        ])->assertRedirect();

        // Institusi A TIDAK berubah.
        $this->institutionA->refresh();
        $this->assertSame('Institusi A', $this->institutionA->app_name);
        $this->assertSame('Universitas A', $this->institutionA->institution_name);
        $this->assertSame(5, $this->institutionA->max_upload_size_mb);

        // Institusi B berubah.
        $this->institutionB->refresh();
        $this->assertSame('Institusi B Updated', $this->institutionB->app_name);
        $this->assertSame(30, $this->institutionB->max_upload_size_mb);
    }

    public function test_admin_a_update_institution_does_not_change_institution_b(): void
    {
        // Admin A update "Pengaturan Institusi".
        $this->actingAs($this->adminA)->post(route('admin.institution.update'), [
            'app_name' => 'Institusi A Updated',
            'institution_name' => 'Universitas A Updated',
            'max_upload_size_mb' => 7,
            'allowed_file_types' => 'pdf',
        ])->assertRedirect();

        // Institusi B TIDAK berubah.
        $this->institutionB->refresh();
        $this->assertSame('Institusi B', $this->institutionB->app_name);
        $this->assertSame(20, $this->institutionB->max_upload_size_mb);

        // Institusi A berubah.
        $this->institutionA->refresh();
        $this->assertSame('Institusi A Updated', $this->institutionA->app_name);
        $this->assertSame(7, $this->institutionA->max_upload_size_mb);
    }

    public function test_institution_current_resolves_to_acting_admin_institution(): void
    {
        $this->actingAs($this->adminA);
        $this->assertSame($this->institutionA->id, Institution::current()->id);

        $this->actingAs($this->adminB);
        $this->assertSame($this->institutionB->id, Institution::current()->id);
    }

    public function test_institution_for_user_resolves_correctly(): void
    {
        $this->assertSame($this->institutionA->id, Institution::forUser($this->dosenA)->id);
        $this->assertSame($this->institutionB->id, Institution::forUser($this->dosenB)->id);
    }

    public function test_institution_for_institution_id_falls_back_to_active_when_not_found(): void
    {
        $this->assertSame(Institution::active()->id, Institution::forInstitutionId(99999)->id);
        $this->assertSame(Institution::active()->id, Institution::forInstitutionId(null)->id);
    }

    public function test_logbook_upload_limit_uses_acting_users_institution(): void
    {
        // Dosen A (institusi A, limit 5 MB) upload lampiran 10 MB -> ditolak.
        $this->actingAs($this->dosenA)->post(route('logbook.store'), [
            'tanggal_bimbingan' => now()->toDateString(),
            'topik' => 'Test limit A',
            'progres_kendala' => 'Test',
            'lampiran' => \Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 10 * 1024, 'application/pdf'),
        ])->assertSessionHasErrors('lampiran');

        // Dosen B (institusi B, limit 20 MB) upload lampiran 10 MB -> sukses.
        $this->actingAs($this->dosenB)->post(route('logbook.store'), [
            'tanggal_bimbingan' => now()->toDateString(),
            'topik' => 'Test limit B',
            'progres_kendala' => 'Test',
            'lampiran' => \Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 10 * 1024, 'application/pdf'),
        ])->assertSessionHasNoErrors();
    }

    public function test_notification_to_mail_uses_recipient_institution_config(): void
    {
        Notification::fake();

        // Kirim notifikasi ke dosen B (institusi B).
        $this->dosenB->notify(new WeeklyDigestNotification('Ringkasan mingguan'));

        Notification::assertSentTo($this->dosenB, WeeklyDigestNotification::class, function ($notification, $channels) {
            // toMail dipanggil — applyToConfig() harus set app.name ke institusi B.
            $notification->toMail($this->dosenB);
            $this->assertSame('Institusi B', config('app.name'));

            return true;
        });
    }

    public function test_rekap_bimbingan_uses_pembimbing1_institution(): void
    {
        // Buat TA dengan pembimbing_1 = dosen B (institusi B).
        $mhs = User::create([
            'name' => 'Mhs Rekap',
            'email' => 'mhs-rekap@test.com',
            'password' => bcrypt('password'),
            'institution_id' => $this->institutionB->id,
        ]);
        $mhs->assignRole('mahasiswa');

        $ta = MahasiswaTa::create([
            'institution_id' => $this->institutionB->id,
            'user_id' => $mhs->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $this->dosenB->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
        ]);

        // Render view rekap-bimbingan.
        $view = view('exports.rekap-bimbingan', [
            'mahasiswaTa' => $ta,
            'target' => 7,
            'approved' => 0,
            'entries' => collect(),
        ])->render();

        // Kop dokumen menampilkan institusi B (pembimbing_1), bukan institusi A.
        $this->assertStringContainsString('UNIVERSITAS B', $view);
        $this->assertStringNotContainsString('UNIVERSITAS A', $view);
    }

    public function test_single_institution_deployment_unchanged(): void
    {
        // Mode individual (1 institusi) — current() fallback ke active().
        config(['app.mode' => 'individual']);

        // User tanpa institution_id (mode individual) → fallback ke active().
        $userNoInstitution = User::create([
            'name' => 'User No Inst',
            'email' => 'user-no-inst@test.com',
            'password' => bcrypt('password'),
            'institution_id' => null,
        ]);

        $this->assertSame(Institution::active()->id, Institution::current()->id);
        $this->assertSame(Institution::active()->id, Institution::forUser($userNoInstitution)->id);
        $this->assertSame(Institution::active()->id, Institution::forInstitutionId(null)->id);
    }
}