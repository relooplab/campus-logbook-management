@extends('layouts.app')
@section('title', 'Chat')
@section('content')
<div class="space-y-4">
    <x-page-header title="Chat" :description="$user->isDosen() ? 'Komunikasi dengan mahasiswa bimbingan dan ujian Anda.' : ($user->isMahasiswa() ? 'Komunikasi dengan dosen pembimbing dan penguji Anda.' : 'Lanjutkan percakapan Anda.')">
        <x-slot:actions><a href="{{ route('dashboard') }}" class="btn-secondary inline-flex items-center px-4 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">← Dashboard</a></x-slot:actions>
    </x-page-header>
    <div class="chat-workspace grid min-w-0 gap-4 md:grid-cols-[minmax(300px,380px)_minmax(0,1fr)] lg:gap-5">
        <section class="card flex min-h-0 min-w-0 flex-col overflow-hidden {{ $conversation ? 'hidden md:flex' : 'flex' }}" aria-label="Daftar percakapan">
            <div class="border-b border-border p-4">
                <div class="flex items-center justify-between gap-3"><h2 class="font-heading text-base font-bold">Percakapan</h2><span class="font-mono text-xs text-text-secondary" aria-label="{{ $counts['semua'] }} kontak">{{ $counts['semua'] }}</span></div>
                <form method="GET" action="{{ route('chat.index') }}" class="mt-3 flex gap-2">
                    <label for="chat-search" class="sr-only">Cari nama atau NIM</label>
                    <input id="chat-search" name="search" type="search" value="{{ $search }}" placeholder="{{ $user->isDosen() ? 'Cari nama mahasiswa atau NIM...' : 'Cari nama atau NIM...' }}" class="min-w-0 flex-1 rounded-control border border-border bg-bg-panel px-3 py-2 text-sm text-text-primary placeholder:text-text-secondary focus:outline-none focus:ring-2 focus:ring-brand/50">
                    @if ($filter !== 'semua') <input type="hidden" name="filter" value="{{ $filter }}"> @endif
                    <button class="btn-secondary px-3 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" aria-label="Cari percakapan"><span class="material-symbols-outlined icon-sm" aria-hidden="true">search</span></button>
                </form>
                <nav id="chat-filters" class="mt-3 flex flex-wrap gap-1.5" aria-label="Filter percakapan">
                    @foreach (($user->isDosen() ? ['semua' => 'Semua', 'dibimbing' => 'Dibimbing', 'diuji' => 'Diuji', 'belum-dibaca' => 'Belum Dibaca'] : ['semua' => 'Semua', 'belum-dibaca' => 'Belum Dibaca']) as $key => $label)
                        <a href="{{ route('chat.index', ['filter' => $key, 'search' => $search]) }}" @if ($filter === $key) aria-current="page" @endif class="rounded-control px-2.5 py-1.5 text-xs font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand {{ $filter === $key ? 'bg-brand-light text-brand' : 'bg-bg-panel text-text-secondary hover:bg-bg-hover hover:text-text-primary' }}">{{ $label }} ({{ $counts[$key] }})</a>
                    @endforeach
                </nav>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain" id="conversation-list">
                @forelse ($rows as $row)
                    @include('chat.partials.conversation-row', ['row' => $row])
                @empty
                    <div class="p-8 text-center text-sm text-text-secondary">{{ $search !== '' || $filter !== 'semua' ? 'Tidak ada mahasiswa atau percakapan yang cocok.' : ($user->isDosen() ? 'Belum ada mahasiswa yang dapat dihubungi.' : 'Belum ada percakapan atau dosen yang dapat dihubungi.') }}</div>
                @endforelse
            </div>
        </section>
        <section class="card min-h-0 min-w-0 flex-col overflow-hidden {{ $conversation ? 'flex' : 'hidden md:flex' }}" aria-label="Percakapan aktif">
            @if ($conversation)
                @include('chat.partials.thread')
            @else
                <div class="flex h-full flex-col items-center justify-center p-8 text-center">
                    <span class="icon-chip h-14 w-14"><span class="material-symbols-outlined text-3xl" aria-hidden="true">forum</span></span>
                    <h2 class="mt-4 font-heading text-lg font-bold">Pilih percakapan</h2>
                    <p class="mt-2 max-w-sm text-sm text-text-secondary">Pilih {{ $user->isDosen() ? 'mahasiswa' : 'kontak' }} dari daftar di sebelah kiri untuk memulai atau melanjutkan percakapan.</p>
                </div>
            @endif
        </section>
    </div>
</div>
@endsection
@section('scripts') @include('chat.partials.scripts') @endsection
