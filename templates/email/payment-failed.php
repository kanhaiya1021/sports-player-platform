<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<h2 style="margin-top:0; color:#b91c1c;"><?php esc_html_e('Payment Failed', 'sports-player-platform'); ?></h2>
<p><?php printf(esc_html__('Hello %s,', 'sports-player-platform'), esc_html($data['name'] ?? '')); ?></p>
<p><?php printf(esc_html__('We could not process your recent payment of %s via %s.', 'sports-player-platform'), esc_html(($data['currency'] ?? 'USD') . ' ' . number_format((float) ($data['amount'] ?? 0), 2)), esc_html($data['gateway'] ?? '')); ?></p>
<p><?php esc_html_e('Please update your payment method or retry checkout to keep your membership active.', 'sports-player-platform'); ?></p>
<?php if (!empty($data['pricing_url'])): ?>
    <p style="margin:24px 0;">
        <a href="<?php echo esc_url($data['pricing_url']); ?>" style="display:inline-block; background-color:#dc2626; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600;">
            <?php esc_html_e('Retry Payment', 'sports-player-platform'); ?>
        </a>
    </p>
<?php endif; ?>
