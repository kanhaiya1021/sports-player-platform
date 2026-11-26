<?php
/**
 * Image uploads and video previews for the player dashboard.
 *
 * @package Sports_Player_Platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SPP_Dashboard_Media {

	public const MAX_GALLERY = 12;
	public const MAX_UPLOADS = 4;
	private const IMAGE_TYPES = array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'gif'          => 'image/gif',
		'webp'         => 'image/webp',
	);

	/**
	 * Return the per-image upload limit in bytes.
	 *
	 * @return int
	 */
	public static function upload_limit(): int {
		return min( 5 * MB_IN_BYTES, wp_max_upload_size() );
	}

	/**
	 * Validate and save the images in this submission as WordPress attachments.
	 *
	 * @param array $files PHP upload data.
	 * @param int   $remaining Available gallery slots.
	 * @return array|WP_Error The photo and gallery attachment IDs, or an upload error.
	 */
	public static function upload( array $files, int $remaining ): array|WP_Error {
		$groups = array(
			'spp_photo'   => array(),
			'spp_gallery' => array(),
		);
		foreach ( array_keys( $groups ) as $field ) {
			$group = $files[ $field ] ?? array( 'error' => UPLOAD_ERR_NO_FILE );
			$error = $group['error'] ?? null;
			if ( UPLOAD_ERR_NO_FILE === $error ) {
				continue;
			}
			foreach ( (array) $error as $index => $code ) {
				if ( UPLOAD_ERR_NO_FILE === $code ) {
					continue;
				}

				$file = array();
				foreach ( array( 'name', 'tmp_name', 'size', 'type' ) as $key ) {
					$file[ $key ] = is_array( $error ) ? ( $group[ $key ][ $index ] ?? null ) : ( $group[ $key ] ?? null );
				}
				$file['error'] = $code;
				$groups[ $field ][] = $file;
			}
		}

		if ( count( $groups['spp_photo'] ) > 1 || count( $groups['spp_gallery'] ) > min( self::MAX_UPLOADS, $remaining ) ) {
			return new WP_Error( 'spp_upload_count', __( 'Choose fewer photos or remove some gallery photos first.', 'sports-player-platform' ), 400 );
		}

		$selected = array_merge( $groups['spp_photo'], $groups['spp_gallery'] );
		foreach ( $selected as $file ) {
			if ( ! self::valid_file( $file ) ) {
				return new WP_Error( 'spp_upload_invalid', __( 'Upload a valid JPEG, PNG, GIF or WebP image within the size limit shown on the form.', 'sports-player-platform' ), 400 );
			}
		}

		if ( ! $selected ) {
			return array();
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$uploads = array();
		foreach ( $groups as $field => $images ) {
			foreach ( $images as $file ) {
				// WordPress's native upload API accepts the key of a single $_FILES entry.
				$file_key           = 'spp_dashboard_image';
				$file['name']       = 'spp-' . wp_generate_uuid4() . '.' . strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
				$_FILES[ $file_key ] = $file;
				try {
					$id = media_handle_upload(
						$file_key,
						0,
						array( 'post_title' => __( 'Player photo', 'sports-player-platform' ) ),
						array( 'test_form' => false, 'mimes' => self::IMAGE_TYPES )
					);
				} finally {
					unset( $_FILES[ $file_key ] );
				}

				if ( is_wp_error( $id ) || ! wp_get_attachment_url( $id ) ) {
					if ( ! is_wp_error( $id ) ) {
						wp_delete_attachment( $id, true );
					}
					self::discard( $uploads );
					return new WP_Error( 'spp_upload_failed', __( 'WordPress could not save the image. Try a different image or contact the site administrator.', 'sports-player-platform' ), 400 );
				}

				$uploads[ $field ][] = $id;
			}
		}

		return $uploads;
	}

	/**
	 * Delete attachments created by an unsuccessful submission.
	 *
	 * @param array $uploads New attachment IDs, grouped by field.
	 */
	public static function discard( array $uploads ): void {
		foreach ( $uploads as $ids ) {
			foreach ( $ids as $id ) {
				wp_delete_attachment( $id, true );
			}
		}
	}

	/**
	 * Validate the PHP temporary file and its actual image type and dimensions.
	 *
	 * @param array $file PHP upload data.
	 * @return bool Whether the upload is a supported image.
	 */
	private static function valid_file( array $file ): bool {
		if ( UPLOAD_ERR_OK !== $file['error'] || ! is_string( $file['name'] ) || ! is_string( $file['tmp_name'] )
			|| ! is_int( $file['size'] ) || $file['size'] < 1 || $file['size'] > self::upload_limit() || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return false;
		}

		$info = wp_getimagesize( $file['tmp_name'] );
		$type = wp_check_filetype( $file['name'], self::IMAGE_TYPES );
		return $info && $type['type'] === $info['mime'] && $info[0] > 0 && $info[1] > 0 && $info[0] * $info[1] <= 25000000;
	}

	/**
	 * Build an iframe URL for a supported YouTube or Vimeo link.
	 *
	 * @param string $url Validated HTTP(S) video link.
	 * @return string An iframe URL, or an empty string for unsupported links.
	 */
	public static function embed_url( string $url ): string {
		$parts = wp_parse_url( $url );
		$host  = strtolower( $parts['host'] ?? '' );
		$path  = trim( $parts['path'] ?? '', '/' );
		if ( in_array( $host, array( 'youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com' ), true ) ) {
			if ( 'watch' === $path ) {
				wp_parse_str( $parts['query'] ?? '', $query );
				$id = $query['v'] ?? '';
			} elseif ( preg_match( '~^(?:embed|shorts|live)/([^/]+)$~D', $path, $matches ) ) {
				$id = $matches[1];
			}
		} elseif ( 'youtu.be' === $host ) {
			$id = $path;
		} elseif ( in_array( $host, array( 'vimeo.com', 'www.vimeo.com', 'player.vimeo.com' ), true ) ) {
			if ( preg_match( '~^(?:video/)?([0-9]+)(?:/([a-z0-9]+))?$~iD', $path, $matches ) ) {
				wp_parse_str( $parts['query'] ?? '', $query );
				$hash  = $matches[2] ?? ( $query['h'] ?? '' );
				$embed = 'https://player.vimeo.com/video/' . $matches[1];
				return is_string( $hash ) && preg_match( '/^[a-z0-9]+$/iD', $hash ) ? add_query_arg( 'h', $hash, $embed ) : $embed;
			}
		}

		return isset( $id ) && is_string( $id ) && preg_match( '/^[a-z0-9_-]{11}$/iD', $id ) ? 'https://www.youtube-nocookie.com/embed/' . $id : '';
	}
}
