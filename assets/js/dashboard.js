document.addEventListener('DOMContentLoaded', () => {
    // Tab switching
    const tabBtns = document.querySelectorAll('.spp-tab-btn');
    const tabPanels = document.querySelectorAll('.spp-tab-panel');

    tabBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
            const target = btn.getAttribute('data-tab');
            tabBtns.forEach((b) => b.classList.remove('is-active'));
            tabPanels.forEach((p) => p.classList.remove('is-active'));
            btn.classList.add('is-active');
            const panel = document.querySelector(`.spp-tab-panel[data-panel="${target}"]`);
            if (panel) panel.classList.add('is-active');
        });
    });

    // AJAX Edit Profile Form
    const profileForm = document.getElementById('spp-edit-profile-form');
    if (profileForm && typeof sppDashboard !== 'undefined') {
        profileForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('spp-save-profile-btn');
            const feedback = document.getElementById('spp-profile-feedback');
            const originalText = btn ? btn.textContent : '';
            if (btn) {
                btn.disabled = true;
                btn.textContent = sppDashboard.i18n.saving;
            }

            const formData = new FormData(profileForm);
            formData.append('action', 'spp_save_player_profile');
            formData.append('nonce', sppDashboard.nonce);

            try {
                const res = await fetch(sppDashboard.ajaxUrl, { method: 'POST', body: formData });
                const data = await res.json();

                if (feedback) {
                    feedback.style.display = 'block';
                    feedback.className = 'spp-notice ' + (data.success ? 'spp-notice-success' : 'spp-notice-error');
                    feedback.textContent = data.data?.message || sppDashboard.i18n.error;
                }

                if (data.success && data.data?.completion !== undefined) {
                    const pct = document.getElementById('spp-completion-pct');
                    const bar = document.getElementById('spp-completion-bar');
                    if (pct) pct.textContent = `${data.data.completion}%`;
                    if (bar) bar.style.width = `${data.data.completion}%`;
                }
            } catch (err) {
                if (feedback) {
                    feedback.style.display = 'block';
                    feedback.className = 'spp-notice spp-notice-error';
                    feedback.textContent = sppDashboard.i18n.error;
                }
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            }
        });
    }

    // Cancel Membership
    const cancelBtn = document.getElementById('spp-cancel-membership-btn');
    if (cancelBtn && typeof sppDashboard !== 'undefined') {
        cancelBtn.addEventListener('click', async () => {
            if (!confirm(sppDashboard.i18n.confirmCancel)) return;

            const formData = new FormData();
            formData.append('action', 'spp_cancel_membership');
            formData.append('nonce', sppDashboard.nonce);

            const res = await fetch(sppDashboard.ajaxUrl, { method: 'POST', body: formData });
            const data = await res.json();
            const feedback = document.getElementById('spp-membership-feedback');

            if (feedback) {
                feedback.style.display = 'block';
                feedback.className = 'spp-notice ' + (data.success ? 'spp-notice-success' : 'spp-notice-error');
                feedback.textContent = data.data?.message || sppDashboard.i18n.error;
            }

            if (data.success) {
                const statusEl = document.getElementById('spp-membership-status');
                if (statusEl) statusEl.textContent = 'Cancelled';
                cancelBtn.remove();
            }
        });
    }

    // Remove Shortlisted Player
    document.querySelectorAll('.spp-remove-shortlist').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (typeof sppDashboard === 'undefined' || !confirm(sppDashboard.i18n.confirmRemove)) return;

            const playerId = btn.getAttribute('data-player-id');
            const formData = new FormData();
            formData.append('action', 'spp_remove_saved_player');
            formData.append('nonce', sppDashboard.nonce);
            formData.append('player_id', playerId);

            const res = await fetch(sppDashboard.ajaxUrl, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                const row = document.querySelector(`tr[data-player-id="${playerId}"]`);
                if (row) row.remove();
            }
        });
    });
});
