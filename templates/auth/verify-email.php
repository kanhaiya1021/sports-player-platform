<?php
/**
 * SPP email verification confirmation.
 *
 * @package Sports_Player_Platform
 * @var array $view Template data supplied by SPP_Auth.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="spp-auth-container spp-verify-form">
	<?php if ( $view['valid'] ) : ?>
		<form method="post" action="<?php echo esc_url( spp_page_url( 'verify-email' ) ); ?>" class="spp-form">
			<?php wp_nonce_field( 'spp_auth_verify', 'spp_nonce', false ); ?>
			<input type="hidden" name="spp_auth_action" value="verify">
			<input type="hidden" name="user" value="<?php echo esc_attr( $view['username'] ); ?>">
			<input type="hidden" name="token" value="<?php echo esc_attr( $view['token'] ); ?>">
			<p><?php esc_html_e( 'Confirm this email address to activate your account.', 'sports-player-platform' ); ?></p>
			<p><button type="submit" class="button button-primary spp-btn-primary"><?php esc_html_e( 'Confirm Email', 'sports-player-platform' ); ?></button></p>
		</form>
	<?php else : ?>
		<div class="spp-notice spp-notice-error" role="alert">
			<p><?php esc_html_e( 'This verification link is invalid, expired, or already used.', 'sports-player-platform' ); ?></p>
		</div>
		<a href="<?php echo esc_url( add_query_arg( 'spp_auth', 'invalid_verification', spp_page_url( 'login' ) ) . '#spp-resend-verification' ); ?>"><?php esc_html_e( 'Request a new verification link', 'sports-player-platform' ); ?></a>
	<?php endif; ?>
</div>
