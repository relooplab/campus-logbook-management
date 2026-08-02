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
                @foreach ($user->roles as $r)
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
                <div> <label class="block text-sm font-medium mb-1" for="identifier">NIM / NIDN</label> <input
                        type="text" name="identifier" id="identifier"
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
                <div> <label class="block text-sm font-medium mb-1" for="whatsapp">Nomor WhatsApp</label> <input
                        type="text" name="whatsapp" id="whatsapp" value="{{ old("whatsapp", $user->whatsapp) }}"
                        placeholder="6281xxxxxx"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                <div> <label class="block text-sm font-medium mb-1" for="telegram">Telegram</label> <input
                        type="text" name="telegram" id="telegram" value="{{ old("telegram", $user->telegram) }}"
                        placeholder="@username"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
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
                    </div>
                </div>
            @endif
            <div class="flex items-center gap-3 pt-2"> <button
                    class="px-4 py-2 rounded-md bg-brand hover:bg-brand-hover text-white text-sm font-semibold">Simpan
                    Profil</button> </div>
        </form>
    </div> {{-- Ganti kata sandi --}} <div class="bg-bg-surface rounded-xl border border-border p-6 space-y-4">
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
            </div> <button class="px-4 py-2 rounded-md bg-brand hover:bg-brand-hover text-white text-sm">Ubah
                Kata
                Sandi</button>
        </form>
    </div>
</div>
@endsection
