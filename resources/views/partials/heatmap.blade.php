@php
    $months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    $header = [];
    foreach ($heatmap as $weekIdx => $week) {
        $label = $week[0]['date'];
        $d = \Carbon\Carbon::parse($label);
        $month = $d->month;
        $prevMonth = ($weekIdx > 0) ? \Carbon\Carbon::parse($heatmap[$weekIdx - 1][0]['date'])->month : $month;
        $header[$weekIdx] = ($weekIdx === 0 || $month !== $prevMonth) ? $months[$month - 1] : '';
    }
    function heatColor($c) {
        if ($c <= 0) return 'bg-bg-hover';
        if ($c === 1) return 'bg-accent-teal/40';
        return 'bg-accent-teal';
    }
@endphp
<div class="overflow-x-auto">
    <table class="border-separate" style="border-spacing:2px">
        <tr>
            <td></td>
            @foreach ($header as $label)
                <td class="text-[9px] text-text-secondary px-0.5">{{ $label }}</td>
            @endforeach
        </tr>
        @for ($day = 0; $day < 7; $day++)
            <tr>
                <td class="text-[9px] text-text-secondary pr-1">
                    @php $names = ['', 'Sen', '', 'Rab', '', 'Jum', '']; @endphp {{ $names[$day] }}
                </td>
                @foreach ($heatmap as $week)
                    @php $cell = $week[$day]; @endphp
                    <td>
                        <span class="block h-3 w-3 rounded-sm {{ heatColor($cell['count']) }}"
                            title="{{ $cell['date'] }}: {{ $cell['count'] }} aktivitas"></span>
                    </td>
                @endforeach
            </tr>
        @endfor
    </table>
</div>
<div class="mt-2 flex items-center gap-1 text-[10px] text-text-secondary">
    Sedikit <span class="h-2.5 w-2.5 rounded-sm bg-bg-hover"></span>
    <span class="h-2.5 w-2.5 rounded-sm bg-accent-teal/40"></span>
    <span class="h-2.5 w-2.5 rounded-sm bg-accent-teal"></span>
    <span class="h-2.5 w-2.5 rounded-sm bg-accent-teal"></span>
    Banyak
</div>
