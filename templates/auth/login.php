<?php
/**
 * Login Form Template
 *
 * Available variables:
 * @var string $error
 * @var bool   $unverified
 * @var string $unverified_user
 * @var bool   $registered
 * @var bool   $verified
 * @var bool   $resent
 * @var bool   $reset_success
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="spp-auth-container spp-login-form">
    <?php if (!empty($registered)): ?>
        <div class="spp-notice spp-notice-success">
            <p><?php esc_html_e('Registration successful! Please check your email and click the verification link before logging in.', 'sports-player-platform'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($verified)): ?>
        <div class="spp-notice spp-notice-success">
            <p><?php esc_html_e('Your email has been verified! You can now log in.', 'sports-player-platform'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($resent)): ?>
        <div class="spp-notice spp-notice-success">
            <p><?php esc_html_e('A new verification email has been sent. Please check your inbox.', 'sports-player-platform'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($reset_success)): ?>
        <div class="spp-notice spp-notice-success">
            <p><?php esc_html_e('Your password has been reset successfully. You can now log in.', 'sports-player-platform'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="spp-notice spp-notice-error">
            <p><?php echo esc_html($error); ?></p>
            <?php if (!empty($unverified) && !empty($unverified_user)): ?>
                <p>
                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['spp_resend_verify' => '1', 'user' => rawurlencode($unverified_user)], home_url('/login/')), 'spp_resend_verify_nonce')); ?>">
                        <?php esc_html_e('Resend verification email', 'sports-player-platform'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <h3><?php esc_html_e('Log In', 'sports-player-platform'); ?></h3>
    <form method="post" action="" class="spp-form">
        <?php wp_nonce_field('spp_login_nonce', 'spp_nonce'); ?>
        <input type="hidden" name="spp_login_action" value="login">

        <p class="spp-form-field">
            <label for="spp_username"><?php esc_html_e('Username or Email', 'sports-player-platform'); ?></label>
            <input type="text" name="username" id="spp_username" required autocomplete="username">
        </p>

        <p class="spp-form-field">
            <label for="spp_password"><?php esc_html_e('Password', 'sports-player-platform'); ?></label>
            <input type="password" name="password" id="spp_password" required autocomplete="current-password">
        </p>

        <p class="spp-form-field spp-form-remember">
            <label>
                <input type="checkbox" name="remember" value="1">
                <?php esc_html_e('Remember me', 'sports-player-platform'); ?>
            </label>
        </p>

        <p class="spp-form-submit">
            <button type="submit" class="button button-primary spp-btn-primary">
                <?php esc_html_e('Log In', 'sports-player-platform'); ?>
            </button>
        </p>
    </form>

    <div class="spp-auth-links">
        <a href="<?php echo esc_url(home_url('/reset-password/')); ?>">
            <?php esc_html_e('Forgot your password?', 'sports-player-platform'); ?>
        </a>
        <span>|</span>
        <a href="<?php echo esc_url(home_url('/register/')); ?>">
            <?php esc_html_e('Create an account', 'sports-player-platform'); ?>
        </a>
    </div>
</div>
