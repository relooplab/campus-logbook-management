@php
    $map = [
        "draft" => ["badge-neutral", "Draf"],
        "submitted" => ["badge-pending", "Dikirim"],
        "approved" => ["badge-success", "Disetujui"],
        "revisi" => ["badge-danger", "Revisi"],
    ];
    [$cls, $label] = $map[$status] ?? ["badge-neutral", $status];
@endphp

<span class="badge {{ $cls }}">{{ $label }}</span>
