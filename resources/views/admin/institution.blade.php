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
            </div>
            <div class="flex items-center gap-3 pt-2"> <button
                    class="px-4 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm font-semibold">Simpan</button>
            </div>
        </form>
    </div>
</div>
@endsection
