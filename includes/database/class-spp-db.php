<?php
/**
 * Database class for Sports Player Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SPP_DB')) {

    final class SPP_DB {

        /**
         * Database instance
         *
         * @var SPP_DB|null
         */
        private static $instance = null;

        /**
         * Database version
         *
         * @var string
         */
        private $db_version;

        /**
         * Get database instance
         *
         * @return SPP_DB
         */
        public static function get_instance(): self {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Private constructor to prevent direct instantiation
         */
        private function __construct() {
            $this->db_version = SPP_DB_VERSION;
        }

        /**
         * Create database tables
         */
        public function create_tables(): void {
            global $wpdb;

            $charset_collate = $wpdb->get_charset_collate();

            // Players table
            $table_name = $wpdb->prefix . 'spp_players';
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                player_name VARCHAR(255) NOT NULL,
                sport VARCHAR(100) NOT NULL,
                position VARCHAR(100),
                team_id BIGINT(20) UNSIGNED,
                club VARCHAR(255),
                age TINYINT UNSIGNED,
                height DECIMAL(5,2),
                weight DECIMAL(5,2),
                image_url VARCHAR(500),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY sport_idx (sport),
                KEY team_id_idx (team_id)
            ) $charset_collate;";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);

            // Teams table
            $table_name = $wpdb->prefix . 'spp_teams';
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                team_name VARCHAR(255) NOT NULL,
                sport VARCHAR(100) NOT NULL,
                logo_url VARCHAR(500),
                location VARCHAR(255),
                founded YEAR,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY sport_idx (sport)
            ) $charset_collate;";

            dbDelta($sql);

            // Rankings table
            $table_name = $wpdb->prefix . 'spp_rankings';
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                player_id BIGINT(20) UNSIGNED NOT NULL,
                ranking_category_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
                ranking_position TINYINT UNSIGNED NOT NULL,
                effective_date DATE NOT NULL,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY player_id_idx (player_id),
                KEY ranking_category_id_idx (ranking_category_id),
                KEY effective_date_idx (effective_date)
            ) $charset_collate;";

            dbDelta($sql);

            // Rankings history table for audit trail
            $table_name = $wpdb->prefix . 'spp_rankings_history';
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                ranking_id BIGINT(20) UNSIGNED NOT NULL,
                player_id BIGINT(20) UNSIGNED NOT NULL,
                ranking_category VARCHAR(100) NOT NULL,
                previous_position TINYINT UNSIGNED,
                new_position TINYINT UNSIGNED NOT NULL,
                effective_date DATE NOT NULL,
                updated_by BIGINT(20) UNSIGNED NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY ranking_id_idx (ranking_id),
                KEY player_id_idx (player_id),
                KEY updated_by_idx (updated_by)
            ) $charset_collate;";

            dbDelta($sql);

            // Ranking categories table
            $table_name = $wpdb->prefix . 'spp_ranking_categories';
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY name_idx (name)
            ) $charset_collate;";

            dbDelta($sql);

            // Update database version option
            update_option('spp_db_version', $this->db_version);
        }

        /**
         * Get database version
         *
         * @return string
         */
        public function get_db_version(): string {
            return $this->db_version;
        }

        /**
         * Check if database needs update
         *
         * @return bool
         */
        public function needs_update(): bool {
            $installed_version = get_option('spp_db_version', '0.0.0');
            return version_compare($installed_version, $this->db_version, '<');
        }

        /**
         * Run database upgrades
         */
        public function upgrade(): void {
            if ($this->needs_update()) {
                $this->create_tables();
            }
        }

        /**
         * Get table name with prefix
         *
         * @param string $table Table name without prefix
         * @return string
         */
        public function get_table_name(string $table): string {
            global $wpdb;
            return $wpdb->prefix . 'spp_' . $table;
        }
    }
}