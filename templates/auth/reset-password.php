<?php
/**
 * SPP password request and reset forms.
 *
 * @package Sports_Player_Platform
 * @var array $view Template data supplied by SPP_Auth.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$spp_reset_messages = array(
	'reset_sent'        => __( 'If an account matches this email address, a password reset link will be sent.', 'sports-player-platform' ),
	'invalid_reset'     => __( 'The reset link is invalid, expired, or already used. Request a new link below.', 'sports-player-platform' ),
	'password_mismatch' => __( 'Passwords must match and have at least eight characters.', 'sports-player-platform' ),
);
?>
<div class="spp-auth-container spp-reset-form">
	<?php if ( isset( $spp_reset_messages[ $view['status'] ] ) ) : ?>
		<div class="spp-notice <?php echo 'reset_sent' === $view['status'] ? 'spp-notice-success' : 'spp-notice-error'; ?>" role="<?php echo 'reset_sent' === $view['status'] ? 'status' : 'alert'; ?>">
			<p><?php echo esc_html( $spp_reset_messages[ $view['status'] ] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $view['confirm'] ) : ?>
		<h3><?php esc_html_e( 'Set New Password', 'sports-player-platform' ); ?></h3>
		<form method="post" action="<?php echo esc_url( spp_page_url( 'reset-password' ) ); ?>" class="spp-form">
			<?php wp_nonce_field( 'spp_auth_reset-confirm', 'spp_nonce', false ); ?>
			<input type="hidden" name="spp_auth_action" value="reset-confirm">
			<p class="spp-form-field">
				<label for="spp_pwd"><?php esc_html_e( 'New Password (at least 8 characters)', 'sports-player-platform' ); ?></label>
				<input type="password" name="pwd" id="spp_pwd" required minlength="8" maxlength="4096" autocomplete="new-password">
			</p>
			<p class="spp-form-field">
				<label for="spp_pwd_confirm"><?php esc_html_e( 'Confirm New Password', 'sports-player-platform' ); ?></label>
				<input type="password" name="pwd_confirm" id="spp_pwd_confirm" required minlength="8" maxlength="4096" autocomplete="new-password">
			</p>
			<p class="spp-form-submit">
				<button type="submit" class="button button-primary spp-btn-primary"><?php esc_html_e( 'Reset Password', 'sports-player-platform' ); ?></button>
			</p>
		</form>
	<?php else : ?>
		<h3><?php esc_html_e( 'Reset Password', 'sports-player-platform' ); ?></h3>
		<form method="post" action="<?php echo esc_url( spp_page_url( 'reset-password' ) ); ?>" class="spp-form">
			<?php wp_nonce_field( 'spp_auth_reset-request', 'spp_nonce', false ); ?>
			<input type="hidden" name="spp_auth_action" value="reset-request">
			<p class="spp-form-field">
				<label for="spp_reset_email"><?php esc_html_e( 'Email Address', 'sports-player-platform' ); ?></label>
				<input type="email" name="email" id="spp_reset_email" required autocomplete="email">
			</p>
			<p class="spp-form-submit">
				<button type="submit" class="button button-primary spp-btn-primary"><?php esc_html_e( 'Send Reset Link', 'sports-player-platform' ); ?></button>
			</p>
		</form>
	<?php endif; ?>

	<div class="spp-auth-links">
		<a href="<?php echo esc_url( spp_page_url( 'login' ) ); ?>"><?php esc_html_e( 'Back to Login', 'sports-player-platform' ); ?></a>
	</div>
</div>
