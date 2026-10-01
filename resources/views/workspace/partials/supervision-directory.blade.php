@php
    $directoryUrl = fn (array $changes) => route('workspace.role', array_filter(array_merge([
        'student_search' => $directoryFilters['student_search'],
        'student_program' => $directoryFilters['student_program'],
        'student_role' => $directoryFilters['student_role'],
        'student_view' => $directoryFilters['student_view'],
    ], $changes), fn ($value) => $value !== ''));
    $filtered = $directoryFilters['student_search'] !== '' || $directoryFilters['student_program'] !== '' || $directoryFilters['student_role'] !== '';
@endphp
<section class="card min-w-0 p-4 sm:p-6" aria-labelledby="supervision-workspaces-heading">
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-brand-light text-brand" aria-hidden="true"><span class="material-symbols-outlined icon-md">groups</span></span>
            <div class="min-w-0">
                <h2 id="supervision-workspaces-heading" class="font-heading font-semibold text-text-primary">Workspace Bimbingan</h2>
                <p class="text-xs text-text-secondary sm:text-sm">Daftar mahasiswa yang Anda bimbing atau uji dalam tugas akhir dan kerja praktik.</p>
            </div>
        </div>
        <span class="whitespace-nowrap text-xs font-semibold tabular-nums text-text-primary">{{ $directoryTotal }} mahasiswa</span>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <form method="GET" action="{{ route('workspace.role') }}" role="search" class="flex min-w-0 flex-[1_1_240px] items-center gap-2 sm:max-w-md">
            @if ($directoryFilters['student_program'] !== '')<input type="hidden" name="student_program" value="{{ $directoryFilters['student_program'] }}">@endif
            @if ($directoryFilters['student_role'] !== '')<input type="hidden" name="student_role" value="{{ $directoryFilters['student_role'] }}">@endif
            @if ($directoryFilters['student_view'] !== 'daftar')<input type="hidden" name="student_view" value="{{ $directoryFilters['student_view'] }}">@endif
            <label for="student-workspace-search" class="sr-only">Cari mahasiswa, NIM, atau judul</label>
            <input id="student-workspace-search" name="student_search" type="search" maxlength="100" value="{{ $directoryFilters['student_search'] }}" placeholder="Cari mahasiswa, NIM, atau judul..." class="min-w-0 flex-1 rounded-control border border-border bg-bg-surface px-3 py-2 text-sm text-text-primary placeholder:text-text-secondary focus:outline-none focus:ring-2 focus:ring-brand/40">
            <button type="submit" class="btn-ghost inline-flex h-9 w-9 shrink-0 items-center justify-center focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" aria-label="Cari workspace mahasiswa"><span class="material-symbols-outlined icon-sm" aria-hidden="true">search</span></button>
        </form>
        <nav class="flex flex-wrap items-center gap-1" aria-label="Filter program">
            @foreach (['' => 'Semua', 'ta' => 'TA', 'kp' => 'KP'] as $key => $label)
                <a href="{{ $directoryUrl(['student_program' => $key]) }}" @if ($directoryFilters['student_program'] === $key) aria-current="page" @endif class="rounded-control border px-2.5 py-2 text-xs font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand {{ $directoryFilters['student_program'] === $key ? 'border-brand bg-brand-light text-brand' : 'border-border bg-bg-panel text-text-secondary hover:bg-bg-hover' }}">{{ $label }}</a>
            @endforeach
        </nav>
        <nav class="flex flex-wrap items-center gap-1" aria-label="Filter peran dosen">
            @foreach (['pembimbing' => 'Dibimbing', 'penguji' => 'Diuji'] as $key => $label)
                <a href="{{ $directoryUrl(['student_role' => $directoryFilters['student_role'] === $key ? '' : $key]) }}" @if ($directoryFilters['student_role'] === $key) aria-current="page" @endif class="rounded-control border px-2.5 py-2 text-xs font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand {{ $directoryFilters['student_role'] === $key ? 'border-brand bg-brand-light text-brand' : 'border-border bg-bg-panel text-text-secondary hover:bg-bg-hover' }}">{{ $label }}</a>
            @endforeach
        </nav>
        <nav class="flex items-center gap-1 sm:ml-auto" aria-label="Tampilan workspace">
            <span class="mr-1 text-xs text-text-secondary">Tampilan:</span>
            @foreach (['daftar' => ['Daftar', 'view_list'], 'grid' => ['Grid', 'grid_view']] as $key => [$label, $icon])
                <a href="{{ $directoryUrl(['student_view' => $key]) }}" @if ($directoryFilters['student_view'] === $key) aria-current="page" @endif class="inline-flex items-center gap-1 rounded-control border px-2.5 py-2 text-xs font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand {{ $directoryFilters['student_view'] === $key ? 'border-brand bg-brand-light text-brand' : 'border-border bg-bg-panel text-text-secondary hover:bg-bg-hover' }}"><span class="material-symbols-outlined icon-sm" aria-hidden="true">{{ $icon }}</span>{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    @if ($tas->isEmpty())
        <div class="rounded-control border border-border bg-bg-panel px-4 py-9 text-center text-sm text-text-secondary">
            <span class="material-symbols-outlined icon-lg mb-2" aria-hidden="true">folder_off</span>
            <p>{{ $directoryTotal === 0 ? 'Belum ada workspace bimbingan yang tersedia.' : 'Tidak ada workspace mahasiswa yang cocok dengan pencarian atau filter.' }}</p>
            @if ($filtered)<a href="{{ route('workspace.role') }}" class="mt-3 inline-flex rounded-control px-3 py-2 font-semibold text-brand hover:bg-brand-light focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Reset Filter</a>@endif
        </div>
    @else
        <div class="{{ $directoryFilters['student_view'] === 'grid' ? 'grid gap-2 sm:grid-cols-2 xl:grid-cols-3' : 'space-y-2' }}">
            @foreach ($tas as $ta)
                @php
                    $student = $ta->mahasiswa;
                    $workspaceTitle = $ta->isKp() ? ($ta->judul_ta ?: $ta->tempat_kp) : $ta->judul_ta;
                    $workspaceTitle = $workspaceTitle ?: 'Judul belum tersedia';
                    $roleLabels = array_filter([
                        $ta->pembimbing_1_id === $user->id ? 'Pembimbing 1' : null,
                        $ta->pembimbing_2_id === $user->id ? 'Pembimbing 2' : null,
                        $ta->penguji_1_id === $user->id ? 'Penguji 1' : null,
                        $ta->penguji_2_id === $user->id ? 'Penguji 2' : null,
                    ]);
                @endphp
                <article class="min-w-0 rounded-control border border-border bg-bg-panel p-3 transition-colors hover:border-brand/40 hover:bg-bg-hover {{ $directoryFilters['student_view'] === 'grid' ? 'flex flex-col gap-2' : 'sm:flex sm:items-center sm:gap-3' }}">
                    <span class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-control bg-brand-light text-brand sm:inline-flex" aria-hidden="true"><span class="material-symbols-outlined icon-md">folder</span></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <h3 class="max-w-full min-w-0 truncate text-sm font-semibold text-text-primary" title="{{ $student?->name }}">{{ $student?->name ?? '—' }}</h3>
                            @if ($student?->nim)<span class="max-w-full truncate font-mono text-[11px] text-text-secondary" title="{{ $student->nim }}">{{ $student->nim }}</span>@endif
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            <span class="badge {{ $ta->isKp() ? 'badge-neutral' : 'badge-info' }}">{{ $ta->jenisLabel() }}</span>
                            @foreach ($roleLabels as $roleLabel)<span class="badge {{ str_starts_with($roleLabel, 'Pembimbing') ? 'badge-info' : 'badge-neutral' }}">{{ $roleLabel }}</span>@endforeach
                            <span class="badge badge-pending max-w-full overflow-hidden text-ellipsis" title="Fase: {{ $ta->faseLabel() }}">{{ $ta->faseLabel() }}</span>
                        </div>
                        <p class="mt-1 min-w-0 truncate text-xs text-text-secondary" title="{{ $workspaceTitle }}">{{ $workspaceTitle }}</p>
                    </div>
                    <div class="mt-2 flex shrink-0 items-center justify-between gap-3 sm:mt-0 {{ $directoryFilters['student_view'] === 'grid' ? 'w-full' : 'sm:gap-4' }}">
                        <span class="inline-flex items-center gap-1 whitespace-nowrap text-xs tabular-nums text-text-secondary"><span class="material-symbols-outlined icon-sm" aria-hidden="true">description</span>{{ $ta->workspace_files_count }} file</span>
                        <a href="{{ route('workspace.index', $ta) }}" class="inline-flex items-center gap-1 whitespace-nowrap rounded-control border border-brand/30 bg-bg-surface px-3 py-1.5 text-xs font-semibold text-brand hover:bg-brand-light focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" aria-label="Buka workspace {{ $student?->name ?? 'mahasiswa' }} ({{ $ta->jenisLabel() }})">Buka <span aria-hidden="true">→</span></a>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-3 text-xs text-text-secondary"><span>Menampilkan {{ $tas->firstItem() }}–{{ $tas->lastItem() }} dari {{ $tas->total() }} workspace</span>{{ $tas->links() }}</div>
    @endif
</section>