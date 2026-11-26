<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<h2 style="margin-top:0; color:#b91c1c;"><?php esc_html_e('Player Profile Needs Updates', 'sports-player-platform'); ?></h2>
<p><?php printf(esc_html__('Hello %s,', 'sports-player-platform'), esc_html($data['name'] ?? '')); ?></p>
<p><?php printf(esc_html__('Your player profile "%s" was not approved at this time. Please review your profile details, complete any missing information, and resubmit from your dashboard.', 'sports-player-platform'), '<strong>' . esc_html($data['player_name'] ?? '') . '</strong>'); ?></p>
<?php if (!empty($data['edit_url'])): ?>
    <p style="margin:24px 0;">
        <a href="<?php echo esc_url($data['edit_url']); ?>" style="display:inline-block; background-color:#2563eb; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600;">
            <?php esc_html_e('Update Profile', 'sports-player-platform'); ?>
        </a>
    </p>
<?php endif; ?>
