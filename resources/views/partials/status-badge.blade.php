@php
    // Label status operasional. Prioritas: statusLabel() dari model (menangani
    // status "Terkunci" dinamis), fallback ke mapping sederhana.
    $label = isset($entry) && $entry instanceof \App\Models\LogbookEntry
        ? $entry->statusLabel()
        : (\App\Models\LogbookEntry::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status)));

    $map = [
        "draft" => ["badge-neutral", "Draf"],
        "submitted" => ["badge-pending", "Menunggu review"],
        "approved" => ["badge-success", "Disetujui"],
        "revisi" => ["badge-danger", "Perlu revisi"],
        "revision_in_progress" => ["badge-pending", "Revisi sedang dikerjakan"],
    ];
    [$cls, $defaultLabel] = $map[$status] ?? ["badge-neutral", $label];
    $label = $label ?: $defaultLabel;
@endphp

<span class="badge {{ $cls }}">{{ $label }}</span>