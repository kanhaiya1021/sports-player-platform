<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<h2 style="margin-top:0; color:#111827;"><?php esc_html_e('Membership Confirmed!', 'sports-player-platform'); ?></h2>
<p><?php printf(esc_html__('Hello %s,', 'sports-player-platform'), esc_html($data['name'] ?? '')); ?></p>
<p><?php printf(esc_html__('Your %s membership is now active.', 'sports-player-platform'), '<strong>' . esc_html($data['plan_name'] ?? 'Pro') . '</strong>'); ?></p>
<?php if (!empty($data['expires_at'])): ?>
    <p><?php printf(esc_html__('Next renewal / expiry date: %s', 'sports-player-platform'), esc_html(date_i18n('F j, Y', strtotime($data['expires_at'])))); ?></p>
<?php endif; ?>
<?php if (!empty($data['dashboard_url'])): ?>
    <p style="margin:24px 0;">
        <a href="<?php echo esc_url($data['dashboard_url']); ?>" style="display:inline-block; background-color:#2563eb; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600;">
            <?php esc_html_e('Go to Dashboard', 'sports-player-platform'); ?>
        </a>
    </p>
<?php endif; ?>
