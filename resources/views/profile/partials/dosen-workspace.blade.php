@php
    $field = 'w-full min-w-0 rounded-control border border-border bg-bg-surface px-3 py-2.5 text-sm text-text-primary placeholder:text-text-secondary focus:outline-none focus:ring-2 focus:ring-brand/40';
    $univ = $affiliation;
    $pivot = $univ?->pivot;
    $faculty = $pivot?->faculty_id ? \App\Models\Faculty::find($pivot->faculty_id) : null;
    $department = $pivot?->department_id ? \App\Models\Department::find($pivot->department_id) : null;
    $prodi = $pivot?->study_program_id ? \App\Models\StudyProgram::find($pivot->study_program_id) : null;
@endphp
<div class="profile-workspace space-y-6">
    <header>
        <h1 class="font-heading text-2xl font-bold">Profil</h1>
        <p class="mt-1 text-sm text-text-secondary">Kelola informasi pribadi, kontak, dan akun.</p>
    </header>
    <div class="profile-workspace-grid">
        <aside class="card min-w-0 self-start p-5 sm:p-6" aria-label="Ringkasan profil">
            <div class="flex flex-col items-center text-center">
                <div id="profile-avatar" class="avatar h-24 w-24 overflow-hidden text-2xl">
                    @if ($user->photoUrl())
                        <img src="{{ $user->photoUrl() }}" alt="Foto profil {{ $user->name }}" class="h-full w-full object-cover">
                    @else
                        {{ $user->initials() }}
                    @endif
                </div>
                <h2 class="mt-4 max-w-full break-words text-lg font-semibold">{{ $user->name }}</h2>
                <p class="max-w-full break-all text-sm text-text-secondary">{{ $user->email }}</p>
                <div class="mt-2 flex flex-wrap justify-center gap-1">
                    @foreach ($user->roles->whereNotIn('name', ['admin', 'system_admin']) as $role)
                        <span class="rounded-full bg-bg-panel px-2.5 py-1 text-xs text-text-secondary">{{ ucfirst($role->name) }}</span>
                    @endforeach
                </div>
                <label for="profile-photo" class="btn-ghost mt-5 inline-flex cursor-pointer items-center gap-2 px-4 py-2.5 text-sm font-medium focus-within:ring-2 focus-within:ring-brand/40">
                    <span class="material-symbols-outlined icon-sm" aria-hidden="true">photo_camera</span> Ubah Foto
                    <input id="profile-photo" name="photo" type="file" accept="image/*" form="dosen-profile-form" class="sr-only" aria-describedby="photo-hint">
                </label>
                <p id="photo-hint" class="mt-2 text-xs text-text-secondary">Gambar maksimal 5 MB. Simpan perubahan untuk mengunggah.</p>
                <p id="photo-filename" class="mt-1 max-w-full break-all text-xs text-text-secondary" aria-live="polite"></p>
                @error('photo') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror
            </div>
            <div class="mt-6 border-t border-border pt-5">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-text-secondary">Afiliasi</h3>
                <ul class="mt-3 space-y-3 text-sm text-text-secondary">
                    @foreach ([['account_balance', $univ?->name], ['apartment', $faculty?->name], ['domain', $department?->name], ['school', $prodi?->name]] as [$icon, $value])
                        <li class="flex min-w-0 items-start gap-2"><span class="material-symbols-outlined icon-sm mt-0.5 shrink-0 text-brand" aria-hidden="true">{{ $icon }}</span><span class="min-w-0 break-words">{{ $value ?: 'Belum ditetapkan' }}</span></li>
                    @endforeach
                </ul>
                <a href="{{ route('profile.affiliation') }}" class="btn-ghost mt-5 flex w-full items-center justify-center gap-2 px-3 py-2.5 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand"><span class="material-symbols-outlined icon-sm" aria-hidden="true">settings</span> Kelola Afiliasi</a>
            </div>
        </aside>
        <div class="card min-w-0 p-5 sm:p-6">
            <section aria-labelledby="personal-title">
                <h2 id="personal-title" class="flex items-center gap-2 font-heading font-semibold"><span class="material-symbols-outlined icon-md text-brand" aria-hidden="true">person</span> Informasi Pribadi</h2>
                <div class="mt-4 rounded-control border border-border bg-bg-panel p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0"><p class="text-sm font-medium">Alamat Email</p><p class="break-all text-sm text-text-secondary">{{ $user->email }}</p></div>
                        <button type="button" aria-controls="email-change-form" aria-expanded="{{ $errors->has('email') || $errors->has('email_confirmation') || (old('email') && $errors->has('current_password')) ? 'true' : 'false' }}" onclick="var form=document.getElementById('email-change-form');form.classList.toggle('hidden');this.setAttribute('aria-expanded', !form.classList.contains('hidden'))" class="rounded-control px-2 py-2 text-sm font-semibold text-brand hover:bg-bg-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Ubah Email</button>
                    </div>
                    <form method="POST" action="{{ route('profile.email') }}" id="email-change-form" class="mt-4 space-y-3 border-t border-border pt-4 {{ $errors->has('email') || $errors->has('email_confirmation') || (old('email') && $errors->has('current_password')) ? '' : 'hidden' }}">
                        @csrf @method('PUT')
                        <div><label for="new-email" class="mb-1 block text-sm font-medium">Email Baru</label><input id="new-email" type="email" name="email" required value="{{ old('email') }}" class="{{ $field }}"></div>
                        <div><label for="email-confirmation" class="mb-1 block text-sm font-medium">Konfirmasi Email Baru</label><input id="email-confirmation" type="email" name="email_confirmation" required value="{{ old('email_confirmation') }}" class="{{ $field }}"></div>
                        <div><label for="email-current-password" class="mb-1 block text-sm font-medium">Password Saat Ini</label><input id="email-current-password" type="password" name="current_password" required class="{{ $field }}"></div>
                        @error('email') <p class="text-xs text-status-danger">{{ $message }}</p> @enderror
                        @error('email_confirmation') <p class="text-xs text-status-danger">{{ $message }}</p> @enderror
                        @error('current_password') <p class="text-xs text-status-danger">{{ $message }}</p> @enderror
                        @if (session('info')) <p class="text-xs text-status-info">{{ session('info') }}</p> @endif
                        <button type="submit" class="btn-primary px-4 py-2.5 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Simpan Email</button>
                    </form>
                </div>
            </section>
            <form id="dosen-profile-form" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-5 space-y-6">
                @csrf @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="min-w-0"><label for="name" class="mb-1 block text-sm font-medium">Nama Lengkap</label><input id="name" type="text" name="name" required value="{{ old('name', $user->name) }}" class="{{ $field }}">@error('name') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror</div>
                    <div class="min-w-0"><p class="mb-1 text-sm font-medium">NIDN</p>
                        @if ($user->nidn)
                            <div class="flex items-center gap-2 rounded-control border border-border bg-bg-panel px-3 py-2.5 text-sm text-text-secondary"><span class="material-symbols-outlined icon-sm" aria-hidden="true">lock</span>{{ $user->nidn }}</div>
                        @else
                            <label for="nidn" class="sr-only">NIDN</label><input id="nidn" type="text" name="nidn" inputmode="numeric" pattern="\d{10}" maxlength="10" value="{{ old('nidn') }}" placeholder="10 digit angka" class="{{ $field }}">
                        @endif
                        <p class="mt-1 text-xs text-text-secondary">NIDN hanya dapat diisi satu kali dan tidak dapat diubah sendiri setelah terisi. Jika salah, hubungi admin
                            @if ($adminContactEmail)
                                : <a href="mailto:{{ $adminContactEmail }}" class="text-brand hover:underline">{{ $adminContactEmail }}</a>
                            @endif
                            .
                        </p>
                        @error('nidn') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror
                    </div>
                </div>
                <section class="border-t border-border pt-5" aria-labelledby="contact-title">
                    <h2 id="contact-title" class="mb-4 flex items-center gap-2 font-heading font-semibold"><span class="material-symbols-outlined icon-md text-brand" aria-hidden="true">send</span> Kontak &amp; Komunikasi</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="min-w-0"><label for="whatsapp" class="mb-1 block text-sm font-medium">Nomor WhatsApp</label><input id="whatsapp" type="text" name="whatsapp" value="{{ old('whatsapp', $user->whatsapp) }}" placeholder="6281xxxxxx" class="{{ $field }}"><label class="mt-2 flex cursor-pointer items-center gap-2 text-xs text-text-secondary"><input type="checkbox" name="bimbingan_via_whatsapp" value="1" @checked(old('bimbingan_via_whatsapp', $user->bimbingan_via_whatsapp)) class="h-4 w-4 shrink-0 rounded border-border text-brand focus:ring-brand">Kontak mahasiswa lewat jalur ini untuk bimbingan</label>@error('whatsapp') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror</div>
                        <div class="min-w-0"><label for="telegram" class="mb-1 block text-sm font-medium">Telegram</label><input id="telegram" type="text" name="telegram" value="{{ old('telegram', $user->telegram) }}" placeholder="@username" class="{{ $field }}"><label class="mt-2 flex cursor-pointer items-center gap-2 text-xs text-text-secondary"><input type="checkbox" name="bimbingan_via_telegram" value="1" @checked(old('bimbingan_via_telegram', $user->bimbingan_via_telegram)) class="h-4 w-4 shrink-0 rounded border-border text-brand focus:ring-brand">Kontak mahasiswa lewat jalur ini untuk bimbingan</label>@error('telegram') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror</div>
                        <div class="min-w-0 sm:col-span-2"><label for="linkedin" class="mb-1 block text-sm font-medium">LinkedIn</label><input id="linkedin" type="url" name="linkedin" value="{{ old('linkedin', $user->linkedin) }}" placeholder="https://linkedin.com/in/..." class="{{ $field }}">@error('linkedin') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror</div>
                    </div>
                </section>
                <section class="border-t border-border pt-5" aria-labelledby="links-title">
                    <h2 id="links-title" class="mb-4 flex items-center gap-2 font-heading font-semibold"><span class="material-symbols-outlined icon-md text-brand" aria-hidden="true">link</span> Tautan Akademik</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach (['google_scholar' => ['Google Scholar', 'url'], 'orcid' => ['ORCID', 'text'], 'sinta' => ['SINTA ID', 'text'], 'researchgate' => ['ResearchGate', 'url'], 'jadwal_bimbingan_url' => ['Link Jadwalkan Bimbingan', 'url']] as $key => [$label, $type])
                            <div class="min-w-0 {{ $key === 'jadwal_bimbingan_url' ? 'sm:col-span-2' : '' }}"><label for="{{ $key }}" class="mb-1 block text-sm font-medium">{{ $label }}</label><input id="{{ $key }}" type="{{ $type }}" name="{{ $key }}" value="{{ old($key, $user->$key) }}" placeholder="{{ $key === 'orcid' ? '0000-0000-0000-0000' : ($key === 'jadwal_bimbingan_url' ? 'https://cal.com/... atau https://forms.gle/...' : '') }}" class="{{ $field }}">
                                @if ($key === 'jadwal_bimbingan_url') <p class="mt-1 text-xs text-text-secondary">Link ini akan ditampilkan sebagai card di halaman Jadwalkan Bimbingan agar mahasiswa dapat memesan/bergabung sesi bimbingan Anda. Kosongkan jika belum tersedia.</p> @endif
                                @error($key) <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </section>
                <div class="flex justify-end border-t border-border pt-5"><button type="submit" class="btn-primary px-5 py-2.5 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-60">Simpan Perubahan</button></div>
            </form>
        </div>
    </div>
    <section class="card p-5 sm:p-6" aria-labelledby="security-title">
        <h2 id="security-title" class="mb-4 flex items-center gap-2 font-heading font-semibold"><span class="material-symbols-outlined icon-md text-brand" aria-hidden="true">shield</span> Keamanan Akun</h2>
        <form method="POST" action="{{ route('profile.password') }}" class="space-y-4">
            @csrf @method('PUT')
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="min-w-0"><label for="current_password" class="mb-1 block text-sm font-medium">Kata Sandi Saat Ini</label><input id="current_password" type="password" name="current_password" required class="{{ $field }}">@error('current_password') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror</div>
                <div class="min-w-0"><label for="password" class="mb-1 block text-sm font-medium">Kata Sandi Baru</label><input id="password" type="password" name="password" required minlength="6" class="{{ $field }}">@error('password') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror</div>
                <div class="min-w-0"><label for="password_confirmation" class="mb-1 block text-sm font-medium">Konfirmasi</label><input id="password_confirmation" type="password" name="password_confirmation" required minlength="6" class="{{ $field }}">@error('password_confirmation') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror</div>
            </div>
            <div class="flex justify-end"><button type="submit" class="btn-primary px-5 py-2.5 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Ubah Kata Sandi</button></div>
        </form>
    </section>
    @if ($adminContactEmail)
        <div class="card p-4"><p class="text-sm font-medium">Perlu bantuan admin?</p><p class="mt-1 text-xs text-text-secondary">Hubungi <a href="mailto:{{ $adminContactEmail }}" class="text-brand hover:underline">{{ $adminContactEmail }}</a> untuk pertanyaan atau koreksi data (misalnya NIDN).</p></div>
    @endif
</div>