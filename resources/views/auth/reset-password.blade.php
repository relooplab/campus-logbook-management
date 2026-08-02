@extends("layouts.guest")
@section("title", "Reset Kata Sandi")
@section("guest-content")
    <h2 class="text-lg font-semibold mb-4">Reset Kata Sandi</h2>
    @if ($errors->any())
        <div class="mb-4 px-3 py-2 rounded-md bg-status-danger/10 text-status-danger text-sm">
            {{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route("password.update") }}" class="space-y-4"> @csrf <input type="hidden" name="token"
            value="{{ $token }}">
        <div> <label class="block text-sm font-medium mb-1" for="email">Email</label> <input type="email" name="email"
                id="email" required value="{{ $email ?? old("email") }}"
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none">
        </div>
        <div> <label class="block text-sm font-medium mb-1" for="password">Kata Sandi Baru</label> <input type="password"
                name="password" id="password" required minlength="6"
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
        <div> <label class="block text-sm font-medium mb-1" for="password_confirmation">Konfirmasi Kata Sandi</label> <input
                type="password" name="password_confirmation" id="password_confirmation" required minlength="6"
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div> <button
            type="submit"
            class="w-full rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white py-2 text-sm font-semibold">Reset
            Kata
            Sandi</button>
    </form>
@endsection
