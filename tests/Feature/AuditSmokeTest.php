<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Conversation;
use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\Message;
use App\Models\Sidang;
use App\Models\User;
use App\Models\WorkspaceFile;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * One-off audit sweep: hits every GET route as the appropriate role and
 * reports any 500s. Not meant to be a permanent regression suite.
 */
class AuditSmokeTest extends TestCase
{
    protected User $admin;

    protected User $dosen;

    protected User $dosen2;

    protected User $mhs;

    protected MahasiswaTa $ta;

    protected LogbookEntry $entryDraft;

    protected LogbookEntry $entrySubmitted;

    protected LogbookEntry $entryRevisi;

    protected WorkspaceFile $file;

    protected Announcement $announcement;

    protected Sidang $sidang;

    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        // Nonaktifkan CSRF token di test (POST route tanpa token CSRF).
        $this->withoutMiddleware(ValidateCsrfToken::class);

        foreach (['admin', 'dosen', 'mahasiswa'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->admin = User::firstOrCreate(['email' => 'audit-admin@test.com'], ['name' => 'Audit Admin', 'password' => bcrypt('password')]);
        if (!$this->admin->hasRole('admin')) $this->admin->assignRole('admin');

        $this->dosen = User::firstOrCreate(['email' => 'audit-dosen@test.com'], ['name' => 'Audit Dosen', 'password' => bcrypt('password')]);
        if (!$this->dosen->hasRole('dosen')) $this->dosen->assignRole('dosen');
        // Pastikan link jadwal bimbingan selalu tersedia agar card hyperlink ter-render di audit test.
        $this->dosen->forceFill([
            'jadwal_bimbingan_url' => 'https://cal.com/audit-dosen',
            'bimbingan_via_whatsapp' => true,
            'whatsapp' => '6281234567890',
        ])->save();

        $this->dosen2 = User::firstOrCreate(['email' => 'audit-dosen2@test.com'], ['name' => 'Audit Dosen 2', 'password' => bcrypt('password')]);
        if (!$this->dosen2->hasRole('dosen')) $this->dosen2->assignRole('dosen');

        $this->mhs = User::firstOrCreate(['email' => 'audit-mhs@test.com'], ['name' => 'Audit Mahasiswa', 'password' => bcrypt('password'), 'nim' => 'A001']);
        if (!$this->mhs->hasRole('mahasiswa')) $this->mhs->assignRole('mahasiswa');

        $this->ta = MahasiswaTa::firstOrCreate(
            ['user_id' => $this->mhs->id],
            ['pembimbing_1_id' => $this->dosen->id, 'pembimbing_2_id' => $this->dosen2->id, 'judul_ta' => 'Audit TA', 'status_ta' => 'aktif']
        );

        $this->entryDraft = LogbookEntry::firstOrCreate(
            ['mahasiswa_ta_id' => $this->ta->id, 'jenis' => 'logbook', 'sesi_ke' => 1],
            ['dosen_id' => $this->dosen->id, 'tanggal_bimbingan' => now(), 'topik' => 'topik', 'progres_kendala' => 'progres', 'status' => 'draft']
        );

        $this->entrySubmitted = LogbookEntry::firstOrCreate(
            ['mahasiswa_ta_id' => $this->ta->id, 'jenis' => 'logbook', 'sesi_ke' => 2],
            ['dosen_id' => $this->dosen->id, 'tanggal_bimbingan' => now(), 'topik' => 'topik 2', 'progres_kendala' => 'progres 2', 'status' => 'submitted', 'submitted_at' => now()]
        );

        $this->entryRevisi = LogbookEntry::firstOrCreate(
            ['mahasiswa_ta_id' => $this->ta->id, 'jenis' => 'revisi', 'sesi_ke' => null],
            ['tanggal_pengiriman' => now(), 'progres_kendala' => 'revisi progres', 'status' => 'draft']
        );

        $this->file = WorkspaceFile::firstOrCreate(
            ['mahasiswa_ta_id' => $this->ta->id, 'original_name' => 'audit-file.pdf'],
            ['uploaded_by' => $this->mhs->id, 'path' => 'workspace/audit-file.pdf', 'mime_type' => 'application/pdf', 'size' => 100]
        );

        $this->announcement = Announcement::firstOrCreate(
            ['title' => 'Audit Announcement'],
            ['body' => 'body', 'sender_id' => $this->admin->id]
        );

        $this->sidang = Sidang::firstOrCreate(
            ['mahasiswa_ta_id' => $this->ta->id],
            ['mahasiswa_name' => $this->mhs->name, 'penguji_id' => $this->dosen2->id, 'tanggal' => now()->addDays(7), 'jenis' => Sidang::JENIS_SIDANG]
        );

        $this->conversation = Conversation::firstOrCreate([
            'user_one_id' => min($this->mhs->id, $this->dosen->id),
            'user_two_id' => max($this->mhs->id, $this->dosen->id),
        ]);
        Message::firstOrCreate(
            ['conversation_id' => $this->conversation->id, 'body' => 'halo audit'],
            ['sender_id' => $this->mhs->id]
        );
    }

    public function test_audit_all_get_routes(): void
    {
        $routes = [
            // [method-not-used, name-or-uri, actor, params]
            ['dashboard', $this->mhs, []],
            ['dashboard', $this->dosen, []],
            ['dashboard', $this->admin, []],
            ['dashboard.dosen.mahasiswa-list', $this->dosen, []],
            ['dashboard.dosen.sidang-list', $this->dosen, []],
            ['admin.entries', $this->admin, []],
            ['admin.institution', $this->admin, []],
            ['admin.sidangs', $this->admin, []],
            ['admin.tas', $this->admin, []],
            ['admin.users', $this->admin, []],
            ['approval.index', $this->dosen, []],
            ['chat.index', $this->mhs, []],
            ['chat.show', $this->mhs, ['conversation' => $this->conversation]],
            ['dosen-sidang.index', $this->dosen, []],
            ['logbook.index', $this->mhs, []],
            ['logbook.index', $this->dosen, []],
            ['logbook.create', $this->mhs, []],
            ['logbook.create-revisi', $this->mhs, []],
            ['logbook.show', $this->mhs, ['logbook' => $this->entryDraft]],
            ['logbook.show', $this->dosen, ['logbook' => $this->entrySubmitted]],
            ['logbook.edit', $this->mhs, ['logbook' => $this->entryDraft]],
            ['logbook.pdf-viewer', $this->mhs, ['logbook' => $this->entryDraft]],
            ['logbook.pdf.comments', $this->mhs, ['logbook' => $this->entryDraft]],
            ['mahasiswa-ta.show', $this->dosen, ['mahasiswaTa' => $this->ta]],
            ['notifications.index', $this->mhs, []],
            ['notifications.dropdown', $this->mhs, []],
            ['announcements.index', $this->mhs, []],
            ['announcements.create', $this->admin, []],
            ['profile.index', $this->mhs, []],
            ['profile.affiliation', $this->dosen, []],
            ['affiliation-approval.index', $this->admin, []],
            ['profile.show', $this->dosen, ['user' => $this->mhs]],
            ['scheduling.index', $this->mhs, []],
            ['scheduling.index', $this->dosen, []],
            ['scheduling.index', $this->admin, []],
            ['quick-review.index', $this->dosen, []],
            ['global-search', $this->mhs, ['q' => 'audit']],
            ['workspace.index', $this->mhs, ['mahasiswaTa' => $this->ta]],
        ];

        $failures = [];

        foreach ($routes as [$name, $actor, $params]) {
            try {
                $url = route($name, $params);
            } catch (\Throwable $e) {
                $failures[] = "[ROUTE-GEN] {$name}: ".$e->getMessage();
                continue;
            }

            $response = $this->actingAs($actor)->get($url);

            if ($response->status() >= 500) {
                $failures[] = "[{$response->status()}] {$name} ({$url}) as {$actor->email}";
            }
        }

        if ($failures) {
            $this->fail("Audit found ".count($failures)." failing route(s):\n".implode("\n", $failures));
        }

        $this->assertTrue(true);
    }
}
