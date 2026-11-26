// Sports Player Platform Admin JavaScript
jQuery(document).ready(function($) {
    console.log('SPP Admin loaded');

    // Profile Management AJAX handlers
    $(document).on('click', '.spp-approve-row', function() {
        var profileId = $(this).data('id');
        $('#spp-profile-id').val(profileId);
        $('#spp-approve-btn').trigger('click');
    });

    $(document).on('click', '.spp-reject-row', function() {
        var profileId = $(this).data('id');
        $('#spp-profile-id').val(profileId);
        $('#spp-reject-btn').trigger('click');
    });

    $('#spp-approve-btn').on('click', function() {
        var profileId = $('#spp-profile-id').val();
        var nonce = SPP_Ajax.nonce;

        if (!profileId) {
            alert('Please select a profile');
            return;
        }

        $.post(SPP_Ajax.ajax_url, {
            action: 'spp_approve_profile',
            profile_id: profileId,
            nonce: nonce
        }).done(function(response) {
            location.reload();
        }).fail(function() {
            alert('Approval failed. Please try again.');
        });
    });

    $('#spp-reject-btn').on('click', function() {
        var profileId = $('#spp-profile-id').val();
        var nonce = SPP_Ajax.nonce;

        if (!profileId) {
            alert('Please select a profile');
            return;
        }

        $.post(SPP_Ajax.ajax_url, {
            action: 'spp_reject_profile',
            profile_id: profileId,
            nonce: nonce
        }).done(function(response) {
            location.reload();
        }).fail(function() {
            alert('Rejection failed. Please try again.');
        });
    });

    // Edit Ranking Category Modal
    $(document).on('click', '.spp-edit-category', function() {
        var categoryId = $(this).data('id');
        var categoryName = $(this).data('name');
        var categoryDescription = $(this).data('description');

        $('#edit-category-id').val(categoryId);
        $('#edit-category-name').val(categoryName);
        $('#edit-category-description').val(categoryDescription);

        $('#spp-edit-category-modal').show();
    });

    $('#spp-close-modal, #spp-close-edit-modal').on('click', function() {
        $('#spp-edit-category-modal').hide();
    });

    // Close modal when clicking outside
    $(document).on('click', function(e) {
        if ($(e.target).id === 'spp-edit-category-modal') {
            $('#spp-edit-category-modal').hide();
        }
    });
});