<?php

namespace Tests\Feature;

use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LogbookStudentLinkTest extends TestCase
{
    use DatabaseTransactions;

    private function account(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $suffix = uniqid();
        $user = User::create([
            'name' => 'Logbook Student Link '.$suffix, 'email' => $suffix.'@logbook-link.test',
            'password' => bcrypt('password'), 'registration_status' => 'active',
            'nim' => $role === 'mahasiswa' ? 'NIM'.$suffix : null,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function program(User $student, User $lecturer, string $jenis): MahasiswaTa
    {
        $program = MahasiswaTa::create([
            'user_id' => $student->id, 'pembimbing_1_id' => $lecturer->id, 'jenis' => $jenis,
            'fase' => $jenis === 'kp' ? 'laporan' : 'proposal', 'status_ta' => 'aktif', 'target_sesi' => 7,
        ]);
        LogbookEntry::create([
            'mahasiswa_ta_id' => $program->id, 'dosen_id' => $lecturer->id,
            'jenis' => 'logbook', 'sesi_ke' => 1, 'topik' => 'Link program '.$program->id,
            'status' => LogbookEntry::STATUS_SUBMITTED,
        ]);

        return $program;
    }

    public function test_student_names_link_to_each_entry_program_for_supervisors_examiners_and_admins(): void
    {
        $student = $this->account('mahasiswa');
        $supervisor = $this->account('dosen');
        $examiner = $this->account('dosen');
        $admin = $this->account('admin');
        $ta = $this->program($student, $supervisor, 'ta');
        $kp = $this->program($student, $supervisor, 'kp');
        $ta->update(['penguji_1_id' => $examiner->id]);
        $kp->update(['penguji_1_id' => $examiner->id]);

        foreach ([$supervisor, $examiner, $admin] as $viewer) {
            $response = $this->actingAs($viewer)->get(route('logbook.index'));
            $response->assertOk();
            $document = new \DOMDocument;
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);
            foreach ([$ta, $kp] as $program) {
                $url = route($program->isKp() ? 'mahasiswa-kp.show' : 'mahasiswa-ta.show', $program);
                $links = $xpath->query('//a[@href="'.$url.'"]');
                $this->assertGreaterThan(0, $links->length);
                $this->assertSame($student->name, trim($links->item(0)->textContent));
                $this->get($url)->assertOk();
            }
        }
    }

    public function test_detail_student_names_link_to_the_entry_program_for_logbooks_and_revisions(): void
    {
        $student = $this->account('mahasiswa');
        $supervisor = $this->account('dosen');
        $examiner = $this->account('dosen');

        foreach (['ta', 'kp'] as $jenis) {
            $program = $this->program($student, $supervisor, $jenis);
            $program->update(['penguji_1_id' => $examiner->id]);
            $logbook = $program->entries()->firstOrFail();
            $revision = LogbookEntry::create([
                'mahasiswa_ta_id' => $program->id, 'dosen_id' => $examiner->id,
                'jenis' => 'revisi', 'parent_entry_id' => $logbook->id,
                'topik' => 'Revisi program '.$program->id,
                'status' => LogbookEntry::STATUS_SUBMITTED,
            ]);
            $url = route($program->isKp() ? 'mahasiswa-kp.show' : 'mahasiswa-ta.show', $program);

            foreach ([$student, $supervisor, $examiner] as $viewer) {
                foreach ([$logbook, $revision] as $entry) {
                    $response = $this->actingAs($viewer)->get(route('logbook.show', $entry));
                    $response->assertOk();
                    $document = new \DOMDocument;
                    @$document->loadHTML($response->getContent());
                    $xpath = new \DOMXPath($document);
                    $links = $xpath->query('//section[contains(@aria-label, "Ringkasan")]//a[@href="'.$url.'"]');
                    $this->assertSame(1, $links->length);
                    $this->assertSame($student->name, trim($links->item(0)->textContent));
                    $this->get($url)->assertOk();
                }
            }
        }
    }

    public function test_detail_hides_program_links_from_indirectly_related_lecturers(): void
    {
        $student = $this->account('mahasiswa');
        $otherStudent = $this->account('mahasiswa');
        $supervisor = $this->account('dosen');
        $relatedLecturer = $this->account('dosen');
        $shared = $this->program($otherStudent, $supervisor, 'ta');
        $shared->update(['penguji_1_id' => $relatedLecturer->id]);

        foreach (['ta', 'kp'] as $jenis) {
            $program = $this->program($student, $supervisor, $jenis);
            $logbook = $program->entries()->firstOrFail();
            $revision = LogbookEntry::create([
                'mahasiswa_ta_id' => $program->id, 'dosen_id' => $supervisor->id,
                'jenis' => 'revisi', 'parent_entry_id' => $logbook->id,
                'topik' => 'Revisi program '.$program->id,
                'status' => LogbookEntry::STATUS_SUBMITTED,
            ]);
            $url = route($program->isKp() ? 'mahasiswa-kp.show' : 'mahasiswa-ta.show', $program);

            foreach ([$logbook, $revision] as $entry) {
                $this->actingAs($relatedLecturer)->get(route('logbook.show', $entry))
                    ->assertOk()->assertSee($student->name)->assertDontSee('href="'.$url.'"', false);
            }
            $this->get($url)->assertForbidden();
        }
    }

    public function test_indirectly_related_lecturer_sees_plain_name_without_forbidden_program_link(): void
    {
        $student = $this->account('mahasiswa');
        $otherStudent = $this->account('mahasiswa');
        $supervisor = $this->account('dosen');
        $relatedLecturer = $this->account('dosen');
        $program = $this->program($student, $supervisor, 'ta');
        $shared = $this->program($otherStudent, $supervisor, 'ta');
        $shared->update(['penguji_1_id' => $relatedLecturer->id]);
        $url = route('mahasiswa-ta.show', $program);

        $this->actingAs($relatedLecturer)->get(route('logbook.index'))
            ->assertOk()->assertSee($student->name)->assertDontSee('href="'.$url.'"', false);
        $this->get($url)->assertForbidden();
    }
}