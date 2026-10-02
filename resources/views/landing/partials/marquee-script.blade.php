<script>
    (function () {
        var marquee = document.querySelector('[data-capabilities-marquee]');
        if (!marquee) return;
        var motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        function sync() {
            marquee.classList.toggle('is-marquee-ready', !motion.matches);
        }
        motion.addEventListener('change', sync);
        sync();
    })();
</script>