@extends('layouts.app')

@section('title', 'Pengaturan Autentikasi')

@section('content')
<div class="max-w-3xl space-y-6">
    <div>
        <h1 class="text-xl font-bold">Pengaturan Autentikasi</h1>
        <p class="text-sm text-text-secondary">Atur apakah user yang baru registrasi wajib verifikasi email. Saat ON, form SMTP akan tampil dan harus diisi lengkap.</p>
    </div>

    <div class="bg-bg-surface rounded-xl border border-border p-6">
        <form method="POST" action="{{ route('admin.system.settings.update') }}" class="space-y-4">
            @csrf

            <div class="flex items-start gap-3 p-4 rounded-lg bg-bg-panel border border-border">
                <input type="checkbox" name="email_verification_required" value="1" id="email-verification-toggle"
                    @checked(old('email_verification_required', $institution->email_verification_required))
                    class="mt-1 rounded bg-bg-surface">
                <div class="flex-1">
                    <label for="email-verification-toggle" class="text-sm font-medium text-text-primary cursor-pointer">Wajib Verifikasi Email</label>
                    <p class="text-xs text-text-secondary mt-1">Saat ON, user yang baru registrasi tidak bisa masuk fitur aplikasi sampai mereka mengeklik tautan verifikasi yang dikirim ke email. Saat OFF, user langsung aktif (perilaku lama).</p>
                </div>
            </div>

            @error('email_verification_required')<p class="text-xs text-status-danger">{{ $message }}</p>@enderror

            <div id="smtp-form" class="space-y-4 pt-2 border-t border-border {{ old('email_verification_required', $institution->email_verification_required) ? '' : 'hidden' }}">
                <p class="text-sm font-semibold">Konfigurasi SMTP</p>
                <p class="text-xs text-text-secondary -mt-3">Form ini hanya tampil saat verifikasi email diaktifkan. Isi lengkap agar email verifikasi dapat terkirim.</p>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Mailer</label>
                        <select name="mail_mailer" class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                            @foreach (["smtp", "log", "array", "sendmail", "mailgun", "ses", "postmark", "resend"] as $m)
                                <option value="{{ $m }}" @selected(old("mail_mailer", $institution->mail_mailer ?? "smtp") === $m)>{{ strtoupper($m) }}</option>
                            @endforeach
                        </select>
                        @error('mail_mailer')<p class="text-xs text-status-danger mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Host SMTP</label>
                        <input type="text" name="mail_host" value="{{ old("mail_host", $institution->mail_host) }}" placeholder="smtp.gmail.com"
                            class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                        @error('mail_host')<p class="text-xs text-status-danger mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Port</label>
                        <input type="number" name="mail_port" min="1" max="65535" value="{{ old("mail_port", $institution->mail_port) }}" placeholder="587"
                            class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                        @error('mail_port')<p class="text-xs text-status-danger mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Enkripsi</label>
                        <select name="mail_encryption" class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                            <option value="">Tanpa enkripsi</option>
                            <option value="tls" @selected(old("mail_encryption", $institution->mail_encryption) === "tls")>TLS</option>
                            <option value="ssl" @selected(old("mail_encryption", $institution->mail_encryption) === "ssl")>SSL</option>
                        </select>
                        @error('mail_encryption')<p class="text-xs text-status-danger mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Username</label>
                        <input type="text" name="mail_username" value="{{ old("mail_username", $institution->mail_username) }}" placeholder="email@domain.com"
                            class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                        @error('mail_username')<p class="text-xs text-status-danger mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Password</label>
                        <input type="password" name="mail_password" value="{{ old("mail_password", $institution->mail_password) }}" placeholder="••••••••"
                            class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                        @error('mail_password')<p class="text-xs text-status-danger mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">From Address</label>
                        <input type="email" name="mail_from_address" value="{{ old("mail_from_address", $institution->mail_from_address) }}" placeholder="no-reply@domain.com"
                            class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                        @error('mail_from_address')<p class="text-xs text-status-danger mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">From Name</label>
                        <input type="text" name="mail_from_name" value="{{ old("mail_from_name", $institution->mail_from_name) }}" placeholder="Campus Logbook Management"
                            class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                        @error('mail_from_name')<p class="text-xs text-status-danger mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button class="px-4 py-2 rounded-xl bg-brand hover:bg-brand-hover text-[#0b1420] text-sm font-semibold">Simpan</button>
            </div>
        </form>
    </div>

    <div id="smtp-test" class="bg-bg-surface rounded-xl border border-border p-6 {{ old('email_verification_required', $institution->email_verification_required) ? '' : 'hidden' }}">
        <h2 class="font-semibold mb-1">Kirim Email Uji</h2>
        <p class="text-sm text-text-secondary mb-4">Verifikasi konfigurasi SMTP dengan mengirim email percobaan ke alamat Anda.</p>
        <form method="POST" action="{{ route('admin.system.settings.test-mail') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium mb-1" for="test-mail-to">Alamat Email Tujuan</label>
                <input type="email" name="to" id="test-mail-to" required value="{{ old("to", auth()->user()->email) }}"
                    class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
            </div>
            <button type="submit" class="px-4 py-2 rounded-xl bg-brand hover:bg-brand-hover text-[#0b1420] text-sm font-semibold">
                <span class="material-symbols-outlined icon-sm align-text-bottom">send</span> Kirim Email Uji
            </button>
        </form>
    </div>

    {{-- ===== Pengaturan Fitur Paket (dipindah dari hak akses) ===== --}}
    <div class="bg-bg-surface rounded-xl border border-border p-6">
        <h2 class="font-semibold mb-1">Pengaturan Fitur Paket</h2>
        <p class="text-sm text-text-secondary mb-4">Atur batas penyimpanan &amp; fitur export/import per paket, atau tambah paket baru.</p>

        <div class="space-y-3 mb-6">
            @foreach ($plans as $plan)
                <div class="rounded-xl border border-border bg-bg-panel p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <span class="font-semibold text-text-primary">{{ $plan->name }}</span>
                        <span class="text-xs text-text-secondary">{{ $plan->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                    </div>
                    <form method="POST" action="{{ route('admin.system.plans.update') }}">
                        @csrf
                        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-xs text-text-secondary mb-1">Label</label>
                                <input type="text" name="plans[{{ $plan->id }}][label]" value="{{ $plan->label }}"
                                    class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-text-secondary mb-1">Harga (Rp)</label>
                                <input type="number" name="plans[{{ $plan->id }}][price]" value="{{ $plan->price }}" min="0"
                                    class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-text-secondary mb-1">Storage (MB)</label>
                                <input type="number" name="plans[{{ $plan->id }}][storage_mb]" value="{{ $plan->storageLimitMb() }}" min="0"
                                    class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                            </div>
                            <div class="flex items-end justify-between gap-3 pb-1">
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="plans[{{ $plan->id }}][export]" value="1" @checked($plan->feature('export', false)) class="rounded bg-bg-surface border-border"> Export
                                </label>
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="plans[{{ $plan->id }}][import]" value="1" @checked($plan->feature('import', false)) class="rounded bg-bg-surface border-border"> Import
                                </label>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center gap-3">
                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-brand hover:bg-brand-hover text-[#0b1420] text-sm font-semibold">Simpan</button>
                            <form method="POST" action="{{ route('admin.system.plans.destroy', $plan) }}" class="inline" onsubmit="return confirm('Hapus paket ini?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-status-danger hover:underline text-xs">Hapus</button>
                            </form>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>

        {{-- Form tambah paket --}}
        <div class="border-t border-border pt-4">
            <h3 class="text-sm font-semibold mb-3">Tambah Paket Baru</h3>
            <form method="POST" action="{{ route('admin.system.plans.store') }}" class="space-y-3">
                @csrf
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs text-text-secondary mb-1">Nama (slug)</label>
                        <input type="text" name="name" placeholder="mis. pro" required class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-text-secondary mb-1">Label</label>
                        <input type="text" name="label" placeholder="mis. Pro" required class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-text-secondary mb-1">Harga (Rp)</label>
                        <input type="number" name="price" min="0" required class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-text-secondary mb-1">Periode</label>
                        <select name="period" class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                            @foreach (['monthly' => 'Bulanan', 'yearly' => 'Tahunan', 'daily' => 'Harian', 'weekly' => 'Mingguan', 'once' => 'Sekali'] as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-text-secondary mb-1">Storage (MB)</label>
                        <input type="number" name="storage_mb" min="0" required class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                    </div>
                    <div class="flex items-end gap-4 pb-1">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="export" value="1" class="rounded bg-bg-surface border-border"> Export</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="import" value="1" class="rounded bg-bg-surface border-border"> Import</label>
                    </div>
                </div>
                <button type="submit" class="px-4 py-2 rounded-xl bg-brand hover:bg-brand-hover text-[#0b1420] text-sm font-semibold">+ Tambah Paket</button>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function() {
        var toggle = document.getElementById('email-verification-toggle');
        var smtpForm = document.getElementById('smtp-form');
        var smtpTest = document.getElementById('smtp-test');

        if (!toggle) return;

        function sync() {
            var on = toggle.checked;
            if (smtpForm) smtpForm.classList.toggle('hidden', !on);
            if (smtpTest) smtpTest.classList.toggle('hidden', !on);
        }

        toggle.addEventListener('change', sync);
        sync();
    })();
</script>
@endsection
