@php
    $ta = $row->program;
    $isSelected = $conversation && $row->conversation?->id === $conversation->id;
    $context = $ta ? $ta->jenisLabel().' · '.($user->isDosen() ? $ta->dosenRoleLabel($user) : 'Dosen').' · '.$ta->faseLabel() : null;
@endphp
<a href="{{ $row->url }}" @if ($isSelected) aria-current="page" @endif class="flex min-w-0 gap-3 border-b border-border px-3 py-3.5 transition-colors hover:bg-bg-hover focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand {{ $isSelected ? 'bg-brand-light/70 border-l-2 border-l-brand' : '' }}">
    <span class="avatar h-10 w-10 shrink-0 overflow-hidden text-xs font-bold" aria-hidden="true">@if ($row->other->photoUrl())<img src="{{ $row->other->photoUrl() }}" alt="" class="h-full w-full object-cover">@else{{ $row->other->initials() }}@endif</span>
    <span class="min-w-0 flex-1">
        <span class="flex items-start justify-between gap-2"><span class="truncate text-sm font-semibold text-text-primary">{{ $row->other->name }}</span>@if ($row->latest)<time datetime="{{ $row->latest->created_at?->toIso8601String() }}" class="shrink-0 text-[11px] text-text-secondary">{{ $row->latest->created_at?->isToday() ? $row->latest->created_at->format('H:i') : $row->latest->created_at?->locale('id')->translatedFormat('d M') }}</time>@endif</span>
        <span class="block truncate text-[13px] text-text-secondary" title="{{ $context ?? '' }}">{{ $context ?? ($row->other->nim ?: ($row->other->nidn ?: 'Percakapan')) }}</span>
        <span class="mt-1 flex items-center justify-between gap-2"><span class="truncate text-[13px] {{ $row->unread ? 'font-semibold text-text-primary' : 'text-text-secondary' }}">{{ $row->latest ? ($row->latest->body ?: ($row->latest->workspaceFiles()->exists() ? 'Mengirim file' : 'Mengirim referensi')) : 'Belum ada percakapan' }}</span>@if ($row->unread)<span class="shrink-0 rounded-full bg-brand px-2 py-0.5 text-[11px] font-bold {{ $user->isDosen() ? 'text-[#0b1420]' : 'text-bg-surface' }}" aria-label="{{ $row->unread }} pesan belum dibaca">{{ $row->unread }}</span>@endif</span>
    </span>
</a>