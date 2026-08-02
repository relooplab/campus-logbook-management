<?php

namespace Tests\Feature;

use App\Models\ActionItem;
use App\Models\PdfComment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;

/**
 * One-off audit sweep: exercises every write (POST/PUT/PATCH/DELETE) action
 * as the appropriate role and reports any 500s. Wrapped in a DB transaction
 * so nothing persists. Not meant to be a permanent regression suite.
 */
class AuditWriteActionsTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    public function test_audit_all_write_actions(): void
    {
        $pdf = fn () => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $failures = [];

        $check = function (string $label, \Closure $call) use (&$failures) {
            try {
                $response = $call();
                $status = $response->status();
                if ($status >= 500) {
                    $failures[] = "[{$status}] {$label}";
                }
            } catch (\Throwable $e) {
                $failures[] = "[EXCEPTION] {$label}: ".get_class($e).': '.$e->getMessage();
            }
        };

        // --- Logbook (mahasiswa) ---
        $check('logbook.store (draft)', fn () => $this->actingAs($this->mhs)->post(route('logbook.store'), [
            'tanggal_bimbingan' => now()->toDateString(),
            'topik' => 'Audit topik',
            'progres_kendala' => 'Audit progres',
        ]));

        $check('logbook.store (submit)', fn () => $this->actingAs($this->mhs)->post(route('logbook.store'), [
            'tanggal_bimbingan' => now()->toDateString(),
            'topik' => 'Audit topik submit',
            'progres_kendala' => 'Audit progres submit',
            'submit' => '1',
        ]));

        $check('logbook.store-revisi', fn () => $this->actingAs($this->mhs)->post(route('logbook.store-revisi'), [
            'tanggal_pengiriman' => now()->toDateString(),
            'progres_kendala' => 'Audit revisi progres',
            'lampiran' => $pdf(),
            'catatan_perbaikan' => $pdf(),
        ]));

        $check('logbook.update', fn () => $this->actingAs($this->mhs)->put(route('logbook.update', $this->entryDraft), [
            'tanggal_bimbingan' => now()->toDateString(),
            'topik' => 'Audit topik updated',
            'progres_kendala' => 'Audit progres updated',
        ]));

        $check('logbook.submit', fn () => $this->actingAs($this->mhs)->post(route('logbook.submit', $this->entryDraft)));

        $check('logbook.pdf.store-comment', fn () => $this->actingAs($this->dosen)->postJson(route('logbook.pdf.store-comment', $this->entrySubmitted), [
            'file_type' => 'draft',
            'comment' => 'Audit comment',
            'page_number' => 1,
            'pos_x' => 0.1,
            'pos_y' => 0.1,
            'x2' => 0.2,
            'y2' => 0.2,
        ]));

        $comment = PdfComment::where('logbook_entry_id', $this->entrySubmitted->id)->latest()->first();
        if ($comment) {
            $check('pdf-comments.resolve', fn () => $this->actingAs($this->dosen)->postJson(route('pdf-comments.resolve', $comment)));
            $check('pdf-comments.destroy', fn () => $this->actingAs($this->dosen)->deleteJson(route('pdf-comments.destroy', $comment)));
        } else {
            $failures[] = '[SETUP] pdf comment was not created by storeComment';
        }

        $check('logbook.approve', fn () => $this->actingAs($this->dosen)->post(route('logbook.approve', $this->entrySubmitted)));

        $check('logbook.request-revisi', fn () => $this->actingAs($this->dosen)->post(route('logbook.request-revisi', $this->entrySubmitted), [
            'feedback_dosen' => 'Tolong perbaiki bagian ini.',
        ]));

        // --- Action items (uses entryRevisi: entryDraft gets submitted above) ---
        $check('action-items.store', fn () => $this->actingAs($this->mhs)->postJson(route('action-items.store', $this->entryRevisi), [
            'text' => 'Audit action item',
        ]));
        $item = ActionItem::where('logbook_entry_id', $this->entryRevisi->id)->latest()->first();
        if ($item) {
            $check('action-items.toggle', fn () => $this->actingAs($this->mhs)->postJson(route('action-items.toggle', [$this->entryRevisi, $item])));
            $check('action-items.destroy', fn () => $this->actingAs($this->mhs)->deleteJson(route('action-items.destroy', [$this->entryRevisi, $item])));
        } else {
            $failures[] = '[SETUP] action item was not created';
        }

        // --- Quick review (dosen) ---
        $check('quick-review.approve-next', fn () => $this->actingAs($this->dosen)->post(route('quick-review.approve-next', $this->entrySubmitted)));

        // --- Chat ---
        $check('chat.store', fn () => $this->actingAs($this->mhs)->post(route('chat.store', $this->conversation), [
            'body' => 'Pesan audit',
        ]));
        $msg = \App\Models\Message::where('conversation_id', $this->conversation->id)->latest()->first();
        if ($msg) {
            $check('chat.update', fn () => $this->actingAs($msg->sender_id === $this->mhs->id ? $this->mhs : $this->dosen)
                ->put(route('chat.update', [$this->conversation, $msg]), ['body' => 'Pesan audit diedit']));
        }
        $check('chat.attach-options', fn () => $this->actingAs($this->mhs)->postJson(route('chat.attach-options', $this->conversation)));

        // --- Workspace ---
        $check('workspace.store', fn () => $this->actingAs($this->mhs)->post(route('workspace.store', $this->ta), [
            'files' => [$pdf()],
            'bab' => 'bab1',
        ]));
        $check('workspace.update', fn () => $this->actingAs($this->mhs)->patch(route('workspace.update', $this->file), [
            'description' => 'Audit description',
        ]));

        // --- Announcements ---
        $check('announcements.store', fn () => $this->actingAs($this->dosen)->post(route('announcements.store'), [
            'title' => 'Audit pengumuman',
            'body' => 'Isi pengumuman audit',
            'target_mode' => 'all',
        ]));
        $check('announcements.read', fn () => $this->actingAs($this->mhs)->post(route('announcements.read', $this->announcement)));
        $check('announcements.remind', fn () => $this->actingAs($this->admin)->post(route('announcements.remind', $this->announcement)));

        // --- MahasiswaTa fase ---
        $check('mahasiswa-ta.fase', fn () => $this->actingAs($this->dosen)->post(route('mahasiswa-ta.fase', $this->ta), [
            'fase' => array_key_first(\App\Models\MahasiswaTa::FASES),
        ]));

        // --- Approval (registrasi mahasiswa) ---
        $pending = \App\Models\User::firstOrCreate(
            ['email' => 'audit-pending@test.com'],
            ['name' => 'Audit Pending', 'password' => bcrypt('password'), 'registration_status' => 'pending']
        );
        if (!$pending->hasRole('mahasiswa')) $pending->assignRole('mahasiswa');
        if ($pending->registration_status !== 'pending') $pending->update(['registration_status' => 'pending']);
        $check('approval.approve', fn () => $this->actingAs($this->dosen)->post(route('approval.approve', $pending), [
            'judul_ta' => 'Audit TA pending',
            'role_dosen' => 'pembimbing_1',
        ]));

        $pending2 = \App\Models\User::firstOrCreate(
            ['email' => 'audit-pending2@test.com'],
            ['name' => 'Audit Pending 2', 'password' => bcrypt('password'), 'registration_status' => 'pending']
        );
        if (!$pending2->hasRole('mahasiswa')) $pending2->assignRole('mahasiswa');
        if ($pending2->registration_status !== 'pending') $pending2->update(['registration_status' => 'pending']);
        $check('approval.reject', fn () => $this->actingAs($this->dosen)->post(route('approval.reject', $pending2)));

        // --- Dosen sidang ---
        $check('dosen-sidang.store', fn () => $this->actingAs($this->dosen)->post(route('dosen-sidang.store'), [
            'mahasiswa_ta_id' => $this->ta->id,
            'jenis' => 'seminar_proposal',
            'tanggal' => now()->addDays(3)->toDateString(),
        ]));

        // --- Notifications ---
        $check('notifications.mark-all-read', fn () => $this->actingAs($this->mhs)->post(route('notifications.mark-all-read')));

        // --- Feedback templates ---
        $check('feedback-templates.store', fn () => $this->actingAs($this->dosen)->postJson(route('feedback-templates.store'), [
            'title' => 'Audit template',
            'body' => 'Isi template audit',
        ]));

        // --- Profile ---
        $check('profile.update', fn () => $this->actingAs($this->mhs)->put(route('profile.update'), [
            'name' => $this->mhs->name,
        ]));

        // --- Admin ---
        $check('admin.tas.store', fn () => $this->actingAs($this->admin)->post(route('admin.tas.store'), [
            'user_id' => $this->mhs->id,
            'judul_ta' => 'Audit admin TA',
            'target_sesi' => 12,
        ]));
        $check('admin.tas.update', fn () => $this->actingAs($this->admin)->put(route('admin.tas.update', $this->ta), [
            'judul_ta' => 'Audit admin TA updated',
            'target_sesi' => 12,
        ]));
        $check('admin.tas.status', fn () => $this->actingAs($this->admin)->post(route('admin.tas.status', $this->ta), [
            'status_ta' => 'aktif',
        ]));
        $check('admin.sidangs.store', fn () => $this->actingAs($this->admin)->post(route('admin.sidangs.store'), [
            'mahasiswa_ta_id' => $this->ta->id,
            'penguji_id' => $this->dosen2->id,
            'jenis' => 'seminar_proposal',
            'tanggal' => now()->addDays(5)->toDateString(),
        ]));
        $check('admin.institution.update', fn () => $this->actingAs($this->admin)->post(route('admin.institution.update'), [
            'app_name' => 'Audit App',
            'institution_name' => 'Audit Institution',
        ]));
        $check('admin.users.store', fn () => $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Audit New User',
            'email' => 'audit-new-user@test.com',
            'password' => 'password',
            'roles' => ['mahasiswa'],
        ]));
        $check('admin.users.reset-password', fn () => $this->actingAs($this->admin)->post(route('admin.users.reset-password', $this->mhs), [
            'password' => 'newpassword',
        ]));
        $check('admin.bulk', fn () => $this->actingAs($this->admin)->post(route('admin.bulk'), [
            'action' => 'export',
            'ids' => [$this->entryDraft->id],
        ]));

        if ($failures) {
            $this->fail('Audit found '.count($failures)." failing write action(s):\n".implode("\n", $failures));
        }

        $this->assertTrue(true);
    }
}
