@php
    function tlColor($status) {
        return match ($status) {
            "approved" => "bg-accent-teal",
            "revisi" => "bg-status-pending",
            "submitted" => "bg-accent-blue",
            "comment" => "bg-accent-blue",
            "future" => "bg-bg-hover",
            default => "bg-bg-hover",
        };
    }
    function tlIcon($status) {
        return match ($status) {
            "approved" => "✅",
            "revisi" => "🔄",
            "submitted" => "📤",
            "comment" => "💬",
            "future" => "○",
            default => "●",
        };
    }
    // Dot health: green/yellow/red (sama dengan health indicator).
    function healthDot($r) {
        return match ($r) {
            'green' => 'bg-status-success',
            'yellow' => 'bg-status-pending',
            default => 'bg-status-danger',
        };
    }
    $reg = $regularity ?? 'red';
    $regTip = $regularityTooltip ?? '';
@endphp

@if (empty($timeline))
    <p class="text-sm text-text-secondary">Belum ada aktivitas.</p>
@else
    <ol class="relative border-l-2 border-border ml-2 space-y-4">
        @foreach ($timeline as $item)
            <li class="ml-4"> <span
                    class="absolute -left-[9px] mt-1.5 h-3.5 w-3.5 rounded-full {{ tlColor($item["status"]) }}"></span>
                <div class="flex items-center gap-2"> <span
                        class="text-xs text-text-secondary font-medium w-16">{{ $item["date"] }}</span> <span
                        class="text-sm">{{ $item["label"] }}</span> <span
                        class="text-xs">{{ tlIcon($item["status"]) }}</span>
                    @if ($item["type"] !== "future")
                        <span class="inline-block w-2.5 h-2.5 rounded-full ml-auto {{ healthDot($reg) }}"
                            title="Health bimbingan: {{ ucfirst($reg) }} — {{ $regTip }}"></span>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
@endif
