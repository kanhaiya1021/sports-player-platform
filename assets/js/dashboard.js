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

            // Get current tab before saving
            const currentTab = document.querySelector('.spp-tab-panel.is-active')?.getAttribute('data-panel') || 'personal';

            const formData = new FormData(profileForm);
            formData.append('action', 'spp_save_player_profile');
            formData.append('nonce', sppDashboard.nonce);
            formData.append('current_tab', currentTab);

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

                // Auto-advance to next tab on successful save
                if (data.success && data.data?.next_tab) {
                    switchToTab(data.data.next_tab);
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

    // Helper function to switch tabs with animation
    function switchToTab(tabName) {
        const tabBtn = document.querySelector(`.spp-tab-btn[data-tab="${tabName}"]`);
        const tabPanel = document.querySelector(`.spp-tab-panel[data-panel="${tabName}"]`);
        if (tabBtn && tabPanel) {
            // Reset all tabs
            document.querySelectorAll('.spp-tab-btn').forEach(b => b.classList.remove('is-active'));
            document.querySelectorAll('.spp-tab-panel').forEach(p => p.classList.remove('is-active'));
            // Activate new tab
            tabBtn.classList.add('is-active');
            tabPanel.classList.add('is-active');
        }
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

    // Dynamic Add/Remove Image Gallery URLs (Now uses image cards)
    const addImageButton = document.getElementById('spp-add-image-gallery');
    const imageGalleryWrap = document.getElementById('spp-image-gallery-wrap');
    if (addImageButton && imageGalleryWrap) {
        addImageButton.addEventListener('click', () => {
            const field = document.createElement('div');
            field.className = 'spp-image-card spp-card';
            field.setAttribute('data-url', '');
            field.style.borderColor = 'var(--spp-border)';
            field.style.background = '#fff';
            field.innerHTML = `
                <div class="spp-image-preview" style="background-image: none; background-color: #f3f4f6;"></div>
                <input type="url" name="image_gallery[]" placeholder="https://example.com/image.jpg" class="spp-image-url" style="flex: 1;">
                <button type="button" class="spp-image-remove" aria-label="Remove" style="position: absolute; top: 8px; right: 8px;">×</button>
            `;
            imageGalleryWrap.appendChild(field);
            field.querySelector('.spp-image-remove').addEventListener('click', () => {
                field.remove();
                updateImageCount();
            });
            updateImageCount();
        });

        // Attach remove handlers to existing image cards
        imageGalleryWrap.querySelectorAll('.spp-image-remove').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.closest('.spp-image-card').remove();
                updateImageCount();
            });
        });

        // Handle URL input to show preview
        imageGalleryWrap.querySelectorAll('.spp-image-url').forEach(input => {
            input.addEventListener('change', function() {
                const card = this.closest('.spp-image-card');
                const preview = card.querySelector('.spp-image-preview');
                const url = this.value.trim();
                if (url) {
                    preview.style.backgroundImage = `url('${url}')`;
                    card.setAttribute('data-url', url);
                }
                updateImageCount();
            });
        });

        // Update image count display
        window.updateImageCount = function() {
            const count = imageGalleryWrap.querySelectorAll('.spp-image-card').length;
            const countInput = document.getElementById('spp_image_count');
            if (countInput) countInput.value = count;
        };
    }

    // Image upload with single file selection
    const imageUploadInput = document.getElementById('spp_image_upload');
    const imagePreviewContainer = document.getElementById('spp-image-preview-container');
    let uploadedFilesCount = 0;

    if (imageUploadInput) {
        imageUploadInput.addEventListener('change', async (e) => {
            const files = Array.from(e.target.files || []);
            if (!files.length) return;

            for (const file of files) {
                // Check image count (6 maximum)
                const currentCount = imageGalleryWrap.querySelectorAll('.spp-image-card').length;
                if (currentCount >= 6) {
                    alert('Maximum 6 images allowed.');
                    break;
                }

                uploadedFilesCount = currentCount + 1;

                // Preview the selected file
                const previewEl = document.createElement('div');
                previewEl.className = 'spp-uploaded-image';
                previewEl.style.width = '80px';
                previewEl.style.height = '80px';
                previewEl.style.borderRadius = '8px';
                previewEl.style.background = '#f3f4f6';
                previewEl.style.display = 'flex';
                previewEl.style.alignItems = 'center';
                previewEl.style.justifyContent = 'center';
                previewEl.style.fontSize = '12px';
                previewEl.style.color = '#6b7280';
                previewEl.textContent = 'Uploading...';
                if (imagePreviewContainer) {
                    imagePreviewContainer.appendChild(previewEl);
                }

                // Upload via AJAX
                const formData = new FormData();
                formData.append('action', 'spp_upload_image');
                formData.append('nonce', sppDashboard.nonce);
                formData.append('file', file);

            try {
                const res = await fetch(sppDashboard.ajaxUrl, { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success && data.data?.url) {
                    // Add to image gallery
                    const card = document.createElement('div');
                    card.className = 'spp-image-card';
                    card.setAttribute('data-url', data.data.url);
                    card.style.marginTop = '12px';
                    card.style.padding = '8px';
                    card.style.background = '#fff';
                    card.style.border = '1px solid var(--spp-border)';
                    card.style.borderRadius = '6px';
                    card.innerHTML = `
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <div class="spp-image-preview" style="background-image: url('${escapeHtml(data.data.url)}'); width: 60px; height: 60px; border-radius: 4px;"></div>
                            <input type="url" name="image_gallery[]" value="${escapeHtml(data.data.url)}" class="spp-image-url" placeholder="Image URL" style="flex: 1;">
                            <button type="button" class="spp-image-remove" aria-label="Remove">×</button>
                        </div>
                    `;
                    imageGalleryWrap.appendChild(card);

                    // Add remove handler
                    card.querySelector('.spp-image-remove').addEventListener('click', () => {
                        card.remove();
                        updateImageCount();
                    });

                    // Add URL change handler
                    card.querySelector('.spp-image-url').addEventListener('change', function() {
                        const url = this.value.trim();
                        if (url) {
                            card.setAttribute('data-url', url);
                            card.querySelector('.spp-image-preview').style.backgroundImage = `url('${escapeHtml(url)}')`;
                        }
                        updateImageCount();
                    });

                    updateImageCount();
                } else {
                    alert(data.data?.message || 'Upload failed');
                    previewEl.remove();
                }
            } catch (err) {
                alert('Upload error');
                previewEl.remove();
            }
            }
            e.target.value = '';
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Image gallery URL field remove handlers for dynamic cards
    if (imageGalleryWrap) {
        imageGalleryWrap.querySelectorAll('.spp-image-remove').forEach(btn => {
            if (!btn.hasAttribute('data-listener-attached')) {
                btn.setAttribute('data-listener-attached', 'true');
                btn.addEventListener('click', () => {
                    btn.closest('.spp-image-card').remove();
                    updateImageCount();
                });
            }
        });
    }

    // Dynamic Add/Remove Video Gallery URLs
    const addVideoButton = document.getElementById('spp-add-video-gallery');
    const videoGalleryWrap = document.getElementById('spp-video-gallery-wrap');
    if (addVideoButton && videoGalleryWrap) {
        addVideoButton.addEventListener('click', () => {
            if (videoGalleryWrap.querySelectorAll('.spp-url-field').length >= 6) {
                alert('Maximum 6 videos allowed.');
                return;
            }
            const field = document.createElement('div');
            field.className = 'spp-url-field';
            field.innerHTML = `
                <input type="url" name="videos_highlights[]" placeholder="https://youtube.com/watch?v=..." class="spp-url-input">
                <button type="button" class="spp-url-remove" aria-label="Remove">&times;</button>
            `;
            videoGalleryWrap.appendChild(field);
            field.querySelector('.spp-url-remove').addEventListener('click', () => {
                field.remove();
            });
        });

        // Attach remove handlers to existing video fields
        videoGalleryWrap.querySelectorAll('.spp-url-remove').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.closest('.spp-url-field').remove();
            });
        });
    }

    const videoUploadInput = document.getElementById('spp_video_upload');
    if (videoUploadInput && videoGalleryWrap) {
        videoUploadInput.addEventListener('change', (e) => {
            const existingCount = videoGalleryWrap.querySelectorAll('.spp-url-field').length;
            const selectedCount = (e.target.files || []).length;
            if (existingCount + selectedCount > 6) {
                alert(`Maximum 6 videos allowed. You already have ${existingCount} video(s).`);
                e.target.value = '';
            }
        });
    }
});
