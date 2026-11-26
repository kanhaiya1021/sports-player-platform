<?php
/**
 * Dashboard Home Template
 *
 * @var WP_User    $user
 * @var string     $role
 * @var array|null $membership
 * @var array      $stats
 * @var string     $active_tab
 */

if (!defined('ABSPATH')) {
    exit;
}

$plan_type   = $membership['type'] ?? 'free';
$plan_status = $membership['status'] ?? 'active';
$avatar_url  = function_exists('spp_get_avatar_url') ? spp_get_avatar_url($user->ID, 80) : get_avatar_url($user->ID);
?>
<div class="spp-dashboard-wrap">
    <?php include SPP_PLUGIN_DIR . 'templates/dashboard/nav.php'; ?>

    <div class="spp-dashboard-header">
        <div class="spp-dashboard-user">
            <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($user->display_name); ?>" class="spp-avatar">
            <div>
                <h2><?php printf(esc_html__('Welcome back, %s!', 'sports-player-platform'), esc_html($user->display_name)); ?></h2>
                <span class="spp-badge spp-badge-plan">
                    <?php echo esc_html(ucfirst($plan_type) . ' (' . ucfirst($plan_status) . ')'); ?>
                </span>
            </div>
        </div>
        <?php if ($plan_type === 'free'): ?>
            <a href="<?php echo esc_url(spp_page_url('pricing')); ?>" class="spp-btn spp-btn-primary">
                <?php esc_html_e('Upgrade Plan', 'sports-player-platform'); ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="spp-stats-grid">
        <?php foreach ($stats as $stat): ?>
            <div class="spp-stat-card">
                <div class="spp-stat-value"><?php echo esc_html((string) $stat['value']); ?></div>
                <div class="spp-stat-label"><?php echo esc_html($stat['label']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($role === 'spp_player' && class_exists('SPP_Recruiting')): ?>
        <?php
        $player_posts = get_posts(['post_type' => 'player', 'author' => $user->ID, 'post_status' => ['publish', 'spp_pending'], 'posts_per_page' => 1, 'fields' => 'ids']);
        $coach_views  = !empty($player_posts) ? SPP_Recruiting::get_instance()->get_coach_views_count((int) $player_posts[0]) : 0;
        ?>
        <div class="spp-notice spp-notice-info">
            <p><?php printf(esc_html__('Your profile was viewed by %d coaches in the last 30 days.', 'sports-player-platform'), $coach_views); ?></p>
        </div>
    <?php endif; ?>

    <div class="spp-quick-links">
        <h3><?php esc_html_e('Quick Actions', 'sports-player-platform'); ?></h3>
        <div class="spp-quick-links-grid">
            <a href="<?php echo esc_url(home_url('/players/')); ?>" class="spp-quick-card">
                <?php esc_html_e('Browse Players', 'sports-player-platform'); ?>
            </a>
            <a href="<?php echo esc_url(home_url('/rankings/')); ?>" class="spp-quick-card">
                <?php esc_html_e('View Rankings', 'sports-player-platform'); ?>
            </a>
            <a href="<?php echo esc_url(spp_page_url('dashboard/membership')); ?>" class="spp-quick-card">
                <?php esc_html_e('Manage Membership', 'sports-player-platform'); ?>
            </a>
        </div>
    </div>
</div>
