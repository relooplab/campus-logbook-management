@if ($ta->isPembimbing($user))
    <form method="POST" action="{{ route($ta->isKp() ? 'mahasiswa-kp.fase' : 'mahasiswa-ta.fase', $ta) }}" class="flex min-w-0 flex-wrap items-center gap-1" onsubmit="return confirm('Ubah fase mahasiswa ini menjadi ' + this.elements.fase.options[this.elements.fase.selectedIndex].text + '?')">
        @csrf
        <label class="sr-only" for="fase-{{ $ta->id }}-{{ $controlId }}">Fase {{ $ta->mahasiswa?->name }} ({{ $ta->jenisLabel() }})</label>
        <select id="fase-{{ $ta->id }}-{{ $controlId }}" name="fase" class="min-w-0 max-w-full flex-1 rounded-control border border-border bg-bg-surface px-2 py-1.5 text-xs text-text-primary focus:outline-none focus:ring-2 focus:ring-brand/40">
            @foreach ($naming->faseLabels($ta) as $phaseKey => $phaseLabel)
                <option value="{{ $phaseKey }}" @selected($ta->fase === $phaseKey)>{{ $phaseLabel }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-control bg-brand-light px-2 py-1.5 text-xs font-semibold text-brand hover:bg-bg-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" aria-label="Simpan fase {{ $ta->mahasiswa?->name }}">Simpan</button>
    </form>
@else
    <span class="text-xs text-text-secondary">{{ $ta->faseLabel() }}</span>
@endif