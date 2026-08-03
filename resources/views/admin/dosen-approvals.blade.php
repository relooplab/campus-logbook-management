@extends("layouts.app") @section("title", "Persetujuan Dosen") @section("content")
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">Persetujuan Registrasi Dosen ({{ $pending->count() }})</h1> <a
            href="{{ route("dashboard") }}" class="px-3 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm">←
            Dashboard</a>
    </div>
    <p class="text-sm text-text-secondary">Dosen yang mendaftar mandiri perlu disetujui sebelum dapat masuk ke aplikasi.</p>
    @if ($pending->isEmpty())
        <div class="px-4 py-8 rounded-lg bg-bg-surface border border-border text-center text-text-secondary"> Tidak ada
            registrasi dosen yang menunggu. </div>
    @else
        @foreach ($pending as $d)
            <div class="bg-bg-surface rounded-xl border border-border p-5">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                    <div>
                        <p class="font-semibold">{{ $d->name }}</p>
                        <p class="text-sm text-text-secondary">{{ $d->email }}</p>
                        <p class="text-xs text-text-secondary mt-1">Mendaftar: {{ $d->created_at?->diffForHumans() }}</p>
                    </div> <span class="badge badge-pending">Pending</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route("admin.approve-dosen.approve", $d) }}"> @csrf <button
                            class="px-4 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm">Setujui</button>
                    </form>
                    <form method="POST" action="{{ route("admin.approve-dosen.reject", $d) }}"> @csrf <button
                            class="px-4 py-2 rounded-md bg-status-danger hover:bg-status-danger/90 text-white text-sm">Tolak</button>
                    </form>
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection