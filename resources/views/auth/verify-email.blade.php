@extends('layouts.guest')
@section('title', 'Verifikasi Email')
@section('guest-content')
    <h2 class="text-lg font-semibold mb-4">Verifikasi Email</h2>

    @if (session('status') === 'verification-link-sent')
        <div class="mb-4 px-3 py-2 rounded-md bg-status-success/10 text-status-success text-sm">
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
            class="w-full rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white py-2 text-sm font-semibold">
            Kirim Ulang Email Verifikasi
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-2">
        @csrf
        <button type="submit"
            class="w-full rounded-md bg-bg-hover hover:bg-border text-text-primary py-2 text-sm font-medium">
            Keluar
        </button>
    </form>
@endsection