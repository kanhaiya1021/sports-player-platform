<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<h2 style="margin-top:0; color:#111827;"><?php esc_html_e('Verify Your Email Address', 'sports-player-platform'); ?></h2>
<p><?php printf(esc_html__('Hello %s,', 'sports-player-platform'), esc_html($data['name'] ?? '')); ?></p>
<p><?php echo esc_html($data['intro'] ?? __('Please click the button below to verify your email address. This link expires in 24 hours.', 'sports-player-platform')); ?></p>
<?php if (!empty($data['url'])): ?>
    <p style="margin:24px 0;">
        <a href="<?php echo esc_url($data['url']); ?>" style="display:inline-block; background-color:#2563eb; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600;">
            <?php esc_html_e('Verify Email Address', 'sports-player-platform'); ?>
        </a>
    </p>
    <p style="font-size:13px; color:#6b7280; word-break:break-all;"><?php echo esc_url($data['url']); ?></p>
<?php endif; ?>
