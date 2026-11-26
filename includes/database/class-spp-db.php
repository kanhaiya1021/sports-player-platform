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

            // Memberships table
            $table_name = $wpdb->prefix . 'spp_memberships';
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                type VARCHAR(50) NOT NULL DEFAULT 'free',
                status VARCHAR(50) NOT NULL DEFAULT 'active',
                started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NULL,
                plan_details LONGTEXT,
                payment_method VARCHAR(50),
                transaction_id VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id_idx (user_id),
                KEY status_idx (status)
            ) $charset_collate;";

            dbDelta($sql);

            // Conversations table
            $table_name = $wpdb->prefix . 'spp_conversations';
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                subject VARCHAR(255) NOT NULL,
                sender_id BIGINT(20) UNSIGNED NOT NULL,
                participant_ids TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                is_archived TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY sender_id_idx (sender_id),
                KEY updated_at_idx (updated_at)
            ) $charset_collate;";

            dbDelta($sql);

            // Messages table
            $table_name = $wpdb->prefix . 'spp_messages';
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                conversation_id BIGINT(20) UNSIGNED NOT NULL,
                sender_id BIGINT(20) UNSIGNED NOT NULL,
                recipient_id BIGINT(20) UNSIGNED NOT NULL,
                message LONGTEXT NOT NULL,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                deleted_by_sender TINYINT(1) NOT NULL DEFAULT 0,
                deleted_by_receiver TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY conversation_id_idx (conversation_id),
                KEY sender_id_idx (sender_id),
                KEY recipient_id_idx (recipient_id),
                KEY is_read_idx (is_read)
            ) $charset_collate;";

            dbDelta($sql);

            // Recruiting lists table
            $table_name = $wpdb->prefix . 'spp_recruiting_lists';
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                coach_id BIGINT(20) UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                description LONGTEXT,
                sport VARCHAR(100) DEFAULT '',
                is_private TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                KEY coach_id_idx (coach_id),
                KEY sport_idx (sport)
            ) $charset_collate;";

            dbDelta($sql);

            // Recruiting list players table
            $table_name = $wpdb->prefix . 'spp_recruiting_players';
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                list_id BIGINT(20) UNSIGNED NOT NULL,
                player_id BIGINT(20) UNSIGNED NOT NULL,
                added_by BIGINT(20) UNSIGNED NOT NULL,
                notes TEXT,
                sort_order INT(11) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY list_player_idx (list_id, player_id),
                KEY player_id_idx (player_id)
            ) $charset_collate;";

            dbDelta($sql);

            // Player interest tracking table
            $table_name = $wpdb->prefix . 'spp_player_interest';
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                coach_id BIGINT(20) UNSIGNED NOT NULL,
                player_id BIGINT(20) UNSIGNED NOT NULL,
                interest_level ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
                notes LONGTEXT,
                first_viewed TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_viewed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                status ENUM('interested', 'contacted', 'under_consideration', 'scouted', 'committed') NOT NULL DEFAULT 'interested',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY coach_player_idx (coach_id, player_id),
                KEY player_id_idx (player_id),
                KEY status_idx (status)
            ) $charset_collate;";

            dbDelta($sql);

            // Saved players list table
            $table_name = $wpdb->prefix . 'spp_saved_players';
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                player_id BIGINT(20) UNSIGNED NOT NULL,
                saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                notes TEXT,
                PRIMARY KEY (id),
                UNIQUE KEY user_player_idx (user_id, player_id)
            ) $charset_collate;";

            dbDelta($sql);

            // In-app notifications table
            $table_name = $wpdb->prefix . 'spp_notifications';
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                type VARCHAR(50) NOT NULL DEFAULT 'info',
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                link VARCHAR(500) DEFAULT '',
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id_idx (user_id),
                KEY is_read_idx (is_read)
            ) $charset_collate;";

            dbDelta($sql);

            // Player profile view tracking table
            $table_name = $wpdb->prefix . 'spp_player_views';
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                player_id BIGINT(20) UNSIGNED NOT NULL,
                viewer_id BIGINT(20) UNSIGNED DEFAULT NULL,
                viewer_role VARCHAR(50) DEFAULT 'guest',
                ip_address VARCHAR(45) DEFAULT '',
                viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY player_id_idx (player_id),
                KEY viewer_id_idx (viewer_id),
                KEY viewed_at_idx (viewed_at)
            ) $charset_collate;";

            dbDelta($sql);

            // Payment logs table
            $table_name = $wpdb->prefix . 'spp_payment_logs';
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                membership_id BIGINT(20) UNSIGNED DEFAULT NULL,
                gateway VARCHAR(50) NOT NULL,
                transaction_id VARCHAR(255) NOT NULL,
                amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                currency VARCHAR(10) NOT NULL DEFAULT 'USD',
                status VARCHAR(50) NOT NULL DEFAULT 'pending',
                payload LONGTEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id_idx (user_id),
                KEY transaction_id_idx (transaction_id),
                KEY status_idx (status)
            ) $charset_collate;";

            dbDelta($sql);

            // Email logs table
            $table_name = $wpdb->prefix . 'spp_email_logs';
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                recipient VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                template VARCHAR(100) NOT NULL DEFAULT '',
                status VARCHAR(20) NOT NULL DEFAULT 'sent',
                error_message TEXT,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY recipient_idx (recipient),
                KEY status_idx (status)
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