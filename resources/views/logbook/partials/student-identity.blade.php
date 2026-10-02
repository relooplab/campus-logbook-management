@php
    $program = $entry->mahasiswaTa;
    $viewer = auth()->user();
    $canViewProgram = $program && (
        ($viewer->isAdmin() && ($viewer->isSystemAdmin() || $viewer->institution_id === null || $program->institution_id === $viewer->institution_id))
        || (!$viewer->isAdmin() && $viewer->isDosen() && ($program->isPembimbing($viewer) || $program->isPenguji($viewer)))
    );
@endphp
@if ($canViewProgram && $program->mahasiswa)
    <a href="{{ route($program->isKp() ? 'mahasiswa-kp.show' : 'mahasiswa-ta.show', $program) }}" class="history-student-link">{{ $program->mahasiswa->name }}</a>
@else
    <span class="font-semibold">{{ $program?->mahasiswa?->name ?? 'Mahasiswa' }}</span>
@endif
<p class="mt-1 text-xs text-text-secondary">{{ $program?->jenisLabel() }}@if ($role = $program?->dosenRoleLabel($viewer)) · {{ $role }}@endif</p>
