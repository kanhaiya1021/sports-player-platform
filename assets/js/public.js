// Sports Player Platform Public JavaScript (Vanilla JS Player Directory AJAX Filtering)
document.addEventListener('DOMContentLoaded', () => {
    const filterForm = document.getElementById('spp-directory-filters');
    const grid = document.getElementById('spp-directory-grid');
    const countEl = document.getElementById('spp-results-count');
    const resetBtn = document.getElementById('spp-dir-reset');
    if (!filterForm || !grid || typeof SPP_Public === 'undefined') return;

    // Pre-fill filters from URL query parameters if present (e.g. ?search=elena)
    const urlParams = new URLSearchParams(window.location.search);
    let hasInitialParams = false;
    ['search', 'sport', 'position', 'grad_year', 'ranking'].forEach((key) => {
        const val = urlParams.get(key);
        if (val) {
            const field = filterForm.elements.namedItem(key);
            if (field) {
                field.value = val;
                hasInitialParams = true;
            }
        }
    });

    let timer = null;
    const fetchPlayers = async (paged = 1) => {
        const body = new FormData(filterForm);
        body.append('action', 'spp_filter_players');
        body.append('nonce', SPP_Public.nonce);
        body.append('paged', String(paged));

        grid.style.opacity = '0.5';
        try {
            const res = await fetch(SPP_Public.ajax_url, { method: 'POST', body });
            const data = await res.json();
            if (data.success && data.data) {
                grid.innerHTML = data.data.html || '';
                if (countEl) {
                    countEl.textContent = `Showing ${data.data.found} players`;
                }
            }
        } finally {
            grid.style.opacity = '1';
        }
    };

    filterForm.addEventListener('submit', (e) => {
        e.preventDefault();
        fetchPlayers(1);
    });

    filterForm.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => fetchPlayers(1), 300);
    });

    filterForm.addEventListener('change', () => fetchPlayers(1));

    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            filterForm.reset();
            fetchPlayers(1);
        });
    }

    if (hasInitialParams) {
        fetchPlayers(1);
    }
});
