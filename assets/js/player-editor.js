document.addEventListener('DOMContentLoaded', () => {
    const playerName = document.getElementById('title');
    if (playerName) {
        playerName.required = true;
    }

    const primarySport = document.getElementById('spp-player-field-sport');
    const dominantLabel = document.querySelector('[data-spp-dominant-side]');
    function selectedSport() {
        const sport = (primarySport?.value || '').trim().toLowerCase();
        const aliases = sppPlayerEditor.statSportAliases;
        return Object.hasOwn(aliases, sport) ? aliases[sport] : 'other';
    }

    function updateDominantLabel() {
        if (dominantLabel) {
            dominantLabel.textContent = {
                cricket: sppPlayerEditor.dominantHand,
                football: sppPlayerEditor.dominantFoot,
                other: sppPlayerEditor.dominantSide,
            }[selectedSport()];
        }
    }
    primarySport?.addEventListener('input', updateDominantLabel);
    updateDominantLabel();

    const recruitingStatus = document.getElementById('spp-player-field-recruiting_status');
    function updateRecruitingPreferences() {
        const open = recruitingStatus?.value === 'open';
        document.querySelectorAll('[data-spp-recruiting-preference]').forEach((field) => {
            field.hidden = !open;
            field.querySelector('input').disabled = !open;
        });
    }
    recruitingStatus?.addEventListener('change', updateRecruitingPreferences);
    updateRecruitingPreferences();

    function updateMetrics(row) {
        const select = row.querySelector('[data-spp-stat-sport]');
        if (select) {
            row.querySelectorAll('[data-spp-stat-metrics]').forEach((group) => {
                group.hidden = group.dataset.sppStatMetrics !== select.value;
                group.querySelectorAll('input').forEach((input) => { input.disabled = group.hidden; });
            });
        }
    }

    document.querySelectorAll('[data-spp-season-group]').forEach((group) => {
        const rows = group.querySelector('[data-spp-season-rows]');
        const template = group.querySelector('[data-spp-season-template]');
        const add = group.querySelector('[data-spp-add-season]');
        const maximum = Number(group.dataset.maxRows);
        let index = Number(group.dataset.nextIndex);

        function updateAdd() {
            add.disabled = group.dataset.canAdd !== '1' || rows.childElementCount >= maximum;
        }
        add.addEventListener('click', () => {
            if (rows.childElementCount >= maximum) {
                return;
            }
            const row = template.content.firstElementChild.cloneNode(true);
            row.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace('__row__', String(index));
            });
            index++;
            const sport = row.querySelector('[data-spp-stat-sport]');
            if (sport) {
                sport.value = selectedSport();
                updateMetrics(row);
            }
            rows.append(row);
            row.querySelector('select').focus();
            updateAdd();
        });
        rows.addEventListener('click', (event) => {
            const button = event.target.closest('[data-spp-remove-season]');
            if (button) {
                const row = button.closest('[data-spp-season-row]');
                const adjacent = row.nextElementSibling || row.previousElementSibling;
                row.remove();
                updateAdd();
                (adjacent?.querySelector('[data-spp-remove-season]') || add).focus();
            }
        });
        rows.addEventListener('change', (event) => {
            if (event.target.matches('[data-spp-stat-sport]')) {
                updateMetrics(event.target.closest('[data-spp-season-row]'));
            }
            if (event.target.matches('[data-spp-current-team]') && event.target.checked) {
                rows.querySelectorAll('[data-spp-current-team]').forEach((checkbox) => {
                    if (checkbox !== event.target) {
                        checkbox.checked = false;
                    }
                });
            }
        });
        updateAdd();
    });

    const galleryInput = document.getElementById('spp-player-field-image_gallery');
    const gallery = document.querySelector('[data-spp-gallery]');
    const picker = document.querySelector('[data-spp-gallery-picker]');

    if (!galleryInput || !gallery) {
        return;
    }

    function galleryUrls() {
        return galleryInput.value.split(/\r\n?|\n|,\s*(?=https?:\/\/)/i)
            .map((url) => url.trim())
            .filter(Boolean);
    }

    function isPreviewUrl(value) {
        if (!/^https?:\/\//i.test(value) || /\s/.test(value)) {
            return false;
        }
        try {
            const url = new URL(value);
            return !url.username && !url.password;
        } catch {
            return false;
        }
    }

    function renderGallery() {
        const urls = [...new Set(galleryUrls())].filter(isPreviewUrl);
        gallery.replaceChildren();
        gallery.hidden = urls.length === 0;

        urls.forEach((url, index) => {
            const item = document.createElement('li');
            const photo = document.createElement('img');
            photo.alt = '';
            photo.loading = 'lazy';
            photo.referrerPolicy = 'no-referrer';
            photo.src = url;

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'button button-small';
            remove.textContent = sppPlayerEditor.removePhoto.replace('%d', String(index + 1));
            remove.addEventListener('click', () => {
                galleryInput.value = galleryUrls().filter((entry) => entry !== url).join('\n');
                renderGallery();
                const buttons = gallery.querySelectorAll('button');
                (buttons[index] || buttons[buttons.length - 1] || picker || galleryInput).focus();
            });

            item.append(photo, remove);
            gallery.append(item);
        });
    }

    galleryInput.addEventListener('change', renderGallery);
    renderGallery();

    if (!picker || !window.wp || !wp.media) {
        return;
    }

    let frame;
    picker.addEventListener('click', () => {
        if (!frame) {
            frame = wp.media({
                title: sppPlayerEditor.galleryTitle,
                button: { text: sppPlayerEditor.galleryButton },
                library: { type: 'image' },
                multiple: true,
            });
            frame.on('open', () => frame.state().get('selection').reset());
            frame.on('select', () => {
                const selected = frame.state().get('selection').toJSON()
                    .map((attachment) => attachment.url)
                    .filter(Boolean);
                galleryInput.value = [...new Set([...galleryUrls(), ...selected])].join('\n');
                renderGallery();
            });
        }
        frame.open();
    });
});
