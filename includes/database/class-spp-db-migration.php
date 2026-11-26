<?php
/**
 * Compatibility backfills for Phase 1.
 *
 * @package Sports_Player_Platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Copy legacy fields while the existing readers and writers still use them.
 */
final class SPP_DB_Migration {

	public const CURSOR_OPTION = 'spp_db_phase1_conversation_cursor';

	/**
	 * Backfill renamed fields and eligible direct conversations.
	 *
	 * @param SPP_DB $database Plugin database.
	 * @return bool Whether all migration statements succeeded.
	 */
	public static function run( SPP_DB $database ): bool {
		global $wpdb;

		$messages = $database->get_table_name( 'messages' );
		$lists    = $database->get_table_name( 'recruiting_lists' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Installation backfills must run directly.
		$messages_updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET receiver_id = COALESCE(receiver_id, NULLIF(recipient_id, %d)), created_at = COALESCE(created_at, sent_at)
				WHERE created_at IS NULL OR (receiver_id IS NULL AND recipient_id > %d)',
				$messages,
				0,
				0
			)
		);
		$lists_updated    = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET coach_user_id = COALESCE(coach_user_id, NULLIF(coach_id, %d)), list_name = COALESCE(list_name, name)
				WHERE list_name IS NULL OR (coach_user_id IS NULL AND coach_id > %d)',
				$lists,
				0,
				0
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return false !== $messages_updated && false !== $lists_updated && self::backfill_threads( $database );
	}

	/**
	 * Process conversations in batches that can resume after an interrupted upgrade.
	 *
	 * @param SPP_DB $database Plugin database.
	 * @return bool Whether all batches completed.
	 */
	private static function backfill_threads( SPP_DB $database ): bool {
		global $wpdb;

		$conversations = $database->get_table_name( 'conversations' );
		$cursor        = (int) get_option( self::CURSOR_OPTION, 0 );
		$batch_size    = 250;

		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read the next migration batch.
			$batch = $wpdb->get_results( $wpdb->prepare( 'SELECT id, sender_id, participant_ids, updated_at FROM %i WHERE id > %d ORDER BY id ASC LIMIT %d', $conversations, $cursor, $batch_size ) );
			if ( '' !== $wpdb->last_error ) {
				return false;
			}

			foreach ( $batch as $conversation ) {
				if ( ! self::backfill_thread( $database, $conversation ) ) {
					return false;
				}
				$cursor = (int) $conversation->id;
			}

			if ( $batch && ! update_option( self::CURSOR_OPTION, $cursor, false ) && (int) get_option( self::CURSOR_OPTION, 0 ) !== $cursor ) {
				return false;
			}
			$batch_count = count( $batch );
		} while ( $batch_count === $batch_size );

		return true;
	}

	/**
	 * Map only conversations whose declared participants and messages form a direct thread.
	 *
	 * @param SPP_DB $database     Plugin database.
	 * @param object $conversation Legacy conversation row.
	 * @return bool Whether the row was processed without a database failure.
	 */
	private static function backfill_thread( SPP_DB $database, object $conversation ): bool {
		global $wpdb;

		$participants   = array_map( 'trim', explode( ',', $conversation->participant_ids ) );
		$participants[] = (string) $conversation->sender_id;
		$user_ids       = array();

		foreach ( $participants as $participant ) {
			if ( ! ctype_digit( $participant ) || (int) $participant <= 0 ) {
				return true;
			}
			$user_ids[ (int) $participant ] = (int) $participant;
		}

		if ( 2 !== count( $user_ids ) ) {
			return true;
		}

		sort( $user_ids, SORT_NUMERIC );
		list( $first_user, $second_user ) = $user_ids;
		$messages                         = $database->get_table_name( 'messages' );
		$threads                          = $database->get_table_name( 'message_threads' );
		$conversation_id                  = (int) $conversation->id;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration reads and writes require current database state.
		$result = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT MAX(sent_at) AS last_message_at,
					SUM(CASE WHEN sender_id IN (%d, %d) AND recipient_id IN (%d, %d) AND sender_id <> recipient_id THEN 0 ELSE 1 END) AS incompatible
				FROM %i WHERE conversation_id = %d',
				$first_user,
				$second_user,
				$first_user,
				$second_user,
				$messages,
				$conversation_id
			)
		);
		if ( ! $result || '' !== $wpdb->last_error ) {
			return false;
		}
		if ( (int) $result->incompatible > 0 ) {
			return true;
		}

		$inserted = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (user1_id, user2_id, legacy_conversation_id, last_message_at)
				VALUES (%d, %d, %d, %s)
				ON DUPLICATE KEY UPDATE legacy_conversation_id = legacy_conversation_id',
				$threads,
				$first_user,
				$second_user,
				$conversation_id,
				$result->last_message_at ?? $conversation->updated_at
			)
		);
		if ( false === $inserted ) {
			return false;
		}

		return false !== $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i AS messages INNER JOIN %i AS threads ON threads.legacy_conversation_id = messages.conversation_id
				SET messages.thread_id = threads.thread_id
				WHERE messages.conversation_id = %d AND messages.thread_id IS NULL',
				$messages,
				$threads,
				$conversation_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
