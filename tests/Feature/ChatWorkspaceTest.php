<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\MahasiswaTa;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatWorkspaceTest extends TestCase
{
    use DatabaseTransactions;

    private function account(string $role, string $label): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $suffix = uniqid();
        $user = User::create([
            'name' => $label.' '.$suffix, 'email' => $suffix.'@chat.test',
            'password' => bcrypt('password'), 'registration_status' => 'active',
            'nim' => $role === 'mahasiswa' ? 'NIM'.$suffix : null,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function program(User $student, User $lecturer, string $role = 'pembimbing_1_id'): MahasiswaTa
    {
        return MahasiswaTa::create(['user_id' => $student->id, 'jenis' => 'ta',
            'fase' => 'proposal', 'status_ta' => 'aktif', 'target_sesi' => 7,
            'judul_ta' => 'Judul rahasia panjang untuk program '.$student->id, $role => $lecturer->id]);
    }

    public function test_lecturer_list_has_authorized_contacts_filters_search_and_empty_thread(): void
    {
        $lecturer = $this->account('dosen', 'Lecturer');
        $student = $this->account('mahasiswa', 'VisibleStudent');
        $outsider = $this->account('mahasiswa', 'HiddenStudent');
        $program = $this->program($student, $lecturer, 'penguji_1_id');

        $this->actingAs($lecturer)->get(route('chat.index'))
            ->assertOk()->assertSee('Pilih percakapan')->assertSee($student->name)
            ->assertSee('Penguji 1')->assertDontSee($outsider->name)->assertDontSee($program->judul_ta)
            ->assertSee(route('chat.start', ['user' => $student->id, 'ta' => $program->id]));
        $this->get(route('chat.index', ['filter' => 'dibimbing']))->assertOk()->assertDontSee($student->name);
        $this->get(route('chat.index', ['filter' => 'diuji', 'search' => $student->nim]))->assertOk()->assertSee($student->name);
        $this->get(route('chat.index', ['search' => 'no-match-chat-xyz']))->assertOk()->assertSee('Tidak ada mahasiswa atau percakapan yang cocok.');

        $this->get(route('chat.start', ['user' => $student->id, 'ta' => $program->id]))->assertRedirect();
        $thread = Conversation::where('mahasiswa_ta_id', $program->id)->firstOrFail();
        $this->get(route('chat.show', $thread))->assertOk()->assertSee('Belum ada percakapan.')
            ->assertSee('Kembali ke daftar percakapan')->assertSee('Tulis pesan...')
            ->assertSee('Sematkan referensi karya (bukan unggah file)');
        $this->get(route('chat.show', ['conversation' => $thread, 'search' => $student->nim]))
            ->assertOk()->assertSee('action="'.route('chat.index').'"', false)
            ->assertSee(route('chat.index', ['filter' => 'diuji', 'search' => $student->nim]));
    }

    public function test_student_can_start_with_assigned_lecturer_and_cannot_open_or_start_outside_relationship(): void
    {
        $lecturer = $this->account('dosen', 'AssignedLecturer');
        $student = $this->account('mahasiswa', 'Student');
        $other = $this->account('mahasiswa', 'OtherStudent');
        $program = $this->program($student, $lecturer);
        $foreign = $this->program($other, $lecturer);

        $this->actingAs($student)->get(route('chat.index'))->assertOk()->assertSee($lecturer->name)
            ->assertSee(route('chat.start', ['user' => $lecturer->id, 'ta' => $program->id]));
        $this->get(route('chat.start', ['user' => $lecturer->id, 'ta' => $foreign->id]))->assertForbidden();
        $this->get(route('chat.start', ['user' => $lecturer->id, 'ta' => $program->id]))->assertRedirect();
        $thread = Conversation::where('mahasiswa_ta_id', $program->id)->firstOrFail();
        $this->get(route('chat.show', $thread))->assertOk();
        $this->post(route('chat.store', $thread), ['body' => 'Halo dosen'])->assertRedirect(route('chat.show', $thread));
        $this->assertDatabaseHas('messages', ['conversation_id' => $thread->id, 'body' => 'Halo dosen']);
        $this->actingAs($other)->get(route('chat.show', $thread))->assertForbidden();
        $this->post(route('chat.store', $thread), ['body' => 'Tidak boleh'])->assertForbidden();
    }

    public function test_unread_preview_read_transition_and_edit_authorization(): void
    {
        $lecturer = $this->account('dosen', 'Lecturer');
        $student = $this->account('mahasiswa', 'Student');
        $program = $this->program($student, $lecturer);
        $thread = Conversation::create(['user_one_id' => min($student->id, $lecturer->id),
            'user_two_id' => max($student->id, $lecturer->id), 'mahasiswa_ta_id' => $program->id]);
        $message = Message::create(['conversation_id' => $thread->id, 'sender_id' => $student->id, 'body' => 'Pesan belum dibaca']);

        $this->actingAs($lecturer)->get(route('chat.index', ['filter' => 'belum-dibaca']))
            ->assertOk()->assertSee('Pesan belum dibaca')->assertSee('1 pesan belum dibaca');
        $this->get(route('chat.show', $thread))->assertOk()->assertSee('Pesan belum dibaca');
        $this->assertNotNull($message->fresh()->read_at);
        $this->get(route('chat.index', ['filter' => 'belum-dibaca']))->assertOk()->assertDontSee($student->name);
        $this->put(route('chat.update', [$thread, $message]), ['body' => 'Not mine'])->assertForbidden();
    }

    public function test_multiple_roles_share_one_contact_and_history_is_bounded(): void
    {
        $lecturer = $this->account('dosen', 'Lecturer');
        $student = $this->account('mahasiswa', 'DoubleRoleStudent');
        $program = $this->program($student, $lecturer);
        $program->update(['penguji_2_id' => $lecturer->id]);

        $this->actingAs($lecturer)->get(route('chat.index'))
            ->assertOk()->assertViewHas('counts', fn ($counts) => $counts['semua'] === 1
                && $counts['dibimbing'] === 1 && $counts['diuji'] === 1);
        $thread = Conversation::create(['user_one_id' => min($student->id, $lecturer->id),
            'user_two_id' => max($student->id, $lecturer->id), 'mahasiswa_ta_id' => $program->id]);
        for ($i = 0; $i < 102; $i++) {
            Message::create(['conversation_id' => $thread->id, 'sender_id' => $student->id,
                'body' => 'Pesan ke-'.$i]);
        }

        $this->get(route('chat.show', $thread))->assertOk()
            ->assertViewHas('messages', fn ($messages) => $messages->count() === 100)
            ->assertSee('Pesan ke-101')->assertDontSee('Pesan ke-0</p>', false)
            ->assertSee('Lihat pesan sebelumnya');
        $this->get(route('chat.show', ['conversation' => $thread, 'before' => $thread->messages()->orderByDesc('id')->skip(99)->first()->id]))
            ->assertOk()->assertSee('Pesan ke-0')->assertSee('Kembali ke pesan terbaru');
        $this->get(route('chat.index'))->assertOk()
            ->assertViewHas('counts', fn ($counts) => $counts['semua'] === 1);
    }

    public function test_attachment_reference_picker_remains_participant_only(): void
    {
        $lecturer = $this->account('dosen', 'Lecturer');
        $student = $this->account('mahasiswa', 'Student');
        $outsider = $this->account('mahasiswa', 'Outsider');
        $program = $this->program($student, $lecturer);
        $thread = Conversation::create(['user_one_id' => min($student->id, $lecturer->id),
            'user_two_id' => max($student->id, $lecturer->id), 'mahasiswa_ta_id' => $program->id]);

        $this->actingAs($lecturer)->postJson(route('chat.attach-options', $thread))->assertOk()->assertJsonStructure(['categories']);
        $this->actingAs($outsider)->postJson(route('chat.attach-options', $thread))->assertForbidden();
        $this->actingAs($student)->post(route('chat.store', $thread), ['body' => 'Pesan',
            'attachable_type' => 'workspace', 'attachable_id' => 99999999])->assertRedirect();
        $this->assertDatabaseHas('messages', ['conversation_id' => $thread->id,
            'body' => 'Pesan', 'attachable_type' => null, 'attachable_id' => null]);
    }
}
