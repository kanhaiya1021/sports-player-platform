<?php
/**
 * Email verification tokens and account state.
 *
 * @package Sports_Player_Platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Issue email-bound, expiring tokens and consume each token once.
 */
final class SPP_Auth_Verification {

	public const STATUS_META = 'spp_email_verified';
	public const TOKEN_META  = 'spp_email_verify_token';

	/**
	 * Create a token and its stored digest.
	 *
	 * @param string $email Verification address.
	 * @return array{token: string, stored: string}
	 */
	public static function issue( string $email ): array {
		$token   = wp_generate_password( 32, false );
		$expires = time() + DAY_IN_SECONDS;
		return array(
			'token'  => $token,
			'stored' => $expires . ':' . self::digest( $token, $email, $expires ),
		);
	}

	/**
	 * Check whether email verification is enabled in plugin settings.
	 *
	 * @return bool Whether new accounts must verify their email before signing in.
	 */
	public static function is_required(): bool {
		$settings = get_option( 'spp_settings', array() );
		return is_array( $settings ) && ! empty( $settings['spp_require_email_verification'] );
	}

	/**
	 * Check if a plugin account still needs verification.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool Whether the account is pending verification.
	 */
	public static function is_pending( int $user_id ): bool {
		if ( ! self::is_required() ) {
			return false;
		}
		return '0' === get_user_meta( $user_id, self::STATUS_META, true );
	}

	/**
	 * Check the candidate against the pending account's digest.
	 *
	 * @param WP_User $user  WordPress user.
	 * @param string  $token Candidate token.
	 * @return bool Whether the token and address still match.
	 */
	public static function matches( WP_User $user, #[\SensitiveParameter] string $token ): bool {
		if ( ! preg_match( '/^[A-Za-z0-9]{32}$/D', $token ) || ! self::is_pending( $user->ID ) ) {
			return false;
		}

		$stored = get_user_meta( $user->ID, self::TOKEN_META, true );
		if ( ! is_string( $stored ) || ! preg_match( '/^(\d+):([a-f0-9]{64})$/D', $stored, $parts ) ) {
			return false;
		}

		$expires = (int) $parts[1];
		return $expires > time() && hash_equals( $parts[2], self::digest( $token, $user->user_email, $expires ) );
	}

	/**
	 * Consume a token under the same lock used for issuing replacements.
	 *
	 * @param WP_User $user  WordPress user.
	 * @param string  $token Candidate token.
	 * @return bool Whether this request verified the account.
	 */
	public static function consume( WP_User $user, #[\SensitiveParameter] string $token ): bool {
		return SPP_DB::with_lock(
			'email-verification-' . $user->ID,
			static function () use ( $user, $token ): bool {
				clean_user_cache( $user );
				wp_cache_delete( $user->ID, 'user_meta' );
				$current = get_userdata( $user->ID );
				if ( ! $current || ! self::matches( $current, $token ) || ! update_user_meta( $user->ID, self::STATUS_META, '1' ) ) {
					return false;
				}
				delete_user_meta( $user->ID, self::TOKEN_META );
				return true;
			}
		);
	}

	/**
	 * Replace an expired or previously issued token for a pending account.
	 *
	 * @param WP_User $user WordPress user.
	 * @return string New raw token, or an empty string if the account cannot be updated.
	 */
	public static function replace( WP_User $user ): string {
		$token = '';
		$saved = SPP_DB::with_lock(
			'email-verification-' . $user->ID,
			static function () use ( $user, &$token ): bool {
				clean_user_cache( $user );
				wp_cache_delete( $user->ID, 'user_meta' );
				$current = get_userdata( $user->ID );
				if ( ! $current || ! self::is_pending( $current->ID ) ) {
					return false;
				}

				$issued = self::issue( $current->user_email );
				$token  = $issued['token'];
				return (bool) update_user_meta( $current->ID, self::TOKEN_META, $issued['stored'] );
			}
		);
		return $saved ? $token : '';
	}

	/**
	 * Hash a token for one email address and expiration value.
	 *
	 * @param string $token   Raw verification token.
	 * @param string $email   Verification address.
	 * @param int    $expires Expiration timestamp.
	 * @return string Token digest.
	 */
	private static function digest( #[\SensitiveParameter] string $token, string $email, int $expires ): string {
		return hash_hmac( 'sha256', $expires . ':' . strtolower( $email ) . ':' . $token, wp_salt( 'auth' ) );
	}
}
