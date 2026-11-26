<?php
/**
 * Admin View: Platform Reports & Analytics
 *
 * @package Sports_Player_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Platform Reports', 'sports-player-platform'); ?></h1>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin: 20px 0;">
        <div class="card" style="padding: 16px; margin: 0;">
            <h3 style="margin-top: 0;"><?php esc_html_e('Total Revenue', 'sports-player-platform'); ?></h3>
            <p style="font-size: 26px; font-weight: 700; margin: 0;">$<?php echo esc_html(number_format((float) $reports['total_revenue'], 2)); ?></p>
            <small><?php printf(esc_html__('This month: $%s', 'sports-player-platform'), esc_html(number_format((float) $reports['month_revenue'], 2))); ?></small>
        </div>

        <div class="card" style="padding: 16px; margin: 0;">
            <h3 style="margin-top: 0;"><?php esc_html_e('Active Paid Members', 'sports-player-platform'); ?></h3>
            <p style="font-size: 26px; font-weight: 700; margin: 0;"><?php echo absint($reports['active_paid']); ?></p>
            <small><?php printf(esc_html__('Free members: %d', 'sports-player-platform'), absint($reports['active_free'])); ?></small>
        </div>

        <div class="card" style="padding: 16px; margin: 0;">
            <h3 style="margin-top: 0;"><?php esc_html_e('New Signups (7d)', 'sports-player-platform'); ?></h3>
            <p style="font-size: 26px; font-weight: 700; margin: 0;"><?php echo absint($reports['signups_7d']); ?></p>
            <small><?php printf(esc_html__('Last 30 days: %d', 'sports-player-platform'), absint($reports['signups_30d'])); ?></small>
        </div>

        <div class="card" style="padding: 16px; margin: 0;">
            <h3 style="margin-top: 0;"><?php esc_html_e('Messages Sent (30d)', 'sports-player-platform'); ?></h3>
            <p style="font-size: 26px; font-weight: 700; margin: 0;"><?php echo absint($reports['messages_30d']); ?></p>
            <small><?php printf(esc_html__('Total conversations: %d', 'sports-player-platform'), absint($reports['total_conversations'])); ?></small>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 20px; margin-top: 24px;">
        <div>
            <h2><?php esc_html_e('Most Viewed Players (Last 30 Days)', 'sports-player-platform'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Player', 'sports-player-platform'); ?></th>
                        <th><?php esc_html_e('Views', 'sports-player-platform'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($reports['top_viewed_players'])): ?>
                        <?php foreach ($reports['top_viewed_players'] as $player): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url(get_edit_post_link($player['player_id'])); ?>">
                                        <?php echo esc_html($player['post_title'] ?: ('#' . $player['player_id'])); ?>
                                    </a>
                                </td>
                                <td><strong><?php echo absint($player['view_count']); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2"><?php esc_html_e('No player views recorded yet.', 'sports-player-platform'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div>
            <h2><?php esc_html_e('Messages Per Day (Last 14 Days)', 'sports-player-platform'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'sports-player-platform'); ?></th>
                        <th><?php esc_html_e('Messages Sent', 'sports-player-platform'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($reports['messages_per_day'])): ?>
                        <?php foreach ($reports['messages_per_day'] as $day): ?>
                            <tr>
                                <td><?php echo esc_html($day['msg_date']); ?></td>
                                <td><strong><?php echo absint($day['msg_count']); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2"><?php esc_html_e('No messages sent in the last 14 days.', 'sports-player-platform'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <h2 style="margin-top: 28px;"><?php esc_html_e('Recent Payment Transactions', 'sports-player-platform'); ?></h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('User', 'sports-player-platform'); ?></th>
                <th><?php esc_html_e('Gateway', 'sports-player-platform'); ?></th>
                <th><?php esc_html_e('Amount', 'sports-player-platform'); ?></th>
                <th><?php esc_html_e('Status', 'sports-player-platform'); ?></th>
                <th><?php esc_html_e('Transaction ID', 'sports-player-platform'); ?></th>
                <th><?php esc_html_e('Date', 'sports-player-platform'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($reports['recent_payments'])): ?>
                <?php foreach ($reports['recent_payments'] as $payment): ?>
                    <tr>
                        <td><?php echo esc_html($payment['user_email'] ?: ('User #' . $payment['user_id'])); ?></td>
                        <td><?php echo esc_html(ucfirst($payment['gateway'])); ?></td>
                        <td><?php echo esc_html(strtoupper($payment['currency']) . ' ' . number_format((float) $payment['amount'], 2)); ?></td>
                        <td><?php echo esc_html(ucfirst($payment['status'])); ?></td>
                        <td><code><?php echo esc_html($payment['transaction_id']); ?></code></td>
                        <td><?php echo esc_html(date_i18n('Y-m-d H:i', strtotime($payment['created_at']))); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6"><?php esc_html_e('No payment logs found.', 'sports-player-platform'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
