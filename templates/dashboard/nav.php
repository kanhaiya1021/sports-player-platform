<?php
/**
 * Shared Dashboard Navigation Partial
 *
 * @var string $active_tab
 */

if (!defined('ABSPATH')) {
    exit;
}

$active_tab    = $active_tab ?? 'overview';
$current_uid   = get_current_user_id();
$is_player     = current_user_can('edit_own_profile') || current_user_can('manage_options');
$is_coach      = current_user_can('create_recruiting_list') || current_user_can('manage_options');
$can_save      = current_user_can('view_saved_players') || current_user_can('manage_options');
$logout_url    = wp_nonce_url(add_query_arg(['spp_logout' => '1'], home_url('/')), 'spp_logout_nonce');
$unread_msgs   = class_exists('SPP_Messaging') ? SPP_Messaging::get_instance()->get_unread_count($current_uid) : 0;
$notifications = class_exists('SPP_Messaging') ? SPP_Messaging::get_instance()->get_unread_notifications($current_uid, 5) : [];
?>
<nav class="spp-dashboard-nav" aria-label="<?php esc_attr_e('Dashboard Navigation', 'sports-player-platform'); ?>">
    <a href="<?php echo esc_url(spp_page_url('dashboard')); ?>" class="spp-nav-item <?php echo $active_tab === 'overview' ? 'is-active' : ''; ?>">
        <?php esc_html_e('Overview', 'sports-player-platform'); ?>
    </a>

    <?php if ($is_player): ?>
        <a href="<?php echo esc_url(spp_page_url('dashboard/edit-profile')); ?>" class="spp-nav-item <?php echo $active_tab === 'edit-profile' ? 'is-active' : ''; ?>">
            <?php esc_html_e('Edit Profile', 'sports-player-platform'); ?>
        </a>
    <?php endif; ?>

    <?php if ($can_save): ?>
        <a href="<?php echo esc_url(spp_page_url('dashboard/shortlist')); ?>" class="spp-nav-item <?php echo $active_tab === 'shortlist' ? 'is-active' : ''; ?>">
            <?php esc_html_e('Shortlist', 'sports-player-platform'); ?>
        </a>
    <?php endif; ?>

    <?php if ($is_coach): ?>
        <a href="<?php echo esc_url(spp_page_url('dashboard/recruiting')); ?>" class="spp-nav-item <?php echo $active_tab === 'recruiting' ? 'is-active' : ''; ?>">
            <?php esc_html_e('Recruiting Lists', 'sports-player-platform'); ?>
        </a>
    <?php endif; ?>

    <a href="<?php echo esc_url(spp_page_url('dashboard/messages')); ?>" class="spp-nav-item <?php echo $active_tab === 'messages' ? 'is-active' : ''; ?>">
        <?php esc_html_e('Messages', 'sports-player-platform'); ?>
        <span id="spp-nav-unread-badge" class="spp-unread-pill" style="<?php echo $unread_msgs > 0 ? '' : 'display:none;'; ?>">
            <?php echo esc_html((string) $unread_msgs); ?>
        </span>
    </a>

    <a href="<?php echo esc_url(spp_page_url('dashboard/membership')); ?>" class="spp-nav-item <?php echo $active_tab === 'membership' ? 'is-active' : ''; ?>">
        <?php esc_html_e('Membership', 'sports-player-platform'); ?>
    </a>

    <div class="spp-notif-wrap">
        <button type="button" id="spp-notif-bell" class="spp-nav-item" aria-label="<?php esc_attr_e('Notifications', 'sports-player-platform'); ?>">
            🔔 <span id="spp-notif-count" style="<?php echo count($notifications) > 0 ? '' : 'display:none;'; ?>">(<?php echo esc_html((string) count($notifications)); ?>)</span>
        </button>
        <div id="spp-notif-dropdown" class="spp-notif-dropdown" style="display:none;">
            <?php if (empty($notifications)): ?>
                <p style="margin:0; color:#6b7280;"><?php esc_html_e('No new notifications.', 'sports-player-platform'); ?></p>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <a href="<?php echo esc_url($notif['link'] ?: '#'); ?>" class="spp-notif-item">
                        <strong><?php echo esc_html($notif['title']); ?></strong><br>
                        <span><?php echo esc_html($notif['message']); ?></span>
                    </a>
                <?php endforeach; ?>
                <button type="button" id="spp-notif-mark-read" class="spp-btn spp-btn-primary" style="margin-top:8px; width:100%; font-size:12px;">
                    <?php esc_html_e('Mark all read', 'sports-player-platform'); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <a href="<?php echo esc_url($logout_url); ?>" class="spp-nav-item spp-nav-logout">
        <?php esc_html_e('Log Out', 'sports-player-platform'); ?>
    </a>
</nav>
