<?php
/**
 * Dashboard Membership & Payment History Template
 *
 * @var array|null $membership
 * @var array      $payment_logs
 * @var string     $active_tab
 */

if (!defined('ABSPATH')) {
    exit;
}

$plan_type   = $membership['type'] ?? 'free';
$plan_status = $membership['status'] ?? 'active';
?>
<div class="spp-dashboard-wrap">
    <?php include SPP_PLUGIN_DIR . 'templates/dashboard/nav.php'; ?>

    <h2><?php esc_html_e('My Membership', 'sports-player-platform'); ?></h2>
    <?php if (($_GET['spp_payment'] ?? '') === 'success' && ($plan_status !== 'active' || $plan_type === 'free')): ?>
        <p><?php esc_html_e('Your membership will update when the payment provider confirms your payment.', 'sports-player-platform'); ?></p>
    <?php endif; ?>

    <div id="spp-membership-feedback" class="spp-notice" style="display:none;"></div>

    <div class="spp-card spp-membership-card">
        <div class="spp-membership-details">
            <p><strong><?php esc_html_e('Current Plan:', 'sports-player-platform'); ?></strong> <?php echo esc_html(ucfirst($plan_type)); ?></p>
            <p><strong><?php esc_html_e('Status:', 'sports-player-platform'); ?></strong> <span id="spp-membership-status"><?php echo esc_html(ucfirst($plan_status)); ?></span></p>
            <?php if (!empty($membership['started_at'])): ?>
                <p><strong><?php esc_html_e('Started:', 'sports-player-platform'); ?></strong> <?php echo esc_html(spp_format_date($membership['started_at'])); ?></p>
            <?php endif; ?>
            <?php if (!empty($membership['expires_at'])): ?>
                <p><strong><?php esc_html_e('Renews / Expires:', 'sports-player-platform'); ?></strong> <?php echo esc_html(spp_format_date($membership['expires_at'])); ?></p>
            <?php endif; ?>
        </div>

        <div class="spp-membership-actions">
            <a href="<?php echo esc_url(spp_page_url('pricing')); ?>" class="spp-btn spp-btn-primary">
                <?php echo $plan_type === 'free' ? esc_html__('Upgrade Plan', 'sports-player-platform') : esc_html__('Change Plan', 'sports-player-platform'); ?>
            </a>
            <?php if ($plan_type !== 'free' && $plan_status !== 'cancelled'): ?>
                <button type="button" id="spp-cancel-membership-btn" class="spp-btn spp-btn-danger">
                    <?php esc_html_e('Cancel Subscription', 'sports-player-platform'); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <h3><?php esc_html_e('Payment History', 'sports-player-platform'); ?></h3>
    <?php if (empty($payment_logs)): ?>
        <p><?php esc_html_e('No payment records found.', 'sports-player-platform'); ?></p>
    <?php else: ?>
        <table class="spp-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Date', 'sports-player-platform'); ?></th>
                    <th><?php esc_html_e('Gateway', 'sports-player-platform'); ?></th>
                    <th><?php esc_html_e('Transaction ID', 'sports-player-platform'); ?></th>
                    <th><?php esc_html_e('Amount', 'sports-player-platform'); ?></th>
                    <th><?php esc_html_e('Status', 'sports-player-platform'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payment_logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html(spp_format_date($log['created_at'])); ?></td>
                        <td><?php echo esc_html(ucfirst($log['gateway'])); ?></td>
                        <td><code><?php echo esc_html($log['transaction_id']); ?></code></td>
                        <td><?php echo esc_html(strtoupper($log['currency']) . ' ' . number_format((float) $log['amount'], 2)); ?></td>
                        <td><?php echo esc_html(ucfirst($log['status'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
