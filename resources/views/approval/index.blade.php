@extends("layouts.app") @section("title", "Persetujuan Registrasi") @section("content")
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">Persetujuan Registrasi Mahasiswa ({{ $pending->count() }})</h1> <a
            href="{{ route("dashboard") }}" class="px-3 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm">←
            Dashboard</a>
    </div>
    @if ($pending->isEmpty())
        <div class="px-4 py-8 rounded-lg bg-bg-surface border border-border text-center text-text-secondary"> Tidak ada
            registrasi mahasiswa yang menunggu. </div>
    @else
        @foreach ($pending as $m)
            <div class="bg-bg-surface rounded-xl border border-border p-5">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                    <div>
                        <p class="font-semibold">{{ $m->name }}</p>
                        <p class="text-sm text-text-secondary">{{ $m->email }}</p>
                        @if ($m->examiner_supervisor_names)
                            <p class="text-xs text-status-pending mt-1">Ingin jadi penguji — Pembimbing:
                                {{ implode(", ", $m->examiner_supervisor_names) }}</p>
                        @endif
                    </div> <span class="badge badge-pending">Pending</span>
                </div>
                <form method="POST" action="{{ route("approval.approve", $m) }}" class="grid sm:grid-cols-2 gap-3">
                    @csrf <div class="sm:col-span-2"> <label class="block text-xs text-text-secondary mb-1">Judul
                            TA</label> <input type="text" name="judul_ta" required
                            class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                    <div> <label class="block text-xs text-text-secondary mb-1">Peran Anda untuk mahasiswa ini</label>
                        <select name="role_dosen"
                            class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                            <option value="pembimbing_1">Pembimbing 1</option>
                            <option value="pembimbing_2">Pembimbing 2</option>
                            <option value="penguji_1">Penguji 1</option>
                            <option value="penguji_2">Penguji 2</option>
                        </select>
                    </div>
                    <div> <label class="block text-xs text-text-secondary mb-1">Target Sesi</label> <input
                            type="number" name="target_sesi" value="7" min="1"
                            class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                    @if ($m->examiner_supervisor_names)
                        <div class="sm:col-span-2"> <label class="flex items-center gap-2 text-sm"> <input
                                    type="checkbox" name="allow_examiner" value="1" checked
                                    class="rounded bg-bg-surface"> Izinkan menjadi penguji </label> </div>
                    @endif
                    <div class="sm:col-span-2 flex flex-wrap gap-2 pt-1"> <button
                            class="px-4 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm">Setujui
                            & Assign</button> </div>
                </form>
                <form method="POST" action="{{ route("approval.reject", $m) }}" class="mt-2"> @csrf <button
                        class="px-4 py-2 rounded-md bg-status-danger hover:bg-status-danger/90 text-white text-sm">Tolak</button>
                </form>
            </div>
        @endforeach
    @endif
</div>
@endsection
