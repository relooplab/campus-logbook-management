@extends("layouts.app") @section("title", "Profil " . $profile->name) @section("content")
<div class="max-w-2xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">Profil</h1> <a href="{{ url()->previous() }}"
            class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">← Kembali</a>
    </div>
    <div class="bg-bg-surface rounded-xl border border-border p-6">
        <div class="flex items-center gap-4">
            <div
                class="h-20 w-20 rounded-full overflow-hidden bg-accent-teal text-white flex items-center justify-center text-2xl font-bold flex-shrink-0">
                @if ($profile->photoUrl())
                    <img src="{{ $profile->photoUrl() }}" class="h-full w-full object-cover" alt="Foto profil">
                @else
                    {{ $profile->initials() }}
                @endif
            </div>
            <div>
                <h2 class="font-semibold text-lg">{{ $profile->name }}</h2>
                <p class="text-sm text-text-secondary">{{ $profile->email }}</p>
                @if ($profile->identifier)
                    <p class="text-xs text-text-secondary">{{ $profile->identifier }}</p>
                    @endif @foreach ($profile->roles as $r)
                        <span
                            class="inline-block px-2 py-0.5 rounded-full text-xs bg-bg-panel mt-1 mr-1">{{ ucfirst($r->name) }}</span>
                    @endforeach
            </div>
        </div> {{-- Kontak --}} <div class="mt-6 grid sm:grid-cols-2 gap-3 text-sm">
            @if ($profile->whatsapp)
                <a href="{{ $profile->whatsappUrl() }}" target="_blank" rel="noopener"
                    class="px-3 py-2 rounded-md bg-bg-panel hover:bg-bg-hover hover:bg-bg-hover">💬 WhatsApp:
                    {{ $profile->whatsapp }}</a>
                @endif @if ($profile->telegram)
                    <span class="px-3 py-2 rounded-md bg-bg-panel">✈️ Telegram: {{ $profile->telegram }}</span>
                    @endif @if ($profile->linkedin)
                        <a href="{{ $profile->linkedin }}" target="_blank" rel="noopener"
                            class="px-3 py-2 rounded-md bg-bg-panel hover:bg-bg-hover hover:bg-bg-hover">🔗
                            LinkedIn: {{ $profile->linkedin }}</a>
                        @endif @if ($profile->mahasiswaTa)
                            <span class="px-3 py-2 rounded-md bg-bg-panel"> TA:
                                {{ \Illuminate\Support\Str::limit($profile->mahasiswaTa->judul_ta, 60) }} </span>
                        @endif
        </div> {{-- Tautan akademik dosen --}} @if ($profile->isDosen() && ($profile->google_scholar || $profile->orcid || $profile->sinta || $profile->researchgate))
            <div class="mt-6 pt-4 border-t border-border">
                <h3 class="text-sm font-semibold mb-3">Tautan Akademik</h3>
                <div class="flex flex-wrap gap-2 text-sm">
                    @if ($profile->google_scholar)
                        <a href="{{ $profile->google_scholar }}" target="_blank" rel="noopener"
                            class="px-3 py-1.5 rounded-md bg-accent-blue/10 text-accent-blue hover:bg-accent-blue/10 hover:bg-accent-blue/15">🎓
                            Google Scholar</a>
                        @endif @if ($profile->orcid)
                            <a href="https://orcid.org/{{ $profile->orcid }}" target="_blank" rel="noopener"
                                class="px-3 py-1.5 rounded-md bg-accent-teal/10 text-accent-teal hover:bg-accent-teal/10">🆔
                                ORCID</a>
                            @endif @if ($profile->sinta)
                                <a href="https://sinta.kemdikbud.go.id/authors/profile/{{ $profile->sinta }}"
                                    target="_blank" rel="noopener"
                                    class="px-3 py-1.5 rounded-md bg-status-pending/10 text-status-pending">📊
                                    SINTA</a>
                                @endif @if ($profile->researchgate)
                                    <a href="{{ $profile->researchgate }}" target="_blank" rel="noopener"
                                        class="px-3 py-1.5 rounded-md bg-bg-hover hover:bg-bg-hover hover:bg-bg-hover">🔬
                                        ResearchGate</a>
                                @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
