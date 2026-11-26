<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<h2 style="margin-top:0; color:#111827;"><?php esc_html_e('Reset Your Password', 'sports-player-platform'); ?></h2>
<p><?php printf(esc_html__('Hello %s,', 'sports-player-platform'), esc_html($data['name'] ?? '')); ?></p>
<p><?php echo esc_html($data['intro'] ?? __('We received a request to reset your password. Click the button below to choose a new password.', 'sports-player-platform')); ?></p>
<?php if (!empty($data['url'])): ?>
    <p style="margin:24px 0;">
        <a href="<?php echo esc_url($data['url']); ?>" style="display:inline-block; background-color:#2563eb; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600;">
            <?php esc_html_e('Reset Password', 'sports-player-platform'); ?>
        </a>
    </p>
    <p style="font-size:13px; color:#6b7280; word-break:break-all;"><?php echo esc_url($data['url']); ?></p>
<?php endif; ?>
<p style="font-size:13px; color:#6b7280;"><?php esc_html_e('If you did not request a password reset, you can safely ignore this email.', 'sports-player-platform'); ?></p>
