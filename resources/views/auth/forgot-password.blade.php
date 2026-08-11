@extends("layouts.guest")
@section("title", "Lupa Kata Sandi")
@section("guest-content")
    <h2 class="text-lg font-semibold mb-4">Lupa Kata Sandi</h2>
    <p class="text-sm text-text-secondary mb-4">Masukkan email Anda, atau NIM (mahasiswa) / NIDN (dosen). Kami akan mengirimkan tautan reset ke email terdaftar Anda.
    </p>
    @if (session("status"))
        <div class="mb-4 px-3 py-2 rounded-md bg-brand/10 text-brand text-sm">
            {{ session("status") }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 px-3 py-2 rounded-md bg-status-danger/10 text-status-danger text-sm">
            {{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route("password.email") }}" class="space-y-4"> @csrf <div> <label
                class="block text-sm font-medium mb-1" for="email">Email / NIM / NIDN</label> <input type="text" name="email"
                id="email" required value="{{ old("email") }}" autofocus placeholder="Email, NIM, atau NIDN"
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none">
        </div> <button type="submit"
            class="w-full rounded-md bg-brand hover:bg-brand-hover text-[#0b1420] py-2 text-sm font-semibold">Kirim
            Tautan
            Reset</button> <a href="{{ route("login") }}"
            class="block text-center text-sm text-brand hover:underline">← Kembali ke login</a>
    </form>
@endsection
