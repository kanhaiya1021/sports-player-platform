<?php
/**
 * SPP public registration form.
 *
 * @package Sports_Player_Platform
 * @var array $view Template data supplied by SPP_Auth.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$spp_registration_messages = array(
	'invalid_registration' => __( 'Check your name, username, email address, and account role.', 'sports-player-platform' ),
	'invalid_password'     => __( 'Choose a password with at least eight characters.', 'sports-player-platform' ),
	'registration_failed'  => __( 'We could not create the account. Try another username or sign in if you already have an account.', 'sports-player-platform' ),
);
?>
<div class="spp-auth-container spp-register-form">
	<?php if ( isset( $spp_registration_messages[ $view['status'] ] ) ) : ?>
		<div class="spp-notice spp-notice-error" role="alert">
			<p><?php echo esc_html( $spp_registration_messages[ $view['status'] ] ); ?></p>
		</div>
	<?php endif; ?>

	<h3><?php esc_html_e( 'Create Account', 'sports-player-platform' ); ?></h3>
	<form method="post" action="<?php echo esc_url( spp_page_url( 'register' ) ); ?>" class="spp-form">
		<?php wp_nonce_field( 'spp_auth_register', 'spp_nonce', false ); ?>
		<input type="hidden" name="spp_auth_action" value="register">

		<p class="spp-form-field">
			<label for="spp_full_name"><?php esc_html_e( 'Full Name', 'sports-player-platform' ); ?></label>
			<input type="text" name="full_name" id="spp_full_name" required maxlength="100" autocomplete="name">
		</p>
		<p class="spp-form-field">
			<label for="spp_reg_username"><?php esc_html_e( 'Username', 'sports-player-platform' ); ?></label>
			<input type="text" name="username" id="spp_reg_username" required maxlength="60" autocomplete="username">
		</p>
		<p class="spp-form-field">
			<label for="spp_reg_email"><?php esc_html_e( 'Email Address', 'sports-player-platform' ); ?></label>
			<input type="email" name="email" id="spp_reg_email" required autocomplete="email">
		</p>
		<p class="spp-form-field">
			<label for="spp_reg_password"><?php esc_html_e( 'Password (at least 8 characters)', 'sports-player-platform' ); ?></label>
			<input type="password" name="password" id="spp_reg_password" required minlength="8" maxlength="4096" autocomplete="new-password">
		</p>
		<p class="spp-form-field">
			<label for="spp_reg_role"><?php esc_html_e( 'Account Role', 'sports-player-platform' ); ?></label>
			<select name="role" id="spp_reg_role" required>
				<option value=""><?php esc_html_e( 'Select a role', 'sports-player-platform' ); ?></option>
				<?php foreach ( $view['roles'] as $spp_role => $spp_label ) : ?>
					<option value="<?php echo esc_attr( $spp_role ); ?>"><?php echo esc_html( $spp_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="spp-form-field">
			<label for="spp_reg_sport"><?php esc_html_e( 'Primary Sport (optional)', 'sports-player-platform' ); ?></label>
			<input type="text" name="sport" id="spp_reg_sport" maxlength="100">
		</p>
		<p class="spp-form-submit">
			<button type="submit" class="button button-primary spp-btn-primary"><?php esc_html_e( 'Register', 'sports-player-platform' ); ?></button>
		</p>
	</form>

	<div class="spp-auth-links">
		<a href="<?php echo esc_url( spp_page_url( 'login' ) ); ?>"><?php esc_html_e( 'Already have an account? Log in', 'sports-player-platform' ); ?></a>
	</div>
</div>
