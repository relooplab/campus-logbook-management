@extends("layouts.app") @section("title", "Profil") @section("content")
<div class="max-w-2xl space-y-6">
    <h1 class="text-xl font-bold">Profil</h1> {{-- Data profil --}} <div
        class="bg-bg-surface rounded-xl border border-border p-6 space-y-4">
        <div class="flex items-center gap-4">
            <div
                class="h-20 w-20 rounded-full overflow-hidden bg-brand text-white flex items-center justify-center text-2xl font-bold flex-shrink-0">
                @if ($user->photoUrl())
                    <img src="{{ $user->photoUrl() }}" class="h-full w-full object-cover" alt="Foto profil">
                @else
                    {{ $user->initials() }}
                @endif
            </div>
            <div>
                <h2 class="font-semibold text-lg">{{ $user->name }}</h2>
                <p class="text-sm text-text-secondary">{{ $user->email }}</p>
                @foreach ($user->roles->whereNotIn('name', ['admin', 'system_admin']) as $r)
                    <span
                        class="inline-block px-2 py-0.5 rounded-full text-xs bg-bg-panel mt-1 mr-1">{{ ucfirst($r->name) }}</span>
                @endforeach
            </div>
        </div>
        <form method="POST" action="{{ route("profile.update") }}" enctype="multipart/form-data" class="space-y-4">
            @csrf @method("PUT") <div class="grid sm:grid-cols-2 gap-4">
                <div> <label class="block text-sm font-medium mb-1" for="name">Nama Lengkap</label> <input
                        type="text" name="name" id="name" required value="{{ old("name", $user->name) }}"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none">
                    @error("name")
                        <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div> <label class="block text-sm font-medium mb-1" for="identifier">NIM / NIDN @if ($user->isMahasiswa())<span class="text-status-danger">*</span>@endif</label> <input
                        type="text" name="identifier" id="identifier" @if ($user->isMahasiswa()) required @endif
                        value="{{ old("identifier", $user->identifier) }}"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    @error("identifier")
                        <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div> <label class="block text-sm font-medium mb-1">Foto Profil</label> <input type="file"
                        name="photo" accept="image/*" class="w-full text-sm"> @error("photo")
                        <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div> <label class="block text-sm font-medium mb-1" for="whatsapp">Nomor WhatsApp @if ($user->isMahasiswa())<span class="text-status-danger">*</span>@endif</label> <input
                        type="text" name="whatsapp" id="whatsapp" @if ($user->isMahasiswa()) required @endif value="{{ old("whatsapp", $user->whatsapp) }}"
                        placeholder="6281xxxxxx"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    @if ($user->isDosen())
                        <label class="mt-2 flex items-center gap-2 text-xs text-text-secondary cursor-pointer">
                            <input type="checkbox" name="bimbingan_via_whatsapp" value="1"
                                @checked(old('bimbingan_via_whatsapp', $user->bimbingan_via_whatsapp))
                                class="rounded border-border">
                            Kontak mahasiswa lewat jalur ini untuk bimbingan
                        </label>
                    @endif
                </div>
                <div> <label class="block text-sm font-medium mb-1" for="telegram">Telegram</label> <input
                        type="text" name="telegram" id="telegram" value="{{ old("telegram", $user->telegram) }}"
                        placeholder="@username"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    @if ($user->isDosen())
                        <label class="mt-2 flex items-center gap-2 text-xs text-text-secondary cursor-pointer">
                            <input type="checkbox" name="bimbingan_via_telegram" value="1"
                                @checked(old('bimbingan_via_telegram', $user->bimbingan_via_telegram))
                                class="rounded border-border">
                            Kontak mahasiswa lewat jalur ini untuk bimbingan
                        </label>
                    @endif
                </div>
                <div> <label class="block text-sm font-medium mb-1" for="linkedin">LinkedIn</label> <input
                        type="url" name="linkedin" id="linkedin" value="{{ old("linkedin", $user->linkedin) }}"
                        placeholder="https://linkedin.com/in/..."
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
            </div>
            @if ($user->isDosen())
                <div class="pt-2 border-t border-border">
                    <p class="text-sm font-semibold mb-3">Tautan Akademik (Dosen)</p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div> <label class="block text-sm font-medium mb-1" for="google_scholar">Google Scholar</label>
                            <input type="url" name="google_scholar" id="google_scholar"
                                value="{{ old("google_scholar", $user->google_scholar) }}"
                                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                        </div>
                        <div> <label class="block text-sm font-medium mb-1" for="orcid">ORCID</label> <input
                                type="text" name="orcid" id="orcid" value="{{ old("orcid", $user->orcid) }}"
                                placeholder="0000-0000-0000-0000"
                                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                        <div> <label class="block text-sm font-medium mb-1" for="sinta">SINTA ID</label> <input
                                type="text" name="sinta" id="sinta" value="{{ old("sinta", $user->sinta) }}"
                                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                        <div> <label class="block text-sm font-medium mb-1" for="researchgate">ResearchGate</label>
                            <input type="url" name="researchgate" id="researchgate"
                                value="{{ old("researchgate", $user->researchgate) }}"
                                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium mb-1" for="jadwal_bimbingan_url">Link Jadwalkan Bimbingan</label>
                            <input type="url" name="jadwal_bimbingan_url" id="jadwal_bimbingan_url"
                                value="{{ old("jadwal_bimbingan_url", $user->jadwal_bimbingan_url) }}"
                                placeholder="https://cal.com/... atau https://forms.gle/..."
                                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                            <p class="text-xs text-text-secondary mt-1">Link ini akan ditampilkan sebagai card di halaman Jadwalkan Bimbingan agar mahasiswa dapat memesan/bergabung sesi bimbingan Anda. Kosongkan jika belum tersedia.</p>
                        </div>
                    </div>
                </div>
            @endif
            <div class="flex items-center gap-3 pt-2"> <button
                    class="px-4 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm font-semibold">Simpan
                    Profil</button> </div>
        </form>
        @include('partials.profile-affiliation', ['affUser' => $user])
    </div>

    @if ($user->isDosen())
        <div class="bg-bg-surface rounded-xl border border-border p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold">Data Institusi / Afiliasi</h2>
                    <p class="text-sm text-text-secondary mt-0.5">Perguruan tinggi, fakultas, departemen, dan program studi Anda.</p>
                </div>
                <a href="{{ route('profile.affiliation') }}"
                    class="px-4 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm font-semibold">Kelola</a>
            </div>
        </div>
    @endif

    @if ($user->isMahasiswa() && $programs->isNotEmpty())
        @foreach ($programs as $prog)
            <div class="bg-bg-surface rounded-xl border border-border p-6">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <h2 class="font-semibold">{{ $prog->jenisLabel() }}</h2>
                    @include('partials.status-badge', ['status' => $prog->status_ta])
                </div>
                @if ($prog->isKp())
                    <p class="text-sm mb-3"><span class="text-text-secondary">Tempat Kerja Praktek:</span> <span class="font-medium text-text-primary break-words">{{ $prog->tempat_kp ?: 'Belum diisi' }}</span></p>
                @else
                    <p class="text-sm mb-3"><span class="text-text-secondary">Judul Tugas Akhir:</span> <span class="font-medium text-text-primary break-words">{{ $prog->judul_ta ?: 'Belum diisi' }}</span></p>
                @endif
                <form method="POST" action="{{ route('profile.program', $prog) }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    @if ($prog->isKp())
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Tempat Kerja Praktek <span class="text-status-danger">*</span></label>
                            <input type="text" name="tempat_kp" required value="{{ old('tempat_kp', $prog->tempat_kp) }}" placeholder="Contoh: PT Teknologi Indonesia" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                            @error('tempat_kp') <p class="text-status-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Judul Tugas Akhir <span class="text-status-danger">*</span></label>
                            <input type="text" name="judul_ta" required value="{{ old('judul_ta', $prog->judul_ta) }}" placeholder="Contoh: Rancang Bangun Sistem ..." class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                            @error('judul_ta') <p class="text-status-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <button class="px-4 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm font-semibold">Simpan {{ $prog->isKp() ? 'Tempat KP' : 'Judul' }}</button>
                </form>
            </div>
        @endforeach
    @endif

    {{-- Ganti kata sandi --}} <div class="bg-bg-surface rounded-xl border border-border p-6 space-y-4">
        <h2 class="font-semibold">Ganti Kata Sandi</h2>
        <form method="POST" action="{{ route("profile.password") }}" class="space-y-4"> @csrf @method("PUT") <div>
                <label class="block text-sm font-medium mb-1" for="current_password">Kata Sandi Saat Ini</label>
                <input type="password" name="current_password" id="current_password" required
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                @error("current_password")
                    <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div> <label class="block text-sm font-medium mb-1" for="password">Kata Sandi Baru</label> <input
                        type="password" name="password" id="password" required minlength="6"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    @error("password")
                        <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div> <label class="block text-sm font-medium mb-1" for="password_confirmation">Konfirmasi</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required
                        minlength="6" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                </div>
            </div> <button class="px-4 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm">Ubah
                Kata
                Sandi</button>
        </form>
    </div>
</div>
@endsection
