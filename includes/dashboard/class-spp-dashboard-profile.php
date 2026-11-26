<?php
/**
 * Self-service editing for owned player profiles.
 *
 * @package Sports_Player_Platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SPP_Dashboard_Profile {

	public const TABS = array(
		'personal' => array( 'location', 'date_of_birth' ),
		'athletic' => array( 'sport', 'playing_position', 'height', 'weight', 'graduation_year', 'school' ),
		'media'    => array( 'videos_highlights' ),
		'academic' => array( 'gpa', 'sat_act_scores', 'awards' ),
	);

	private const COMPLETION_FIELDS = array(
		'player_name', 'bio', 'date_of_birth', 'location', 'photo_url', 'sport',
		'playing_position', 'height', 'weight', 'graduation_year', 'school',
	);

	/**
	 * Read the player's editable profile and related preview information.
	 *
	 * @param int          $user_id Actor ID.
	 * @param WP_Post|null $post    Owned player post.
	 * @return array
	 */
	public static function data( int $user_id, ?WP_Post $post ): array {
		$user    = get_userdata( $user_id );
		$post_id = $post ? $post->ID : 0;
		$data    = array(
			'player_name' => $post ? $post->post_title : ( $user ? $user->display_name : '' ),
			'bio'         => $post ? $post->post_content : '',
			'status'      => $post ? $post->post_status : 'new',
		);

		$keys = array_merge( ...array_values( self::TABS ) );
		foreach ( array_merge( $keys, array( 'avatar_url', 'image_gallery' ) ) as $key ) {
			$value        = $post ? SPP_Player_Profile::get( $post_id, $key ) : '';
			$data[ $key ] = is_scalar( $value ) ? (string) $value : '';
		}

		$data['sport']        = $data['sport'] ?: (string) get_user_meta( $user_id, 'spp_sport', true );
		$data['photo_url']    = ( $post ? get_the_post_thumbnail_url( $post_id, 'medium' ) : false ) ?: $data['avatar_url'];
		$data['revision']     = hash( 'sha256', serialize( array( $post_id, $data ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Local data used as a revision fingerprint.
		$data['gallery']      = self::gallery( $data['image_gallery'] );
		$data['completion']   = self::completion( $data );

		return $data;
	}

	/**
	 * Calculate completion from populated profile fields.
	 *
	 * @param array $data Profile data.
	 * @return int Percentage.
	 */
	private static function completion( array $data ): int {
		$filled = 0;
		foreach ( self::COMPLETION_FIELDS as $key ) {
			if ( '' !== trim( wp_strip_all_tags( $data[ $key ] ) ) ) {
				++$filled;
			}
		}
		return (int) round( 100 * $filled / count( self::COMPLETION_FIELDS ) );
	}

	/**
	 * Get the translated moderation status.
	 *
	 * @param string $status WordPress profile status.
	 * @return string
	 */
	public static function status_label( string $status ): string {
		$labels = array(
			'new'         => __( 'New profile', 'sports-player-platform' ),
			'draft'       => __( 'Draft', 'sports-player-platform' ),
			'spp_pending' => __( 'Pending review', 'sports-player-platform' ),
			'publish'     => __( 'Published', 'sports-player-platform' ),
			'rejected'    => __( 'Changes requested', 'sports-player-platform' ),
		);
		$object = get_post_status_object( $status );
		return $labels[ $status ] ?? ( $object->label ?? __( 'Unpublished', 'sports-player-platform' ) );
	}

	/**
	 * Apply a validated profile submission, serialized for this player account.
	 *
	 * @param int   $user_id Current actor ID.
	 * @param array $input   Unslashed submitted fields.
	 * @param array $files   PHP upload data.
	 * @return array|WP_Error Dashboard feedback and current profile state.
	 */
	public static function submit( int $user_id, array $input, array $files ): array|WP_Error {
		$busy   = new WP_Error( 'spp_profile_busy', __( 'The profile is being updated. Try again shortly.', 'sports-player-platform' ), 409 );
		$result = $busy;
		SPP_DB::with_lock(
			'dashboard-profile-' . $user_id,
			static function () use ( $user_id, $input, $files, $busy, &$result ): bool {
				$post = SPP_Player_Profile::account_profile( $user_id );
				if ( $post && ! current_user_can( 'edit_own_profile', $post->ID ) ) {
					$result = new WP_Error( 'spp_profile_forbidden', __( 'You can only edit your own player profile.', 'sports-player-platform' ), 403 );
					return false;
				}

				if ( ! $post ) {
					$result = self::save( $user_id, null, $input, $files );
					return ! is_wp_error( $result );
				}

				$result = $busy;
				return SPP_DB::with_lock(
					'player-profile-' . $post->ID,
					static function () use ( $user_id, $post, $input, $files, &$result ): bool {
						$current = get_post( $post->ID );
						if ( ! $current || 'trash' === $current->post_status || ! current_user_can( 'edit_own_profile', $post->ID ) ) {
							$result = new WP_Error( 'spp_profile_forbidden', __( 'This player profile is no longer available to edit.', 'sports-player-platform' ), 403 );
							return false;
						}
						$result = self::save( $user_id, $current, $input, $files );
						return ! is_wp_error( $result );
					}
				);
			}
		);
		return $result;
	}

	/**
	 * Validate the full form before modifying storage or public visibility.
	 *
	 * @param int          $user_id Actor ID.
	 * @param WP_Post|null $post    Owned player post.
	 * @param array        $input   Unslashed submitted fields.
	 * @param array        $files   PHP upload data.
	 * @return array|WP_Error
	 */
	private static function save( int $user_id, ?WP_Post $post, array $input, array $files ): array|WP_Error {
		$previous = self::data( $user_id, $post );
		$revision = self::value( $input, 'spp_revision' );
		if ( null === $revision || ! hash_equals( $previous['revision'], $revision ) ) {
			return new WP_Error( 'spp_profile_changed', __( 'The profile changed while you were editing. Reload it before saving again.', 'sports-player-platform' ), 409 );
		}

		$name = sanitize_text_field( self::value( $input, 'player_name' ) ?? '' );
		$bio  = wp_kses_post( self::value( $input, 'bio' ) ?? '' );
		if ( '' === $name ) {
			return new WP_Error( 'spp_name_required', __( 'Enter the player’s name.', 'sports-player-platform' ), array( 'status' => 400, 'fields' => array( 'player_name' ) ) );
		}

		$fields = self::profile_fields( $input, $previous );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}

		$remove_gallery = array_filter( (array) ( $input['spp_remove_gallery'] ?? array() ), 'is_string' );
		$gallery        = array_diff_key( $previous['gallery'], array_fill_keys( $remove_gallery, true ) );
		$uploads        = SPP_Dashboard_Media::upload( $files, max( 0, SPP_Dashboard_Media::MAX_GALLERY - count( $gallery ) ) );
		if ( is_wp_error( $uploads ) ) {
			return $uploads;
		}

		$photo_id     = $uploads['spp_photo'][0] ?? 0;
		$remove_photo = '1' === self::value( $input, 'spp_remove_photo' ) && '' !== $previous['photo_url'];
		if ( $photo_id || $remove_photo ) {
			$fields['avatar_url'] = $photo_id ? wp_get_attachment_url( $photo_id ) : '';
		}

		if ( $gallery !== $previous['gallery'] || ! empty( $uploads['spp_gallery'] ) ) {
			$gallery                 = array_merge( array_values( $gallery ), array_map( 'wp_get_attachment_url', $uploads['spp_gallery'] ?? array() ) );
			$fields['image_gallery'] = implode( "\n", $gallery );
		}

		if ( $post && ! $fields && $name === $post->post_title && $bio === $post->post_content && in_array( $post->post_status, array( 'publish', 'spp_pending' ), true ) ) {
			return self::response( $user_id, $post, __( 'Your profile is already up to date.', 'sports-player-platform' ) );
		}

		$changes = array(
			'post_title'   => $name,
			'post_content' => $bio,
			'post_type'    => 'player',
			'post_author'  => $post ? $post->post_author : $user_id,
			'post_status'  => 'spp_pending',
		);
		if ( $post ) {
			$changes['ID'] = $post->ID;
		}

		$saved = self::persist( $changes, $fields, $uploads, $photo_id, $remove_photo, $post );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return self::response( $user_id, get_post( $saved ), __( 'Profile saved and submitted for review.', 'sports-player-platform' ) );
	}

	/**
	 * Validate editable metadata using the same definitions as the admin editor.
	 *
	 * @param array $input    Unslashed fields.
	 * @param array $previous Previously stored data.
	 * @return array|WP_Error Changed values or invalid field errors.
	 */
	private static function profile_fields( array $input, array $previous ): array|WP_Error {
		$definitions = SPP_Player_Profile::fields();
		$fields      = array();
		$invalid     = array();

		foreach ( array_merge( ...array_values( self::TABS ) ) as $key ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$raw = self::value( $input, $key );
			if ( $raw === trim( $previous[ $key ] ) ) {
				continue;
			}

			$value = null === $raw ? null : SPP_Player_Profile::normalize( $raw, $definitions[ $key ] );
			if ( 'videos_highlights' === $key && '' !== $value && null !== $value ) {
				$urls = SPP_Player_Profile::urls( $value );
				if ( count( $urls ) > 12 || in_array( '', array_map( array( 'SPP_Dashboard_Media', 'embed_url' ), $urls ), true ) ) {
					$value = null;
				}
			}

			if ( null === $value ) {
				$invalid[ $key ] = $definitions[ $key ]['label'];
			} else {
				$fields[ $key ] = $value;
			}
		}

		if ( $invalid ) {
			return new WP_Error(
				'spp_invalid_fields',
				sprintf(
					/* translators: %s: comma-separated names of invalid profile fields. */
					__( 'Check the values for %s.', 'sports-player-platform' ),
					implode( ', ', $invalid )
				),
				array( 'status' => 400, 'fields' => array_keys( $invalid ) )
			);
		}

		return $fields;
	}

	/**
	 * Persist the submission, restoring edited data and discarding staged uploads on error.
	 *
	 * @param array        $changes      Player post fields.
	 * @param array        $fields       Validated profile metadata.
	 * @param array        $uploads      New attachment IDs.
	 * @param int          $photo_id     New featured image ID.
	 * @param bool         $remove_photo Whether to clear the current photo.
	 * @param WP_Post|null $previous     Original post if editing.
	 * @return int|WP_Error
	 */
	private static function persist( array $changes, array $fields, array $uploads, int $photo_id, bool $remove_photo, ?WP_Post $previous ): int|WP_Error {
		$error = new WP_Error( 'spp_profile_save_failed', __( 'WordPress could not save the profile. Try again or contact the site administrator.', 'sports-player-platform' ), 500 );
		$id    = wp_insert_post( wp_slash( $changes ), true );
		if ( ! $id || is_wp_error( $id ) ) {
			SPP_Dashboard_Media::discard( $uploads );
			return $error;
		}

		$old_meta = array();
		foreach ( array_merge( array_keys( $fields ), array( '_thumbnail_id' ) ) as $key ) {
			$old_meta[ $key ] = get_post_meta( $id, $key, false );
		}

		$failed = SPP_Player_Profile::save( $id, $fields );
		if ( $photo_id || $remove_photo ) {
			$thumb_ok = $photo_id ? set_post_thumbnail( $id, $photo_id ) : delete_post_thumbnail( $id );
			if ( ! $thumb_ok && (int) get_post_thumbnail_id( $id ) !== $photo_id ) {
				$failed[] = '_thumbnail_id';
			}
		}

		foreach ( $uploads as $ids ) {
			foreach ( $ids as $attachment ) {
				if ( ! wp_update_post( array( 'ID' => $attachment, 'post_parent' => $id ), true ) || $id !== wp_get_post_parent_id( $attachment ) ) {
					$failed[] = 'attachment';
					break 2;
				}
			}
		}

		if ( ! $failed ) {
			return $id;
		}

		if ( $previous ) {
			foreach ( $old_meta as $key => $values ) {
				if ( get_post_meta( $id, $key, false ) === $values ) {
					continue;
				}
				delete_post_meta( $id, $key );
				foreach ( $values as $value ) {
					add_post_meta( $id, $key, wp_slash( $value ) );
				}
			}
			wp_update_post(
				wp_slash(
					array(
						'ID'           => $id,
						'post_title'   => $previous->post_title,
						'post_content' => $previous->post_content,
						'post_status'  => $previous->post_status,
					)
				)
			);
		} else {
			wp_delete_post( $id, true );
		}

		SPP_Dashboard_Media::discard( $uploads );
		return $error;
	}

	/**
	 * Build the response needed to refresh the editor without a page navigation.
	 *
	 * @param int     $user_id Actor ID.
	 * @param WP_Post $post    Saved player post.
	 * @param string  $message User feedback.
	 * @return array
	 */
	private static function response( int $user_id, WP_Post $post, string $message ): array {
		$profile = self::data( $user_id, $post );
		return array(
			'message'      => $message,
			'post_id'      => $post->ID,
			'status'       => $post->post_status,
			'status_label' => self::status_label( $post->post_status ),
			'completion'   => $profile['completion'],
			'revision'     => $profile['revision'],
			'photo_url'    => esc_url_raw( $profile['photo_url'], array( 'http', 'https' ) ),
			'media_html'   => SPP_Dashboard::template( 'profile-media', array( 'profile' => $profile ) ),
		);
	}

	/**
	 * Create stable removal keys from the currently saved gallery URLs.
	 *
	 * @param string $value Stored gallery URLs.
	 * @return array
	 */
	private static function gallery( string $value ): array {
		$gallery = array();
		foreach ( array_filter( SPP_Player_Profile::urls( $value ) ) as $url ) {
			$gallery[ hash( 'sha256', $url ) ] = $url;
		}
		return $gallery;
	}

	/**
	 * Read a string from the submitted field set.
	 *
	 * @param array  $input Submitted data.
	 * @param string $key   Field name.
	 * @return string|null
	 */
	private static function value( array $input, string $key ): ?string {
		return is_string( $input[ $key ] ?? null ) ? trim( $input[ $key ] ) : null;
	}
}
