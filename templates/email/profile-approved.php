<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<h2 style="margin-top:0; color:#15803d;"><?php esc_html_e('Player Profile Approved!', 'sports-player-platform'); ?></h2>
<p><?php printf(esc_html__('Hello %s,', 'sports-player-platform'), esc_html($data['name'] ?? '')); ?></p>
<p><?php printf(esc_html__('Great news! Your player profile "%s" has been approved and is now live in the public player directory.', 'sports-player-platform'), '<strong>' . esc_html($data['player_name'] ?? '') . '</strong>'); ?></p>
<?php if (!empty($data['profile_url'])): ?>
    <p style="margin:24px 0;">
        <a href="<?php echo esc_url($data['profile_url']); ?>" style="display:inline-block; background-color:#16a34a; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600;">
            <?php esc_html_e('View Live Profile', 'sports-player-platform'); ?>
        </a>
    </p>
<?php endif; ?>
