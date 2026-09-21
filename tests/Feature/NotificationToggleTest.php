<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\User;
use App\Notifications\ReminderNotification;
use App\Notifications\WeeklyDigestNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Toggle notifikasi berkala per institusi (Opsi A):
 * - weekly_digest_enabled OFF  => ta:weekly-digest tidak mengirim ke user
 *   institusi tersebut (institusi lain tetap menerima).
 * - daily_reminder_enabled OFF  => logbook:send-reminders & ta:notify-inactive
 *   tidak mengirim ke user institusi tersebut.
 * - Default (kolom null / baris lama) => tetap kirim (backward compatible).
 */
class NotificationToggleTest extends TestCase
{
    use DatabaseTransactions;

    private Institution $institutionA;

    private Institution $institutionB;

    private User $dosenA;

    private User $dosenB;

    private User $mahasiswaA;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['mahasiswa', 'dosen'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->institutionA = Institution::create([
            'app_name' => 'Institusi A',
            'institution_name' => 'Universitas A',
            'email' => 'a@test.com',
        ]);
        $this->institutionB = Institution::create([
            'app_name' => 'Institusi B',
            'institution_name' => 'Universitas B',
            'email' => 'b@test.com',
        ]);

        $this->dosenA = $this->makeUser('Dosen Toggle A', 'dosen', $this->institutionA->id);
        $this->dosenB = $this->makeUser('Dosen Toggle B', 'dosen', $this->institutionB->id);
        $this->mahasiswaA = $this->makeUser('Mhs Toggle A', 'mahasiswa', $this->institutionA->id);

        // TA milik institusi A agar send-reminders punya kandidat.
        MahasiswaTa::create([
            'institution_id' => $this->institutionA->id,
            'user_id' => $this->mahasiswaA->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $this->dosenA->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
            'fase' => 'proposal',
        ]);
    }

    private function makeUser(string $name, string $role, int $institutionId): User
    {
        $user = User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid().'@t.test',
            'password' => bcrypt('password'),
            'institution_id' => $institutionId,
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_weekly_digest_off_melewati_institusi_tersebut_saja(): void
    {
        Notification::fake();

        $this->institutionA->update(['weekly_digest_enabled' => false]);

        $this->artisan('ta:weekly-digest')->assertSuccessful();

        Notification::assertNotSentTo($this->dosenA, WeeklyDigestNotification::class);
        Notification::assertNotSentTo($this->mahasiswaA, WeeklyDigestNotification::class);
        Notification::assertSentTo($this->dosenB, WeeklyDigestNotification::class);
    }

    public function test_weekly_digest_on_mengirim_ke_semua(): void
    {
        Notification::fake();

        $this->artisan('ta:weekly-digest')->assertSuccessful();

        Notification::assertSentTo($this->dosenA, WeeklyDigestNotification::class);
        Notification::assertSentTo($this->dosenB, WeeklyDigestNotification::class);
    }

    public function test_daily_reminder_off_melewati_institusi_tersebut_saja(): void
    {
        Notification::fake();

        $this->institutionA->update(['daily_reminder_enabled' => false]);

        // Mahasiswa A tanpa entri => kandidat reminder + inaktivitas.
        $this->artisan('logbook:send-reminders')->assertSuccessful();
        $this->artisan('ta:notify-inactive')->assertSuccessful();

        Notification::assertNotSentTo($this->mahasiswaA, ReminderNotification::class);
        Notification::assertNotSentTo(
            $this->mahasiswaA,
            \App\Notifications\InactivityReminderNotification::class
        );
    }

    public function test_daily_reminder_on_mengirim_pengingat(): void
    {
        Notification::fake();

        $this->artisan('logbook:send-reminders')->assertSuccessful();

        Notification::assertSentTo($this->mahasiswaA, ReminderNotification::class);
    }

    public function test_helper_default_true_untuk_baris_lama_tanpa_nilai(): void
    {
        $institution = new Institution;

        $this->assertTrue($institution->isWeeklyDigestEnabled());
        $this->assertTrue($institution->isDailyReminderEnabled());
    }

    public function test_antrean_review_dosen_off_tidak_mengirim(): void
    {
        Notification::fake();

        // Entri submitted lama milik TA institusi A => kandidat antrean dosen A.
        $ta = MahasiswaTa::where('user_id', $this->mahasiswaA->id)->firstOrFail();
        LogbookEntry::create([
            'mahasiswa_ta_id' => $ta->id,
            'jenis' => LogbookEntry::JENIS_LOGBOOK,
            'sesi_ke' => 1,
            'dosen_id' => $this->dosenA->id,
            'topik' => 'Antrean lama',
            'status' => LogbookEntry::STATUS_SUBMITTED,
            'submitted_at' => now()->subDays(10),
        ]);

        $this->institutionA->update(['daily_reminder_enabled' => false]);

        $this->artisan('logbook:send-reminders', ['--queue-days' => 3])->assertSuccessful();

        Notification::assertNotSentTo($this->dosenA, ReminderNotification::class);
    }
}
