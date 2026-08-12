@extends('layouts.guest')
@section('title', 'Verifikasi Email')
@section('guest-content')
    <h2 class="text-lg font-semibold mb-4 text-text-primary">Verifikasi Email</h2>

    @if (session('status') === 'verification-link-sent')
        <div class="mb-4 px-3 py-2 rounded-xl bg-status-success/10 text-status-success text-sm">
            Link verifikasi baru telah dikirim ke email Anda.
        </div>
    @endif

    <p class="text-sm text-text-secondary mb-4">
        Sebelum melanjutkan, silakan periksa email Anda untuk link verifikasi.
        Jika Anda tidak menerima email, klik tombol di bawah untuk mengirim ulang.
    </p>

    <form method="POST" action="{{ route('verification.send') }}" class="space-y-4">
        @csrf
        <button type="submit"
            class="w-full rounded-xl bg-brand hover:bg-brand-hover text-[#0b1420] py-2 text-sm font-semibold transition-colors">
            Kirim Ulang Email Verifikasi
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-2">
        @csrf
        <button type="submit"
            class="w-full rounded-xl bg-bg-hover hover:bg-border text-text-primary py-2 text-sm font-medium transition-colors">
            Keluar
        </button>
    </form>

    <div class="mt-6 pt-4 border-t border-border">
        <details class="text-sm">
            <summary class="cursor-pointer text-text-secondary font-medium">Alamat email salah? Ganti email</summary>
            <form method="POST" action="{{ route('profile.email') }}" class="mt-3 space-y-3">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs text-text-secondary mb-1">Email Baru</label>
                    <input type="email" name="email" required value="{{ old('email') }}" class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-text-secondary mb-1">Konfirmasi Email Baru</label>
                    <input type="email" name="email_confirmation" required value="{{ old('email_confirmation') }}" class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-text-secondary mb-1">Password Saat Ini</label>
                    <input type="password" name="current_password" required class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm">
                </div>
                @if ($errors->any())
                    <p class="text-xs text-status-danger">{{ $errors->first() }}</p>
                @endif
                <button type="submit"
                    class="w-full rounded-xl bg-bg-hover hover:bg-border text-text-primary py-2 text-sm font-medium transition-colors">
                    Simpan &amp; Kirim Ulang Verifikasi
                </button>
            </form>
        </details>
    </div>
@endsection