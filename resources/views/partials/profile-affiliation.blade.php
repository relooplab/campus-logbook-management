@php
    $affUser = $affUser ?? ($user ?? null);
@endphp
@if ($affUser)
    @php
        $univ = $affUser->primaryUniversity();
        $pivot = $univ?->pivot;
        $faculty = $pivot?->faculty_id ? \App\Models\Faculty::find($pivot->faculty_id) : null;
        $department = $pivot?->department_id ? \App\Models\Department::find($pivot->department_id) : null;
        $prodi = $pivot?->study_program_id ? \App\Models\StudyProgram::find($pivot->study_program_id) : null;
        $hasAff = ($affUser->isDosen() && $affUser->nidn) || ($affUser->isMahasiswa() && $affUser->identifier) || $univ;
    @endphp
    @if ($hasAff)
        <div class="mt-6 pt-4 border-t border-border">
            <h3 class="text-sm font-semibold mb-3">Afiliasi</h3>
            <div class="grid sm:grid-cols-2 gap-2 text-sm text-text-secondary">
                @if ($affUser->isDosen() && $affUser->nidn)
                    <div class="flex items-center gap-2"><span class="material-symbols-outlined icon-sm">badge</span> <span>NIDN: <span class="text-text-primary font-medium">{{ $affUser->nidn }}</span></span></div>
                @endif
                @if ($affUser->isMahasiswa() && $affUser->identifier)
                    <div class="flex items-center gap-2"><span class="material-symbols-outlined icon-sm">confirmation_number</span> <span>NIM: <span class="text-text-primary font-medium">{{ $affUser->identifier }}</span></span></div>
                @endif
                @if ($univ)
                    <div class="flex items-center gap-2"><span class="material-symbols-outlined icon-sm">account_balance</span> <span>{{ $univ->name }}</span></div>
                @endif
                @if ($faculty)
                    <div class="flex items-center gap-2"><span class="material-symbols-outlined icon-sm">apartment</span> <span>{{ $faculty->name }}</span></div>
                @endif
                @if ($department)
                    <div class="flex items-center gap-2"><span class="material-symbols-outlined icon-sm">domain</span> <span>{{ $department->name }}</span></div>
                @endif
                @if ($prodi)
                    <div class="flex items-center gap-2"><span class="material-symbols-outlined icon-sm">school</span> <span>{{ $prodi->name }}</span></div>
                @endif
            </div>
        </div>
    @endif
@endif
