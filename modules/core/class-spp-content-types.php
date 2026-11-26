<?php
/**
 * Player and team content types.
 *
 * @package Sports_Player_Platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the content types before WordPress generates rewrite rules.
 */
final class SPP_Content_Types {

	public const POST_TYPES = array(
		'player' => array(
			'label'        => 'Player',
			'labels'       => array(
				'name'          => 'Players',
				'singular_name' => 'Player',
				'menu_name'     => 'Players',
				'all_items'     => 'All Players',
				'add_new'       => 'Add New',
				'add_new_item'  => 'Add New Player',
				'edit_item'     => 'Edit Player',
				'new_item'      => 'New Player',
				'view_item'     => 'View Player',
				'search_items'  => 'Search Players',
			),
			'public'       => true,
			'show_ui'      => true,
			'show_in_menu' => false,
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'has_archive'  => true,
			'rewrite'      => array( 'slug' => 'players' ),
		),
		'team'   => array(
			'label'        => 'Team',
			'labels'       => array(
				'name'          => 'Teams',
				'singular_name' => 'Team',
				'menu_name'     => 'Teams',
				'all_items'     => 'All Teams',
				'add_new'       => 'Add New',
				'add_new_item'  => 'Add New Team',
				'edit_item'     => 'Edit Team',
				'new_item'      => 'New Team',
				'view_item'     => 'View Team',
				'search_items'  => 'Search Teams',
			),
			'public'       => true,
			'show_ui'      => true,
			'show_in_menu' => false,
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'has_archive'  => true,
			'rewrite'      => array( 'slug' => 'teams' ),
		),
	);

	/**
	 * Register player and team posts and their workflow statuses.
	 */
	public static function register(): void {
		self::register_statuses();

		foreach ( self::POST_TYPES as $type => $args ) {
			register_post_type( $type, $args );
		}
	}

	/**
	 * Register the existing private moderation statuses.
	 */
	public static function register_statuses(): void {
		register_post_status(
			'spp_pending',
			array(
				'label'       => __( 'Pending Review', 'sports-player-platform' ),
				'public'      => false,
				'internal'    => true,
				/* translators: %s: number of players pending review. */
				'label_count' => _n_noop( 'Pending Review <span class="count">(%s)</span>', 'Pending Review <span class="count">(%s)</span>', 'sports-player-platform' ),
			)
		);
		register_post_status(
			'rejected',
			array(
				'label'    => __( 'Rejected', 'sports-player-platform' ),
				'public'   => false,
				'internal' => true,
			)
		);
	}
}
