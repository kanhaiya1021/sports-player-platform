document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('spp-toggle-create-list');
    const createForm = document.getElementById('spp-create-list-form');
    const feedback = document.getElementById('spp-recruiting-feedback');

    const showFeedback = (msg, isError = false) => {
        if (!feedback) return;
        feedback.style.display = 'block';
        feedback.className = 'spp-notice ' + (isError ? 'spp-notice-error' : 'spp-notice-success');
        feedback.textContent = msg;
    };

    const withLoading = async (btn, task) => {
        if (!btn) return task();
        const prevDisabled = btn.disabled;
        btn.disabled = true;
        btn.classList.add('is-loading');
        try {
            return await task();
        } finally {
            btn.disabled = prevDisabled;
            btn.classList.remove('is-loading');
        }
    };

    if (toggleBtn && createForm) {
        toggleBtn.addEventListener('click', () => {
            createForm.style.display = createForm.style.display === 'none' ? 'block' : 'none';
        });
    }

    if (createForm && typeof sppRecruiting !== 'undefined') {
        createForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = createForm.querySelector('button[type="submit"]');
            await withLoading(submitBtn, async () => {
                const body = new FormData(createForm);
                body.append('action', 'spp_create_recruiting_list');
                body.append('nonce', sppRecruiting.nonce);

                const res = await fetch(sppRecruiting.ajaxUrl, { method: 'POST', body });
                const data = await res.json();
                if (data.success && data.data?.list_id) {
                    window.location.search = `?list_id=${data.data.list_id}`;
                } else {
                    showFeedback(data.data?.message || sppRecruiting.i18n.error, true);
                }
            });
        });
    }

    // Add to list from single player profile
    const addToListForm = document.getElementById('spp-add-to-list-form');
    if (addToListForm && typeof sppRecruiting !== 'undefined') {
        addToListForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = addToListForm.querySelector('button[type="submit"]');
            await withLoading(submitBtn, async () => {
                const body = new FormData(addToListForm);
                body.append('action', 'spp_add_player_to_list');
                body.append('nonce', sppRecruiting.nonce);

                const msgEl = document.getElementById('spp-add-to-list-msg');
                const res = await fetch(sppRecruiting.ajaxUrl, { method: 'POST', body });
                const data = await res.json();
                if (msgEl) {
                    msgEl.textContent = data.success ? sppRecruiting.i18n.added : (data.data?.message || sppRecruiting.i18n.error);
                }
            });
        });
    }

    // Save inline notes
    document.querySelectorAll('.spp-save-list-notes').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (typeof sppRecruiting === 'undefined') return;
            await withLoading(btn, async () => {
                const listId = btn.getAttribute('data-list-id');
                const playerId = btn.getAttribute('data-player-id');
                const row = btn.closest('tr');
                const notes = row ? row.querySelector('.spp-list-notes-input')?.value || '' : '';

                const body = new FormData();
                body.append('action', 'spp_update_list_player_notes');
                body.append('nonce', sppRecruiting.nonce);
                body.append('list_id', listId);
                body.append('player_id', playerId);
                body.append('notes', notes);

                const res = await fetch(sppRecruiting.ajaxUrl, { method: 'POST', body });
                const data = await res.json();
                showFeedback(data.success ? sppRecruiting.i18n.saved : (data.data?.message || sppRecruiting.i18n.error), !data.success);
            });
        });
    });

    // Remove player from list
    document.querySelectorAll('.spp-remove-from-list').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (typeof sppRecruiting === 'undefined') return;
            await withLoading(btn, async () => {
                const listId = btn.getAttribute('data-list-id');
                const playerId = btn.getAttribute('data-player-id');

                const body = new FormData();
                body.append('action', 'spp_remove_player_from_list');
                body.append('nonce', sppRecruiting.nonce);
                body.append('list_id', listId);
                body.append('player_id', playerId);

                const res = await fetch(sppRecruiting.ajaxUrl, { method: 'POST', body });
                const data = await res.json();
                if (data.success) {
                    const row = btn.closest('tr');
                    if (row) row.remove();
                }
            });
        });
    });
});
