@props(['items' => [], 'cols' => null])
{{-- Kisi metadata key/value konsisten. item: ['label'=>..., 'value'=>...] --}}
<dl class="grid {{ $cols ?: 'sm:grid-cols-2' }} gap-3 text-sm">
    @foreach ($items as $item)
        <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
            <dt class="text-xs text-text-secondary">{{ $item['label'] }}</dt>
            <dd class="font-medium mt-0.5 break-words">{{ $item['value'] ?? '—' }}</dd>
        </div>
    @endforeach
</dl>