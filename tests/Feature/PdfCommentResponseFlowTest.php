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
}
