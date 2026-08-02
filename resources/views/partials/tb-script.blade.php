<script>
    // Toolbar Ringkasan Perbaikan: Bullet / Number / Dash + lanjut penomoran via Enter.
    function initTbToolbar(id) {
        var ta = document.getElementById(id);
        if (!ta) return;
        var counters = { bullet: 0, number: 0, dash: 0 };

        function selStart() {
            return ta.selectionStart != null ? ta.selectionStart : ta.value.length;
        }

        function lineAtCursor() {
            var s = selStart();
            var start = ta.value.lastIndexOf('\n', s - 1) + 1;
            var end = ta.value.indexOf('\n', s);
            return end === -1 ? ta.value.slice(start) : ta.value.slice(start, end);
        }

        function insertAtCursor(text) {
            var start = selStart();
            var end = ta.selectionEnd != null ? ta.selectionEnd : start;
            ta.focus();
            ta.setSelectionRange(start, end);
            var v = ta.value;
            ta.value = v.slice(0, start) + text + v.slice(end);
            var pos = start + text.length;
            ta.setSelectionRange(pos, pos);
            if (typeof ta.dispatchEvent === 'function') {
                ta.dispatchEvent(new Event('input'));
            }
        }

        function markerFor(kind) {
            if (kind === 'bullet') return '• ';
            if (kind === 'number') {
                counters.number++;
                return counters.number + '. ';
            }
            return '— ';
        }

        document.querySelectorAll('#tb-toolbar button').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var kind = btn.dataset.insert;
                var cur = lineAtCursor();
                var marker = markerFor(kind);
                var text = (cur.trim() === '') ? marker : ('\n' + marker);
                insertAtCursor(text);
            });
        });

        // Enter pada baris bernomor -> lanjut nomor berikutnya.
        ta.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            var cur = lineAtCursor();
            var m = cur.match(/^(\s*)(\d+)\.\s/);
            if (m) {
                e.preventDefault();
                insertAtCursor('\n' + m[1] + (parseInt(m[2], 10) + 1) + '. ');
            }
        });
    }
</script>
