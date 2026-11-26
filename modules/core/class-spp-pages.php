<?php
/**
 * Provision the blueprint's WordPress pages.
 *
 * @package Sports_Player_Platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create each missing page under its expected parent.
 */
final class SPP_Pages {

	private const VERSION = '2';

	/**
	 * Install missing pages without editing any existing page.
	 *
	 * @return bool Whether all paths were provisioned.
	 */
	public static function install(): bool {
		if ( self::VERSION === get_option( 'spp_pages_version' ) ) {
			return true;
		}

		return SPP_DB::with_lock(
			'pages',
			static function (): bool {
				$pages = array(
					'login'                  => array( __( 'Login', 'sports-player-platform' ), 'spp_login_form' ),
					'register'               => array( __( 'Register', 'sports-player-platform' ), 'spp_register_form' ),
					'reset-password'         => array( __( 'Reset Password', 'sports-player-platform' ), 'spp_password_reset_form' ),
					'verify-email'           => array( __( 'Verify Email', 'sports-player-platform' ), '' ),
					'pricing'                => array( __( 'Pricing', 'sports-player-platform' ), 'spp_pricing_table' ),
					'dashboard'              => array( __( 'Dashboard', 'sports-player-platform' ), 'spp_dashboard' ),
					'dashboard/edit-profile' => array( __( 'Edit Profile', 'sports-player-platform' ), 'spp_edit_profile' ),
					'dashboard/membership'   => array( __( 'Membership', 'sports-player-platform' ), 'spp_my_membership' ),
					'dashboard/messages'     => array( __( 'Messages', 'sports-player-platform' ), 'spp_messages' ),
					'dashboard/shortlist'    => array( __( 'Shortlist', 'sports-player-platform' ), 'spp_shortlist' ),
					'dashboard/recruiting'   => array( __( 'Recruiting', 'sports-player-platform' ), 'spp_recruiting' ),
				);

				foreach ( $pages as $path => list( $title, $shortcode ) ) {
					if ( ! self::ensure_page( $path, $title, $shortcode ) ) {
						spp_log( 'A plugin page could not be provisioned.', 'warning', array( 'path' => $path ) );
						return false;
					}
				}

				update_option( 'spp_pages_version', self::VERSION );
				return self::VERSION === get_option( 'spp_pages_version' );
			}
		);
	}

	/**
	 * Create a page only when its exact WordPress path is available.
	 *
	 * @param string $path      Plugin page path.
	 * @param string $title     Localized page title.
	 * @param string $shortcode Content shortcode, when the page has one.
	 * @return bool Whether the expected page exists.
	 */
	private static function ensure_page( string $path, string $title, string $shortcode ): bool {
		if ( get_page_by_path( $path, OBJECT, 'page' ) ) {
			return true;
		}

		$slug      = basename( $path );
		$parent_id = 0;
		if ( str_contains( $path, '/' ) ) {
			$parent = get_page_by_path( dirname( $path ), OBJECT, 'page' );
			if ( ! $parent ) {
				return false;
			}
			$parent_id = $parent->ID;
		}

		if ( wp_unique_post_slug( $slug, 0, 'publish', 'page', $parent_id ) !== $slug ) {
			return false;
		}

		$page_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_name'    => $slug,
					'post_parent'  => $parent_id,
					'post_content' => '' === $shortcode ? '' : '[' . $shortcode . ']',
				)
			),
			true
		);

		return ! is_wp_error( $page_id ) && $page_id > 0 && get_page_by_path( $path, OBJECT, 'page' )?->ID === $page_id;
	}
}
