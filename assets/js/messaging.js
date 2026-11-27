document.addEventListener('DOMContentLoaded', () => {
    const shell = document.querySelector('.spp-messaging-shell');
    const threadBox = document.getElementById('spp-thread-messages');
    const replyForm = document.getElementById('spp-reply-form');
    const newConvToggle = document.getElementById('spp-new-conv-toggle');
    const newConvForm = document.getElementById('spp-new-conv-form');
    const feedback = document.getElementById('spp-msg-feedback');

    const showFeedback = (msg, isError = false) => {
        if (!feedback) return;
        feedback.style.display = 'block';
        feedback.className = 'spp-notice ' + (isError ? 'spp-notice-error' : 'spp-notice-success');
        feedback.textContent = msg;
    };

    const renderMessages = (messages) => {
        if (!threadBox || !Array.isArray(messages)) return;
        const currentId = Number(sppMessaging.currentUserId);
        threadBox.innerHTML = messages.map((m) => {
            const isMine = Number(m.sender_id) === currentId;
            return `
                <div class="spp-bubble ${isMine ? 'is-mine' : 'is-theirs'}">
                    <div class="spp-bubble-meta">
                        <strong>${escapeHtml(m.sender_name || '')}</strong>
                        <span>${escapeHtml(m.sent_at || '')}</span>
                    </div>
                    <div class="spp-bubble-body">${escapeHtml(m.message || '')}</div>
                </div>
            `;
        }).join('');
        threadBox.scrollTop = threadBox.scrollHeight;
    };

    const escapeHtml = (str) => {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    };

    if (threadBox) {
        threadBox.scrollTop = threadBox.scrollHeight;
    }

    if (newConvToggle && newConvForm) {
        newConvToggle.addEventListener('click', () => {
            newConvForm.style.display = newConvForm.style.display === 'none' ? 'block' : 'none';
        });
    }

    // Start new conversation
    if (newConvForm && typeof sppMessaging !== 'undefined') {
        newConvForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = newConvForm.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            try {
                const body = new FormData(newConvForm);
                body.append('action', 'spp_send_message');
                body.append('nonce', sppMessaging.nonce);

                const res = await fetch(sppMessaging.ajaxUrl, { method: 'POST', body });
                const data = await res.json();
                if (data.success && data.data?.conversation_id) {
                    window.location.search = `?conversation=${data.data.conversation_id}`;
                } else {
                    showFeedback(data.data?.message || sppMessaging.i18n.error, true);
                }
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    }

    // Reply in active thread
    if (replyForm && typeof sppMessaging !== 'undefined') {
        replyForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('spp-reply-btn');
            const input = document.getElementById('spp-reply-input');
            if (btn) btn.disabled = true;

            const body = new FormData(replyForm);
            body.append('action', 'spp_send_message');
            body.append('nonce', sppMessaging.nonce);

            try {
                const res = await fetch(sppMessaging.ajaxUrl, { method: 'POST', body });
                const data = await res.json();
                if (data.success) {
                    if (input) input.value = '';
                    renderMessages(data.data?.messages || []);
                } else {
                    showFeedback(data.data?.message || sppMessaging.i18n.error, true);
                }
            } finally {
                if (btn) btn.disabled = false;
            }
        });
    }

    // 5-second polling for active conversation & unread badge
    if (shell && typeof sppMessaging !== 'undefined') {
        const activeConvId = shell.getAttribute('data-active-conversation');
        setInterval(async () => {
            if (document.hidden) return;
            const url = `${sppMessaging.ajaxUrl}?action=spp_fetch_messages&nonce=${encodeURIComponent(sppMessaging.nonce)}&conversation_id=${encodeURIComponent(activeConvId || '0')}`;
            try {
                const res = await fetch(url);
                const data = await res.json();
                if (data.success) {
                    if (activeConvId && data.data?.messages) {
                        renderMessages(data.data.messages);
                    }
                    const badge = document.getElementById('spp-nav-unread-badge');
                    if (badge && data.data?.unread_total !== undefined) {
                        badge.textContent = data.data.unread_total;
                        badge.style.display = Number(data.data.unread_total) > 0 ? 'inline-block' : 'none';
                    }
                }
            } catch (_) {}
        }, sppMessaging.pollInterval || 5000);
    }

    // Notification bell toggle & mark all read
    const bellBtn = document.getElementById('spp-notif-bell');
    const bellDropdown = document.getElementById('spp-notif-dropdown');
    const markReadBtn = document.getElementById('spp-notif-mark-read');

    if (bellBtn && bellDropdown) {
        bellBtn.addEventListener('click', (e) => {
            e.preventDefault();
            bellDropdown.style.display = bellDropdown.style.display === 'none' ? 'block' : 'none';
        });
    }

    if (markReadBtn && typeof sppMessaging !== 'undefined') {
        markReadBtn.addEventListener('click', async () => {
            const body = new FormData();
            body.append('action', 'spp_mark_notifications_read');
            body.append('nonce', sppMessaging.nonce);
            await fetch(sppMessaging.ajaxUrl, { method: 'POST', body });
            const countEl = document.getElementById('spp-notif-count');
            if (countEl) countEl.style.display = 'none';
            if (bellDropdown) bellDropdown.style.display = 'none';
        });
    }
});
