<?php
/**
 * Password Reset Form Template
 *
 * Available variables:
 * @var string $mode   'request' | 'confirm' | 'invalid'
 * @var string $error
 * @var bool   $sent
 * @var string $key
 * @var string $login
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="spp-auth-container spp-reset-form">
    <?php if (!empty($error)): ?>
        <div class="spp-notice spp-notice-error">
            <p><?php echo esc_html($error); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($sent)): ?>
        <div class="spp-notice spp-notice-success">
            <p><?php esc_html_e('If an account exists with that email, a password reset link has been sent.', 'sports-player-platform'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($mode === 'confirm'): ?>
        <h3><?php esc_html_e('Set New Password', 'sports-player-platform'); ?></h3>
        <form method="post" action="" class="spp-form">
            <?php wp_nonce_field('spp_reset_confirm_nonce', 'spp_nonce'); ?>
            <input type="hidden" name="spp_reset_action" value="reset_confirm">
            <input type="hidden" name="key" value="<?php echo esc_attr($key); ?>">
            <input type="hidden" name="login" value="<?php echo esc_attr($login); ?>">

            <p class="spp-form-field">
                <label for="spp_pwd"><?php esc_html_e('New Password (min 8 characters)', 'sports-player-platform'); ?></label>
                <input type="password" name="pwd" id="spp_pwd" required minlength="8" autocomplete="new-password">
            </p>

            <p class="spp-form-field">
                <label for="spp_pwd_confirm"><?php esc_html_e('Confirm New Password', 'sports-player-platform'); ?></label>
                <input type="password" name="pwd_confirm" id="spp_pwd_confirm" required minlength="8" autocomplete="new-password">
            </p>

            <p class="spp-form-submit">
                <button type="submit" class="button button-primary spp-btn-primary">
                    <?php esc_html_e('Reset Password', 'sports-player-platform'); ?>
                </button>
            </p>
        </form>
    <?php elseif ($mode === 'invalid'): ?>
        <p><a href="<?php echo esc_url(home_url('/reset-password/')); ?>"><?php esc_html_e('Request a new password reset link', 'sports-player-platform'); ?></a></p>
    <?php else: ?>
        <h3><?php esc_html_e('Reset Password', 'sports-player-platform'); ?></h3>
        <form method="post" action="" class="spp-form">
            <?php wp_nonce_field('spp_reset_nonce', 'spp_nonce'); ?>
            <input type="hidden" name="spp_reset_action" value="reset_request">

            <p class="spp-form-field">
                <label for="spp_reset_email"><?php esc_html_e('Enter your email address', 'sports-player-platform'); ?></label>
                <input type="email" name="email" id="spp_reset_email" required autocomplete="email">
            </p>

            <p class="spp-form-submit">
                <button type="submit" class="button button-primary spp-btn-primary">
                    <?php esc_html_e('Send Reset Link', 'sports-player-platform'); ?>
                </button>
            </p>
        </form>
    <?php endif; ?>

    <div class="spp-auth-links">
        <a href="<?php echo esc_url(home_url('/login/')); ?>">
            <?php esc_html_e('Back to Login', 'sports-player-platform'); ?>
        </a>
    </div>
</div>
