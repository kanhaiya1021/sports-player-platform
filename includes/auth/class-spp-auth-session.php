<?php
/**
 * Browser session state for public authentication forms.
 *
 * @package Sports_Player_Platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bind guest form nonces to a browser and keep reset keys out of rendered pages.
 */
final class SPP_Auth_Session {

	/**
	 * Prevent caching and set a short-lived cookie for the browser's form nonces.
	 */
	public static function prepare(): void {
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'Referrer-Policy: no-referrer' );
		}
		if ( ! is_user_logged_in() && '' === self::guest_token() ) {
			self::write_cookie( 'guest', wp_generate_password( 32, false ), time() + DAY_IN_SECONDS );
		}
	}

	/**
	 * Scope SPP guest nonces without changing core WordPress nonces.
	 *
	 * @param int        $user_id Guest user ID.
	 * @param string|int $action  Nonce action.
	 * @return int Guest nonce identity.
	 */
	public static function guest_nonce_user( int $user_id, $action ): int {
		if ( ! is_string( $action ) || ! str_starts_with( $action, 'spp_auth_' ) ) {
			return $user_id;
		}

		$token = self::guest_token();
		return '' === $token ? $user_id : (int) hexdec( substr( hash( 'sha256', $token ), 0, PHP_INT_SIZE * 2 - 2 ) );
	}

	/**
	 * Check for an initialized guest session.
	 *
	 * @return bool Whether a browser cookie is present.
	 */
	public static function has_guest(): bool {
		return '' !== self::guest_token();
	}

	/**
	 * Store a reset credential while the user chooses a password.
	 *
	 * @param string $login WordPress login.
	 * @param string $key   WordPress-generated key.
	 * @return bool Whether the cookie was issued.
	 */
	public static function start_reset( string $login, #[\SensitiveParameter] string $key ): bool {
		return self::write_cookie( 'reset', $login . ':' . $key, time() + 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * Read the password reset credential from its HTTP-only cookie.
	 *
	 * @return array{string, string} WordPress login and key.
	 */
	public static function reset_credentials(): array {
		$value = self::read_cookie( 'reset' );
		$parts = explode( ':', $value, 2 );
		if ( 2 !== count( $parts ) || ! preg_match( '/^[A-Za-z0-9]+$/D', $parts[1] ) ) {
			return array( '', '' );
		}
		return array( sanitize_user( $parts[0] ), $parts[1] );
	}

	/**
	 * Remove the short-lived password reset cookie from the browser.
	 */
	public static function clear_reset(): void {
		self::write_cookie( 'reset', '', time() - HOUR_IN_SECONDS );
	}

	/**
	 * Read a well-formed guest token.
	 *
	 * @return string Guest token or an empty string.
	 */
	private static function guest_token(): string {
		$value = self::read_cookie( 'guest' );
		return preg_match( '/^[A-Za-z0-9]{32}$/D', $value ) ? $value : '';
	}

	/**
	 * Return an untrusted cookie value for format validation.
	 *
	 * @param string $kind Cookie purpose.
	 * @return string Raw cookie value.
	 */
	private static function read_cookie( string $kind ): string {
		$name = self::cookie_name( $kind );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Callers validate the format before using the value.
		return isset( $_COOKIE[ $name ] ) && is_string( $_COOKIE[ $name ] ) ? wp_unslash( $_COOKIE[ $name ] ) : '';
	}

	/**
	 * Send a host-only authentication cookie.
	 *
	 * @param string $kind    Cookie purpose.
	 * @param string $value   Validated value to store.
	 * @param int    $expires Expiration timestamp.
	 * @return bool Whether it was written.
	 */
	private static function write_cookie( string $kind, #[\SensitiveParameter] string $value, int $expires ): bool {
		if ( headers_sent() ) {
			return false;
		}

		$name    = self::cookie_name( $kind );
		$path    = 'reset' === $kind ? wp_parse_url( spp_page_url( 'reset-password' ), PHP_URL_PATH ) : COOKIEPATH;
		$written = setcookie(
			$name,
			$value,
			array(
				'expires'  => $expires,
				'path'     => $path ? $path : '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		if ( $written ) {
			$_COOKIE[ $name ] = wp_slash( $value );
		}
		return $written;
	}

	/**
	 * Scope cookies to this WordPress site.
	 *
	 * @param string $kind Cookie purpose.
	 * @return string Cookie name.
	 */
	private static function cookie_name( string $kind ): string {
		return 'spp_auth_' . $kind . '_' . COOKIEHASH;
	}
}
