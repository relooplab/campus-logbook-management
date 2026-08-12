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
                        class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none">
                </div>
                <div> <label class="block text-sm font-medium mb-1">Nama Institusi</label> <input type="text"
                        name="institution_name" value="{{ old("institution_name", $institution->institution_name) }}"
                        required class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                <div> <label class="block text-sm font-medium mb-1">Fakultas</label> <input type="text"
                        name="faculty" value="{{ old("faculty", $institution->faculty) }}"
                        class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                <div> <label class="block text-sm font-medium mb-1">Program Studi</label> <input type="text"
                        name="study_program" value="{{ old("study_program", $institution->study_program) }}"
                        class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                <div class="sm:col-span-2"> <label class="block text-sm font-medium mb-1">Alamat</label> <input
                        type="text" name="address" value="{{ old("address", $institution->address) }}"
                        class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                <div> <label class="block text-sm font-medium mb-1">Kota</label> <input type="text" name="city"
                        value="{{ old("city", $institution->city) }}"
                        class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                <div> <label class="block text-sm font-medium mb-1">Telepon</label> <input type="text" name="phone"
                        value="{{ old("phone", $institution->phone) }}"
                        class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                <div> <label class="block text-sm font-medium mb-1">Email</label> <input type="email" name="email"
                        value="{{ old("email", $institution->email) }}"
                        class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                <div> <label class="block text-sm font-medium mb-1">Email Kontak Admin</label> <input type="email"
                        name="admin_contact_email"
                        value="{{ old("admin_contact_email", $institution->admin_contact_email) }}"
                        class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                    <p class="text-xs text-text-secondary mt-1">Info bantuan di halaman daftar, masuk, dan bawah profil user (mis. untuk koreksi NIDN). Kosongkan untuk memakai default dari System Admin.</p>
                </div>
                <div> <label class="block text-sm font-medium mb-1">Website</label> <input type="url" name="website"
                        value="{{ old("website", $institution->website) }}"
                        class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
                <div class="sm:col-span-2"> <label class="block text-sm font-medium mb-1">Catatan Kaki (dokumen)</label>
                    <textarea name="footer_note" rows="2"
                        class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">{{ old("footer_note", $institution->footer_note) }}</textarea>
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
                                class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                        </div>
                        <div> <label class="block text-sm font-medium mb-1">Jenis File Diizinkan</label>
                            <input type="text" name="allowed_file_types" required
                                value="{{ old("allowed_file_types", $institution->allowed_file_types ?? "pdf") }}"
                                placeholder="pdf,doc,docx"
                                class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                            <p class="text-xs text-text-secondary mt-1">Pisahkan dengan koma, mis. pdf,doc,docx</p>
                        </div>
                    </div>
                </div>
                <div class="sm:col-span-2 pt-2 border-t border-border">
                    <p class="text-sm font-semibold mb-3">Catatan Hardcopy Seminar/Sidang</p>
                    <label class="block text-sm font-medium mb-1">Catatan default (dapat diubah dosen per submission)</label>
                    <textarea name="seminar_hardcopy_note" rows="3"
                        class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">{{ old("seminar_hardcopy_note", $institution->seminar_hardcopy_note) }}</textarea>
                    <p class="text-xs text-text-secondary mt-1">Ditampilkan pada form pemberian bahan seminar/sidang.</p>
                </div>
                </div>
            </div>
            <div class="flex items-center gap-3 pt-2"> <button
                    class="px-4 py-2 rounded-xl bg-brand hover:bg-brand-hover text-[#0b1420] text-sm font-semibold">Simpan</button>
            </div>
        </form>
    </div>

    {{-- Pengaturan Autentikasi dipindahkan ke panel System Admin --}}
    <div class="bg-bg-surface rounded-xl border border-border p-6">
        <h2 class="font-semibold mb-1">Pengaturan Autentikasi &amp; SMTP</h2>
        <p class="text-sm text-text-secondary mb-3">Pengaturan verifikasi email (wajib / tidak wajib) dan konfigurasi SMTP dipindahkan ke <strong>panel System Admin &rarr; Pengaturan</strong>. Pengaturan SMTP hanya tampil di sana saat verifikasi email diaktifkan.</p>
        <a href="{{ route('admin.system.settings') }}"
            class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-bg-hover hover:bg-border text-text-primary text-sm font-medium">
            <span class="material-symbols-outlined icon-sm">settings</span>
            Buka Pengaturan Autentikasi
        </a>
    </div>
</div>
@endsection
