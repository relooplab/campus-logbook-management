@extends("layouts.app") @section("title", "Laporan Baca") @section("content")
<div class="max-w-3xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">Laporan Baca — {{ $announcement->title }}</h1> <a
            href="{{ route("announcements.index") }}" class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">←
            Kembali</a>
    </div>
    <div class="bg-bg-surface rounded-xl border border-border p-5">
        <p class="text-sm">{{ $announcement->body }}</p>
        <p class="text-xs text-text-secondary mt-2">Dikirim: {{ $announcement->created_at?->format("d M Y H:i") }}</p>
    </div>
    <div class="bg-bg-surface rounded-xl border border-border overflow-hidden">
        <div class="px-4 py-3 border-b border-border font-semibold text-sm"> {{ $read->count() }} dari
            {{ $announcement->recipients->count() }} mahasiswa sudah membaca </div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-text-secondary border-b border-border">
                    <th class="py-2 px-4">Mahasiswa</th>
                    <th class="py-2 px-4">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($read as $r)
                    <tr class="border-b border-border">
                        <td class="py-2 px-4">{{ $r->name }}</td>
                        <td class="py-2 px-4"><span class="badge badge-success">Dibaca
                                {{ \Illuminate\Support\Carbon::parse($r->pivot->read_at)?->format("d M H:i") }}</span>
                        </td>
                </tr> @empty
                    @endforelse @foreach ($unread as $r)
                        <tr class="border-b border-border">
                            <td class="py-2 px-4">{{ $r->name }}</td>
                            <td class="py-2 px-4"><span class="badge badge-pending">Belum dibaca</span></td>
                        </tr>
                    @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
