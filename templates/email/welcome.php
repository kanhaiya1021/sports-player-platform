<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<h2 style="margin-top:0; color:#111827;"><?php esc_html_e('Welcome to Sports Player Platform!', 'sports-player-platform'); ?></h2>
<p><?php printf(esc_html__('Hello %s,', 'sports-player-platform'), esc_html($data['name'] ?? '')); ?></p>
<p><?php echo esc_html($data['intro'] ?? __('Your account is verified and ready to use.', 'sports-player-platform')); ?></p>
<?php if (!empty($data['url'])): ?>
    <p style="margin:24px 0;">
        <a href="<?php echo esc_url($data['url']); ?>" style="display:inline-block; background-color:#2563eb; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600;">
            <?php esc_html_e('Sign In to Your Account', 'sports-player-platform'); ?>
        </a>
    </p>
<?php endif; ?>
