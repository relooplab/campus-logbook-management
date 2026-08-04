<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\Message;
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

        // Hitung unread tiap percakapan.
        foreach ($conversations as $c) {
            $c->unread = $c->unreadCountFor($user->id);
            $c->other_user = $c->other($user);
        }

        return view('chat.index', compact('conversations', 'user'));
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
            'attachable_type' => ['nullable', 'in:workspace,logbook'],
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
     * Daftar file yang bisa di-attach (workspace + logbook entry) untuk user.
     */
    public function attachOptions(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_unless($conversation->hasUser($user->id), 403);

        $files = WorkspaceFile::whereHas('mahasiswaTa', function ($q) use ($user) {
            $this->scopeForUser($q, $user);
        })->with('mahasiswaTa.mahasiswa')->limit(10)->get();

        $entries = LogbookEntry::whereHas('mahasiswaTa', function ($q) use ($user) {
            $this->scopeForUser($q, $user);
        })->with('mahasiswaTa.mahasiswa')->limit(10)->get();

        return response()->json([
            'files' => $files->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->original_name,
                'student' => $f->mahasiswaTa?->mahasiswa?->name,
                'url' => $f->isPdf() ? route('workspace.preview', $f) : route('workspace.download', $f),
            ]),
            'entries' => $entries->map(fn ($e) => [
                'id' => $e->id,
                'label' => $e->jenis === 'revisi' ? 'Revisi' : 'Entri #'.$e->sesi_ke,
                'student' => $e->mahasiswaTa?->mahasiswa?->name,
                'url' => route('logbook.show', $e),
            ]),
        ]);
    }

    // ----------------------------------------------------------- helpers

    private function authorizeChat(User $user, User $other): void
    {
        if ($user->isAdmin()) {
            // Admin bisa chat dengan semua user di scope tenant-nya (mode institusi).
            if (Feature::isInstitution() && $other->institution_id && $other->institution_id !== $user->institution_id) {
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
        if ($type === 'workspace') {
            $file = WorkspaceFile::find($id);
            if ($file && $this->canAccess($file->mahasiswaTa, $user)) return $file;
        } else {
            $entry = LogbookEntry::find($id);
            if ($entry && $this->canAccess($entry->mahasiswaTa, $user)) return $entry;
        }
        return null;
    }

    private function canAccess(?MahasiswaTa $ta, User $user): bool
    {
        if (!$ta) return false;
        return $user->isAdmin()
            || $ta->isMember($user)
            || $ta->isPembimbing($user)
            || $ta->isPenguji($user);
    }

    private function scopeForUser($q, User $user): void
    {
        if ($user->isAdmin()) return;

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
