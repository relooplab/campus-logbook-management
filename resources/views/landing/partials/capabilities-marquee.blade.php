<div class="landing-container landing-capabilities" data-capabilities-marquee role="region" aria-label="Cakupan aplikasi">
    <div class="landing-marquee-viewport">
        <div id="landing-capabilities-track" class="landing-marquee-track">
            @for ($copy = 0; $copy < 2; $copy++)
                <div class="landing-marquee-group" @if($copy === 1) aria-hidden="true" @endif>
                    <span>Tugas Akhir &amp; Kerja Praktik</span>
                    <span>Logbook &amp; revisi</span>
                    <span>Review dokumen</span>
                    <span>Seminar &amp; sidang</span>
                </div>
            @endfor
        </div>
    </div>
</div>