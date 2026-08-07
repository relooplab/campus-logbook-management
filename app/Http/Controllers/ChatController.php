<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\LogbookEntry;
use App\Models\LogbookHarianKp;
use App\Models\MahasiswaTa;
use App\Models\Message;
use App\Models\SeminarSubmission;
use App\Models\ThesisFinalization;
use App\Models\User;
use App\Models\WorkspaceFile;
use App\Support\Feature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatController extends Controller
{
    /**
     * Daftar percakapan user.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $conversations = Conversation::where('user_one_id', $user->id)
            ->orWhere('user_two_id', $user->id)
            ->with(['userOne', 'userTwo', 'mahasiswaTa.mahasiswa'])
            ->orderByDesc('updated_at')
            ->get();

        $supervised = collect();
        if ($user->isDosen()) {
            $supervised = MahasiswaTa::bimbinganOleh($user)
                ->with(['mahasiswa', 'pembimbing1', 'pembimbing2'])
                ->latest()
                ->get();
        }

        // Hitung unread tiap percakapan.
        foreach ($conversations as $c) {
            $c->unread = $c->unreadCountFor($user->id);
            $c->other_user = $c->other($user);
        }

        return view('chat.index', compact('conversations', 'user', 'supervised'));
    }

    /**
     * Tampilkan thread percakapan.
     */
    public function show(Request $request, Conversation $conversation): View
    {
        $user = $request->user();
        abort_unless($conversation->hasUser($user->id), 403, 'Anda bukan peserta percakapan ini.');

        // Tandai semua pesan sebagai dibaca.
        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = $conversation->messages()->with('sender', 'attachable')->get();
        $conversation->other_user = $conversation->other($user);

        return view('chat.show', compact('conversation', 'messages', 'user'));
    }

    /**
     * Cari/ciptakan percakapan dengan user lain, atau buka thread dari detail mahasiswa.
     */
    public function start(Request $request): RedirectResponse
    {
        $user = $request->user();
        $otherId = (int) $request->query('user', $request->input('user_id', 0));
        $taId = $request->query('ta') ?: $request->input('mahasiswa_ta_id');
        $other = User::find($otherId);
        abort_unless($other, 404, 'User tidak ditemukan.');
        $this->authorizeChat($user, $other);

        $conversation = $this->findOrCreate($user, $other, $taId ?: null);

        return redirect()->route('chat.show', $conversation);
    }

    /**
     * Kirim pesan (dari halaman chat).
     */
    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = $request->user();
        abort_unless($conversation->hasUser($user->id), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'attachable_type' => ['nullable', 'in:workspace,logbook,logbook_harian,seminar,finalization'],
            'attachable_id' => ['nullable', 'integer'],
        ]);

        $attach = null;
        if (!empty($validated['attachable_type']) && !empty($validated['attachable_id'])) {
            $attach = $this->resolveAttachable($validated['attachable_type'], (int) $validated['attachable_id'], $user);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'body' => $validated['body'],
            'attachable_type' => $attach ? get_class($attach) : null,
            'attachable_id' => $attach ? $attach->id : null,
        ]);

        $conversation->touch();
        $this->bestEffort(fn () => broadcast(new MessageSent($message, $conversation)));

        return redirect()->route('chat.show', $conversation);
    }

    /**
     * Edit pesan (hanya 15 menit pertama).
     */
    public function update(Request $request, Conversation $conversation, Message $message): RedirectResponse
    {
        abort_unless($message->conversation_id === $conversation->id, 404);
        abort_unless($message->sender_id === $request->user()->id, 403);
        abort_unless($message->isEditable(), 403, 'Waktu edit pesan telah habis.');

        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $message->update(['body' => $validated['body'], 'edited_at' => now()]);

        return redirect()->route('chat.show', $conversation);
    }

    /**
     * Daftar karya mahasiswa yang bisa disematkan (workspace, logbook,
     * revisi, logbook harian KP, seminar/sidang, finalisasi).
     */
    public function attachOptions(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_unless($conversation->hasUser($user->id), 403);

        // Batasi ke program percakapan (pekerjaan mahasiswa) bila ada.
        $ta = $conversation->mahasiswaTa;
        $scope = function ($q) use ($user, $ta) {
            if ($ta) {
                $q->where('id', $ta->id);
                return;
            }
            $this->scopeForUser($q, $user);
        };

        $files = WorkspaceFile::whereHas('mahasiswaTa', $scope)
            ->with('mahasiswaTa.mahasiswa')->orderByDesc('created_at')->limit(10)->get();

        $entries = LogbookEntry::whereHas('mahasiswaTa', $scope)
            ->with('mahasiswaTa.mahasiswa')->orderByDesc('created_at')->limit(20)->get();

        $logbooks = $entries->where('jenis', LogbookEntry::JENIS_LOGBOOK)->take(10)->values();
        $revisis = $entries->where('jenis', LogbookEntry::JENIS_REVISI)->take(10)->values();

        $harian = LogbookHarianKp::whereHas('mahasiswaTa', $scope)
            ->with('mahasiswaTa.mahasiswa')->orderByDesc('tanggal')->limit(10)->get();

        $seminars = SeminarSubmission::whereHas('mahasiswaTa', $scope)
            ->with('mahasiswaTa.mahasiswa')->orderByDesc('created_at')->limit(10)->get();

        $finals = ThesisFinalization::whereHas('mahasiswaTa', $scope)
            ->with('mahasiswaTa.mahasiswa')->orderByDesc('updated_at')->limit(5)->get();

        return response()->json([
            'categories' => [
                $this->attachCategory('workspace', 'Workspace', 'description', $files->map(fn ($f) => [
                    'type' => 'workspace',
                    'id' => $f->id,
                    'label' => $f->original_name,
                    'student' => $f->mahasiswaTa?->mahasiswa?->name,
                    'url' => $f->isPdf() ? route('workspace.preview', $f) : route('workspace.download', $f),
                ])),
                $this->attachCategory('logbook', 'Logbook', 'assignment', $logbooks->map(fn ($e) => [
                    'type' => 'logbook',
                    'id' => $e->id,
                    'label' => 'Entri #'.$e->sesi_ke.($e->topik ? ' — '.$e->topik : ''),
                    'student' => $e->mahasiswaTa?->mahasiswa?->name,
                    'url' => route('logbook.show', $e),
                ])),
                $this->attachCategory('revisi', 'Revisi', 'sync', $revisis->map(fn ($e) => [
                    'type' => 'logbook',
                    'id' => $e->id,
                    'label' => 'Revisi r'.$e->revision_round.($e->topik ? ' — '.$e->topik : ''),
                    'student' => $e->mahasiswaTa?->mahasiswa?->name,
                    'url' => route('logbook.show', $e),
                ])),
                $this->attachCategory('logbook_harian', 'Logbook Harian KP', 'calendar_month', $harian->map(fn ($h) => [
                    'type' => 'logbook_harian',
                    'id' => $h->id,
                    'label' => 'KP '.$h->tanggal->format('d M Y').($h->kegiatan ? ' — '.mb_strimwidth($h->kegiatan, 0, 40, '…') : ''),
                    'student' => $h->mahasiswaTa?->mahasiswa?->name,
                    'url' => $h->mahasiswaTa ? route('logbook-harian.index', $h->mahasiswaTa) : '#',
                ])),
                $this->attachCategory('seminar', 'Seminar / Sidang', 'school', $seminars->map(fn ($s) => [
                    'type' => 'seminar',
                    'id' => $s->id,
                    'label' => $s->jenisLabel(),
                    'student' => $s->mahasiswaTa?->mahasiswa?->name,
                    'url' => route('seminar-submission.show', $s),
                ])),
                $this->attachCategory('finalization', 'Finalisasi', 'task_alt', $finals->map(fn ($fz) => [
                    'type' => 'finalization',
                    'id' => $fz->id,
                    'label' => 'Finalisasi'.($fz->full_file_original_name ? ' — '.$fz->full_file_original_name : ''),
                    'student' => $fz->mahasiswaTa?->mahasiswa?->name,
                    'url' => $fz->mahasiswaTa ? route('finalization.index', $fz->mahasiswaTa) : '#',
                ])),
            ],
        ]);
    }

    private function attachCategory(string $key, string $title, string $icon, iterable $items): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'icon' => $icon,
            'items' => $items->values()->all(),
        ];
    }

    // ----------------------------------------------------------- helpers

    private function authorizeChat(User $user, User $other): void
    {
        if ($user->isAdmin()) {
            // Admin bisa chat dengan semua user di scope tenant-nya.
            if ($other->institution_id && $other->institution_id !== $user->institution_id) {
                abort(403);
            }
            return;
        }

        if ($user->isDosen()) {
            // Dosen dengan mahasiswa bimbingannya / penguji, atau admin.
            if ($other->isDosen() && !$other->isAdmin()) {
                // Dosen boleh chat dengan dosen lain jika ada hubungan langsung
                // (TA bersama atau grup yang sama).
                abort_unless($user->hasDirectRelation($other), 403, 'Anda tidak memiliki hubungan langsung dengan dosen ini.');
            }
            if ($other->isMahasiswa() && !$this->isRelatedTo($user, $other)) abort(403);
            return;
        }

        // Mahasiswa: hanya pembimbing/penguji atau admin.
        if ($other->isMahasiswa()) abort(403, 'Mahasiswa tidak bisa chat dengan mahasiswa lain.');
        if ($other->isDosen() && !$this->isRelatedTo($user, $other) && !$other->isAdmin()) abort(403);
    }

    private function isRelatedTo(User $a, User $b): bool
    {
        // a = dosen, b = mahasiswa (atau sebaliknya): cek TA di mana mereka terhubung.
        return MahasiswaTa::where(function ($q) use ($a, $b) {
            $q->where('user_id', $b->id)
                ->where(function ($w) use ($a) {
                    $w->where('pembimbing_1_id', $a->id)->orWhere('pembimbing_2_id', $a->id)
                        ->orWhere('penguji_1_id', $a->id)->orWhere('penguji_2_id', $a->id);
                });
        })->orWhere(function ($q) use ($a, $b) {
            $q->where('user_id', $a->id)
                ->where(function ($w) use ($b) {
                    $w->where('pembimbing_1_id', $b->id)->orWhere('pembimbing_2_id', $b->id)
                        ->orWhere('penguji_1_id', $b->id)->orWhere('penguji_2_id', $b->id);
                });
        })->exists();
    }

    private function findOrCreate(User $a, User $b, ?int $taId): Conversation
    {
        // Konsisten: user_one_id selalu ID lebih kecil.
        [$one, $two] = $a->id < $b->id ? [$a->id, $b->id] : [$b->id, $a->id];

        return Conversation::firstOrCreate(
            ['mahasiswa_ta_id' => $taId, 'user_one_id' => $one, 'user_two_id' => $two],
            ['user_one_id' => $one, 'user_two_id' => $two]
        );
    }

    private function resolveAttachable(string $type, int $id, User $user): ?Model
    {
        $model = match ($type) {
            'workspace' => WorkspaceFile::find($id),
            'logbook' => LogbookEntry::find($id),
            'logbook_harian' => LogbookHarianKp::find($id),
            'seminar' => SeminarSubmission::find($id),
            'finalization' => ThesisFinalization::find($id),
            default => null,
        };

        if ($model && $this->canAccess($model->mahasiswaTa, $user)) {
            return $model;
        }

        return null;
    }

    private function canAccess(?MahasiswaTa $ta, User $user): bool
    {
        if (!$ta) return false;

        if ($user->isAdmin()) {
            return $user->isSystemAdmin() || $user->institution_id === null || $ta->institution_id === $user->institution_id;
        }

        return $ta->isMember($user)
            || $ta->isPembimbing($user)
            || $ta->isPenguji($user);
    }

    private function scopeForUser($q, User $user): void
    {
        if ($user->isAdmin()) {
            if (!$user->isSystemAdmin() && $user->institution_id) {
                $q->where('institution_id', $user->institution_id);
            }
            return;
        }

        $memberProgramIds = \DB::table('mahasiswa_ta_members')
            ->where('user_id', $user->id)
            ->pluck('mahasiswa_ta_id');

        $q->where(function ($w) use ($user, $memberProgramIds) {
            $w->where('user_id', $user->id)
                ->orWhereIn('id', $memberProgramIds)
                ->orWhere('pembimbing_1_id', $user->id)
                ->orWhere('pembimbing_2_id', $user->id)
                ->orWhere('penguji_1_id', $user->id)
                ->orWhere('penguji_2_id', $user->id);
        });
    }
}
