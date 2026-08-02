@extends("layouts.guest")
@section("title", "Lupa Kata Sandi")
@section("guest-content")
    <h2 class="text-lg font-semibold mb-4">Lupa Kata Sandi</h2>
    <p class="text-sm text-text-secondary mb-4">Masukkan email Anda. Kami akan mengirimkan tautan untuk mereset kata sandi.
    </p>
    @if (session("status"))
        <div class="mb-4 px-3 py-2 rounded-md bg-accent-teal/10 text-accent-teal text-sm">
            {{ session("status") }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 px-3 py-2 rounded-md bg-status-danger/10 text-status-danger text-sm">
            {{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route("password.email") }}" class="space-y-4"> @csrf <div> <label
                class="block text-sm font-medium mb-1" for="email">Email</label> <input type="email" name="email"
                id="email" required value="{{ old("email") }}" autofocus
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-accent-teal focus:outline-none">
        </div> <button type="submit"
            class="w-full rounded-md bg-accent-teal hover:bg-accent-teal/90 text-white py-2 text-sm font-semibold">Kirim
            Tautan
            Reset</button> <a href="{{ route("login") }}"
            class="block text-center text-sm text-accent-teal hover:underline">← Kembali ke login</a>
    </form>
@endsection
