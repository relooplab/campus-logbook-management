@extends("layouts.app") @section("title", "Profil Institusi") @section("content")
<div class="max-w-3xl space-y-6">
    <div>
        <h1 class="text-xl font-bold">Profil Institusi</h1>
        <p class="text-sm text-text-secondary">Informasi ini dipakai untuk kop dokumen resmi (rekap bimbingan), pengirim
            email, dan identitas aplikasi.</p>
    </div>
    <div class="bg-bg-surface rounded-xl border border-border p-6">
        <form method="POST" action="{{ route("admin.institution.update") }}" enctype="multipart/form-data"
            class="space-y-4"> @csrf <div class="grid sm:grid-cols-2 gap-4">
                <div> <label class="block text-sm font-medium mb-1">Nama Aplikasi</label> <input type="text"
                        name="app_name" value="{{ old("app_name", $institution->app_name) }}" required
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none">
                </div>
                <div> <label class="block text-sm font-medium mb-1">Nama Institusi</label> <input type="text"
                        name="institution_name" value="{{ old("institution_name", $institution->institution_name) }}"
                        required class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                <div> <label class="block text-sm font-medium mb-1">Fakultas</label> <input type="text"
                        name="faculty" value="{{ old("faculty", $institution->faculty) }}"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                <div> <label class="block text-sm font-medium mb-1">Program Studi</label> <input type="text"
                        name="study_program" value="{{ old("study_program", $institution->study_program) }}"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                <div class="sm:col-span-2"> <label class="block text-sm font-medium mb-1">Alamat</label> <input
                        type="text" name="address" value="{{ old("address", $institution->address) }}"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                <div> <label class="block text-sm font-medium mb-1">Kota</label> <input type="text" name="city"
                        value="{{ old("city", $institution->city) }}"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                <div> <label class="block text-sm font-medium mb-1">Telepon</label> <input type="text" name="phone"
                        value="{{ old("phone", $institution->phone) }}"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                <div> <label class="block text-sm font-medium mb-1">Email</label> <input type="email" name="email"
                        value="{{ old("email", $institution->email) }}"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                <div> <label class="block text-sm font-medium mb-1">Website</label> <input type="url" name="website"
                        value="{{ old("website", $institution->website) }}"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                <div class="sm:col-span-2"> <label class="block text-sm font-medium mb-1">Catatan Kaki (dokumen)</label>
                    <textarea name="footer_note" rows="2"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">{{ old("footer_note", $institution->footer_note) }}</textarea>
                </div>
                <div class="sm:col-span-2"> <label class="block text-sm font-medium mb-1">Logo (opsional,
                        PNG/JPG)</label> <input type="file" name="logo" accept="image/png,image/jpeg"
                        class="w-full text-sm">
                    @if ($institution->logo_path)
                        <p class="text-xs text-text-secondary mt-1">Logo saat ini tersimpan.</p>
                    @endif
                </div>
                <div class="sm:col-span-2 pt-2 border-t border-border">
                    <p class="text-sm font-semibold mb-3">Pengaturan Upload</p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div> <label class="block text-sm font-medium mb-1">Maks Ukuran Upload (MB)</label>
                            <input type="number" name="max_upload_size_mb" min="1" max="100" required
                                value="{{ old("max_upload_size_mb", $institution->max_upload_size_mb ?? 10) }}"
                                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                        </div>
                        <div> <label class="block text-sm font-medium mb-1">Jenis File Diizinkan</label>
                            <input type="text" name="allowed_file_types" required
                                value="{{ old("allowed_file_types", $institution->allowed_file_types ?? "pdf") }}"
                                placeholder="pdf,doc,docx"
                                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                            <p class="text-xs text-text-secondary mt-1">Pisahkan dengan koma, mis. pdf,doc,docx</p>
                        </div>
                    </div>
                </div>
                <div class="sm:col-span-2 pt-2 border-t border-border">
                    <p class="text-sm font-semibold mb-3">Catatan Hardcopy Seminar/Sidang</p>
                    <label class="block text-sm font-medium mb-1">Catatan default (dapat diubah dosen per submission)</label>
                    <textarea name="seminar_hardcopy_note" rows="3"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">{{ old("seminar_hardcopy_note", $institution->seminar_hardcopy_note) }}</textarea>
                    <p class="text-xs text-text-secondary mt-1">Ditampilkan pada form pemberian bahan seminar/sidang.</p>
                </div>
                <div class="sm:col-span-2 pt-2 border-t border-border">
                    <p class="text-sm font-semibold mb-3">Pengaturan Email (SMTP)</p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div> <label class="block text-sm font-medium mb-1">Mailer</label>
                            <select name="mail_mailer"
                                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                                @foreach (["smtp", "log", "array", "sendmail", "mailgun", "ses", "postmark", "resend"] as $m)
                                    <option value="{{ $m }}" @selected(old("mail_mailer", $institution->mail_mailer ?? "smtp") === $m)>{{ strtoupper($m) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div> <label class="block text-sm font-medium mb-1">Host SMTP</label>
                            <input type="text" name="mail_host" value="{{ old("mail_host", $institution->mail_host) }}"
                                placeholder="smtp.gmail.com"
                                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                        </div>
                        <div> <label class="block text-sm font-medium mb-1">Port</label>
                            <input type="number" name="mail_port" min="1" max="65535"
                                value="{{ old("mail_port", $institution->mail_port) }}"
                                placeholder="587"
                                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                        </div>
                        <div> <label class="block text-sm font-medium mb-1">Enkripsi</label>
                            <select name="mail_encryption"
                                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                                <option value="">Tanpa enkripsi</option>
                                <option value="tls" @selected(old("mail_encryption", $institution->mail_encryption) === "tls")>TLS</option>
                                <option value="ssl" @selected(old("mail_encryption", $institution->mail_encryption) === "ssl")>SSL</option>
                            </select>
                        </div>
                        <div> <label class="block text-sm font-medium mb-1">Username</label>
                            <input type="text" name="mail_username" value="{{ old("mail_username", $institution->mail_username) }}"
                                placeholder="email@domain.com"
                                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                        </div>
                        <div> <label class="block text-sm font-medium mb-1">Password</label>
                            <input type="password" name="mail_password" value="{{ old("mail_password", $institution->mail_password) }}"
                                placeholder="••••••••"
                                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                        </div>
                        <div> <label class="block text-sm font-medium mb-1">From Address</label>
                            <input type="email" name="mail_from_address" value="{{ old("mail_from_address", $institution->mail_from_address) }}"
                                placeholder="no-reply@domain.com"
                                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                        </div>
                        <div> <label class="block text-sm font-medium mb-1">From Name</label>
                            <input type="text" name="mail_from_name" value="{{ old("mail_from_name", $institution->mail_from_name) }}"
                                placeholder="Campus Logbook Management"
                                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                        </div>
                    </div>
                    <p class="text-xs text-text-secondary mt-2">Kosongkan field yang tidak digunakan. Konfigurasi ini diterapkan otomatis untuk semua notifikasi email aplikasi.</p>
                </div>
            </div>
            <div class="flex items-center gap-3 pt-2"> <button
                    class="px-4 py-2 rounded-md bg-brand hover:bg-brand-hover text-[#0b1420] text-sm font-semibold">Simpan</button>
            </div>
        </form>
    </div>

    {{-- Kirim email uji --}}
    <div class="bg-bg-surface rounded-xl border border-border p-6">
        <h2 class="font-semibold mb-1">Kirim Email Uji</h2>
        <p class="text-sm text-text-secondary mb-4">Verifikasi konfigurasi SMTP dengan mengirim email percobaan ke alamat Anda.</p>
        <form method="POST" action="{{ route("admin.institution.test-mail") }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium mb-1" for="test-mail-to">Alamat Email Tujuan</label>
                <input type="email" name="to" id="test-mail-to" required
                    value="{{ old("to", auth()->user()->email) }}"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
            </div>
            <button type="submit"
                class="px-4 py-2 rounded-md bg-brand hover:bg-brand-hover text-[#0b1420] text-sm font-semibold">
                <span class="material-symbols-outlined icon-sm align-text-bottom">send</span> Kirim Email Uji
            </button>
        </form>
    </div>
</div>
@endsection
