<?php
/**
 * SPP role registration and capability policy.
 *
 * @package Sports_Player_Platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keep role capabilities and account ownership checks in one place.
 */
final class SPP_Auth_Roles {

	public const PLAYER  = 'spp_player';
	public const PARENT  = 'spp_parent';
	public const COACH   = 'spp_coach';
	public const VERSION = '1.2.0';

	/**
	 * Return the roles available through public registration.
	 *
	 * @return array<string, string> Role keys and labels.
	 */
	public static function registration_roles(): array {
		return array(
			self::PLAYER => __( 'Player / Athlete', 'sports-player-platform' ),
			self::PARENT => __( 'Parent / Guardian', 'sports-player-platform' ),
			self::COACH  => __( 'Coach / Recruiter', 'sports-player-platform' ),
		);
	}

	/**
	 * Upgrade the SPP grants while preserving site-specific grants.
	 */
	public static function register(): void {
		if ( version_compare( (string) get_option( 'spp_roles_version', '0.0.0' ), self::VERSION, '>=' ) ) {
			return;
		}

		SPP_DB::with_lock(
			'roles',
			static function (): bool {
				$common  = array( 'read', 'list_players', 'browse_players' );
				$grants  = array(
					self::PLAYER => array( 'edit_own_profile', 'send_messages' ),
					self::PARENT => array( 'view_saved_players', 'spp_paid_player_contact' ),
					self::COACH  => array( 'view_saved_players', 'track_recruiting', 'create_recruiting_list', 'send_messages', 'spp_paid_player_contact' ),
				);
				$managed = array_unique( array_merge( $common, ...array_values( $grants ) ) );
				foreach ( self::registration_roles() as $key => $label ) {
					$allowed = array_merge( $common, $grants[ $key ] );
					$role    = get_role( $key );
					if ( ! $role ) {
						$role = add_role( $key, $label, array_fill_keys( $allowed, true ) );
						if ( ! $role ) {
							return false;
						}
					}

					foreach ( $managed as $capability ) {
						if ( in_array( $capability, $allowed, true ) ) {
							if ( ! $role->has_cap( $capability ) ) {
								$role->add_cap( $capability );
							}
						} elseif ( array_key_exists( $capability, $role->capabilities ) ) {
							$role->remove_cap( $capability );
						}
					}
				}

				update_option( 'spp_roles_version', self::VERSION );
				return true;
			}
		);
	}

	/**
	 * Resolve the blueprint's object and entitlement capabilities.
	 *
	 * @param string[] $capabilities Primitive capabilities.
	 * @param string   $requested    Requested capability.
	 * @param int      $user_id      Actor ID.
	 * @param array    $args         Optional capability arguments.
	 * @return string[] Required primitive capabilities.
	 */
	public static function map_capability( array $capabilities, string $requested, int $user_id, array $args ): array {
		if ( 'edit_own_profile' === $requested && array_key_exists( 0, $args ) ) {
			$raw_id     = $args[0];
			$profile_id = is_int( $raw_id ) || is_string( $raw_id )
				? filter_var( $raw_id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) )
				: false;
			$profile    = $profile_id ? get_post( $profile_id ) : null;
			if ( ! $profile || 'player' !== $profile->post_type || SPP_Player_Profile::owner_id( $profile ) !== $user_id ) {
				return array( 'do_not_allow' );
			}
		}

		if ( 'view_contact_info' === $requested ) {
			$user = get_userdata( $user_id );
			if ( ! $user || ! array_intersect( array( self::PARENT, self::COACH ), $user->roles ) || ! spp_user_has_membership( $user_id, 'paid' ) ) {
				return array( 'do_not_allow' );
			}
			return array( 'spp_paid_player_contact' );
		}

		return $capabilities;
	}
}
