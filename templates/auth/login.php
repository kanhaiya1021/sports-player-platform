<?php
/**
 * SPP login and email resend forms.
 *
 * @package Sports_Player_Platform
 * @var array $view Template data supplied by SPP_Auth.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$spp_login_messages    = array(
	'registered'             => __( 'Account created. Check your email for the verification link.', 'sports-player-platform' ),
	'registered_active'      => __( 'Account created! You can now sign in.', 'sports-player-platform' ),
	'registered_mail_failed' => __( 'Account created. The verification email could not be sent. You can request another link below.', 'sports-player-platform' ),
	'verified'               => __( 'Email verified. You can now sign in.', 'sports-player-platform' ),
	'verification_sent'      => __( 'If an unverified account matches, a verification email will be sent.', 'sports-player-platform' ),
	'reset_complete'         => __( 'Password reset. Sign in with your new password.', 'sports-player-platform' ),
	'unverified'             => __( 'Verify your email address before signing in.', 'sports-player-platform' ),
	'throttled'              => __( 'Too many failed login attempts. Try again in 15 minutes.', 'sports-player-platform' ),
	'invalid_login'          => __( 'The username, email address, or password was incorrect.', 'sports-player-platform' ),
	'invalid_verification'   => __( 'The verification link is invalid, expired, or already used. You can request a new link below.', 'sports-player-platform' ),
);
$spp_login_error_codes = array( 'registered_mail_failed', 'unverified', 'throttled', 'invalid_login', 'invalid_verification' );
$spp_login_error       = in_array( $view['status'], $spp_login_error_codes, true );
?>
<div class="spp-auth-container spp-login-form">
	<?php if ( isset( $spp_login_messages[ $view['status'] ] ) ) : ?>
		<div class="spp-notice <?php echo $spp_login_error ? 'spp-notice-error' : 'spp-notice-success'; ?>" role="<?php echo $spp_login_error ? 'alert' : 'status'; ?>">
			<p><?php echo esc_html( $spp_login_messages[ $view['status'] ] ); ?></p>
		</div>
	<?php endif; ?>

	<h3><?php esc_html_e( 'Log In', 'sports-player-platform' ); ?></h3>
	<form method="post" action="<?php echo esc_url( spp_page_url( 'login' ) ); ?>" class="spp-form">
		<?php wp_nonce_field( 'spp_auth_login', 'spp_nonce', false ); ?>
		<input type="hidden" name="spp_auth_action" value="login">
		<?php if ( '' !== $view['destination'] ) : ?>
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $view['destination'] ); ?>">
		<?php endif; ?>

		<p class="spp-form-field">
			<label for="spp_username"><?php esc_html_e( 'Username or Email', 'sports-player-platform' ); ?></label>
			<input type="text" name="username" id="spp_username" required autocomplete="username">
		</p>
		<p class="spp-form-field">
			<label for="spp_password"><?php esc_html_e( 'Password', 'sports-player-platform' ); ?></label>
			<input type="password" name="password" id="spp_password" required autocomplete="current-password">
		</p>
		<p class="spp-form-field">
			<label><input type="checkbox" name="remember" value="1"> <?php esc_html_e( 'Remember me', 'sports-player-platform' ); ?></label>
		</p>
		<p class="spp-form-submit">
			<button type="submit" class="button button-primary spp-btn-primary"><?php esc_html_e( 'Log In', 'sports-player-platform' ); ?></button>
		</p>
	</form>

	<div class="spp-auth-links">
		<a href="<?php echo esc_url( spp_page_url( 'reset-password' ) ); ?>"><?php esc_html_e( 'Forgot your password?', 'sports-player-platform' ); ?></a>
		<a href="<?php echo esc_url( spp_page_url( 'register' ) ); ?>"><?php esc_html_e( 'Create an account', 'sports-player-platform' ); ?></a>
	</div>

	<?php if ( SPP_Auth_Verification::is_required() ) : ?>
		<details id="spp-resend-verification"
		<?php
		if ( in_array( $view['status'], array( 'unverified', 'invalid_verification', 'registered_mail_failed' ), true ) ) :
			?>
			open<?php endif; ?>>
			<summary><?php esc_html_e( 'Resend verification email', 'sports-player-platform' ); ?></summary>
			<form method="post" action="<?php echo esc_url( spp_page_url( 'login' ) ); ?>" class="spp-form">
				<?php wp_nonce_field( 'spp_auth_resend', 'spp_nonce', false ); ?>
				<input type="hidden" name="spp_auth_action" value="resend">
				<p class="spp-form-field">
					<label for="spp_resend_identity"><?php esc_html_e( 'Username or Email', 'sports-player-platform' ); ?></label>
					<input type="text" name="identity" id="spp_resend_identity" required autocomplete="username">
				</p>
				<p class="spp-form-submit">
					<button type="submit" class="button"><?php esc_html_e( 'Send verification link', 'sports-player-platform' ); ?></button>
				</p>
			</form>
		</details>
	<?php endif; ?>
</div>
