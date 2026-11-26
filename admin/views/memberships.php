<?php
/**
 * Admin View: Memberships Management
 *
 * @package Sports_Player_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Memberships Management', 'sports-player-platform'); ?></h1>

    <?php if (!empty($notice)): ?>
        <div class="notice notice-<?php echo esc_attr($notice_type ?? 'success'); ?> is-dismissible">
            <p><?php echo esc_html($notice); ?></p>
        </div>
    <?php endif; ?>

    <form method="get" style="margin: 15px 0; display: flex; gap: 8px; align-items: center;">
        <input type="hidden" name="page" value="spp-memberships">
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search user or email...', 'sports-player-platform'); ?>">
        <select name="plan">
            <option value=""><?php esc_html_e('All Plans', 'sports-player-platform'); ?></option>
            <option value="free" <?php selected($plan_filter, 'free'); ?>><?php esc_html_e('Free', 'sports-player-platform'); ?></option>
            <option value="monthly" <?php selected($plan_filter, 'monthly'); ?>><?php esc_html_e('Monthly Pro', 'sports-player-platform'); ?></option>
            <option value="annual" <?php selected($plan_filter, 'annual'); ?>><?php esc_html_e('Annual All-Access', 'sports-player-platform'); ?></option>
        </select>
        <select name="status">
            <option value=""><?php esc_html_e('All Statuses', 'sports-player-platform'); ?></option>
            <option value="active" <?php selected($status_filter, 'active'); ?>><?php esc_html_e('Active', 'sports-player-platform'); ?></option>
            <option value="expired" <?php selected($status_filter, 'expired'); ?>><?php esc_html_e('Expired', 'sports-player-platform'); ?></option>
            <option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>><?php esc_html_e('Cancelled', 'sports-player-platform'); ?></option>
        </select>
        <button type="submit" class="button"><?php esc_html_e('Filter', 'sports-player-platform'); ?></button>
    </form>

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('User', 'sports-player-platform'); ?></th>
                <th><?php esc_html_e('Plan', 'sports-player-platform'); ?></th>
                <th><?php esc_html_e('Status', 'sports-player-platform'); ?></th>
                <th><?php esc_html_e('Started', 'sports-player-platform'); ?></th>
                <th><?php esc_html_e('Expires', 'sports-player-platform'); ?></th>
                <th><?php esc_html_e('Payment Method', 'sports-player-platform'); ?></th>
                <th><?php esc_html_e('Actions', 'sports-player-platform'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($memberships)): ?>
                <?php foreach ($memberships as $row): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($row['display_name'] ?: $row['user_login']); ?></strong><br>
                            <small><?php echo esc_html($row['user_email']); ?></small>
                        </td>
                        <td><?php echo esc_html(ucfirst($row['type'])); ?></td>
                        <td>
                            <span class="spp-status spp-status-<?php echo esc_attr($row['status']); ?>">
                                <?php echo esc_html(ucfirst($row['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo $row['started_at'] ? esc_html(date_i18n('Y-m-d', strtotime($row['started_at']))) : '—'; ?></td>
                        <td><?php echo $row['expires_at'] ? esc_html(date_i18n('Y-m-d', strtotime($row['expires_at']))) : esc_html__('Never', 'sports-player-platform'); ?></td>
                        <td><?php echo $row['payment_method'] ? esc_html(ucfirst($row['payment_method'])) : '—'; ?></td>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-flex; gap:6px; align-items:center; flex-wrap:wrap;">
                                <?php wp_nonce_field('spp_admin_membership_action'); ?>
                                <input type="hidden" name="action" value="spp_admin_update_membership">
                                <input type="hidden" name="membership_id" value="<?php echo absint($row['id']); ?>">
                                <input type="hidden" name="user_id" value="<?php echo absint($row['user_id']); ?>">

                                <select name="new_plan">
                                    <option value="free" <?php selected($row['type'], 'free'); ?>><?php esc_html_e('Free', 'sports-player-platform'); ?></option>
                                    <option value="monthly" <?php selected($row['type'], 'monthly'); ?>><?php esc_html_e('Monthly', 'sports-player-platform'); ?></option>
                                    <option value="annual" <?php selected($row['type'], 'annual'); ?>><?php esc_html_e('Annual', 'sports-player-platform'); ?></option>
                                </select>
                                <button type="submit" name="sub_action" value="change_plan" class="button button-small button-primary">
                                    <?php esc_html_e('Set Plan', 'sports-player-platform'); ?>
                                </button>
                                <?php if ($row['type'] !== 'free'): ?>
                                    <button type="submit" name="sub_action" value="extend_expiry" class="button button-small">
                                        <?php esc_html_e('+30 Days', 'sports-player-platform'); ?>
                                    </button>
                                <?php endif; ?>
                                <?php if ($row['status'] === 'active' && $row['type'] !== 'free'): ?>
                                    <button type="submit" name="sub_action" value="cancel" class="button button-small" onclick="return confirm('<?php echo esc_js(__('Cancel this membership?', 'sports-player-platform')); ?>');">
                                        <?php esc_html_e('Cancel', 'sports-player-platform'); ?>
                                    </button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7"><?php esc_html_e('No memberships found.', 'sports-player-platform'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
