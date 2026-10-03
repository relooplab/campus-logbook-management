<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\User;
use App\Notifications\ActivityNotification;
use App\Services\AchievementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AchievementServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private MahasiswaTa $ta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create();
        $this->ta = MahasiswaTa::create([
            'user_id' => $this->student->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
            'target_sesi' => 7,
        ]);
        $this->artisan('achievements:sync')->assertSuccessful();
    }

    public function test_sync_command_creates_and_updates_the_complete_catalog(): void
    {
        $this->assertSame(8, Achievement::count());
        $this->assertSame(
            '2 logbook dikirim < 2 hari setelah bimbingan',
            Achievement::where('code', Achievement::TEPAT_WAKTU)->value('description'),
        );
    }

    public function test_early_progress_badges_use_attainable_historical_events(): void
    {
        Notification::fake();
        $this->logbook(1, LogbookEntry::STATUS_APPROVED, now()->subDays(7), now()->subDays(6));
        $this->logbook(2, LogbookEntry::STATUS_APPROVED, now()->subDay(), now());

        app(AchievementService::class)->evaluateForUser($this->student);

        $codes = $this->student->achievements()->pluck('code');
        $this->assertTrue($codes->contains(Achievement::LANGAH_PERTAMA));
        $this->assertTrue($codes->contains(Achievement::KONSISTEN));
        $this->assertTrue($codes->contains(Achievement::ZERO_REVISI));
        $this->assertTrue($codes->contains(Achievement::TEPAT_WAKTU));

        // Satu notifikasi rangkuman saja (in-app + email) untuk 4 badge sekaligus.
        Notification::assertSentTo($this->student, ActivityNotification::class, fn ($notification) => str_contains($notification->message, '4 achievement baru')
            && $notification->subject === 'Achievement Baru Terkunci');
    }

    public function test_no_notification_when_no_new_badge_unlocked(): void
    {
        Notification::fake();
        app(AchievementService::class)->evaluateForUser($this->student);

        Notification::assertNothingSent();
        $this->assertSame(0, $this->student->achievements()->count());
    }

    public function test_konsisten_stays_locked_when_sessions_farther_than_fourteen_days(): void
    {
        Notification::fake();
        $this->logbook(1, LogbookEntry::STATUS_APPROVED, now()->subDays(30), now()->subDays(29));
        $this->logbook(2, LogbookEntry::STATUS_APPROVED, now()->subDays(1), now()->subDay());

        app(AchievementService::class)->evaluateForUser($this->student);

        $this->assertFalse($this->student->achievements()->where('code', Achievement::KONSISTEN)->exists());
    }

    public function test_comeback_uses_child_revision_submit_time_after_parent_feedback(): void
    {
        $parent = $this->logbook(1, LogbookEntry::STATUS_REVISI, now()->subDays(4), now()->subDays(3));
        $parent->update(['feedback_dosen' => 'Lengkapi pembahasan hasil.', 'reviewed_at' => now()->subDays(2)]);
        LogbookEntry::create([
            'mahasiswa_ta_id' => $this->ta->id,
            'parent_entry_id' => $parent->id,
            'jenis' => LogbookEntry::JENIS_REVISI,
            'status' => LogbookEntry::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'tanggal_pengiriman' => today(),
        ]);

        app(AchievementService::class)->evaluateForUser($this->student);

        $this->assertTrue($this->student->achievements()->where('code', Achievement::COMEBACK)->exists());
    }

    public function test_comeback_stays_locked_when_child_submitted_after_three_days(): void
    {
        $parent = $this->logbook(1, LogbookEntry::STATUS_REVISI, now()->subDays(9), now()->subDays(8));
        $parent->update(['feedback_dosen' => 'Lengkapi pembahasan hasil.', 'reviewed_at' => now()->subDays(7)]);
        LogbookEntry::create([
            'mahasiswa_ta_id' => $this->ta->id,
            'parent_entry_id' => $parent->id,
            'jenis' => LogbookEntry::JENIS_REVISI,
            'status' => LogbookEntry::STATUS_SUBMITTED,
            'submitted_at' => now()->subDays(2),
            'tanggal_pengiriman' => today()->subDays(2),
        ]);

        app(AchievementService::class)->evaluateForUser($this->student);

        $this->assertFalse($this->student->achievements()->where('code', Achievement::COMEBACK)->exists());
    }

    private function logbook(int $session, string $status, $guidanceDate, $submittedAt): LogbookEntry
    {
        return LogbookEntry::create([
            'mahasiswa_ta_id' => $this->ta->id,
            'jenis' => LogbookEntry::JENIS_LOGBOOK,
            'sesi_ke' => $session,
            'status' => $status,
            'tanggal_bimbingan' => $guidanceDate,
            'submitted_at' => $submittedAt,
        ]);
    }
}
