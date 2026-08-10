@extends("layouts.guest")
@section("title", "Masuk")
@section("guest-content")
    <h2 class="text-lg font-semibold mb-4">Masuk</h2>
    @if ($errors->any())
        <div class="mb-4 px-3 py-2 rounded-md bg-status-danger/10 text-status-danger text-sm">
            {{ $errors->first() }} </div>
    @endif
    <form method="POST" action="{{ route("login.attempt") }}" class="space-y-4"> @csrf <div> <label
                class="block text-sm font-medium mb-1" for="email">Email</label> <input type="email" name="email"
                id="email" required value="{{ old("email") }}" autofocus
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none">
        </div>
        <div> <label class="block text-sm font-medium mb-1" for="password">Kata Sandi</label> <input type="password"
                name="password" id="password" required
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none">
        </div> <label class="flex items-center gap-2 text-sm"> <input type="checkbox" name="remember"
                class="rounded bg-bg-surface"> Ingat saya </label> <button type="submit"
            class="w-full rounded-md bg-brand hover:bg-brand-hover text-[#0b1420] py-2 text-sm font-semibold"> Masuk
        </button> <a href="{{ route("password.request") }}"
            class="block text-center text-sm text-brand hover:underline">Lupa kata sandi?</a>
        <p class="block text-center text-sm text-text-secondary">Belum punya akun? <a href="{{ route("register") }}"
                class="text-brand hover:underline">Daftar</a></p>
    </form>
    <p class="mt-6 text-center text-xs text-text-secondary/70">
        v{{ \App\Support\ReleaseVersion::get() }}
    </p>
@endsection
