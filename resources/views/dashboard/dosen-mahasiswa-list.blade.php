@extends("layouts.app") @section("title", "Daftar Mahasiswa Bimbingan") @section("content")
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">Daftar Mahasiswa Bimbingan ({{ $list->count() }})</h1> <a
            href="{{ route("dashboard") }}" class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">←
            Dashboard</a>
    </div> {{-- Filter status --}} <div class="flex flex-wrap gap-2"> <a
            href="{{ route("dashboard.dosen.mahasiswa-list") }}"
            class="px-3 py-1.5 rounded-md text-sm {{ $status === "all" ? "bg-accent-teal text-white" : "bg-bg-hover" }}">Semua</a>
        <a href="{{ route("dashboard.dosen.mahasiswa-list", ["status" => "aktif"]) }}"
            class="px-3 py-1.5 rounded-md text-sm {{ $status === "aktif" ? "bg-accent-blue text-white" : "bg-accent-blue/10 text-accent-blue" }}"><span class="material-symbols-outlined icon-sm align-text-bottom">fiber_manual_record</span>
            Aktif</a> <a href="{{ route("dashboard.dosen.mahasiswa-list", ["status" => "tamat"]) }}"
            class="px-3 py-1.5 rounded-md text-sm {{ $status === "tamat" ? "bg-accent-teal text-white" : "bg-accent-teal/10 text-accent-teal" }}"><span class="material-symbols-outlined icon-sm align-text-bottom">school</span>
            Tamat</a>
    </div>
    @if ($list->isEmpty())
        <div class="px-4 py-8 rounded-lg bg-bg-surface border border-border text-center text-text-secondary"> Belum ada
            mahasiswa bimbingan. </div>
    @else
        <div class="bg-bg-surface rounded-xl border border-border overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-text-secondary border-b border-border">
                        <th class="py-3 px-4">Mahasiswa</th>
                        <th class="py-3 px-4">Status TA</th>
                        <th class="py-3 px-4">Fase</th>
                        <th class="py-3 px-4">Keteraturan</th>
                        <th class="py-3 px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($list as $row)
                        @php $ta = $row['ta']; @endphp <tr class="border-b border-border hover:bg-bg-hover cursor-pointer"
                            onclick="window.location='{{ route("mahasiswa-ta.show", $ta) }}'">
                            <td class="py-3 px-4"><a href="{{ route("mahasiswa-ta.show", $ta) }}"
                                    class="hover:underline">{{ $ta->mahasiswa?->name }}</a> <span
                                    class="text-text-secondary text-xs">({{ $ta->mahasiswa?->identifier }})</span></td>
                            <td class="py-3 px-4"> <span
                                    class="badge {{ $ta->status_ta === "aktif" ? "badge-info" : "" }} {{ $ta->status_ta === "tamat" ? "badge-success" : "" }} {{ $ta->status_ta === "nonaktif" ? "badge-neutral" : "" }}">
                                    {{ ucfirst($ta->status_ta) }} </span> </td>
                            <td class="py-3 px-4">{{ $ta->faseLabel() }}</td>
                            <td class="py-3 px-4"> <span
                                    class="inline-block w-3 h-3 rounded-full mr-1 align-middle {{ $row["regularity"] === "green" ? "bg-status-success" : "" }} {{ $row["regularity"] === "yellow" ? "bg-status-pending" : "" }} {{ $row["regularity"] === "red" ? "bg-status-danger" : "" }}"
                                    title="{{ $row["tooltip"] }}"></span> <span
                                    class="text-xs text-text-secondary">{{ ucfirst($row["regularity"]) }}</span> </td>
                            <td class="py-3 px-4"><a href="{{ route("mahasiswa-ta.show", $ta) }}"
                                    class="text-accent-teal hover:underline">Detail</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
