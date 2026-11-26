<?php
/**
 * Shared Dashboard Navigation Partial
 *
 * @var string $active_tab
 */

if (!defined('ABSPATH')) {
    exit;
}

$active_tab = $active_tab ?? 'overview';
$is_player  = current_user_can('edit_own_profile') || current_user_can('manage_options');
$is_coach   = current_user_can('create_recruiting_list') || current_user_can('manage_options');
$can_save   = current_user_can('view_saved_players') || current_user_can('manage_options');
$logout_url = wp_nonce_url(add_query_arg(['spp_logout' => '1'], home_url('/')), 'spp_logout_nonce');
?>
<nav class="spp-dashboard-nav" aria-label="<?php esc_attr_e('Dashboard Navigation', 'sports-player-platform'); ?>">
    <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="spp-nav-item <?php echo $active_tab === 'overview' ? 'is-active' : ''; ?>">
        <?php esc_html_e('Overview', 'sports-player-platform'); ?>
    </a>

    <?php if ($is_player): ?>
        <a href="<?php echo esc_url(home_url('/dashboard/edit-profile/')); ?>" class="spp-nav-item <?php echo $active_tab === 'edit-profile' ? 'is-active' : ''; ?>">
            <?php esc_html_e('Edit Profile', 'sports-player-platform'); ?>
        </a>
    <?php endif; ?>

    <?php if ($can_save): ?>
        <a href="<?php echo esc_url(home_url('/dashboard/shortlist/')); ?>" class="spp-nav-item <?php echo $active_tab === 'shortlist' ? 'is-active' : ''; ?>">
            <?php esc_html_e('Shortlist', 'sports-player-platform'); ?>
        </a>
    <?php endif; ?>

    <?php if ($is_coach): ?>
        <a href="<?php echo esc_url(home_url('/dashboard/recruiting/')); ?>" class="spp-nav-item <?php echo $active_tab === 'recruiting' ? 'is-active' : ''; ?>">
            <?php esc_html_e('Recruiting Lists', 'sports-player-platform'); ?>
        </a>
    <?php endif; ?>

    <a href="<?php echo esc_url(home_url('/dashboard/messages/')); ?>" class="spp-nav-item <?php echo $active_tab === 'messages' ? 'is-active' : ''; ?>">
        <?php esc_html_e('Messages', 'sports-player-platform'); ?>
    </a>

    <a href="<?php echo esc_url(home_url('/dashboard/membership/')); ?>" class="spp-nav-item <?php echo $active_tab === 'membership' ? 'is-active' : ''; ?>">
        <?php esc_html_e('Membership', 'sports-player-platform'); ?>
    </a>

    <a href="<?php echo esc_url($logout_url); ?>" class="spp-nav-item spp-nav-logout">
        <?php esc_html_e('Log Out', 'sports-player-platform'); ?>
    </a>
</nav>
