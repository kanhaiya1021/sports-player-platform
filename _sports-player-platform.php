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
 * Requires at least: 6.0
 * Requires PHP: 8.3
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin version constant
 */
define('SPP_VERSION', '1.0.0');

/**
 * Plugin directory path constant
 */
define('SPP_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Plugin directory URL constant
 */
define('SPP_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Plugin file constant
 */
define('SPP_PLUGIN_FILE', __FILE__);

/**
 * Plugin base name constant
 */
define('SPP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Database version constant
 */
define('SPP_DB_VERSION', '1.0.0');

/**
 * Autoloader
 */
require_once SPP_PLUGIN_DIR . 'vendor/autoload.php';

/**
 * Main plugin class
 */
final class Sports_Player_Platform {

    /**
     * Plugin instance
     *
     * @var Sports_Player_Platform|null
     */
    private static $instance = null;

    /**
     * Module registry
     *
     * @var array
     */
    private $modules = [];

    /**
     * Get plugin instance
     *
     * @return Sports_Player_Platform
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
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        // Activation and deactivation hooks
        register_activation_hook(SPP_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(SPP_PLUGIN_FILE, [$this, 'deactivate']);
        register_uninstall_hook(SPP_PLUGIN_FILE, [__CLASS__, 'uninstall']);

        // Initialize on plugins_loaded
        add_action('plugins_loaded', [$this, 'load_plugin'], 10);

        // Load admin functionality early (on plugins_loaded before admin_menu runs)
        add_action('plugins_loaded', [$this, 'load_admin'], 5);

        // Initialize public on init early to register post types before admin_menu
        add_action('init', [$this, 'load_public'], 5);
    }

    /**
     * Plugin activation hook
     */
    public function activate(): void {
        // Create database tables
        $this->create_database_tables();

        // Set database version
        update_option('spp_db_version', SPP_DB_VERSION);

        // Flush rewrite rules
        flush_rewrite_rules();

        // Do action for other plugins/modules
        do_action('spp_activated');
    }

    /**
     * Plugin deactivation hook
     */
    public function deactivate(): void {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Do action for other plugins/modules
        do_action('spp_deactivated');
    }

    /**
     * Plugin uninstall hook
     */
    public static function uninstall(): void {
        // Delete options
        delete_option('spp_db_version');
        delete_option('spp_settings');

        // Do action for other plugins/modules
        do_action('spp_uninstalled');
    }

    /**
     * Create database tables
     */
    private function create_database_tables(): void {
        global $wpdb;

        require_once SPP_PLUGIN_DIR . 'includes/database/class-spp-db.php';
        SPP_DB::get_instance()->create_tables();
    }

    /**
     * Load plugin core functionality
     */
    public function load_plugin(): void {
        // Load text domain
        load_plugin_textdomain('sports-player-platform', false, dirname(SPP_PLUGIN_BASENAME) . '/languages/');

        // Load core classes
        $this->load_core_classes();

        // Load modules
        $this->load_modules();

        // Do action for other plugins
        do_action('spp_loaded');
    }

    /**
     * Load core classes
     */
    private function load_core_classes(): void {
        // Load helper functions
        require_once SPP_PLUGIN_DIR . 'includes/functions.php';

        // Load authentication class
        require_once SPP_PLUGIN_DIR . 'includes/auth/class-spp-auth.php';
        SPP_Auth::get_instance();

        // Load membership class
        require_once SPP_PLUGIN_DIR . 'includes/membership/class-spp-membership.php';
        SPP_Membership::get_instance();

        // Load messaging class
        require_once SPP_PLUGIN_DIR . 'includes/messaging/class-spp-messaging.php';
        SPP_Messaging::get_instance();

        // Load recruiting class
        require_once SPP_PLUGIN_DIR . 'includes/recruiting/class-spp-recruiting.php';
        SPP_Recruiting::get_instance();

        // Load settings class
        require_once SPP_PLUGIN_DIR . 'includes/settings/class-spp-settings.php';
        SPP_Settings::get_instance();

        // Note: SPP_Frontend class is loaded on 'init' hook with priority 0 in load_public()
        // Note: SPP_Public class is loaded on 'init' hook via load_public()
    }

    /**
     * Load modules
     */
    private function load_modules(): void {
        $module_dir = SPP_PLUGIN_DIR . 'modules/';

        if (!is_dir($module_dir)) {
            return;
        }

        $modules = array_diff(scandir($module_dir), ['.', '..']);

        foreach ($modules as $module) {
            $module_path = $module_dir . $module . '/bootstrap.php';
            if (file_exists($module_path)) {
                require_once $module_path;
                $this->modules[$module] = true;
            }
        }

        do_action('spp_modules_loaded', $this->modules);
    }

    /**
     * Load admin functionality
     */
    public function load_admin(): void {
        if (is_admin() || defined('DOING_AJAX') && DOING_AJAX) {
            // Load admin classes
            require_once SPP_PLUGIN_DIR . 'admin/class-spp-admin.php';
            SPP_Admin::get_instance();

            do_action('spp_admin_loaded');
        }
    }

    /**
     * Load public functionality
     */
    public function load_public(): void {
        // Load frontend class first (for SEO and articles post type - priority 0 in hooks)
        require_once SPP_PLUGIN_DIR . 'includes/frontend/class-spp-frontend.php';
        SPP_Frontend::get_instance();

        // Load public classes (for player/team post types - priority 10)
        require_once SPP_PLUGIN_DIR . 'public/class-spp-public.php';
        SPP_Public::get_instance();

        do_action('spp_public_loaded');
    }

    /**
     * Get loaded modules
     *
     * @return array
     */
    public function get_modules(): array {
        return $this->modules;
    }

    /**
     * Check if module is loaded
     *
     * @param string $module_name
     * @return bool
     */
    public function is_module_loaded(string $module_name): bool {
        return isset($this->modules[$module_name]);
    }
}

/**
 * Main plugin bootstrap function
 *
 * @return Sports_Player_Platform
 */
function spp(): Sports_Player_Platform {
    return Sports_Player_Platform::get_instance();
}

// Initialize the plugin
spp();