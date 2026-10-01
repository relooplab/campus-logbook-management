<?php

namespace Tests\Feature;

use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\PdfComment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Alur mahasiswa menanggapi komentar dosen pada PDF:
 *  - Membalas komentar dosen otomatis menandai "addressed" (sudah diperbaiki).
 *  - Dosen penulis komentar menerima notifikasi.
 */
class PdfCommentResponseFlowTest extends TestCase
{
    use DatabaseTransactions;

    private User $mahasiswa;
    private User $dosen;
    private LogbookEntry $entry;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['mahasiswa', 'dosen'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->mahasiswa = User::create([
            'name' => 'Mhs Pdf',
            'email' => 'mhs-pdf-'.uniqid().'@t.test',
            'password' => bcrypt('password'),
            'nim' => 'NIM'.substr(md5(uniqid()), 0, 8),
            'whatsapp' => '6281234567890',
            'registration_status' => 'active',
        ]);
        $this->mahasiswa->assignRole('mahasiswa');

        $this->dosen = User::create([
            'name' => 'Dosen Pdf',
            'email' => 'dosen-pdf-'.uniqid().'@t.test',
            'password' => bcrypt('password'),
            'nidn' => 'NIDN'.substr(md5(uniqid()), 0, 10),
            'registration_status' => 'active',
        ]);
        $this->dosen->assignRole('dosen');

        $ta = MahasiswaTa::create([
            'user_id' => $this->mahasiswa->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $this->dosen->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
            'fase' => 'proposal',
        ]);

        $this->entry = LogbookEntry::create([
            'mahasiswa_ta_id' => $ta->id,
            'jenis' => LogbookEntry::JENIS_LOGBOOK,
            'sesi_ke' => 1,
            'dosen_id' => $this->dosen->id,
            'topik' => 'Bimbingan',
            'status' => LogbookEntry::STATUS_APPROVED,
        ]);
    }

    private function dosenComment(): PdfComment
    {
        $c = new PdfComment([
            'user_id' => $this->dosen->id,
            'file_type' => PdfComment::FILE_TYPE_CATATAN,
            'page_number' => 1,
            'comment' => 'Tambah penjelasan metode.',
            'resolution_status' => PdfComment::STATUS_OPEN,
        ]);
        $this->entry->comments()->save($c);

        return $c->fresh();
    }

    public function test_mahasiswa_membalas_komentar_dosen_otomatis_addressed(): void
    {
        $comment = $this->dosenComment();

        $this->actingAs($this->mahasiswa)
            ->postJson(route('pdf-comments.reply', $comment), ['reply' => 'Sudah saya perbaiki di halaman 2.'])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'resolution_status' => PdfComment::STATUS_ADDRESSED,
            ]);

        $fresh = $comment->fresh();
        $this->assertSame('Sudah saya perbaiki di halaman 2.', $fresh->reply);
        $this->assertSame(PdfComment::STATUS_ADDRESSED, $fresh->resolution_status);
    }

    public function test_dosen_penulis_komentar_dapat_notifikasi(): void
    {
        $comment = $this->dosenComment();

        $this->actingAs($this->mahasiswa)
            ->postJson(route('pdf-comments.reply', $comment), ['reply' => 'Selesai diperbaiki.'])
            ->assertOk();

        $this->assertSame(1, $this->dosen->fresh()->notifications()->count());
    }

    public function test_mahasiswa_dan_reviewer_dapat_berdiskusi_tanpa_menimpa_balasan_sebelumnya(): void
    {
        $comment = $this->dosenComment();
        $this->actingAs($this->mahasiswa)->postJson(route('pdf-comments.reply', $comment), [
            'reply' => 'Penjelasan metode sudah saya tambahkan.',
        ])->assertOk()->assertJsonCount(1, 'replies');

        $this->actingAs($this->dosen)->postJson(route('pdf-comments.reply', $comment), [
            'reply' => 'Mohon sertakan rujukannya juga.',
        ])->assertOk()->assertJsonPath('resolution_status', PdfComment::STATUS_ADDRESSED)
            ->assertJsonCount(2, 'replies');

        $this->actingAs($this->mahasiswa)->postJson(route('pdf-comments.reply', $comment), [
            'reply' => 'Rujukan sudah saya tambahkan.',
        ])->assertOk()->assertJsonCount(3, 'replies');

        $messages = $comment->replies()->orderBy('id')->get();
        $this->assertSame([$this->mahasiswa->id, $this->dosen->id, $this->mahasiswa->id], $messages->pluck('user_id')->all());
        $this->assertSame(['Penjelasan metode sudah saya tambahkan.', 'Mohon sertakan rujukannya juga.', 'Rujukan sudah saya tambahkan.'], $messages->pluck('body')->all());
        $this->assertSame('Penjelasan metode sudah saya tambahkan.', $comment->fresh()->reply);
        $this->actingAs($this->dosen)->getJson(route('logbook.pdf.comments', ['logbook' => $this->entry, 'type' => PdfComment::FILE_TYPE_CATATAN]))
            ->assertOk()->assertJsonPath('0.replies.1.body', 'Mohon sertakan rujukannya juga.')
            ->assertJsonPath('0.replies.2.user_id', $this->mahasiswa->id);
        $this->assertSame(PdfComment::STATUS_ADDRESSED, $comment->fresh()->resolution_status);
    }

    public function test_balasan_legacy_tetap_ditampilkan_dan_balasan_baru_tidak_menggantinya(): void
    {
        $comment = $this->dosenComment();
        $comment->update(['reply' => 'Balasan lama sebelum migrasi.']);

        $this->actingAs($this->dosen)->getJson(route('logbook.pdf.comments', ['logbook' => $this->entry, 'type' => PdfComment::FILE_TYPE_CATATAN]))
            ->assertOk()->assertJsonPath('0.replies.0.body', 'Balasan lama sebelum migrasi.');

        $this->postJson(route('pdf-comments.reply', $comment), ['reply' => 'Mohon revisi lagi.'])
            ->assertOk()->assertJsonPath('replies.0.body', 'Balasan lama sebelum migrasi.')
            ->assertJsonPath('replies.1.body', 'Mohon revisi lagi.');
        $this->assertSame('Balasan lama sebelum migrasi.', $comment->fresh()->reply);
    }

    public function test_hanya_pemilik_dan_reviewer_yang_dapat_mengirim_balasan_valid(): void
    {
        $comment = $this->dosenComment();
        $unrelated = User::create(['name' => 'Dosen Lain', 'email' => 'outsider-'.uniqid().'@t.test', 'password' => bcrypt('password')]);
        $unrelated->assignRole('dosen');

        $this->actingAs($unrelated)->postJson(route('pdf-comments.reply', $comment), ['reply' => 'Tidak boleh.'])->assertForbidden();
        $this->actingAs($this->dosen)->postJson(route('pdf-comments.reply', $comment), ['reply' => '  '])->assertUnprocessable();
        $this->postJson(route('pdf-comments.reply', $comment), ['reply' => str_repeat('x', 2001)])->assertUnprocessable();
        $this->assertSame(0, $comment->replies()->count());
    }

    public function test_komentar_dan_balasan_panjang_tidak_dipotong_dari_api(): void
    {
        $comment = $this->dosenComment();
        $longComment = str_repeat('Uraikan hasil uji dan metode secara rinci. ', 35);
        $longReply = trim(str_repeat('Penjelasan dan rujukan sudah dilengkapi. ', 35));
        $comment->update(['comment' => $longComment]);

        $this->actingAs($this->mahasiswa)->postJson(route('pdf-comments.reply', $comment), ['reply' => $longReply])
            ->assertOk()->assertJsonPath('replies.0.body', $longReply);
        $this->getJson(route('logbook.pdf.comments', ['logbook' => $this->entry, 'type' => PdfComment::FILE_TYPE_CATATAN]))
            ->assertOk()->assertJsonPath('0.payload.body.0.value', $longComment)
            ->assertJsonPath('0.replies.0.body', $longReply);
    }
}
