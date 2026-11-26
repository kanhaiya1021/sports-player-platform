<?php
/**
 * Plugin lifecycle and module registration.
 *
 * @package Sports_Player_Platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-spp-content-types.php';
require_once __DIR__ . '/class-spp-pages.php';

/**
 * Connect the existing feature modules to WordPress.
 */
final class SPP_Plugin {

	/**
	 * Plugin singleton.
	 *
	 * @var SPP_Plugin|null
	 */
	private static ?self $instance = null;

	/**
	 * Loaded modules.
	 *
	 * @var array<string, bool>
	 */
	private array $modules = array();

	/**
	 * Whether the database passed its installation check.
	 *
	 * @var bool
	 */
	private bool $database_ready = true;

	/**
	 * Whether the required pages were provisioned.
	 *
	 * @var bool
	 */
	private bool $pages_ready = true;

	/**
	 * Whether the public hooks were set up in this request.
	 *
	 * @var bool
	 */
	private bool $public_loaded = false;

	/**
	 * Register the WordPress lifecycle hooks.
	 */
	private function __construct() {
		register_activation_hook( SPP_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( SPP_PLUGIN_FILE, array( $this, 'deactivate' ) );
		add_action( 'plugins_loaded', array( $this, 'load_admin' ), 5 );
		add_action( 'plugins_loaded', array( $this, 'load_plugin' ) );
		add_action( 'init', array( $this, 'load_public' ), 5 );
		add_action( 'admin_init', array( $this, 'install_pages' ) );
		add_action( 'admin_notices', array( $this, 'installation_notice' ) );
	}

	/**
	 * Return the registered plugin instance.
	 *
	 * @return self Plugin instance.
	 */
	public static function get_instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Install the database and pages, then generate the content rewrites.
	 */
	public function activate(): void {
		$this->load_core_classes();
		if ( ! SPP_DB::get_instance()->create_tables() ) {
			wp_die( esc_html__( 'Sports Player Platform could not verify its database upgrade. Check the WordPress error log and retry activation.', 'sports-player-platform' ) );
		}

		$this->load_public();
		SPP_Auth::get_instance()->register_user_roles();
		if ( ! SPP_Pages::install() ) {
			wp_die( esc_html__( 'Sports Player Platform could not create its WordPress pages. Check the WordPress error log and retry activation.', 'sports-player-platform' ) );
		}

		flush_rewrite_rules( false );
		do_action( 'spp_activated' );
	}

	/**
	 * Remove plugin rewrites during deactivation.
	 */
	public function deactivate(): void {
		foreach ( array( 'player', 'team', 'spp_article' ) as $post_type ) {
			if ( post_type_exists( $post_type ) ) {
				unregister_post_type( $post_type );
			}
		}
		$this->public_loaded = false;

		flush_rewrite_rules( false );
		do_action( 'spp_deactivated' );
	}

	/**
	 * Load required services and apply an outstanding schema upgrade.
	 */
	public function load_plugin(): void {
		$this->load_core_classes();
		$this->database_ready = SPP_DB::get_instance()->upgrade();
		$this->load_modules();
		do_action( 'spp_loaded' );
	}

	/**
	 * Retry page provisioning from an authorized admin screen.
	 */
	public function install_pages(): void {
		if ( ! wp_doing_ajax() && current_user_can( 'manage_options' ) && $this->database_ready ) {
			$this->pages_ready = SPP_Pages::install();
		}
	}

	/**
	 * Surface failed installation steps to site administrators.
	 */
	public function installation_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $this->database_ready ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Sports Player Platform has a pending database upgrade. Check the WordPress error log for details.', 'sports-player-platform' ) . '</p></div>';
		} elseif ( ! $this->pages_ready ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Sports Player Platform could not provision one or more pages. Check the WordPress error log for the affected page path.', 'sports-player-platform' ) . '</p></div>';
		}
	}

	/**
	 * Register feature services used by both admin and public requests.
	 */
	private function load_core_classes(): void {
		require_once SPP_PLUGIN_DIR . 'includes/database/class-spp-db.php';
		require_once SPP_PLUGIN_DIR . 'includes/player/class-spp-player-profile.php';
		require_once SPP_PLUGIN_DIR . 'includes/functions.php';
		require_once SPP_PLUGIN_DIR . 'includes/auth/class-spp-auth.php';
		require_once SPP_PLUGIN_DIR . 'includes/membership/class-spp-membership.php';
		require_once SPP_PLUGIN_DIR . 'includes/payment/class-spp-payment.php';
		require_once SPP_PLUGIN_DIR . 'includes/messaging/class-spp-messaging.php';
		require_once SPP_PLUGIN_DIR . 'includes/recruiting/class-spp-recruiting.php';
		require_once SPP_PLUGIN_DIR . 'includes/dashboard/class-spp-dashboard.php';
		require_once SPP_PLUGIN_DIR . 'includes/email/class-spp-email.php';
		require_once SPP_PLUGIN_DIR . 'includes/settings/class-spp-settings.php';

		SPP_Auth::get_instance();
		SPP_Membership::get_instance();
		SPP_Payment::get_instance();
		SPP_Messaging::get_instance();
		SPP_Recruiting::get_instance();
		SPP_Dashboard::get_instance();
		SPP_Email::get_instance();
		SPP_Settings::get_instance();
	}

	/**
	 * Load installed modules and advertise the loaded registry.
	 */
	private function load_modules(): void {
		$module_dir = SPP_PLUGIN_DIR . 'modules/';
		if ( ! is_dir( $module_dir ) ) {
			return;
		}

		$files = glob( $module_dir . '*/bootstrap.php' );
		foreach ( is_array( $files ) ? $files : array() as $file ) {
			require_once $file;
			$this->modules[ basename( dirname( $file ) ) ] = true;
		}

		do_action( 'spp_modules_loaded', $this->modules );
	}

	/**
	 * Load REST registration in all requests and the editor in admin requests.
	 */
	public function load_admin(): void {
		require_once SPP_PLUGIN_DIR . 'admin/class-spp-admin.php';
		SPP_Admin::get_instance();

		if ( is_admin() || wp_doing_ajax() ) {
			require_once SPP_PLUGIN_DIR . 'admin/class-spp-player-editor.php';
			new SPP_Player_Editor();
			do_action( 'spp_admin_loaded' );
		}
	}

	/**
	 * Register public hooks and content types.
	 */
	public function load_public(): void {
		if ( $this->public_loaded ) {
			return;
		}

		load_plugin_textdomain( 'sports-player-platform', false, dirname( SPP_PLUGIN_BASENAME ) . '/languages/' );
		SPP_Content_Types::register();
		require_once SPP_PLUGIN_DIR . 'includes/frontend/class-spp-frontend.php';
		require_once SPP_PLUGIN_DIR . 'public/class-spp-public.php';
		$frontend = SPP_Frontend::get_instance();
		if ( ! post_type_exists( 'spp_article' ) ) {
			$frontend->create_articles_post_type();
		}
		SPP_Public::get_instance();
		$this->public_loaded = true;
		do_action( 'spp_public_loaded' );
	}

	/**
	 * Return the module registry.
	 *
	 * @return array<string, bool> Loaded module names.
	 */
	public function get_modules(): array {
		return $this->modules;
	}

	/**
	 * Check whether a module was loaded.
	 *
	 * @param string $module_name Module name.
	 * @return bool Whether the module was loaded.
	 */
	public function is_module_loaded( string $module_name ): bool {
		return isset( $this->modules[ $module_name ] );
	}
}
