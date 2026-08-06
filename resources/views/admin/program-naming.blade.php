@extends('layouts.app')

@section('title', 'Penamaan Program')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="font-heading font-bold text-2xl text-text-primary">Penamaan Program (TA/KP)</h1>
        <p class="text-sm text-text-secondary mt-0.5">Kustomisasi nama program & label fase per prodi/departemen. Kosongkan untuk memakai nama default.</p>
    </div>

    @if ($universities->isEmpty())
        <div class="px-4 py-10 rounded-xl bg-bg-panel border border-border text-center text-text-secondary">
            <span class="material-symbols-outlined icon-lg mb-2 text-text-secondary/50">account_balance</span>
            <p>Belum ada data direktori organisasi. Tambahkan universitas/fakultas/departemen/prodi dulu.</p>
        </div>
    @else
        @foreach ($universities as $university)
            <div class="card p-6">
                <h2 class="font-heading font-semibold text-text-primary mb-4">{{ $university->name }}</h2>

                @foreach ($university->faculties as $faculty)
                    <div class="mb-6">
                        <h3 class="font-medium text-text-primary mb-3">{{ $faculty->name }}</h3>

                        @foreach ($faculty->departments as $department)
                            <div class="mb-5">
                                <h4 class="text-sm font-semibold text-text-secondary mb-2">{{ $department->name }}</h4>

                                {{-- Form konfigurasi departemen --}}
                                @php
                                    $deptCanManage = $scopes->isEmpty() || in_array($department->id, $allowedDepartmentIds, true);
                                @endphp
                                @if ($deptCanManage)
                                    <div class="bg-bg-panel rounded-xl border border-border p-4 mb-3">
                                        <p class="text-xs font-semibold text-text-secondary mb-3">Konfigurasi Departemen</p>
                                        <div class="grid lg:grid-cols-2 gap-4">
                                            @foreach (['ta' => 'Tugas Akhir (TA)', 'kp' => 'Kerja Praktek (KP)'] as $jenis => $jenisLabel)
                                                @php
                                                    $config = $configs->get('department:'.$department->id.':'.$jenis);
                                                    $defaults = $jenis === 'kp' ? \App\Models\MahasiswaTa::FASES_KP : \App\Models\MahasiswaTa::FASES;
                                                @endphp
                                                <form method="POST" action="{{ route('admin.program-naming.update') }}" class="space-y-3">
                                                    @csrf
                                                    <input type="hidden" name="scope_type" value="department">
                                                    <input type="hidden" name="scope_id" value="{{ $department->id }}">
                                                    <input type="hidden" name="jenis" value="{{ $jenis }}">

                                                    <div class="flex items-center justify-between">
                                                        <p class="text-sm font-medium text-text-primary">{{ $jenisLabel }}</p>
                                                        <span class="text-xs text-text-secondary">Departemen</span>
                                                    </div>

                                                    <div>
                                                        <label class="block text-xs text-text-secondary mb-1">Nama Program (opsional)</label>
                                                        <input type="text" name="program_label" value="{{ $config?->program_label ?? '' }}" placeholder="{{ $jenis === 'kp' ? 'KP' : 'TA' }}" maxlength="100"
                                                            class="w-full rounded-lg border border-border bg-bg-surface px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                                                    </div>

                                                    <div>
                                                        <label class="block text-xs text-text-secondary mb-1">Label Fase (opsional)</label>
                                                        <div class="space-y-1.5">
                                                            @foreach ($defaults as $key => $defaultLabel)
                                                                <div class="flex items-center gap-2">
                                                                    <span class="text-xs text-text-secondary w-40 shrink-0">{{ $defaultLabel }}</span>
                                                                    <input type="text" name="fase_labels[{{ $key }}]" value="{{ $config?->fase_labels[$key] ?? '' }}" placeholder="{{ $defaultLabel }}" maxlength="100"
                                                                        class="flex-1 rounded-lg border border-border bg-bg-surface px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>

                                                    <button type="submit" class="px-4 py-2 rounded-lg bg-brand text-white text-sm font-medium hover:opacity-90">Simpan</button>
                                                </form>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Prodi di departemen ini --}}
                                @foreach ($department->studyPrograms as $studyProgram)
                                    @php
                                        $prodiCanManage = $scopes->isEmpty() || in_array($studyProgram->id, $allowedStudyProgramIds, true);
                                    @endphp
                                    @if ($prodiCanManage)
                                        <div class="bg-bg-surface rounded-xl border border-border p-4 mb-3">
                                            <p class="text-sm font-semibold text-text-primary mb-3">{{ $studyProgram->name }}</p>
                                            <div class="grid lg:grid-cols-2 gap-4">
                                                @foreach (['ta' => 'Tugas Akhir (TA)', 'kp' => 'Kerja Praktek (KP)'] as $jenis => $jenisLabel)
                                                    @php
                                                        $config = $configs->get('study_program:'.$studyProgram->id.':'.$jenis);
                                                        $defaults = $jenis === 'kp' ? \App\Models\MahasiswaTa::FASES_KP : \App\Models\MahasiswaTa::FASES;
                                                    @endphp
                                                    <form method="POST" action="{{ route('admin.program-naming.update') }}" class="space-y-3">
                                                        @csrf
                                                        <input type="hidden" name="scope_type" value="study_program">
                                                        <input type="hidden" name="scope_id" value="{{ $studyProgram->id }}">
                                                        <input type="hidden" name="jenis" value="{{ $jenis }}">

                                                        <div class="flex items-center justify-between">
                                                            <p class="text-sm font-medium text-text-primary">{{ $jenisLabel }}</p>
                                                            <span class="text-xs text-text-secondary">Prodi</span>
                                                        </div>

                                                        <div>
                                                            <label class="block text-xs text-text-secondary mb-1">Nama Program (opsional)</label>
                                                            <input type="text" name="program_label" value="{{ $config?->program_label ?? '' }}" placeholder="{{ $jenis === 'kp' ? 'KP' : 'TA' }}" maxlength="100"
                                                                class="w-full rounded-lg border border-border bg-bg-surface px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                                                        </div>

                                                        <div>
                                                            <label class="block text-xs text-text-secondary mb-1">Label Fase (opsional)</label>
                                                            <div class="space-y-1.5">
                                                                @foreach ($defaults as $key => $defaultLabel)
                                                                    <div class="flex items-center gap-2">
                                                                        <span class="text-xs text-text-secondary w-40 shrink-0">{{ $defaultLabel }}</span>
                                                                        <input type="text" name="fase_labels[{{ $key }}]" value="{{ $config?->fase_labels[$key] ?? '' }}" placeholder="{{ $defaultLabel }}" maxlength="100"
                                                                            class="flex-1 rounded-lg border border-border bg-bg-surface px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>

                                                        <button type="submit" class="px-4 py-2 rounded-lg bg-brand text-white text-sm font-medium hover:opacity-90">Simpan</button>
                                                    </form>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endforeach
    @endif
</div>
@endsection