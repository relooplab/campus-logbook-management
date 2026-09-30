<script>
    (function () {
        const form = document.getElementById('quick-review-form');
        const feedback = document.getElementById('feedback_dosen');
        const error = document.getElementById('feedback-error');
        const submit = document.getElementById('quick-review-submit');
        const radios = Array.from(form.querySelectorAll('[name="review_decision"]'));
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const initialFeedback = feedback.value;
        const initialDecision = form.querySelector('[name="review_decision"]:checked')?.value || '';
        let submitting = false;

        function decision() { return form.querySelector('[name="review_decision"]:checked')?.value || ''; }
        function syncDecision() {
            const revision = decision() === 'revisi';
            feedback.required = revision;
            feedback.disabled = decision() === 'approve';
            document.getElementById('feedback-required').classList.toggle('hidden', !revision);
            submit.disabled = !decision();
            form.action = revision ? form.dataset.revisionUrl : form.dataset.approveUrl;
            if (!revision) error.classList.add('hidden');
        }
        radios.forEach(radio => radio.addEventListener('change', syncDecision));
        syncDecision();

        feedback.addEventListener('input', () => error.classList.add('hidden'));
        document.querySelectorAll('.quick-review-navigation').forEach(link => link.addEventListener('click', event => {
            if ((feedback.value !== initialFeedback || decision() !== initialDecision) && !window.confirm('Keputusan atau feedback belum disimpan. Pindah item tanpa menyimpan?')) event.preventDefault();
        }));
        document.getElementById('use-last')?.addEventListener('click', function () {
            feedback.value = @json($lastFeedback);
            feedback.dispatchEvent(new Event('input'));
            if (decision() === 'approve') { radios.find(r => r.value === 'revisi').checked = true; syncDecision(); }
            feedback.focus();
        });
        document.getElementById('template-select').addEventListener('change', function () {
            const option = this.selectedOptions[0];
            if (!option?.value) return;
            feedback.value = option.dataset.body || '';
            feedback.dispatchEvent(new Event('input'));
            if (decision() === 'approve') { radios.find(r => r.value === 'revisi').checked = true; syncDecision(); }
            feedback.focus();
        });
        form.addEventListener('submit', function (event) {
            if (submitting) { event.preventDefault(); return; }
            if (decision() === 'revisi' && feedback.value.trim().length < 20) {
                event.preventDefault(); error.classList.remove('hidden'); feedback.focus(); return;
            }
            if (form.dataset.pdfOpened !== '1' && !window.confirm('Lampiran PDF belum tercatat dibuka. Tetap ' + (decision() === 'revisi' ? 'minta revisi' : 'setujui') + '?')) {
                event.preventDefault(); return;
            }
            submitting = true;
            submit.disabled = true;
            submit.textContent = 'Memproses…';
        });

        document.getElementById('build-feedback').addEventListener('click', async function () {
            this.disabled = true;
            try {
                const response = await fetch(@json(route('quick-review.build-feedback', $entry)), {method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}, credentials: 'same-origin'});
                if (!response.ok) throw new Error();
                const result = await response.json();
                if (!result.feedback) { window.alert('Tidak ada komentar PDF yang belum diselesaikan.'); return; }
                feedback.value = result.feedback;
                feedback.dispatchEvent(new Event('input'));
                if (decision() === 'approve') { radios.find(r => r.value === 'revisi').checked = true; syncDecision(); }
                feedback.focus();
            } catch (e) { window.alert('Gagal memuat komentar PDF.'); }
            finally { this.disabled = false; }
        });

        const modal = document.getElementById('tpl-modal');
        const title = document.getElementById('tpl-title');
        const body = document.getElementById('tpl-body');
        const templateError = document.getElementById('tpl-error');
        function closeModal() { modal.classList.add('hidden'); document.getElementById('new-tpl').focus(); }
        document.getElementById('new-tpl').addEventListener('click', () => { body.value = feedback.value; templateError.classList.add('hidden'); modal.classList.remove('hidden'); title.focus(); });
        document.getElementById('tpl-cancel').addEventListener('click', closeModal);
        modal.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
        document.getElementById('tpl-save').addEventListener('click', async function () {
            if (!body.value.trim()) { templateError.textContent = 'Isi template wajib diisi.'; templateError.classList.remove('hidden'); body.focus(); return; }
            this.disabled = true;
            try {
                const response = await fetch(@json(route('feedback-templates.store')), {method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}, credentials: 'same-origin', body: JSON.stringify({title: title.value.trim(), body: body.value.trim()})});
                if (!response.ok) throw new Error();
                const template = await response.json();
                const option = new Option(template.title || template.body.slice(0, 50), template.id);
                option.dataset.body = template.body;
                const select = document.getElementById('template-select');
                select.add(option); select.value = String(template.id);
                select.options[0].textContent = 'Pilih template...';
                closeModal();
            } catch (e) { templateError.textContent = 'Gagal menyimpan template. Coba lagi.'; templateError.classList.remove('hidden'); }
            finally { this.disabled = false; }
        });
    })();
</script>