<?php
/**
 * Public account flows and WordPress authentication integration.
 *
 * @package Sports_Player_Platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-spp-auth-roles.php';
require_once __DIR__ . '/class-spp-auth-attempts.php';
require_once __DIR__ . '/class-spp-auth-session.php';
require_once __DIR__ . '/class-spp-auth-verification.php';

/**
 * Register the SPP authentication forms and enforce access to account pages.
 */
final class SPP_Auth {

	public const ROLE_PLAYER        = SPP_Auth_Roles::PLAYER;
	public const ROLE_PARENT        = SPP_Auth_Roles::PARENT;
	public const ROLE_COACH         = SPP_Auth_Roles::COACH;
	public const ROLES_VERSION      = SPP_Auth_Roles::VERSION;
	public const MAX_LOGIN_ATTEMPTS = SPP_Auth_Attempts::MAX_LOGIN_ATTEMPTS;
	public const LOCKOUT_SECONDS    = SPP_Auth_Attempts::LOCKOUT_SECONDS;

	private const DASHBOARD_SHORTCODES = array(
		'spp_dashboard'        => 'dashboard',
		'spp_edit_profile'     => 'dashboard/edit-profile',
		'spp_my_membership'    => 'dashboard/membership',
		'spp_messages'         => 'dashboard/messages',
		'spp_message_form'     => 'dashboard/messages',
		'spp_shortlist'        => 'dashboard/shortlist',
		'spp_recruiting'       => 'dashboard/recruiting',
		'spp_recruiting_lists' => 'dashboard/recruiting',
	);

	/**
	 * Plugin authentication singleton.
	 *
	 * @var SPP_Auth|null
	 */
	private static ?self $instance = null;

	/**
	 * Register WordPress authentication filters and frontend hooks.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_user_roles' ) );
		add_filter( 'map_meta_cap', array( SPP_Auth_Roles::class, 'map_capability' ), 10, 4 );
		add_filter( 'nonce_user_logged_out', array( SPP_Auth_Session::class, 'guest_nonce_user' ), 10, 2 );
		add_filter( 'wp_authenticate_user', array( SPP_Auth_Attempts::class, 'before_password' ) );
		add_filter( 'authenticate', array( $this, 'native_password_compatibility' ), 75, 3 );
		add_filter( 'authenticate', array( SPP_Auth_Attempts::class, 'after_password' ), 80, 2 );
		add_filter( 'authenticate', array( $this, 'require_verified_email' ), 100 );
		add_filter( 'determine_current_user', array( $this, 'require_verified_session' ), 99 );
		add_action( 'wp_login_failed', array( SPP_Auth_Attempts::class, 'record_failure' ), 10, 2 );
		add_action( 'wp_login', array( SPP_Auth_Attempts::class, 'clear_failures' ), 10, 2 );
		add_filter( 'login_redirect', array( $this, 'native_login_redirect' ), 10, 3 );

		add_action( 'template_redirect', array( $this, 'handle_request' ), 5 );
		add_action( 'template_redirect', array( $this, 'guard_protected_pages' ), 9 );
		add_filter( 'pre_do_shortcode_tag', array( $this, 'guard_account_shortcodes' ), 10, 2 );
		add_shortcode( 'spp_login_form', array( $this, 'render_login_form' ) );
		add_shortcode( 'spp_register_form', array( $this, 'render_registration_form' ) );
		add_shortcode( 'spp_password_reset_form', array( $this, 'render_password_reset_form' ) );
	}

	/**
	 * Return the authentication singleton.
	 *
	 * @return self Authentication service.
	 */
	public static function get_instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Preserve the installer entry point for existing callers.
	 */
	public function install_pages(): void {
		SPP_Pages::install();
	}

	/**
	 * Install or update the SPP role definitions.
	 */
	public function register_user_roles(): void {
		SPP_Auth_Roles::register();
	}

	/**
	 * Reject a pending account after WordPress has checked its credentials.
	 *
	 * @param WP_User|WP_Error|null $user Authentication result.
	 * @return WP_User|WP_Error|null Authentication result.
	 */
	public function require_verified_email( $user ) {
		if ( $user instanceof WP_User && SPP_Auth_Verification::is_pending( $user->ID ) ) {
			return new WP_Error( 'spp_unverified_email', __( 'Verify your email address before signing in.', 'sports-player-platform' ) );
		}
		return $user;
	}

	/**
	 * Honor literal plugin passwords when the native form passes slashed credentials.
	 *
	 * @param WP_User|WP_Error|null $result   Core authentication result.
	 * @param string                $identity Username or email.
	 * @param string                $password Current credential.
	 * @return WP_User|WP_Error|null Authentication result before verification.
	 */
	public function native_password_compatibility( $result, string $identity, #[\SensitiveParameter] string $password ) {
		if ( 'wp-login.php' !== ( $GLOBALS['pagenow'] ?? '' ) || ! is_wp_error( $result ) || 'incorrect_password' !== $result->get_error_code() || ! str_contains( $password, '\\' ) ) {
			return $result;
		}
		$user = get_user_by( 'login', $identity );
		if ( ! $user && is_email( $identity ) ) {
			$user = get_user_by( 'email', $identity );
		}
		if ( ! $user || ! metadata_exists( 'user', $user->ID, SPP_Auth_Verification::STATUS_META ) ) {
			return $result;
		}

		// Use core's pre-password hooks, including the SPP throttle, on this compatibility check.
		return wp_authenticate_username_password( null, $user->user_login, wp_unslash( $password ) );
	}

	/**
	 * Deny existing sessions and application password sessions for pending users.
	 *
	 * @param int|false $user_id Resolved WordPress user ID.
	 * @return int|false Resolved WordPress user ID.
	 */
	public function require_verified_session( $user_id ) {
		return $user_id && SPP_Auth_Verification::is_pending( (int) $user_id ) ? false : $user_id;
	}

	/**
	 * Handle credential links and forms before WordPress loads the theme.
	 */
	public function handle_request(): void {
		$path    = is_page() ? get_page_uri( get_queried_object_id() ) : '';
		$actions = array(
			'login'         => 'login',
			'resend'        => 'login',
			'register'      => 'register',
			'verify'        => 'verify-email',
			'reset-request' => 'reset-password',
			'reset-confirm' => 'reset-password',
		);

		if ( '1' === self::query( 'spp_logout' ) ) {
			$this->handle_logout();
		}

		$content = is_singular() ? get_post_field( 'post_content', get_queried_object_id() ) : '';
		if ( in_array( $path, $actions, true ) || $this->has_auth_shortcode( $content ) ) {
			SPP_Auth_Session::prepare();
		}
		foreach ( array_keys( self::DASHBOARD_SHORTCODES ) as $tag ) {
			if ( has_shortcode( $content, $tag ) ) {
				nocache_headers();
				break;
			}
		}

		if ( 'verify-email' === $path ) {
			add_filter( 'the_content', array( $this, 'render_verification_content' ) );
		}
		if ( 'reset-password' === $path && 'rp' === self::query( 'action' ) ) {
			$this->open_password_reset_link();
		}

		$action = sanitize_key( self::post( 'spp_auth_action' ) );
		if ( ! isset( $actions[ $action ] ) || $path !== $actions[ $action ] ) {
			return;
		}

		$this->verify_form( $action );
		switch ( $action ) {
			case 'login':
				$this->handle_login();
				break;
			case 'register':
				$this->handle_registration_form();
				break;
			case 'resend':
				$this->handle_resend_verification();
				break;
			case 'verify':
				$this->handle_email_verification();
				break;
			case 'reset-request':
				$this->request_password_reset();
				break;
			case 'reset-confirm':
				$this->confirm_password_reset();
				break;
		}
	}

	/**
	 * Sign in with WordPress and redirect according to the account role.
	 */
	private function handle_login(): void {
		if ( is_user_logged_in() ) {
			wp_safe_redirect( $this->role_destination( wp_get_current_user() ) );
			exit;
		}

		$destination = esc_url_raw( self::post( 'redirect_to' ) );
		$user        = wp_signon(
			array(
				'user_login'    => sanitize_text_field( self::post( 'username' ) ),
				'user_password' => self::password( 'password' ),
				'remember'      => '1' === self::post( 'remember' ),
			),
			is_ssl()
		);
		if ( is_wp_error( $user ) ) {
			$status = match ( $user->get_error_code() ) {
				'spp_unverified_email'  => 'unverified',
				'spp_login_throttled'  => 'throttled',
				default                => 'invalid_login',
			};
			$this->redirect( 'login', $status, array( 'redirect_to' => $this->safe_destination( $destination, '' ) ) );
		}

		wp_safe_redirect( $this->safe_destination( $destination, $this->role_destination( $user ) ) );
		exit;
	}

	/**
	 * Verify the current session's existing logout link.
	 */
	private function handle_logout(): void {
		if ( ! is_user_logged_in() ) {
			$this->redirect( 'login' );
		}
		if ( ! wp_verify_nonce( self::query( '_wpnonce' ), 'spp_logout_nonce' ) ) {
			$this->deny();
		}
		wp_logout();
		$this->redirect( 'login' );
	}

	/**
	 * Create a user with the selected public role and pending verification metadata.
	 */
	private function handle_registration_form(): void {
		if ( is_user_logged_in() ) {
			$this->redirect( 'dashboard' );
		}

		$name      = sanitize_text_field( self::post( 'full_name' ) );
		$username  = sanitize_user( self::post( 'username' ), true );
		$raw_email = trim( self::post( 'email' ) );
		$email     = sanitize_email( $raw_email );
		$password  = self::password( 'password' );
		$role      = sanitize_key( self::post( 'role' ) );
		$sport     = sanitize_text_field( self::post( 'sport' ) );
		$public    = SPP_Auth_Roles::registration_roles();

		if ( '' === $name || '' === $username || ! is_email( $raw_email ) || ! is_email( $email ) || strlen( $name ) > 400 || strlen( $sport ) > 400 || ! isset( $public[ $role ] ) || ! get_role( $role ) ) {
			$this->redirect( 'register', 'invalid_registration' );
		}
		if ( ! self::valid_password( $password ) ) {
			$this->redirect( 'register', 'invalid_password' );
		}

		$require_verification = SPP_Auth_Verification::is_required();
		$issued               = $require_verification ? SPP_Auth_Verification::issue( $email ) : array();
		$user_id              = 0;
		$saved                = SPP_DB::with_lock(
			'public-registration',
			static function () use ( $username, $email, $password, $name, $role, $sport, $issued, $require_verification, &$user_id ): bool {
				$meta_input = array(
					SPP_Auth_Verification::STATUS_META => $require_verification ? '0' : '1',
					'spp_sport'                        => $sport,
				);
				if ( $require_verification ) {
					$meta_input[ SPP_Auth_Verification::TOKEN_META ] = $issued['stored'];
				}
				// Set role and verification state before WordPress dispatches user_register.
				$result = wp_insert_user(
					array(
						'user_login'   => $username,
						'user_email'   => $email,
						'user_pass'    => $password,
						'display_name' => $name,
						'first_name'   => $name,
						'role'         => $role,
						'meta_input'   => $meta_input,
					)
				);
				if ( is_wp_error( $result ) ) {
					return false;
				}
				$user_id = $result;
				return $require_verification ? SPP_Auth_Verification::is_pending( $user_id ) : true;
			}
		);

		if ( ! $saved ) {
			$this->redirect( 'register', 'registration_failed' );
		}

		if ( ! $require_verification ) {
			$this->redirect( 'login', 'registered_active' );
		}

		$sent = $this->send_verification_email( $user_id, $email, $issued['token'] );
		$this->redirect( 'login', $sent ? 'registered' : 'registered_mail_failed' );
	}

	/**
	 * Consume the token only after the visitor confirms the email link.
	 */
	private function handle_email_verification(): void {
		$user  = get_user_by( 'login', sanitize_user( self::post( 'user' ) ) );
		$token = self::post( 'token' );
		if ( ! $user || ! SPP_Auth_Verification::consume( $user, $token ) ) {
			$this->redirect( 'login', 'invalid_verification' );
		}

		$this->send_welcome_email( $user->ID, $user->user_email );
		$this->redirect( 'login', 'verified' );
	}

	/**
	 * Send a replacement token with per-account cooldown and a generic result.
	 */
	private function handle_resend_verification(): void {
		$identity = sanitize_text_field( trim( self::post( 'identity' ) ) );
		$user     = get_user_by( 'login', $identity );
		if ( ! $user && is_email( $identity ) ) {
			$user = get_user_by( 'email', $identity );
		}
		if ( $user && SPP_Auth_Verification::is_pending( $user->ID ) && SPP_Auth_Attempts::reserve( 'spp_auth_resend_' . $user->ID, 1, MINUTE_IN_SECONDS ) ) {
			$token = SPP_Auth_Verification::replace( $user );
			if ( '' !== $token ) {
				$this->send_verification_email( $user->ID, $user->user_email, $token );
			}
		}
		$this->redirect( 'login', 'verification_sent' );
	}

	/**
	 * Send a WordPress reset link without disclosing whether an address exists.
	 */
	private function request_password_reset(): void {
		$address = trim( self::post( 'email' ) );
		$user    = is_email( $address ) ? get_user_by( 'email', sanitize_email( $address ) ) : false;
		if ( $user && SPP_Auth_Attempts::reserve( 'spp_auth_reset_' . $user->ID, 1, MINUTE_IN_SECONDS ) ) {
			$key = get_password_reset_key( $user );
			if ( ! is_wp_error( $key ) ) {
				$url = add_query_arg(
					array(
						'action' => 'rp',
						'key'    => rawurlencode( $key ),
						'login'  => rawurlencode( $user->user_login ),
					),
					spp_page_url( 'reset-password' )
				);
				$this->mail_link( $user, __( 'Reset your Sports Player Platform password', 'sports-player-platform' ), __( 'Open this link to reset your password:', 'sports-player-platform' ), $url, 'password-reset' );
			}
		}
		$this->redirect( 'reset-password', 'reset_sent' );
	}

	/**
	 * Move a verified reset key into an HTTP-only cookie.
	 */
	private function open_password_reset_link(): void {
		$login = sanitize_user( self::query( 'login' ) );
		$key   = self::query( 'key' );
		if ( ! preg_match( '/^[A-Za-z0-9]+$/D', $key ) || is_wp_error( check_password_reset_key( $key, $login ) ) || ! SPP_Auth_Session::start_reset( $login, $key ) ) {
			SPP_Auth_Session::clear_reset();
			$this->redirect( 'reset-password', 'invalid_reset' );
		}
		$this->redirect( 'reset-password', 'new_password' );
	}

	/**
	 * Check the WordPress key again under lock and complete the reset.
	 */
	private function confirm_password_reset(): void {
		list( $login, $key ) = SPP_Auth_Session::reset_credentials();
		$user                = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) ) {
			SPP_Auth_Session::clear_reset();
			$this->redirect( 'reset-password', 'invalid_reset' );
		}

		$password = self::password( 'pwd' );
		if ( ! self::valid_password( $password ) || self::password( 'pwd_confirm' ) !== $password ) {
			$this->redirect( 'reset-password', 'password_mismatch' );
		}

		$reset = SPP_DB::with_lock(
			'password-reset-' . $user->ID,
			static function () use ( $user, $key, $login, $password ): bool {
				clean_user_cache( $user );
				$current = check_password_reset_key( $key, $login );
				if ( is_wp_error( $current ) || $current->ID !== $user->ID ) {
					return false;
				}

				reset_password( $current, $password );
				$updated = get_userdata( $current->ID );
				if ( ! $updated || '' !== $updated->user_activation_key || ! wp_check_password( $password, $updated->user_pass, $updated->ID ) ) {
					return false;
				}
				WP_Session_Tokens::get_instance( $current->ID )->destroy_all();
				return true;
			}
		);

		SPP_Auth_Session::clear_reset();
		if ( ! $reset ) {
			$this->redirect( 'reset-password', 'invalid_reset' );
		}
		if ( get_current_user_id() === $user->ID ) {
			wp_logout();
		}
		$this->redirect( 'login', 'reset_complete' );
	}

	/**
	 * Redirect requests to protected dashboard pages.
	 */
	public function guard_protected_pages(): void {
		$path = is_page() ? get_page_uri( get_queried_object_id() ) : '';
		if ( 'dashboard' !== $path && ! str_starts_with( $path, 'dashboard/' ) ) {
			return;
		}

		nocache_headers();
		$access = $this->dashboard_access( $path );
		if ( 'login' === $access ) {
			$this->redirect( 'login', '', array( 'redirect_to' => spp_page_url( $path ) ) );
		}
		if ( 'paid' === $access ) {
			$this->redirect( 'pricing' );
		}
		if ( 'forbidden' === $access ) {
			$this->deny();
		}
	}

	/**
	 * Enforce the same rules when a dashboard shortcode is embedded elsewhere.
	 *
	 * @param bool|string $output Prior filter result.
	 * @param string      $tag    Shortcode being rendered.
	 * @return bool|string Prior output or an access notice.
	 */
	public function guard_account_shortcodes( $output, string $tag ) {
		if ( ! isset( self::DASHBOARD_SHORTCODES[ $tag ] ) ) {
			return $output;
		}

		$access = $this->dashboard_access( self::DASHBOARD_SHORTCODES[ $tag ] );
		return match ( $access ) {
			'login'      => '<p>' . esc_html__( 'Sign in to access this section.', 'sports-player-platform' ) . ' <a href="' . esc_url( spp_page_url( 'login' ) ) . '">' . esc_html__( 'Log In', 'sports-player-platform' ) . '</a></p>',
			'paid'       => '<p>' . esc_html__( 'This section requires an active paid membership.', 'sports-player-platform' ) . ' <a href="' . esc_url( spp_page_url( 'pricing' ) ) . '">' . esc_html__( 'View Plans', 'sports-player-platform' ) . '</a></p>',
			'forbidden'  => '<p>' . esc_html__( 'Your account cannot access this section.', 'sports-player-platform' ) . '</p>',
			default      => $output,
		};
	}

	/**
	 * Determine the role and plan requirement for a dashboard section.
	 *
	 * @param string $path Plugin page path.
	 * @return string Access result, or empty for allowed access.
	 */
	private function dashboard_access( string $path ): string {
		if ( ! is_user_logged_in() || SPP_Auth_Verification::is_pending( get_current_user_id() ) ) {
			return 'login';
		}
		if ( current_user_can( 'manage_options' ) ) {
			return '';
		}

		$capability = match ( $path ) {
			'dashboard/edit-profile' => 'edit_own_profile',
			'dashboard/shortlist'    => 'view_saved_players',
			'dashboard/recruiting'   => 'create_recruiting_list',
			'dashboard/messages'     => 'send_messages',
			default                  => 'read',
		};
		if ( ! current_user_can( $capability ) ) {
			return 'forbidden';
		}
		return 'dashboard/messages' === $path && ! spp_user_has_membership( get_current_user_id(), 'paid' ) ? 'paid' : '';
	}

	/**
	 * Apply SPP role landing pages to the native WordPress form.
	 *
	 * @param mixed            $redirect  WordPress fallback target.
	 * @param mixed            $requested Requested target.
	 * @param WP_User|WP_Error $user      Authentication result.
	 * @return string Redirect target.
	 */
	public function native_login_redirect( $redirect, $requested, $user ): string {
		if ( ! $user instanceof WP_User || ! array_intersect( array_keys( SPP_Auth_Roles::registration_roles() ), $user->roles ) ) {
			return is_string( $redirect ) ? $redirect : '';
		}
		return $this->safe_destination( is_string( $requested ) ? $requested : '', $this->role_destination( $user ) );
	}

	/**
	 * Send a verification link to the account address.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $email   Account address at issue time.
	 * @param string $token   Verification token.
	 * @return bool Whether WordPress accepted the email.
	 */
	public function send_verification_email( int $user_id, string $email, #[\SensitiveParameter] string $token ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user || 0 !== strcasecmp( $email, $user->user_email ) ) {
			return false;
		}
		$url = add_query_arg(
			array(
				'token' => rawurlencode( $token ),
				'user'  => rawurlencode( $user->user_login ),
			),
			spp_page_url( 'verify-email' )
		);
		return $this->mail_link( $user, __( 'Verify your Sports Player Platform email', 'sports-player-platform' ), __( 'Open this link to confirm your email address. The link expires in 24 hours.', 'sports-player-platform' ), $url, 'verify-email' );
	}

	/**
	 * Send the account welcome email after verification.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $email   Verified account address.
	 */
	public function send_welcome_email( int $user_id, string $email ): void {
		$user = get_userdata( $user_id );
		if ( $user && 0 === strcasecmp( $email, $user->user_email ) ) {
			$this->mail_link( $user, __( 'Welcome to Sports Player Platform', 'sports-player-platform' ), __( 'Your email is verified. You can now sign in:', 'sports-player-platform' ), spp_page_url( 'login' ), 'welcome' );
		}
	}

	/**
	 * Render the login and resend forms.
	 *
	 * @return string Shortcode output.
	 */
	public function render_login_form(): string {
		if ( is_user_logged_in() ) {
			return '<p><a href="' . esc_url( spp_page_url( 'dashboard' ) ) . '">' . esc_html__( 'Go to Dashboard', 'sports-player-platform' ) . '</a></p>';
		}
		$view = array(
			'status'      => self::query( 'spp_auth' ),
			'destination' => $this->safe_destination( self::query( 'redirect_to' ), '' ),
		);
		return $this->load_template( 'login', $view );
	}

	/**
	 * Render the registration form with the permitted public roles.
	 *
	 * @return string Shortcode output.
	 */
	public function render_registration_form(): string {
		if ( is_user_logged_in() ) {
			return '<p>' . esc_html__( 'You are already signed in.', 'sports-player-platform' ) . '</p>';
		}
		return $this->load_template(
			'register',
			array(
				'status' => self::query( 'spp_auth' ),
				'roles'  => SPP_Auth_Roles::registration_roles(),
			)
		);
	}

	/**
	 * Render the password request or confirmation form.
	 *
	 * @return string Shortcode output.
	 */
	public function render_password_reset_form(): string {
		list( $login, $key ) = SPP_Auth_Session::reset_credentials();
		$status              = self::query( 'spp_auth' );
		$confirm             = in_array( $status, array( 'new_password', 'password_mismatch' ), true );
		$valid               = $confirm && ! is_wp_error( check_password_reset_key( $key, $login ) );
		return $this->load_template(
			'reset-password',
			array(
				'status'  => $confirm && ! $valid ? 'invalid_reset' : $status,
				'confirm' => $valid,
			)
		);
	}

	/**
	 * Append the verification form to the configured page only.
	 *
	 * @param string $content Page content.
	 * @return string Page content and verification form.
	 */
	public function render_verification_content( string $content ): string {
		if ( get_the_ID() !== get_queried_object_id() ) {
			return $content;
		}
		$username = sanitize_user( self::query( 'user' ) );
		$user     = get_user_by( 'login', $username );
		$token    = self::query( 'token' );
		$valid    = $user && SPP_Auth_Verification::matches( $user, $token );
		return $content . $this->load_template(
			'verify-email',
			array(
				'valid'    => $valid,
				'username' => $username,
				'token'    => $token,
			)
		);
	}

	/**
	 * Use a public form's browser-bound nonce before processing a request.
	 *
	 * @param string $action Auth action.
	 */
	private function verify_form( string $action ): void {
		if ( ( ! is_user_logged_in() && ! SPP_Auth_Session::has_guest() ) || ! wp_verify_nonce( sanitize_text_field( self::post( 'spp_nonce' ) ), 'spp_auth_' . $action ) ) {
			$this->deny();
		}
	}

	/**
	 * Respond to a rejected request without running an action.
	 */
	private function deny(): never {
		wp_die( esc_html__( 'Your account or session cannot complete this request. Reload the page and try again.', 'sports-player-platform' ), '', array( 'response' => 403 ) );
		exit;
	}

	/**
	 * Send a safe redirect without placing secrets or error strings in the URL.
	 *
	 * @param string $page   Plugin page path.
	 * @param string $status Internal notice key.
	 * @param array  $args   Extra safe parameters.
	 */
	private function redirect( string $page, string $status = '', array $args = array() ): never {
		if ( '' !== $status ) {
			$args['spp_auth'] = $status;
		}
		$args = array_map( 'rawurlencode', array_filter( $args ) );
		wp_safe_redirect( add_query_arg( $args, spp_page_url( $page ) ) );
		exit;
	}

	/**
	 * Restrict form return URLs to the site's origin.
	 *
	 * @param string $requested Requested location.
	 * @param string $fallback  Trusted location.
	 * @return string Redirect location.
	 */
	private function safe_destination( string $requested, string $fallback ): string {
		$url = wp_validate_redirect( esc_url_raw( $requested ), '' );
		if ( '' === $url ) {
			return $fallback;
		}
		$target = wp_parse_url( $url );
		$home   = wp_parse_url( home_url() );
		if ( isset( $target['host'] ) && ( 0 !== strcasecmp( $target['host'], $home['host'] ) || ( $target['scheme'] ?? '' ) !== $home['scheme'] || ( $target['port'] ?? 0 ) !== ( $home['port'] ?? 0 ) ) ) {
			return $fallback;
		}
		return $url;
	}

	/**
	 * Return the default destination for an authenticated user.
	 *
	 * @param WP_User $user Signed-in user.
	 * @return string Landing page URL.
	 */
	private function role_destination( WP_User $user ): string {
		if ( user_can( $user, 'manage_options' ) ) {
			return admin_url();
		}
		if ( in_array( self::ROLE_COACH, $user->roles, true ) ) {
			return spp_page_url( 'dashboard/recruiting' );
		}
		if ( array_intersect( array( self::ROLE_PLAYER, self::ROLE_PARENT ), $user->roles ) ) {
			return spp_page_url( 'dashboard' );
		}
		return home_url( '/' );
	}

	/**
	 * Check an alternate page for a public auth form.
	 *
	 * @param string $content Queried post content.
	 * @return bool Whether it includes a public auth shortcode.
	 */
	private function has_auth_shortcode( string $content ): bool {
		foreach ( array( 'spp_login_form', 'spp_register_form', 'spp_password_reset_form' ) as $tag ) {
			if ( has_shortcode( $content, $tag ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Send a plain-text authentication email with WordPress.
	 *
	 * @param WP_User $user    Recipient account.
	 * @param string  $subject Email subject.
	 * @param string  $intro   Localized instructions.
	 * @param string  $url     Account action URL.
	 * @return bool Whether WordPress accepted the email.
	 */
	private function mail_link( WP_User $user, string $subject, string $intro, string $url, string $template = '' ): bool {
		if ( '' !== $template && class_exists( 'SPP_Email' ) ) {
			$sent = SPP_Email::send(
				$user->user_email,
				$template,
				array(
					'subject' => $subject,
					'name'    => $user->display_name,
					'intro'   => $intro,
					'url'     => $url,
				)
			);
		} else {
			$sent = wp_mail( $user->user_email, $subject, $intro . "\n\n" . $url );
		}
		if ( ! $sent ) {
			spp_log( 'WordPress could not send an SPP account email.', 'warning' );
		}
		return $sent;
	}

	/**
	 * Read a raw POST field for the handler's validation.
	 *
	 * @param string $key Field name.
	 * @return string Unslashed scalar field value.
	 */
	private static function post( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The dispatcher verifies the nonce and each handler validates its fields.
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
	}

	/**
	 * Read a display parameter or a candidate credential from the query string.
	 *
	 * @param string $key Query key.
	 * @return string Sanitized value.
	 */
	private static function query( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading a query parameter does not authorize an account change.
		return isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
	}

	/**
	 * Read a password using the same whitespace behavior as WordPress.
	 *
	 * @param string $key Form field.
	 * @return string Password value.
	 */
	private static function password( string $key ): string {
		return trim( self::post( $key ) );
	}

	/**
	 * Validate the length of a newly chosen password.
	 *
	 * @param string $password New password.
	 * @return bool Whether it meets the minimum and maximum length.
	 */
	private static function valid_password( #[\SensitiveParameter] string $password ): bool {
		return strlen( $password ) <= 4096 && 1 === preg_match( '/^.{8,}$/usD', $password );
	}

	/**
	 * Render one of the internal auth templates.
	 *
	 * @param string $name Template key.
	 * @param array  $view Template data.
	 * @return string Rendered template.
	 */
	private function load_template( string $name, array $view ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- The included template reads this variable.
		ob_start();
		require SPP_PLUGIN_DIR . 'templates/auth/' . $name . '.php';
		return (string) ob_get_clean();
	}
}
