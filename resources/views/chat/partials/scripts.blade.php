<script>
(function () {
    'use strict';
    const threadUrl = @json($conversation ? route('chat.show', $conversation) : route('chat.index'));
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const list = document.getElementById('conversation-list');
    const filters = document.getElementById('chat-filters');
    let refreshing = false;
    @if ($conversation)
    const history = document.getElementById('message-list');
    const attachUrl = @json(route('chat.attach-options', $conversation));
    const editBase = @json(route('chat.show', $conversation));
    const form = document.getElementById('message-form');
    const body = document.getElementById('msg-body');
    const send = document.getElementById('send-btn');
    history.scrollTop = @json($viewingOlder ?? false) ? 0 : history.scrollHeight;

    form.addEventListener('submit', function (event) {
        if (send.disabled) { event.preventDefault(); return; }
        if (!body.value.trim()) { event.preventDefault(); body.focus(); return; }
        send.disabled = true;
        send.textContent = 'Mengirim...';
    });
    body.addEventListener('input', function () {
        body.style.height = 'auto';
        body.style.height = Math.min(body.scrollHeight, 120) + 'px';
    });
    body.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            if (body.value.trim() && !send.disabled) form.requestSubmit();
        }
    });

    const attach = document.getElementById('attach-panel');
    const attachButton = document.getElementById('attach-btn');
    function closeAttach() { attach.classList.add('hidden'); attachButton.setAttribute('aria-expanded', 'false'); }
    document.getElementById('attach-close').addEventListener('click', closeAttach);
    attachButton.addEventListener('click', async function () {
        if (!attach.classList.contains('hidden')) { closeAttach(); return; }
        attach.classList.remove('hidden');
        attachButton.setAttribute('aria-expanded', 'true');
        const container = document.getElementById('attach-list');
        container.textContent = 'Memuat referensi...';
        try {
            const response = await fetch(attachUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error('Gagal mengambil referensi.');
            const result = await response.json();
            container.replaceChildren();
            (result.categories || []).forEach(function (category) {
                if (!category.items || !category.items.length) return;
                const heading = document.createElement('p');
                heading.className = 'px-2 pt-2 text-xs font-semibold text-text-secondary';
                heading.textContent = category.title;
                container.appendChild(heading);
                category.items.forEach(function (item) {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'block w-full rounded-control px-2 py-1.5 text-left hover:bg-bg-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand';
                    option.textContent = item.label + (item.student ? ' · ' + item.student : '');
                    option.addEventListener('click', function () {
                        document.getElementById('attach-type').value = item.type;
                        document.getElementById('attach-id').value = item.id;
                        const selected = document.getElementById('selected-attach');
                        selected.replaceChildren();
                        selected.append(document.createTextNode('Referensi: ' + item.label + ' '));
                        const clear = document.createElement('button');
                        clear.type = 'button';
                        clear.className = 'underline';
                        clear.textContent = 'Hapus';
                        clear.setAttribute('aria-label', 'Hapus referensi terpilih');
                        clear.addEventListener('click', function () {
                            document.getElementById('attach-type').value = '';
                            document.getElementById('attach-id').value = '';
                            selected.classList.add('hidden');
                        });
                        selected.append(clear);
                        selected.classList.remove('hidden');
                        closeAttach();
                        body.focus();
                    });
                    container.appendChild(option);
                });
            });
            if (!container.children.length) container.textContent = 'Tidak ada karya mahasiswa untuk disematkan.';
        } catch (error) { container.textContent = 'Referensi tidak dapat dimuat. Coba lagi.'; }
    });

    const modal = document.getElementById('edit-modal');
    const editBody = document.getElementById('edit-body');
    history.addEventListener('click', function (event) {
        const edit = event.target.closest('.edit-link');
        if (!edit) return;
        const message = edit.closest('[data-message-id]');
        editBody.value = message.querySelector('.message-body').textContent;
        document.getElementById('edit-form').action = editBase + '/' + edit.dataset.edit;
        modal.classList.remove('hidden');
        editBody.focus();
    });
    function closeModal() { modal.classList.add('hidden'); }
    document.getElementById('edit-cancel').addEventListener('click', closeModal);
    modal.addEventListener('click', function (event) { if (event.target === modal) closeModal(); });
    modal.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeModal(); });

    @endif
    // Fetch the authorized server-rendered workspace; avoid duplicating message/attachment rendering in JS.
    async function refresh() {
        if (refreshing || document.hidden @if ($conversation) || send.disabled @endif) return;
        refreshing = true;
        try {
            const refreshUrl = new URL(threadUrl, window.location.href);
            const query = new URLSearchParams(window.location.search);
            query.delete('before');
            refreshUrl.search = query.toString();
            const response = await fetch(refreshUrl, { credentials: 'same-origin', headers: { 'Accept': 'text/html' } });
            if (!response.ok) return;
            const documentCopy = new DOMParser().parseFromString(await response.text(), 'text/html');
            const freshHistory = documentCopy.getElementById('message-list');
            const freshList = documentCopy.getElementById('conversation-list');
            const freshFilters = documentCopy.getElementById('chat-filters');
            if (!freshList || !freshFilters) return;
            @if ($conversation)
            if (!freshHistory) return;
            const nearBottom = history.scrollHeight - history.scrollTop - history.clientHeight < 100;
            const previousHeight = history.scrollHeight;
            const previousTop = history.scrollTop;
            const oldIds = Array.from(history.querySelectorAll('[data-message-id]')).map(el => el.dataset.messageId).join(',');
            const newIds = Array.from(freshHistory.querySelectorAll('[data-message-id]')).map(el => el.dataset.messageId).join(',');
            if (!@json($viewingOlder ?? false) && (oldIds !== newIds || history.innerHTML !== freshHistory.innerHTML)) {
                history.innerHTML = freshHistory.innerHTML;
                history.scrollTop = nearBottom ? history.scrollHeight : previousTop + (history.scrollHeight - previousHeight);
            }
            @endif
            const listTop = list.scrollTop;
            if (list.innerHTML !== freshList.innerHTML) { list.innerHTML = freshList.innerHTML; list.scrollTop = listTop; }
            if (filters.innerHTML !== freshFilters.innerHTML) filters.innerHTML = freshFilters.innerHTML;
        } catch (error) { /* Subsequent refresh will retry. */ }
        finally { refreshing = false; }
    }
    // One user channel covers active and inactive conversations. The layout has its own
    // separate notification listener; it does not subscribe to message.sent.
    if (typeof Pusher !== 'undefined') {
        try {
            const key = @json(config('broadcasting.connections.reverb.key'));
            const host = @json(config('broadcasting.connections.reverb.host'));
            const port = @json(config('broadcasting.connections.reverb.port'));
            const scheme = @json(config('broadcasting.connections.reverb.scheme'));
            if (key && host) {
                const pusher = new Pusher(key, { wsHost: host, wsPort: port, wssPort: port, forceTLS: scheme === 'https', enabledTransports: ['ws', 'wss'], authEndpoint: '/broadcasting/auth', auth: { headers: { 'X-CSRF-TOKEN': csrf } } });
                pusher.subscribe('private-user.' + @json($user->id)).bind('message.sent', refresh);
            }
        } catch (error) { /* Periodic refresh remains available. */ }
    }
    setInterval(refresh, 15000);
})();
</script>