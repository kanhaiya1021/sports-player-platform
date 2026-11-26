<?php
/**
 * Plugin Name: Sports Player Platform
 * Plugin URI: https://github.com/kanhaiya1021/sports-player-platform
 * Description: A comprehensive sports player profiles, rankings & recruiting platform for WordPress
 * Version: 1.0.0
 * Author: Sports Player Platform Team
 * Author URI: https://github.com/kanhaiya1021
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sports-player-platform
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.2
 * Network: false
 *
 * @package Sports_Player_Platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( PHP_VERSION_ID < 80200 || version_compare( $GLOBALS['wp_version'], '6.2', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Sports Player Platform requires WordPress 6.2 or later and PHP 8.2 or later.', 'sports-player-platform' ) . '</p></div>';
			}
		}
	);
	return;
}

define( 'SPP_VERSION', '1.0.0' );
define( 'SPP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SPP_PLUGIN_FILE', __FILE__ );
define( 'SPP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SPP_DB_VERSION', '1.3.0' );

require_once SPP_PLUGIN_DIR . 'modules/core/class-spp-plugin.php';

class_alias( SPP_Plugin::class, 'Sports_Player_Platform' );

/**
 * Return the Sports Player Platform plugin instance.
 *
 * @return SPP_Plugin Plugin instance.
 */
function spp(): SPP_Plugin {
	return SPP_Plugin::get_instance();
}

spp();
