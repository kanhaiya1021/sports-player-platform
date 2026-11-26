<?php
/**
 * Shared Sports Player Platform helpers.
 *
 * @package Sports_Player_Platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'spp_page_url' ) ) {
	/**
	 * Resolve an installed plugin page with either permalink mode.
	 *
	 * @param string $path Page path.
	 * @return string Page URL.
	 */
	function spp_page_url( string $path ): string {
		$page = get_page_by_path( $path, OBJECT, 'page' );
		$url  = $page && 'publish' === $page->post_status ? get_permalink( $page ) : false;
		return $url ? $url : home_url( user_trailingslashit( '/' . $path ) );
	}
}

if ( ! function_exists( 'spp_get_user_membership' ) ) {
	/**
	 * Return the user's current membership record.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array|null Membership data.
	 */
	function spp_get_user_membership( int $user_id ): ?array {
		return class_exists( 'SPP_Membership' ) ? SPP_Membership::get_instance()->get_user_membership( $user_id ) : null;
	}
}

if ( ! function_exists( 'spp_is_member' ) ) {
	/**
	 * Check active membership and an optional plan requirement.
	 *
	 * @param int         $user_id       WordPress user ID.
	 * @param string|null $required_type Required plan, if any.
	 * @return bool Whether the user meets the requirement.
	 */
	function spp_is_member( int $user_id, ?string $required_type = null ): bool {
		return class_exists( 'SPP_Membership' ) && SPP_Membership::get_instance()->is_member( $user_id, $required_type );
	}
}

if ( ! function_exists( 'spp_user_has_role' ) ) {
	/**
	 * Check a role on the current user.
	 *
	 * @param string $role WordPress role key.
	 * @return bool Whether the current user holds the role.
	 */
	function spp_user_has_role( string $role ): bool {
		return is_user_logged_in() && in_array( $role, wp_get_current_user()->roles, true );
	}
}

if ( ! function_exists( 'spp_can_view_player' ) ) {
	/**
	 * Check published or pending profile visibility for the current user.
	 *
	 * @param int $player_id Player post ID.
	 * @return bool Whether the user may view the profile.
	 */
	function spp_can_view_player( int $player_id ): bool {
		$post = get_post( $player_id );
		if ( ! $post || 'player' !== $post->post_type || post_password_required( $post ) ) {
			return false;
		}

		if ( 'publish' === $post->post_status ) {
			return true;
		}

		return 'spp_pending' === $post->post_status && is_user_logged_in()
			&& ( current_user_can( 'edit_post', $player_id ) || get_current_user_id() === SPP_Player_Profile::owner_id( $post ) );
	}
}

if ( ! function_exists( 'spp_get_player_meta' ) ) {
	/**
	 * Return player metadata respecting field visibility.
	 *
	 * @param int    $player_id Player post ID.
	 * @param string $key       Metadata field.
	 * @param mixed  $default   Result for an empty or inaccessible field.
	 * @return mixed Field value or default.
	 */
	function spp_get_player_meta( int $player_id, string $key, $default = null ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- Preserve the existing PHP named argument.
		if ( ! spp_can_view_player( $player_id ) ) {
			return $default;
		}

		$field = SPP_Player_Profile::fields()[ $key ] ?? array();
		if ( ( ! empty( $field['admin_only'] ) || is_protected_meta( $key, 'post' ) ) && ! current_user_can( 'manage_options' ) ) {
			return $default;
		}

		if ( in_array( $key, array( 'preferred_location', 'competition_level' ), true ) && 'open' !== SPP_Player_Profile::get( $player_id, 'recruiting_status' ) ) {
			return $default;
		}

		$is_owner = is_user_logged_in() && get_current_user_id() === SPP_Player_Profile::owner_id( get_post( $player_id ) );
		if ( empty( $field['public'] ) && ! $is_owner && ! current_user_can( 'edit_post', $player_id ) ) {
			return $default;
		}

		$value = SPP_Player_Profile::get( $player_id, $key );
		if ( '' === $value || false === $value ) {
			return $default;
		}
		if ( ! is_string( $value ) ) {
			return $value;
		}

		return in_array( $field['type'] ?? '', array( 'textarea', 'urls' ), true ) ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
	}
}

if ( ! function_exists( 'spp_get_current_user_role' ) ) {
	/**
	 * Return the primary role of the current user.
	 *
	 * @return string|null WordPress role key.
	 */
	function spp_get_current_user_role(): ?string {
		$roles = wp_get_current_user()->roles;
		return $roles ? (string) reset( $roles ) : null;
	}
}

if ( ! function_exists( 'spp_user_has_membership' ) ) {
	/**
	 * Check whether a user has an active membership of an optional type.
	 *
	 * @param int         $user_id WordPress user ID.
	 * @param string|null $type    Required plan key.
	 * @return bool Whether the user qualifies.
	 */
	function spp_user_has_membership( int $user_id, ?string $type = null ): bool {
		return spp_is_member( $user_id, $type );
	}
}

if ( ! function_exists( 'spp_get_avatar_url' ) ) {
	/**
	 * Return the user's custom avatar or the WordPress fallback.
	 *
	 * @param int $user_id WordPress user ID.
	 * @param int $size    Avatar size in pixels.
	 * @return string Avatar URL.
	 */
	function spp_get_avatar_url( int $user_id, int $size = 96 ): string {
		$custom_avatar = get_user_meta( $user_id, 'spp_avatar_url', true );
		if ( is_string( $custom_avatar ) && filter_var( $custom_avatar, FILTER_VALIDATE_URL ) ) {
			$url = esc_url_raw( $custom_avatar );
			if ( '' !== $url ) {
				return $url;
			}
		}

		return (string) get_avatar_url( $user_id, array( 'size' => $size ) );
	}
}

if ( ! function_exists( 'spp_format_date' ) ) {
	/**
	 * Format a date in the site's timezone and locale.
	 *
	 * @param string $date   A local date or timestamp with an explicit timezone.
	 * @param string $format Optional date format.
	 * @return string Localized date or the original value if parsing fails.
	 */
	function spp_format_date( string $date, string $format = '' ): string {
		if ( '' === $date || str_contains( $date, "\0" ) ) {
			return $date;
		}

		$parsed = date_create_immutable( $date, wp_timezone() );
		if ( ! $parsed ) {
			return $date;
		}

		$target_format = '' !== $format ? $format : (string) get_option( 'date_format', 'Y-m-d' );
		return wp_date( $target_format, $parsed->getTimestamp() );
	}
}

if ( ! function_exists( 'spp_log' ) ) {
	/**
	 * Write a debug log entry when debugging is enabled.
	 *
	 * @param string $message Short diagnostic.
	 * @param string $level   Severity.
	 * @param array  $context Non-sensitive diagnostic context.
	 */
	function spp_log( string $message, string $level = 'info', array $context = array() ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$prefix = '[SPP][' . strtoupper( $level ) . '] ';
		$suffix = $context ? ' ' . wp_json_encode( $context ) : '';
		error_log( $prefix . $message . $suffix ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Plugin debug logging is explicitly enabled.
	}
}
