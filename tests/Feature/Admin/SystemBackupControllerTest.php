<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\SystemBackupController;
use App\Models\User;
use App\Services\Backup\BackupException;
use App\Services\Backup\RestoreValidationException;
use App\Services\SystemBackupService;
use App\Services\SystemRestoreService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Test controller admin/system/backup dengan service DI-MOCK — mysqldump
 * sungguhan tidak dijalankan di test suite CI.
 */
class SystemBackupControllerTest extends TestCase
{
    use DatabaseTransactions;

    private User $systemAdmin;

    private User $regularAdmin;

    private User $dosen;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['system_admin', 'admin', 'dosen', 'mahasiswa'] as $role) {
            Role::findOrCreate($role);
        }

        $this->systemAdmin = User::firstOrCreate(
            ['email' => 'sysbackup-admin@test.com'],
            ['name' => 'System Admin', 'password' => bcrypt('password')]
        );
        if (!$this->systemAdmin->hasRole('system_admin')) {
            $this->systemAdmin->assignRole('system_admin');
        }

        $this->regularAdmin = User::firstOrCreate(
            ['email' => 'sysbackup-regular@test.com'],
            ['name' => 'Regular Admin', 'password' => bcrypt('password')]
        );
        if (!$this->regularAdmin->hasRole('admin')) {
            $this->regularAdmin->assignRole('admin');
        }

        $this->dosen = User::firstOrCreate(
            ['email' => 'sysbackup-dosen@test.com'],
            ['name' => 'Dosen', 'password' => bcrypt('password')]
        );
        if (!$this->dosen->hasRole('dosen')) {
            $this->dosen->assignRole('dosen');
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('framework/restore-tmp'));

        parent::tearDown();
    }

    // ---------------------------------------------------------- middleware

    public function test_non_system_admin_gets_403_on_all_routes(): void
    {
        foreach ([$this->regularAdmin, $this->dosen] as $actor) {
            $this->actingAs($actor)->get(route('admin.system.backup'))->assertForbidden();
            $this->actingAs($actor)->post(route('admin.system.backup.store'))->assertForbidden();
            $this->actingAs($actor)->post(route('admin.system.backup.restore'))->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.system.backup'))->assertRedirect(route('login'));
    }

    // ---------------------------------------------------------------- index

    public function test_system_admin_can_view_backup_page(): void
    {
        $response = $this->actingAs($this->systemAdmin)->get(route('admin.system.backup'));

        $response->assertOk();
        $response->assertViewHas('modules');
        $response->assertViewHas('institutions');
        $response->assertSee('Backup Sekarang');
        $response->assertSee('Restore dari Backup');
    }

    // ---------------------------------------------------------------- store

    public function test_store_streams_zip_when_backup_succeeds(): void
    {
        $fakeZip = tempnam(sys_get_temp_dir(), 'test_backup_').'.zip';
        file_put_contents($fakeZip, 'PK-fake-zip-content');

        $this->mock(SystemBackupService::class, function ($mock) use ($fakeZip) {
            $mock->shouldReceive('create')->once()->with(null, null, false)->andReturn($fakeZip);
        });

        $response = $this->actingAs($this->systemAdmin)->post(route('admin.system.backup.store'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');

        if (file_exists($fakeZip)) {
            @unlink($fakeZip);
        }
    }

    public function test_store_passes_selected_modules_to_service(): void
    {
        $fakeZip = tempnam(sys_get_temp_dir(), 'test_backup_').'.zip';
        file_put_contents($fakeZip, 'PK-fake-zip-content');

        $this->mock(SystemBackupService::class, function ($mock) use ($fakeZip) {
            $mock->shouldReceive('create')->once()->with(['users', 'mahasiswa_ta'], null, false)->andReturn($fakeZip);
        });

        $response = $this->actingAs($this->systemAdmin)->post(route('admin.system.backup.store'), [
            'modules' => ['users', 'mahasiswa_ta'],
        ]);

        $response->assertOk();

        if (file_exists($fakeZip)) {
            @unlink($fakeZip);
        }
    }

    public function test_store_passes_selected_institutions_and_include_individual_to_service(): void
    {
        $fakeZip = tempnam(sys_get_temp_dir(), 'test_backup_').'.zip';
        file_put_contents($fakeZip, 'PK-fake-zip-content');

        $this->mock(SystemBackupService::class, function ($mock) use ($fakeZip) {
            $mock->shouldReceive('create')->once()->with(null, [3, 7], true)->andReturn($fakeZip);
        });

        $response = $this->actingAs($this->systemAdmin)->post(route('admin.system.backup.store'), [
            'institutions' => [3, 7],
            'include_individual' => '1',
        ]);

        $response->assertOk();

        if (file_exists($fakeZip)) {
            @unlink($fakeZip);
        }
    }

    public function test_store_flashes_error_when_backup_fails(): void
    {
        $this->mock(SystemBackupService::class, function ($mock) {
            $mock->shouldReceive('create')->once()->andThrow(new BackupException('Disk tidak cukup.'));
        });

        $response = $this->actingAs($this->systemAdmin)->post(route('admin.system.backup.store'));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Disk tidak cukup.', session('error'));
    }

    // -------------------------------------------------------------- restore

    public function test_restore_rejects_wrong_confirmation_phrase(): void
    {
        $file = UploadedFile::fake()->create('backup.zip', 10, 'application/zip');

        $response = $this->actingAs($this->systemAdmin)->post(route('admin.system.backup.restore'), [
            'backup_file' => $file,
            'confirmation' => 'salah ketik',
        ]);

        $response->assertSessionHasErrors('confirmation');
    }

    public function test_restore_rejects_non_zip_file(): void
    {
        $file = UploadedFile::fake()->create('backup.txt', 10, 'text/plain');

        $response = $this->actingAs($this->systemAdmin)->post(route('admin.system.backup.restore'), [
            'backup_file' => $file,
            'confirmation' => SystemBackupController::CONFIRMATION_PHRASE,
        ]);

        $response->assertSessionHasErrors('backup_file');
    }

    public function test_restore_succeeds_with_correct_confirmation(): void
    {
        $file = UploadedFile::fake()->create('backup.zip', 10, 'application/zip');

        $this->mock(SystemRestoreService::class, function ($mock) {
            $mock->shouldReceive('restore')->once()->andReturn(['safety_backup_path' => '/tmp/safety-backup.zip']);
        });

        $response = $this->actingAs($this->systemAdmin)->post(route('admin.system.backup.restore'), [
            'backup_file' => $file,
            'confirmation' => SystemBackupController::CONFIRMATION_PHRASE,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertStringContainsString('/tmp/safety-backup.zip', session('success'));
    }

    public function test_restore_flashes_error_when_zip_invalid(): void
    {
        $file = UploadedFile::fake()->create('backup.zip', 10, 'application/zip');

        $this->mock(SystemRestoreService::class, function ($mock) {
            $mock->shouldReceive('restore')->once()->andThrow(
                new RestoreValidationException('ZIP tidak berisi database.sql.')
            );
        });

        $response = $this->actingAs($this->systemAdmin)->post(route('admin.system.backup.restore'), [
            'backup_file' => $file,
            'confirmation' => SystemBackupController::CONFIRMATION_PHRASE,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('ZIP tidak berisi database.sql.', session('error'));
    }
}
