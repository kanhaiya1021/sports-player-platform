// Sports Player Platform Public JavaScript
jQuery(document).ready(function($) {
    console.log('SPP Public loaded');

    // Player Directory AJAX Search
    var spp_search_timeout = null;

    function spp_debounce_search() {
        if (spp_search_timeout) {
            clearTimeout(spp_search_timeout);
        }
        spp_search_timeout = setTimeout(function() {
            spp_search_players();
        }, 300);
    }

    // Search input with debounce
    $('#spp_dir_search').on('input', function() {
        if (spp_search_timeout) {
            clearTimeout(spp_search_timeout);
        }
        spp_search_timeout = setTimeout(spp_search_players, 300);
    });

    // Sport filter change
    $(document).on('change', '.spp-filter-sport', function() {
        spp_search_players();
    });

    // Position filter change
    $(document).on('change', '.spp-filter-position', function() {
        spp_search_players();
    });

    // Graduation year filter change
    $(document).on('change', '.spp-filter-grad-year', function() {
        spp_search_players();
    });

    // Ranking filter change
    $(document).on('change', '.spp-filter-ranking', function() {
        spp_search_players();
    });

    // Reset filters
    $(document).on('click', '.spp-reset-filters', function() {
        $('#spp_dir_search').val('');
        $('.spp-filter-sport').val('').trigger('change');
        $('.spp-filter-position').val('').prop('selected', true).trigger('change');
        $('.spp-filter-grad-year').val('').prop('selected', true).trigger('change');
        $('.spp-filter-ranking').val('').prop('selected', true).trigger('change');
    });

    function spp_search_players() {
        var search = $('#spp_dir_search').val();
        var sport = $('.spp-filter-sport').val();
        var position = $('.spp-filter-position').val();
        var grad_year = $('.spp-filter-grad-year').val();
        var ranking = $('.spp-filter-ranking').val();

        $.ajax({
            url: SPP_Public.ajax_url,
            type: 'POST',
            data: {
                action: 'spp_search_players',
                search: search,
                sport: sport,
                playing_position: position,
                graduation_year: grad_year,
                ranking: ranking,
                nonce: SPP_Public.nonce
            },
            beforeSend: function() {
                $('.spp-players-grid').html('<p>Loading...</p>');
            },
            success: function(response) {
                $('.spp-players-grid').html(response);
            },
            error: function() {
                $('.spp-players-grid').html('<p>Error loading players. Please try again.</p>');
            }
        });
    }
});


// Phase 6: Vanilla JS Player Directory Filtering
document.addEventListener('DOMContentLoaded', () => {
    const filterForm = document.getElementById('spp-directory-filters');
    const grid = document.getElementById('spp-directory-grid');
    const countEl = document.getElementById('spp-results-count');
    const resetBtn = document.getElementById('spp-dir-reset');
    if (!filterForm || !grid || typeof SPP_Public === 'undefined') return;

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
});
