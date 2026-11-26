<?php
/**
 * Database definitions for Sports Player Platform.
 *
 * @package Sports_Player_Platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The shared schema catalog used by installation and uninstall.
 */
final class SPP_DB_Schema {

	/**
	 * Return SQL fragments keyed by their unprefixed table suffix.
	 *
	 * @return array<string, string>
	 */
	public static function definitions(): array {
		return array(
			'players'            => <<<'SQL'
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
post_id bigint(20) unsigned DEFAULT NULL,
player_name varchar(255) NOT NULL,
sport varchar(100) NOT NULL,
position varchar(100),
team_id bigint(20) unsigned,
club varchar(255),
age tinyint unsigned,
height decimal(5,2),
weight decimal(5,2),
image_url varchar(500),
created_at timestamp DEFAULT CURRENT_TIMESTAMP,
updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
PRIMARY KEY  (id),
UNIQUE KEY post_id_idx (post_id),
KEY sport_idx (sport),
KEY team_id_idx (team_id)
SQL
			,
			'teams'              => <<<'SQL'
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
post_id bigint(20) unsigned DEFAULT NULL,
team_name varchar(255) NOT NULL,
sport varchar(100) NOT NULL,
logo_url varchar(500),
location varchar(255),
founded year,
created_at timestamp DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY  (id),
UNIQUE KEY post_id_idx (post_id),
KEY sport_idx (sport)
SQL
			,
			'rankings'           => <<<'SQL'
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
player_id bigint(20) unsigned NOT NULL,
ranking_category_id bigint(20) unsigned NOT NULL DEFAULT 1,
ranking_position tinyint unsigned NOT NULL,
effective_date date NOT NULL,
notes text,
created_at timestamp DEFAULT CURRENT_TIMESTAMP,
updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
PRIMARY KEY  (id),
KEY player_id_idx (player_id),
KEY ranking_category_id_idx (ranking_category_id),
KEY effective_date_idx (effective_date)
SQL
			,
			'rankings_history'   => <<<'SQL'
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
ranking_id bigint(20) unsigned NOT NULL,
player_id bigint(20) unsigned NOT NULL,
ranking_category varchar(100) NOT NULL,
previous_position tinyint unsigned,
new_position tinyint unsigned NOT NULL,
effective_date date NOT NULL,
updated_by bigint(20) unsigned NOT NULL,
updated_at timestamp DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY  (id),
KEY ranking_id_idx (ranking_id),
KEY player_id_idx (player_id),
KEY updated_by_idx (updated_by)
SQL
			,
			'ranking_categories' => <<<'SQL'
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
name varchar(255) NOT NULL,
description text,
is_active tinyint(1) NOT NULL DEFAULT 1,
created_at timestamp DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY  (id),
UNIQUE KEY name_idx (name)
SQL
			,
			'memberships'        => <<<'SQL'
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
user_id bigint(20) unsigned NOT NULL,
type varchar(50) NOT NULL DEFAULT 'free',
status varchar(50) NOT NULL DEFAULT 'active',
started_at datetime DEFAULT CURRENT_TIMESTAMP,
expires_at datetime NULL,
plan_details longtext,
payment_method varchar(50),
transaction_id varchar(255),
billing_event_at bigint(20) unsigned NOT NULL DEFAULT 0,
UNIQUE KEY gateway_subscription (payment_method,transaction_id(140)),
created_at timestamp DEFAULT CURRENT_TIMESTAMP,
updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
PRIMARY KEY  (id),
KEY user_id_idx (user_id),
KEY status_idx (status)
SQL
			,
			'conversations'      => <<<'SQL'
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
subject varchar(255) NOT NULL,
sender_id bigint(20) unsigned NOT NULL,
participant_ids text NOT NULL,
created_at timestamp DEFAULT CURRENT_TIMESTAMP,
updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
is_archived tinyint(1) NOT NULL DEFAULT 0,
PRIMARY KEY  (id),
KEY sender_id_idx (sender_id),
KEY updated_at_idx (updated_at)
SQL
			,
			'message_threads'    => <<<'SQL'
thread_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
user1_id BIGINT(20) UNSIGNED NOT NULL,
user2_id BIGINT(20) UNSIGNED NOT NULL,
legacy_conversation_id bigint(20) unsigned DEFAULT NULL,
last_message_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY  (thread_id),
UNIQUE KEY legacy_conversation_idx (legacy_conversation_id),
KEY user1_last_message_idx (user1_id,last_message_at),
KEY user2_last_message_idx (user2_id,last_message_at)
SQL
			,
			'messages'           => <<<'SQL'
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
conversation_id bigint(20) unsigned NOT NULL,
thread_id bigint(20) unsigned DEFAULT NULL,
sender_id bigint(20) unsigned NOT NULL,
recipient_id bigint(20) unsigned NOT NULL,
receiver_id bigint(20) unsigned DEFAULT NULL,
message longtext NOT NULL,
sent_at timestamp DEFAULT CURRENT_TIMESTAMP,
created_at timestamp NULL DEFAULT NULL,
is_read tinyint(1) NOT NULL DEFAULT 0,
is_deleted tinyint(1) NOT NULL DEFAULT 0,
deleted_by_sender tinyint(1) NOT NULL DEFAULT 0,
deleted_by_receiver tinyint(1) NOT NULL DEFAULT 0,
PRIMARY KEY  (id),
KEY conversation_id_idx (conversation_id),
KEY thread_created_idx (thread_id,created_at),
KEY receiver_read_idx (receiver_id,is_read),
KEY sender_id_idx (sender_id),
KEY recipient_id_idx (recipient_id),
KEY is_read_idx (is_read)
SQL
			,
			'recruiting_lists'   => <<<'SQL'
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
coach_id bigint(20) unsigned NOT NULL,
coach_user_id bigint(20) unsigned DEFAULT NULL,
name varchar(255) NOT NULL,
list_name varchar(255) DEFAULT NULL,
description longtext,
sport varchar(100) DEFAULT '',
is_private tinyint(1) NOT NULL DEFAULT 1,
created_at timestamp DEFAULT CURRENT_TIMESTAMP,
updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
is_active tinyint(1) NOT NULL DEFAULT 1,
PRIMARY KEY  (id),
KEY coach_id_idx (coach_id),
KEY coach_user_active_idx (coach_user_id,is_active),
KEY sport_idx (sport)
SQL
			,
			'recruiting_players' => <<<'SQL'
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
list_id bigint(20) unsigned NOT NULL,
player_id bigint(20) unsigned NOT NULL,
added_by bigint(20) unsigned NOT NULL,
notes text,
sort_order int(11) NOT NULL DEFAULT 0,
created_at timestamp DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY  (id),
UNIQUE KEY list_player_idx (list_id, player_id),
KEY player_id_idx (player_id)
SQL
			,
			'player_interest'    => <<<'SQL'
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
coach_id bigint(20) unsigned NOT NULL,
player_id bigint(20) unsigned NOT NULL,
interest_level enum('low','medium','high') NOT NULL DEFAULT 'medium',
notes longtext,
first_viewed timestamp DEFAULT CURRENT_TIMESTAMP,
last_viewed timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
status enum('interested','contacted','under_consideration','scouted','committed') NOT NULL DEFAULT 'interested',
created_at timestamp DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY  (id),
UNIQUE KEY coach_player_idx (coach_id, player_id),
KEY player_id_idx (player_id),
KEY status_idx (status)
SQL
			,
			'saved_players'      => <<<'SQL'
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
user_id bigint(20) unsigned NOT NULL,
player_id bigint(20) unsigned NOT NULL,
saved_at timestamp DEFAULT CURRENT_TIMESTAMP,
notes text,
PRIMARY KEY  (id),
UNIQUE KEY user_player_idx (user_id, player_id)
SQL
			,
			'notifications'      => <<<'SQL'
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
user_id bigint(20) unsigned NOT NULL,
type varchar(50) NOT NULL DEFAULT 'info',
title varchar(255) NOT NULL,
message text NOT NULL,
link varchar(500) DEFAULT '',
is_read tinyint(1) NOT NULL DEFAULT 0,
created_at timestamp DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY  (id),
KEY user_id_idx (user_id),
KEY is_read_idx (is_read)
SQL
			,
			'player_views'       => <<<'SQL'
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
player_id bigint(20) unsigned NOT NULL,
viewer_id bigint(20) unsigned DEFAULT NULL,
viewer_role varchar(50) DEFAULT 'guest',
ip_address varchar(45) DEFAULT '',
viewed_at timestamp DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY  (id),
KEY player_id_idx (player_id),
KEY viewer_id_idx (viewer_id),
KEY viewed_at_idx (viewed_at)
SQL
			,
			'payment_logs'       => <<<'SQL'
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
user_id bigint(20) unsigned NOT NULL,
membership_id bigint(20) unsigned DEFAULT NULL,
gateway varchar(50) NOT NULL,
transaction_id varchar(255) NOT NULL,
event_id varchar(191) DEFAULT NULL,
UNIQUE KEY gateway_event (gateway,event_id(140)),
amount decimal(10,2) NOT NULL DEFAULT 0.00,
currency varchar(10) NOT NULL DEFAULT 'USD',
status varchar(50) NOT NULL DEFAULT 'pending',
payload longtext,
created_at timestamp DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY  (id),
KEY user_id_idx (user_id),
KEY transaction_id_idx (transaction_id),
KEY status_idx (status)
SQL
			,
			'email_logs'         => <<<'SQL'
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
recipient varchar(255) NOT NULL,
subject varchar(255) NOT NULL,
template varchar(100) NOT NULL DEFAULT '',
status varchar(20) NOT NULL DEFAULT 'sent',
error_message text,
sent_at timestamp DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY  (id),
KEY recipient_idx (recipient),
KEY status_idx (status)
SQL
			,
		);
	}
}
