<?php
/**
 * Authentication and email request limits.
 *
 * @package Sports_Player_Platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Share password attempt limits across the SPP and native WordPress forms.
 */
final class SPP_Auth_Attempts {

	public const MAX_LOGIN_ATTEMPTS = 5;
	public const LOCKOUT_SECONDS    = 15 * MINUTE_IN_SECONDS;

	/**
	 * Check whether a known account can attempt another password.
	 *
	 * @param WP_User|WP_Error $user Authentication stage result.
	 * @return WP_User|WP_Error Authentication result.
	 */
	public static function before_password( $user ) {
		if ( $user instanceof WP_User && self::is_blocked( $user->ID ) ) {
			return self::blocked_error();
		}
		return $user;
	}

	/**
	 * Enforce the limit after WordPress resolves an account or unknown identity.
	 *
	 * @param WP_User|WP_Error|null $result   Authentication result.
	 * @param string                $identity Login name or email.
	 * @return WP_User|WP_Error|null Authentication result.
	 */
	public static function after_password( $result, string $identity ) {
		$user_id = $result instanceof WP_User ? $result->ID : self::account_id( $identity );
		if ( '' !== $identity && self::is_blocked( $user_id ) ) {
			return self::blocked_error();
		}
		return $result;
	}

	/**
	 * Count only credential failures towards the password limit.
	 *
	 * @param string   $identity Input login name or email.
	 * @param WP_Error $error    Core authentication error.
	 */
	public static function record_failure( string $identity, WP_Error $error ): void {
		$credential_errors = array( 'invalid_username', 'invalid_email', 'incorrect_password', 'authentication_failed' );
		if ( in_array( $error->get_error_code(), $credential_errors, true ) ) {
			self::reserve( self::key( self::account_id( $identity ) ), self::MAX_LOGIN_ATTEMPTS, self::LOCKOUT_SECONDS );
		}
	}

	/**
	 * Clear a password counter following successful sign-in.
	 *
	 * @param string  $login Successful login name.
	 * @param WP_User $user  Signed-in user.
	 */
	public static function clear_failures( string $login, WP_User $user ): void {
		$key = self::key( $user->ID );
		SPP_DB::with_lock(
			$key,
			static function () use ( $key ): bool {
				delete_transient( $key );
				return true;
			}
		);
	}

	/**
	 * Reserve one action within a fixed window.
	 *
	 * @param string $key    Internal transient key.
	 * @param int    $limit  Maximum actions.
	 * @param int    $window Window in seconds.
	 * @return bool Whether the action can proceed.
	 */
	public static function reserve( string $key, int $limit, int $window ): bool {
		return SPP_DB::with_lock(
			$key,
			static function () use ( $key, $limit, $window ): bool {
				$now = time();
				// Refresh a value the password filter may have read before obtaining the write lock.
				if ( ! wp_using_ext_object_cache() ) {
					wp_cache_delete( '_transient_' . $key, 'options' );
					wp_cache_delete( '_transient_timeout_' . $key, 'options' );
					wp_cache_delete( 'notoptions', 'options' );
				}
				$entry = self::read( $key );
				if ( $entry['count'] >= $limit ) {
					return false;
				}

				$expiry = $entry['expires'] > $now ? $entry['expires'] : $now + $window;
				return set_transient(
					$key,
					array(
						'count'   => $entry['count'] + 1,
						'expires' => $expiry,
					),
					$expiry - $now
				);
			}
		);
	}

	/**
	 * Return the rate limit error used by both login forms.
	 *
	 * @return WP_Error Authentication error.
	 */
	private static function blocked_error(): WP_Error {
		return new WP_Error( 'spp_login_throttled', __( 'Too many failed login attempts. Try again in 15 minutes.', 'sports-player-platform' ) );
	}

	/**
	 * Check the counter for an account and remote address.
	 *
	 * @param int $user_id Matched account ID, or zero.
	 * @return bool Whether the limit is reached.
	 */
	private static function is_blocked( int $user_id ): bool {
		return self::read( self::key( $user_id ) )['count'] >= self::MAX_LOGIN_ATTEMPTS;
	}

	/**
	 * Read a counter and discard expired values.
	 *
	 * @param string $key Internal transient key.
	 * @return array{count: int, expires: int}
	 */
	private static function read( string $key ): array {
		$entry = wp_using_ext_object_cache() ? wp_cache_get( $key, 'transient', true ) : get_transient( $key );
		if ( ! is_array( $entry ) || (int) ( $entry['expires'] ?? 0 ) <= time() ) {
			return array(
				'count'   => 0,
				'expires' => 0,
			);
		}
		return array(
			'count'   => (int) ( $entry['count'] ?? 0 ),
			'expires' => (int) $entry['expires'],
		);
	}

	/**
	 * Normalize username and email aliases to the same counter.
	 *
	 * @param string $identity Login or email.
	 * @return int Matched account ID, or zero.
	 */
	private static function account_id( string $identity ): int {
		$user = get_user_by( 'login', $identity );
		if ( ! $user && is_email( $identity ) ) {
			$user = get_user_by( 'email', $identity );
		}
		return $user ? $user->ID : 0;
	}

	/**
	 * Build a bounded key without persisting the address or login name.
	 *
	 * @param int $user_id Matched account ID, or zero for unknown accounts.
	 * @return string Transient key.
	 */
	private static function key( int $user_id ): string {
		$address = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$packed  = filter_var( $address, FILTER_VALIDATE_IP ) ? inet_pton( $address ) : false;
		return 'spp_auth_login_' . wp_hash( $user_id . ':' . ( $packed ? bin2hex( $packed ) : 'unknown' ) );
	}
}
