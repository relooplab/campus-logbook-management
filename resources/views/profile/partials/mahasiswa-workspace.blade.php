@php
    $field = 'w-full min-w-0 rounded-control border border-border bg-bg-surface px-3 py-2.5 text-sm text-text-primary placeholder:text-text-secondary focus:outline-none focus:ring-2 focus:ring-brand/40';
    $pivot = $affiliation?->pivot;
    $faculty = $pivot?->faculty_id ? \App\Models\Faculty::find($pivot->faculty_id) : null;
    $department = $pivot?->department_id ? \App\Models\Department::find($pivot->department_id) : null;
    $prodi = $pivot?->study_program_id ? \App\Models\StudyProgram::find($pivot->study_program_id) : null;
    $affiliationHasErrors = $errors->hasAny(['university_id', 'faculty_id', 'department_id', 'study_program_id']);
    $emailHasErrors = $errors->has('email') || $errors->has('email_confirmation') || (old('email') && $errors->has('current_password'));
    $activeProgram = $programs->firstWhere('status_ta', \App\Models\MahasiswaTa::STATUS_AKTIF);
@endphp
<div class="profile-workspace space-y-6">
    <header>
        <h1 class="font-heading text-2xl font-bold">Profil Mahasiswa</h1>
        <p class="mt-1 text-sm text-text-secondary">Kelola informasi pribadi, akademik, dan keamanan akun Anda.</p>
    </header>

    <div class="profile-workspace-grid">
        <aside class="card min-w-0 self-start p-5 sm:p-6" aria-label="Ringkasan profil">
            <h2 class="font-heading font-semibold">Informasi Profil</h2>
            <div class="mt-5 flex flex-col items-center text-center">
                <div id="profile-avatar" class="avatar h-24 w-24 overflow-hidden text-2xl">
                    @if ($user->photoUrl())
                        <img src="{{ $user->photoUrl() }}" alt="Foto profil {{ $user->name }}" class="h-full w-full object-cover">
                    @else
                        {{ $user->initials() }}
                    @endif
                </div>
                <p class="mt-4 max-w-full break-words text-lg font-semibold">{{ $user->name }}</p>
                <div class="mt-2 flex flex-wrap justify-center gap-1">
                    @foreach ($user->roles->whereNotIn('name', ['admin', 'system_admin']) as $role)
                        <span class="rounded-full bg-bg-panel px-2.5 py-1 text-xs text-text-secondary">{{ ucfirst($role->name) }}</span>
                    @endforeach
                </div>
                <div class="mt-4 text-sm"><span class="block text-xs text-text-secondary">NIM</span><span class="font-mono break-all">{{ $user->nim ?: 'Belum diisi' }}</span></div>
                <label for="profile-photo" class="btn-ghost mt-5 inline-flex cursor-pointer items-center gap-2 px-4 py-2.5 text-sm font-medium focus-within:ring-2 focus-within:ring-brand/40">
                    <span class="material-symbols-outlined icon-sm" aria-hidden="true">photo_camera</span> Ubah Foto
                    <input id="profile-photo" name="photo" type="file" accept="image/*" form="mahasiswa-profile-form" class="sr-only" aria-describedby="photo-hint">
                </label>
                <p id="photo-hint" class="mt-2 text-xs text-text-secondary">Gambar maksimal 5 MB. Simpan profil untuk mengunggah.</p>
                <p id="photo-filename" class="mt-1 max-w-full break-all text-xs text-text-secondary" aria-live="polite"></p>
                @error('photo') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror
            </div>

            <section class="mt-6 border-t border-border pt-5" aria-labelledby="student-affiliation-title">
                <h3 id="student-affiliation-title" class="font-heading text-sm font-semibold">Afiliasi Akademik</h3>
                <p class="mt-1 text-xs text-text-secondary">Lengkapi hingga program studi sebelum memilih dosen.</p>
                <ul class="mt-4 space-y-3 text-sm text-text-secondary">
                    @foreach ([['account_balance', $affiliation?->name, 'Perguruan Tinggi'], ['apartment', $faculty?->name, 'Fakultas'], ['domain', $department?->name, 'Departemen'], ['school', $prodi?->name, 'Program Studi']] as [$icon, $value, $label])
                        <li class="flex min-w-0 items-start gap-2"><span class="material-symbols-outlined icon-sm mt-0.5 shrink-0 text-brand" aria-hidden="true">{{ $icon }}</span><span class="min-w-0 break-words"><span class="sr-only">{{ $label }}: </span>{{ $value ?: 'Belum ditetapkan' }}</span></li>
                    @endforeach
                </ul>
                <button type="button" id="affiliation-toggle" aria-controls="kartu-afiliasi" aria-expanded="{{ $affiliationHasErrors ? 'true' : 'false' }}" class="btn-ghost mt-5 flex w-full items-center justify-center gap-2 px-3 py-2.5 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                    <span class="material-symbols-outlined icon-sm" aria-hidden="true">edit</span> Kelola Afiliasi
                </button>
                <div id="kartu-afiliasi" class="mt-4 border-t border-border pt-4 {{ $affiliationHasErrors ? '' : 'hidden' }}">
                    <form method="POST" action="{{ route('profile.affiliation-mahasiswa.update') }}" class="space-y-3">
                        @csrf
                        @foreach ([['aff-university', 'university_id', 'Perguruan Tinggi'], ['aff-faculty', 'faculty_id', 'Fakultas'], ['aff-department', 'department_id', 'Departemen'], ['aff-prodi', 'study_program_id', 'Program Studi']] as [$id, $name, $label])
                            <div class="min-w-0">
                                <label for="{{ $id }}" class="mb-1 block text-sm font-medium">{{ $label }} <span class="text-status-danger">*</span></label>
                                <select name="{{ $name }}" id="{{ $id }}" required {{ $name === 'university_id' ? '' : 'disabled' }} class="{{ $field }}"><option value="">— Pilih {{ strtolower($label) }} —</option></select>
                                @error($name) <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                        <div class="flex flex-wrap gap-2 pt-1">
                            <button type="submit" class="btn-primary px-4 py-2.5 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Simpan Afiliasi</button>
                            <button type="button" id="affiliation-cancel" class="btn-ghost px-4 py-2.5 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Batal</button>
                        </div>
                    </form>
                </div>
            </section>
            @if ($activeProgram)
                <section class="mt-5 border-t border-border pt-5" aria-labelledby="academic-status-title">
                    <h3 id="academic-status-title" class="font-heading text-sm font-semibold">Status Akademik</h3>
                    <div class="mt-3 flex flex-wrap items-start justify-between gap-2 rounded-control border border-border bg-bg-panel p-3 text-sm">
                        <p class="min-w-0 flex-1 break-words text-text-secondary">{{ $activeProgram->jenisLabel() }} sedang aktif{{ $prodi ? ' pada '.$prodi->name : '' }}.</p>
                        @include('partials.status-badge', ['status' => $activeProgram->status_ta])
                    </div>
                </section>
            @endif
        </aside>

        <div class="min-w-0 space-y-6">
            <section class="card min-w-0 p-5 sm:p-6" aria-labelledby="student-personal-title">
                <h2 id="student-personal-title" class="font-heading font-semibold">Informasi Pribadi</h2>
                <p class="mt-1 text-sm text-text-secondary">Perbarui informasi kontak dan profil Anda.</p>
                <div class="mt-5 rounded-control border border-border bg-bg-panel p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0"><p class="text-sm font-medium">Alamat Email</p><p class="break-all text-sm text-text-secondary">{{ $user->email }}</p></div>
                        <button type="button" id="email-toggle" aria-controls="email-change-form" aria-expanded="{{ $emailHasErrors ? 'true' : 'false' }}" class="rounded-control px-2 py-2 text-sm font-semibold text-brand hover:bg-bg-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Ubah Email</button>
                    </div>
                    <form method="POST" action="{{ route('profile.email') }}" id="email-change-form" class="mt-4 space-y-3 border-t border-border pt-4 {{ $emailHasErrors ? '' : 'hidden' }}">
                        @csrf @method('PUT')
                        <div><label for="new-email" class="mb-1 block text-sm font-medium">Email Baru</label><input id="new-email" type="email" name="email" required value="{{ old('email') }}" class="{{ $field }}">@error('email') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror</div>
                        <div><label for="email-confirmation" class="mb-1 block text-sm font-medium">Konfirmasi Email Baru</label><input id="email-confirmation" type="email" name="email_confirmation" required value="{{ old('email_confirmation') }}" class="{{ $field }}">@error('email_confirmation') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror</div>
                        <div><label for="email-current-password" class="mb-1 block text-sm font-medium">Password Saat Ini</label><input id="email-current-password" type="password" name="current_password" required class="{{ $field }}">@if ($emailHasErrors) @error('current_password') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror @endif</div>
                        @if (session('info')) <p class="text-xs text-status-info">{{ session('info') }}</p> @endif
                        <button type="submit" class="btn-primary px-4 py-2.5 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Simpan Email</button>
                    </form>
                </div>
                <form id="mahasiswa-profile-form" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-5 space-y-5">
                    @csrf @method('PUT')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="min-w-0"><label for="name" class="mb-1 block text-sm font-medium">Nama Lengkap</label><input id="name" type="text" name="name" required value="{{ old('name', $user->name) }}" class="{{ $field }}">@error('name') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror</div>
                        <div class="min-w-0"><label for="nim" class="mb-1 block text-sm font-medium">NIM <span class="text-status-danger">*</span></label><input id="nim" type="text" name="nim" required maxlength="30" value="{{ old('nim', $user->nim) }}" class="{{ $field }}">@error('nim') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror</div>
                        <div class="min-w-0"><label for="whatsapp" class="mb-1 block text-sm font-medium">Nomor WhatsApp <span class="text-status-danger">*</span></label><input id="whatsapp" type="text" name="whatsapp" required value="{{ old('whatsapp', $user->whatsapp) }}" placeholder="6281xxxxxx" class="{{ $field }}">@error('whatsapp') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror</div>
                        <div class="min-w-0"><label for="telegram" class="mb-1 block text-sm font-medium">Telegram</label><input id="telegram" type="text" name="telegram" value="{{ old('telegram', $user->telegram) }}" placeholder="@username" class="{{ $field }}">@error('telegram') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror</div>
                        <div class="min-w-0 sm:col-span-2"><label for="linkedin" class="mb-1 block text-sm font-medium">LinkedIn</label><input id="linkedin" type="url" name="linkedin" value="{{ old('linkedin', $user->linkedin) }}" placeholder="https://linkedin.com/in/..." class="{{ $field }}">@error('linkedin') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror</div>
                    </div>
                    <div class="flex justify-end border-t border-border pt-5"><button type="submit" class="btn-primary px-5 py-2.5 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-60">Simpan Profil</button></div>
                </form>
            </section>

            <section class="card min-w-0 p-5 sm:p-6" aria-labelledby="student-academic-title">
                <h2 id="student-academic-title" class="font-heading font-semibold">Informasi Akademik</h2>
                <p class="mt-1 text-sm text-text-secondary">Informasi terkait program akademik Anda.</p>
                @if ($programs->isEmpty())
                    <p class="mt-5 rounded-control border border-border bg-bg-panel p-4 text-sm text-text-secondary">Belum ada program tugas akhir atau kerja praktik.</p>
                @else
                    <div class="mt-5 space-y-4">
                        @foreach ($programs as $prog)
                            <section class="min-w-0 overflow-hidden rounded-control border border-border" aria-labelledby="program-title-{{ $prog->id }}">
                                <div class="flex flex-wrap items-center justify-between gap-2 bg-bg-panel px-4 py-3">
                                    <h3 id="program-title-{{ $prog->id }}" class="font-semibold">{{ $prog->jenisLabel() }}</h3>
                                    @include('partials.status-badge', ['status' => $prog->status_ta])
                                </div>
                                <div class="space-y-3 p-4">
                                    <p class="min-w-0 break-words text-sm"><span class="block text-xs text-text-secondary">{{ $prog->isKp() ? 'Tempat Kerja Praktek' : 'Judul Tugas Akhir' }}</span><span class="font-medium">{{ ($prog->isKp() ? $prog->tempat_kp : $prog->judul_ta) ?: 'Belum diisi' }}</span></p>
                                    <form method="POST" action="{{ route('profile.program', $prog) }}" class="space-y-3">
                                        @csrf @method('PUT')
                                        @if ($prog->isKp())
                                            <div><label for="tempat-kp-{{ $prog->id }}" class="mb-1 block text-sm font-medium">Tempat Kerja Praktek <span class="text-status-danger">*</span></label><input id="tempat-kp-{{ $prog->id }}" type="text" name="tempat_kp" required value="{{ old('tempat_kp', $prog->tempat_kp) }}" placeholder="Contoh: PT Teknologi Indonesia" class="{{ $field }}">@error('tempat_kp') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror</div>
                                        @else
                                            <div><label for="judul-ta-{{ $prog->id }}" class="mb-1 block text-sm font-medium">Judul Tugas Akhir <span class="text-status-danger">*</span></label><input id="judul-ta-{{ $prog->id }}" type="text" name="judul_ta" required value="{{ old('judul_ta', $prog->judul_ta) }}" placeholder="Contoh: Rancang Bangun Sistem ..." class="{{ $field }}">@error('judul_ta') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror</div>
                                        @endif
                                        <div class="flex justify-end"><button type="submit" class="btn-primary px-4 py-2.5 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Simpan {{ $prog->isKp() ? 'Tempat KP' : 'Judul' }}</button></div>
                                    </form>
                                    @if ($prog->isKp() && in_array($prog->fase, ['laporan', 'seminar_kp', 'selesai'], true) && $programs->where('jenis', 'ta')->isEmpty())
                                        <div class="border-t border-border pt-3"><a href="{{ route('profile.select-dosen') }}" class="btn-ghost inline-flex items-center gap-2 px-3 py-2.5 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Lanjut ke Tugas Akhir</a></div>
                                    @endif
                                </div>
                            </section>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>

    <section class="card min-w-0 p-5 sm:p-6" aria-labelledby="student-security-title">
        <h2 id="student-security-title" class="flex items-center gap-2 font-heading font-semibold"><span class="material-symbols-outlined icon-md text-brand" aria-hidden="true">shield</span> Keamanan Akun</h2>
        <p class="mt-1 text-sm text-text-secondary">Ubah kata sandi untuk menjaga keamanan akun Anda.</p>
        <form method="POST" action="{{ route('profile.password') }}" class="mt-5 space-y-4">
            @csrf @method('PUT')
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="min-w-0"><label for="current_password" class="mb-1 block text-sm font-medium">Kata Sandi Saat Ini</label><input id="current_password" type="password" name="current_password" autocomplete="current-password" required class="{{ $field }}">@error('current_password') @if (! $emailHasErrors) <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @endif @enderror</div>
                <div class="min-w-0"><label for="password" class="mb-1 block text-sm font-medium">Kata Sandi Baru</label><input id="password" type="password" name="password" autocomplete="new-password" required minlength="6" class="{{ $field }}">@error('password') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror</div>
                <div class="min-w-0"><label for="password_confirmation" class="mb-1 block text-sm font-medium">Konfirmasi Kata Sandi Baru</label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required minlength="6" class="{{ $field }}">@error('password_confirmation') <p class="mt-1 text-xs text-status-danger">{{ $message }}</p> @enderror</div>
            </div>
            <div class="flex justify-end"><button type="submit" class="btn-primary px-5 py-2.5 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Ubah Kata Sandi</button></div>
        </form>
    </section>
    @if ($adminContactEmail)
        <div class="card p-4"><p class="text-sm font-medium">Perlu bantuan admin?</p><p class="mt-1 text-xs text-text-secondary">Hubungi <a href="mailto:{{ $adminContactEmail }}" class="break-all text-brand hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">{{ $adminContactEmail }}</a> untuk pertanyaan atau koreksi data (misalnya NIM).</p></div>
    @endif
</div>