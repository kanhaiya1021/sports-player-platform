<?php
/**
 * Database lifecycle for Sports Player Platform.
 *
 * @package Sports_Player_Platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-spp-db-schema.php';
require_once __DIR__ . '/class-spp-db-migration.php';

/**
 * Install, verify, and upgrade plugin tables.
 */
final class SPP_DB {

	/**
	 * Database singleton.
	 *
	 * @var SPP_DB|null
	 */
	private static ?self $instance = null;

	/**
	 * Restrict instances to the singleton.
	 */
	private function __construct() {}

	/**
	 * Return the database singleton.
	 *
	 * @return self Database instance.
	 */
	public static function get_instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Install or repair tables and run pending data migrations.
	 *
	 * @return bool Whether the schema and migration were verified.
	 */
	public function create_tables(): bool {
		return self::with_lock(
			'database',
			function (): bool {
				if ( ! $this->apply_schema() ) {
					return false;
				}

				if ( ! $this->needs_update() ) {
					return true;
				}

				if ( ! SPP_DB_Migration::run( $this ) ) {
					spp_log( 'Phase 1 database backfill could not complete.', 'error' );
					return false;
				}

				update_option( 'spp_db_version', $this->get_db_version() );
				if ( $this->needs_update() ) {
					return false;
				}

				delete_option( SPP_DB_Migration::CURSOR_OPTION );
				return true;
			}
		);
	}

	/**
	 * Apply the schema catalog and verify every table against it.
	 *
	 * @return bool Whether each declared schema is satisfied.
	 */
	private function apply_schema(): bool {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		foreach ( SPP_DB_Schema::definitions() as $suffix => $definition ) {
			$table = $this->get_table_name( $suffix );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The SQL body and collation are trusted schema fragments. The identifier is prepared.
			$sql = $wpdb->prepare( "CREATE TABLE %i (\n{$definition}\n) {$charset_collate};", $table );
			dbDelta( $sql );

			// dbDelta can run several ALTER statements, so verify the final schema as well as the last error.
			$query_failed = '' !== $wpdb->last_error;
			$pending      = dbDelta( $sql, false );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verify the actual installation result.
			$table_exists = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( $table_exists ) {
				// WordPress 6.2 can retain a stale create entry for a backtick-quoted table.
				unset( $pending[ '`' . $table . '`' ] );
			}
			if ( $query_failed || ! $table_exists || $pending ) {
				spp_log(
					'A database table could not be verified.',
					'error',
					array(
						'table'           => $suffix,
						'pending_changes' => array_keys( $pending ),
					)
				);
				return false;
			}
		}

		return true;
	}

	/**
	 * Serialize a site-scoped installation operation on the active DB connection.
	 *
	 * @param string   $name      Internal lock purpose.
	 * @param callable $operation Operation returning success or failure.
	 * @return bool Whether the operation acquired the lock and succeeded.
	 */
	public static function with_lock( string $name, callable $operation ): bool {
		global $wpdb;

		$lock = 'spp_install_' . md5( DB_NAME . ':' . $wpdb->prefix . ':' . $name );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- This lock belongs to the current database connection.
		if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock, 5 ) ) ) {
			return false;
		}

		try {
			return $operation();
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Release the lock on the same connection.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * Return the target database version.
	 *
	 * @return string Schema version.
	 */
	public function get_db_version(): string {
		return SPP_DB_VERSION;
	}

	/**
	 * Check the stored schema version.
	 *
	 * @return bool Whether a migration is pending.
	 */
	public function needs_update(): bool {
		return version_compare( (string) get_option( 'spp_db_version', '0.0.0' ), $this->get_db_version(), '<' );
	}

	/**
	 * Apply a pending migration.
	 *
	 * @return bool Whether the database is ready.
	 */
	public function upgrade(): bool {
		return ! $this->needs_update() || $this->create_tables();
	}

	/**
	 * Return a table name for the current site.
	 *
	 * @param string $table Table suffix without the spp prefix.
	 * @return string Fully prefixed table name.
	 */
	public function get_table_name( string $table ): string {
		global $wpdb;

		return $wpdb->prefix . 'spp_' . $table;
	}
}
