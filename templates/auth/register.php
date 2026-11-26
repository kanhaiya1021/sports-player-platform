<?php
/**
 * Registration Form Template
 *
 * Available variables:
 * @var string $error
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="spp-auth-container spp-register-form">
    <?php if (!empty($error)): ?>
        <div class="spp-notice spp-notice-error">
            <p><?php echo esc_html($error); ?></p>
        </div>
    <?php endif; ?>

    <h3><?php esc_html_e('Create Account', 'sports-player-platform'); ?></h3>
    <form method="post" action="" class="spp-form">
        <?php wp_nonce_field('spp_register_nonce', 'spp_nonce'); ?>
        <input type="hidden" name="spp_register_action" value="register">

        <p class="spp-form-field">
            <label for="spp_full_name"><?php esc_html_e('Full Name', 'sports-player-platform'); ?></label>
            <input type="text" name="full_name" id="spp_full_name" required maxlength="100" autocomplete="name">
        </p>

        <p class="spp-form-field">
            <label for="spp_reg_username"><?php esc_html_e('Username', 'sports-player-platform'); ?></label>
            <input type="text" name="username" id="spp_reg_username" required maxlength="49" autocomplete="username">
        </p>

        <p class="spp-form-field">
            <label for="spp_reg_email"><?php esc_html_e('Email Address', 'sports-player-platform'); ?></label>
            <input type="email" name="email" id="spp_reg_email" required autocomplete="email">
        </p>

        <p class="spp-form-field">
            <label for="spp_reg_password"><?php esc_html_e('Password (min 8 characters)', 'sports-player-platform'); ?></label>
            <input type="password" name="password" id="spp_reg_password" required minlength="8" autocomplete="new-password">
        </p>

        <p class="spp-form-field">
            <label for="spp_reg_role"><?php esc_html_e('I am a...', 'sports-player-platform'); ?></label>
            <select name="role" id="spp_reg_role" required>
                <option value=""><?php esc_html_e('Select Role', 'sports-player-platform'); ?></option>
                <option value="spp_player"><?php esc_html_e('Player / Athlete', 'sports-player-platform'); ?></option>
                <option value="spp_parent"><?php esc_html_e('Parent / Guardian', 'sports-player-platform'); ?></option>
                <option value="spp_coach"><?php esc_html_e('Coach / Recruiter', 'sports-player-platform'); ?></option>
            </select>
        </p>

        <p class="spp-form-field">
            <label for="spp_reg_sport"><?php esc_html_e('Primary Sport', 'sports-player-platform'); ?></label>
            <input type="text" name="sport" id="spp_reg_sport" placeholder="<?php esc_attr_e('e.g., Soccer, Basketball, Cricket', 'sports-player-platform'); ?>" maxlength="100">
        </p>

        <p class="spp-form-submit">
            <button type="submit" class="button button-primary spp-btn-primary">
                <?php esc_html_e('Register', 'sports-player-platform'); ?>
            </button>
        </p>
    </form>

    <div class="spp-auth-links">
        <a href="<?php echo esc_url(home_url('/login/')); ?>">
            <?php esc_html_e('Already have an account? Log in', 'sports-player-platform'); ?>
        </a>
    </div>
</div>
