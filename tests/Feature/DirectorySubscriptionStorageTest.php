<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DirectorySubscription;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\Plan;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use App\Models\UserPlanOverride;
use App\Models\UserStorageAddon;
use App\Services\OrganizationalDirectoryService;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Fase B — Verifikasi resolusi kuota storage:
 * override > direktori > plan individual, + addon selalu additive.
 */
class DirectorySubscriptionStorageTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    private OrganizationalDirectoryService $service;
    private University $univ;
    private Faculty $faculty;
    private Department $dept;
    private StudyProgram $prodi;
    private Plan $planInstitusi;
    private Plan $planFree;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrganizationalDirectoryService::class);

        // Paksa mode institusi.
        config(['app.mode' => 'institution']);

        // Buat institusi + direktori.
        $institution = Institution::create([
            'app_name' => 'Storage Test',
            'institution_name' => 'Universitas Storage Test',
            'email' => 'storage@test.com',
        ]);

        $this->univ = $this->service->findOrCreateUniversity('Universitas Storage Test');
        $this->faculty = $this->service->findOrCreateFaculty($this->univ, 'Fakultas Teknik');
        $this->dept = $this->service->findOrCreateDepartment($this->faculty, 'Departemen Teknik Informatika');
        $this->prodi = $this->service->findOrCreateStudyProgram($this->dept, 'S1 Teknik Informatika');

        // Plan institusi (10 GB) & plan free (5 GB).
        $this->planInstitusi = Plan::create([
            'name' => 'institusi_test',
            'label' => 'Institusi Test',
            'price' => 100000,
            'period' => 'monthly',
            'features' => ['storage_mb' => 10240, 'export' => true, 'import' => true],
            'is_active' => true,
        ]);

        $this->planFree = Plan::firstOrCreate(
            ['name' => 'free'],
            [
                'label' => 'Gratis',
                'price' => 0,
                'period' => 'monthly',
                'features' => ['storage_mb' => 5120, 'export' => false, 'import' => false],
                'is_active' => true,
            ]
        );

        // Dosen terafiliasi ke prodi.
        $this->service->attachUserToUniversity($this->dosen, $this->univ, $this->faculty, $this->dept, $this->prodi, true);
    }

    protected function tearDown(): void
    {
        config(['app.mode' => 'individual']);
        parent::tearDown();
    }

    public function test_override_admin_menang_mutlak(): void
    {
        // Override 999 MB.
        UserPlanOverride::create([
            'user_id' => $this->dosen->id,
            'storage_limit_mb' => 999,
        ]);

        // Langganan direktori aktif 10 GB — override tetap menang.
        DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodi->id,
            'plan_id' => $this->planInstitusi->id,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        $this->assertSame(999, Feature::storageLimitMb($this->dosen));
    }

    public function test_institusi_aktif_tanpa_addon_kuota_institusi(): void
    {
        DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodi->id,
            'plan_id' => $this->planInstitusi->id,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        $this->assertSame(10240, Feature::storageLimitMb($this->dosen));
    }

    public function test_institusi_aktif_plus_addon_dijumlah(): void
    {
        DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodi->id,
            'plan_id' => $this->planInstitusi->id,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        UserStorageAddon::create([
            'user_id' => $this->dosen->id,
            'storage_mb' => 2048,
            'status' => UserStorageAddon::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        $this->assertSame(10240 + 2048, Feature::storageLimitMb($this->dosen));
    }

    public function test_tanpa_institusi_plan_individual_plus_addon_dijumlah(): void
    {
        // Mode individual (tanpa direktori).
        config(['app.mode' => 'individual']);

        // Plan individual aktif (donasi 10 GB).
        \App\Models\Subscription::create([
            'user_id' => $this->dosen->id,
            'plan_id' => $this->planInstitusi->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        UserStorageAddon::create([
            'user_id' => $this->dosen->id,
            'storage_mb' => 1024,
            'status' => UserStorageAddon::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        $this->assertSame(10240 + 1024, Feature::storageLimitMb($this->dosen));
    }

    public function test_institusi_expired_fallback_ke_plan_individual(): void
    {
        // Langganan direktori sudah expired.
        DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodi->id,
            'plan_id' => $this->planInstitusi->id,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
        ]);

        // Plan individual aktif (free 5 GB).
        \App\Models\Subscription::create([
            'user_id' => $this->dosen->id,
            'plan_id' => $this->planFree->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        $this->assertSame(5120, Feature::storageLimitMb($this->dosen));
    }

    public function test_institusi_expired_fallback_ke_plan_free_default(): void
    {
        // Langganan direktori sudah expired.
        DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodi->id,
            'plan_id' => $this->planInstitusi->id,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
        ]);

        // Tidak ada plan individual aktif -> fallback free (5 GB).
        $this->assertSame(5120, Feature::storageLimitMb($this->dosen));
    }

    public function test_dua_afiliasi_cabang_berbeda_kuota_dijumlah(): void
    {
        // Buat universitas kedua + fakultas + prodi (cabang berbeda).
        $univ2 = $this->service->findOrCreateUniversity('Universitas Kedua');
        $faculty2 = $this->service->findOrCreateFaculty($univ2, 'Fakultas Ekonomi');
        $dept2 = $this->service->findOrCreateDepartment($faculty2, 'Departemen Manajemen');
        $prodi2 = $this->service->findOrCreateStudyProgram($dept2, 'S1 Manajemen');

        // Dosen terafiliasi ke kedua cabang.
        $this->service->attachUserToUniversity($this->dosen, $univ2, $faculty2, $dept2, $prodi2, false);

        // Langganan aktif di kedua prodi (cabang berbeda).
        DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodi->id,
            'plan_id' => $this->planInstitusi->id,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => null,
        ]);
        DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_STUDY_PROGRAM,
            'scope_id' => $prodi2->id,
            'plan_id' => $this->planInstitusi->id,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        // Kuota dijumlah: 10 GB + 10 GB = 20 GB.
        $this->assertSame(20480, Feature::storageLimitMb($this->dosen));
    }

    public function test_dua_afiliasi_resolve_ke_subscription_sama_dedup(): void
    {
        // Dua afiliasi ke prodi berbeda dalam 1 universitas yang sama.
        $prodi2 = $this->service->findOrCreateStudyProgram($this->dept, 'S1 Sistem Informasi');

        $this->service->attachUserToUniversity($this->dosen, $this->univ, $this->faculty, $this->dept, $prodi2, false);

        // Langganan aktif di level universitas (meng-cover kedua prodi).
        DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_UNIVERSITY,
            'scope_id' => $this->univ->id,
            'plan_id' => $this->planInstitusi->id,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        // Kedua afiliasi resolve ke subscription universitas yang sama -> dedup, 10 GB.
        $this->assertSame(10240, Feature::storageLimitMb($this->dosen));
    }

    public function test_validasi_no_overlap_ke_atas_ditolak(): void
    {
        // Langganan aktif di fakultas.
        DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_FACULTY,
            'scope_id' => $this->faculty->id,
            'plan_id' => $this->planInstitusi->id,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        // Assign ke prodi (turunan fakultas) -> ditolak.
        $error = Feature::validateDirectorySubscriptionNoOverlap(
            DirectorySubscription::SCOPE_STUDY_PROGRAM,
            $this->prodi->id
        );

        $this->assertNotNull($error);
        $this->assertStringContainsString('Node induk sudah berlangganan', $error);
    }

    public function test_validasi_no_overlap_ke_bawah_ditolak(): void
    {
        // Langganan aktif di prodi.
        DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodi->id,
            'plan_id' => $this->planInstitusi->id,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        // Assign ke fakultas (leluhur prodi) -> ditolak.
        $error = Feature::validateDirectorySubscriptionNoOverlap(
            DirectorySubscription::SCOPE_FACULTY,
            $this->faculty->id
        );

        $this->assertNotNull($error);
        $this->assertStringContainsString('Ada turunan yang sudah berlangganan sendiri', $error);
    }

    public function test_validasi_no_overlap_aman_saat_tidak_ada_konflik(): void
    {
        // Tidak ada langganan aktif -> valid.
        $error = Feature::validateDirectorySubscriptionNoOverlap(
            DirectorySubscription::SCOPE_STUDY_PROGRAM,
            $this->prodi->id
        );

        $this->assertNull($error);
    }

    public function test_directory_subscription_active_walk_up(): void
    {
        // Langganan aktif di fakultas.
        DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_FACULTY,
            'scope_id' => $this->faculty->id,
            'plan_id' => $this->planInstitusi->id,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        // Prodi ter-cover via leluhur (fakultas).
        $this->assertTrue(Feature::directorySubscriptionActive(
            DirectorySubscription::SCOPE_STUDY_PROGRAM,
            $this->prodi->id
        ));

        // Universitas TIDAK ter-cover (bukan leluhur prodi).
        $this->assertFalse(Feature::directorySubscriptionActive(
            DirectorySubscription::SCOPE_UNIVERSITY,
            $this->univ->id
        ));
    }

    public function test_directory_subscription_active_false_di_mode_individual(): void
    {
        config(['app.mode' => 'individual']);

        // Langganan aktif di prodi.
        DirectorySubscription::create([
            'scope_type' => DirectorySubscription::SCOPE_STUDY_PROGRAM,
            'scope_id' => $this->prodi->id,
            'plan_id' => $this->planInstitusi->id,
            'status' => DirectorySubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        // Mode individual -> selalu false.
        $this->assertFalse(Feature::directorySubscriptionActive(
            DirectorySubscription::SCOPE_STUDY_PROGRAM,
            $this->prodi->id
        ));
    }
}