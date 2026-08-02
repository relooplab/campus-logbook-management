@if ($user) <a href="{{ route("profile.show", $user) }}"
        class="hover:text-accent-teal hover:underline inline-flex items-center gap-1">
        @if ($user->photoUrl())
            <img src="{{ $user->photoUrl() }}" class="h-4 w-4 rounded-full object-cover" alt="">
        @endif {{ $user->name }}
    </a>
@else
    <span class="text-text-secondary">—</span>
@endif
