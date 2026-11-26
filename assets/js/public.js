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
        $('.spp-filter-position').val('').trigger('change');
        $('.spp-filter-grad-year').val('').trigger('change');
        $('.spp-filter-ranking').val('').trigger('change');
    });

    function spp_search_players() {
        var position = $('.spp-filter-position').val();
        var grad_year = $('.spp-filter-grad-year').val();
        var ranking = $('.spp-filter-ranking').val();

        $.ajax({
            url: SPP_Public.ajax_url,
            type: 'POST',
            data: {
                action: 'spp_search_players',
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