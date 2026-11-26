<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<h2 style="margin-top:0; color:#111827;"><?php esc_html_e('New Message Received', 'sports-player-platform'); ?></h2>
<p><?php printf(esc_html__('Hello %s,', 'sports-player-platform'), esc_html($data['name'] ?? '')); ?></p>
<p><?php printf(esc_html__('You received a new message from %s regarding "%s".', 'sports-player-platform'), '<strong>' . esc_html($data['sender_name'] ?? '') . '</strong>', esc_html($data['message_subject'] ?? '')); ?></p>
<?php if (!empty($data['url'])): ?>
    <p style="margin:24px 0;">
        <a href="<?php echo esc_url($data['url']); ?>" style="display:inline-block; background-color:#2563eb; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600;">
            <?php esc_html_e('View Message', 'sports-player-platform'); ?>
        </a>
    </p>
<?php endif; ?>
