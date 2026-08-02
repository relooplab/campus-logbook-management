@extends("layouts.app") @section("title", "Buat Pengumuman") @section("content")
<div class="max-w-2xl">
    <h1 class="text-xl font-bold mb-4">Buat Pengumuman</h1>
    <form method="POST" action="{{ route("announcements.store") }}"
        class="bg-bg-surface rounded-xl border border-border p-6 space-y-4"> @csrf <div> <label
                class="block text-sm font-medium mb-1" for="title">Judul</label> <input type="text" name="title"
                id="title" required class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
        </div>
        <div> <label class="block text-sm font-medium mb-1" for="body">Isi Pengumuman</label>
            <textarea name="body" id="body" rows="4" required
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"></textarea>
        </div>
        <div>
            <p class="text-sm font-medium mb-2">Target</p> <label class="flex items-center gap-2 text-sm mb-2"> <input
                    type="radio" name="target_mode" value="all" checked onchange="toggleTarget(this.value)"
                    class="bg-bg-surface"> Semua mahasiswa bimbingan </label> <label
                class="flex items-center gap-2 text-sm"> <input type="radio" name="target_mode" value="manual"
                    onchange="toggleTarget(this.value)" class="bg-bg-surface"> Pilih manual per mahasiswa </label>
        </div>
        <div id="manual-list" class="hidden border-t border-border pt-3">
            <p class="text-sm text-text-secondary mb-2">Pilih mahasiswa (centang):</p>
            <div class="grid sm:grid-cols-2 gap-2">
                @foreach ($bimbingan as $ta)
                    <label class="flex items-center gap-2 text-sm"> <input type="checkbox" name="target_mahasiswa[]"
                            value="{{ $ta->id }}" class="rounded bg-bg-surface"> {{ $ta->mahasiswa?->name }}
                    </label>
                @endforeach
            </div>
        </div> <button
            class="px-4 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm font-semibold">Kirim
            Pengumuman</button>
    </form>
</div>
@endsection @section("scripts")
<script>
    function toggleTarget(v) {
        document.getElementById('manual-list').classList.toggle('hidden', v !== 'manual');
    }
</script>
@endsection
