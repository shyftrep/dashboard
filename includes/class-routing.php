<?php
/**
 * Dashboard routing, login redirect and wp-admin restrictions.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles /dashboard routing and access control.
 */
final class Shyft_Dashboard_Routing {

	public const QUERY_VAR = 'shyft_dashboard';

	/**
	 * Registers routing hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'bootstrap_dashboard_request' ), 0 );
		add_action( 'init', array( self::class, 'add_rewrite_rules' ) );
		add_action( 'init', array( self::class, 'maybe_flush_rewrite_rules' ), 20 );
		add_filter( 'query_vars', array( self::class, 'add_query_vars' ) );
		add_action( 'parse_request', array( self::class, 'prime_dashboard_query' ) );
		add_filter( 'wp_headers', array( self::class, 'filter_dashboard_headers' ), 99999 );
		add_filter( 'default_content_type', array( self::class, 'filter_default_content_type' ), 999 );
		add_action( 'send_headers', array( self::class, 'send_dashboard_headers' ), 0 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_dashboard_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'isolate_dashboard_assets' ), 9999 );
		add_action( 'template_redirect', array( self::class, 'maybe_prepare_dashboard' ), PHP_INT_MIN );
		add_filter( 'template_include', array( self::class, 'filter_dashboard_template' ), PHP_INT_MAX );
		add_filter( 'redirect_canonical', array( self::class, 'disable_canonical_redirect' ), 10, 2 );
		add_filter( 'login_redirect', array( self::class, 'login_redirect' ), 10, 3 );
		add_action( 'admin_init', array( self::class, 'block_wp_admin_for_kunde' ) );
		add_filter( 'show_admin_bar', array( self::class, 'hide_admin_bar_for_kunde' ) );
	}

	/**
	 * Flushes rewrite rules once after deploy or plugin update.
	 */
	public static function maybe_flush_rewrite_rules(): void {
		$option_key = 'shyft_dashboard_rewrite_version';

		if ( get_option( $option_key ) === SHYFT_DASHBOARD_VERSION ) {
			return;
		}

		self::add_rewrite_rules();
		flush_rewrite_rules( false );
		update_option( $option_key, SHYFT_DASHBOARD_VERSION, false );
	}

	/**
	 * Disables page-cache plugins for dashboard routes (before a poisoned cache entry is served).
	 */
	public static function bootstrap_dashboard_request(): void {
		if ( ! self::matches_dashboard_uri() && ! Shyft_Dashboard_Request::matches_uri() ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		if ( ! defined( 'LSCACHE_NO_CACHE' ) ) {
			define( 'LSCACHE_NO_CACHE', true );
		}

		if ( ! defined( 'DONOTROCKETOPTIMIZE' ) ) {
			define( 'DONOTROCKETOPTIMIZE', true );
		}

		self::send_dashboard_response_headers();
	}

	/**
	 * Ensures the dashboard query var is set before the main query runs.
	 *
	 * @param WP $wp WordPress environment.
	 */
	public static function prime_dashboard_query( WP $wp ): void {
		if ( ! self::matches_dashboard_uri() ) {
			return;
		}

		$wp->query_vars[ self::QUERY_VAR ] = '1';

		$period_days = self::get_period_from_path();

		if ( null !== $period_days ) {
			$wp->query_vars[ Shyft_Dashboard_Period::QUERY_VAR ] = (string) $period_days;
		}
	}

	/**
	 * Prevents canonical redirects away from the dashboard route.
	 *
	 * @param string $redirect_url  Canonical redirect URL.
	 * @param string $requested_url Requested URL.
	 */
	public static function disable_canonical_redirect( string $redirect_url, string $requested_url ): string {
		if ( self::matches_dashboard_uri() || self::is_dashboard_request() ) {
			return '';
		}

		return $redirect_url;
	}

	/**
	 * Adds the dashboard rewrite endpoint.
	 */
	public static function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^dashboard/(7|30|90)/?$',
			'index.php?' . self::QUERY_VAR . '=1&' . Shyft_Dashboard_Period::QUERY_VAR . '=$matches[1]',
			'top'
		);
		add_rewrite_rule( '^dashboard/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Registers the custom query variable.
	 *
	 * @param array<int, string> $vars Query vars.
	 * @return array<int, string>
	 */
	public static function add_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		$vars[] = Shyft_Dashboard_Period::QUERY_VAR;
		return $vars;
	}

	/**
	 * Forces HTML Content-Type on dashboard routes (overrides cache/security plugins).
	 *
	 * @param array<string, string> $headers Outgoing headers.
	 * @return array<string, string>
	 */
	public static function filter_dashboard_headers( array $headers ): array {
		if ( ! self::matches_dashboard_uri() && ! self::is_dashboard_request() ) {
			return $headers;
		}

		$headers['Content-Type'] = 'text/html; charset=' . get_bloginfo( 'charset' );

		return $headers;
	}

	/**
	 * @param string $content_type Default MIME type.
	 */
	public static function filter_default_content_type( string $content_type ): string {
		if ( self::matches_dashboard_uri() || self::is_dashboard_request() ) {
			return 'text/html';
		}

		return $content_type;
	}

	/**
	 * Sends HTML cache headers before any dashboard output (avoids plain-text rendering).
	 */
	public static function send_dashboard_headers(): void {
		if ( ! self::matches_dashboard_uri() && ! self::is_dashboard_request() ) {
			return;
		}

		self::send_dashboard_response_headers();
	}

	/**
	 * Redirects legacy ?shyft_period= URLs to /dashboard/{days}/ (avoids query-string cache bugs).
	 */
	private static function maybe_redirect_legacy_period_url(): void {
		if ( ! self::matches_dashboard_uri() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public period selector.
		if ( ! isset( $_GET[ Shyft_Dashboard_Period::QUERY_VAR ] ) ) {
			return;
		}

		if ( null !== self::get_period_from_path() ) {
			return;
		}

		$days = absint( wp_unslash( (string) $_GET[ Shyft_Dashboard_Period::QUERY_VAR ] ) );

		if ( ! in_array( $days, Shyft_Dashboard_Period::ALLOWED_DAYS, true ) ) {
			return;
		}

		wp_safe_redirect( Shyft_Dashboard_Period::get_dashboard_url( $days ), 301 );
		exit;
	}

	/**
	 * Sends Content-Type and no-cache headers for dashboard responses.
	 */
	private static function send_dashboard_response_headers(): void {
		if ( headers_sent() ) {
			return;
		}

		if ( function_exists( 'header_remove' ) ) {
			header_remove( 'Content-Type' );
		}

		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ), true );
		header( 'X-LiteSpeed-Cache-Control: no-cache', false );
		nocache_headers();
	}

	/**
	 * Auth, headers and query flags before WordPress loads the dashboard template.
	 */
	public static function maybe_prepare_dashboard(): void {
		self::maybe_redirect_legacy_period_url();

		if ( ! self::is_dashboard_request() ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			auth_redirect();
			exit;
		}

		if ( ! current_user_can( 'read' ) ) {
			wp_die( esc_html__( 'Du hast keine Berechtigung, dieses Dashboard aufzurufen.', 'shyft-dashboard' ) );
		}

		self::prepare_dashboard_response();
		show_admin_bar( false );
	}

	/**
	 * Swaps the theme template for the dashboard shell (normal WP lifecycle, no exit).
	 *
	 * @param string $template Path to the theme template.
	 */
	public static function filter_dashboard_template( string $template ): string {
		if ( ! self::is_dashboard_request() ) {
			return $template;
		}

		$shell = SHYFT_DASHBOARD_PATH . 'templates/dashboard-shell.php';

		if ( ! file_exists( $shell ) ) {
			return $template;
		}

		return $shell;
	}

	/**
	 * Outputs the dashboard (called from dashboard-shell.php).
	 */
	public static function render_dashboard_template(): void {
		self::render_dashboard();
	}

	/**
	 * Normalizes the main query and sends HTML headers for the dashboard route.
	 */
	private static function prepare_dashboard_response(): void {
		global $wp_query;

		if ( $wp_query instanceof WP_Query ) {
			$wp_query->is_404      = false;
			$wp_query->is_page     = true;
			$wp_query->is_singular = true;
			$wp_query->is_home     = false;
		}

		status_header( 200 );
		self::send_dashboard_response_headers();
	}

	/**
	 * Returns the dashboard path segment relative to the site home (e.g. "dashboard").
	 */
	private static function get_dashboard_request_path(): string {
		return Shyft_Dashboard_Request::get_path_segment();
	}

	/**
	 * Checks whether the request URI targets the dashboard route.
	 */
	private static function matches_dashboard_uri(): bool {
		return Shyft_Dashboard_Request::matches_uri();
	}

	/**
	 * Reads the period segment from /dashboard/{days}/ when present.
	 */
	private static function get_period_from_path(): ?int {
		$path = self::get_dashboard_request_path();

		if ( ! preg_match( '#^dashboard/(7|30|90)$#', $path, $matches ) ) {
			return null;
		}

		return (int) $matches[1];
	}

	/**
	 * Determines whether the current request targets the dashboard route.
	 */
	public static function is_dashboard_request(): bool {
		$query = get_query_var( self::QUERY_VAR );

		if ( '1' === (string) $query || 1 === $query ) {
			return true;
		}

		return self::matches_dashboard_uri();
	}

	/**
	 * Returns the dashboard URL.
	 */
	public static function get_dashboard_url(): string {
		return home_url( '/dashboard/' );
	}

	/**
	 * Redirects dashboard users (Kunde, Redakteur) after login – not administrators.
	 *
	 * @param string           $redirect_to           Redirect destination.
	 * @param string           $requested_redirect_to Requested redirect.
	 * @param WP_User|WP_Error $user                  User object or error.
	 */
	public static function login_redirect( string $redirect_to, string $requested_redirect_to, $user ): string {
		if ( $user instanceof WP_User && Shyft_Dashboard_Roles::uses_dashboard( $user ) ) {
			return Shyft_Dashboard_Warmup::get_dashboard_entry_url( $user );
		}

		return $redirect_to;
	}

	/**
	 * Blocks wp-admin access for customers (except AJAX).
	 */
	public static function block_wp_admin_for_kunde(): void {
		if ( ! Shyft_Dashboard_Roles::is_kunde() ) {
			return;
		}

		if ( wp_doing_ajax() ) {
			return;
		}

		if ( self::is_elementor_submissions_admin_request() ) {
			return;
		}

		wp_safe_redirect( Shyft_Dashboard_Warmup::get_dashboard_entry_url() );
		exit;
	}

	/**
	 * Whether the current request is the Elementor form submissions admin screen.
	 */
	private static function is_elementor_submissions_admin_request(): bool {
		if ( ! is_admin() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';

		return 'e-form-submissions' === $page;
	}

	/**
	 * Hides the admin bar for customers on the frontend.
	 *
	 * @param bool $show Whether to show the admin bar.
	 */
	public static function hide_admin_bar_for_kunde( bool $show ): bool {
		if ( Shyft_Dashboard_Roles::is_kunde() ) {
			return false;
		}

		return $show;
	}

	/**
	 * Prints dashboard styles in the document head.
	 */
	public static function print_head_assets(): void {
		self::ensure_dashboard_assets_enqueued();

		if ( wp_style_is( 'shyft-dashboard-fonts', 'registered' ) || wp_style_is( 'shyft-dashboard-fonts', 'enqueued' ) ) {
			wp_print_styles( array( 'shyft-dashboard-fonts', 'shyft-dashboard' ) );
			return;
		}

		$fonts_url = 'https://fonts.googleapis.com/css2?family=Anton&family=Roboto:wght@300;400;500;600;700&display=swap';
		$css_url   = SHYFT_DASHBOARD_URL . 'assets/css/dashboard.css';

		printf(
			'<link rel="stylesheet" href="%1$s" />' . "\n" . '<link rel="stylesheet" href="%2$s?ver=%3$s" />' . "\n",
			esc_url( $fonts_url ),
			esc_url( $css_url ),
			esc_attr( SHYFT_DASHBOARD_VERSION )
		);
	}

	/**
	 * Prints dashboard scripts before closing body.
	 */
	public static function print_footer_assets(): void {
		self::ensure_dashboard_assets_enqueued();

		if ( wp_script_is( 'shyft-dashboard', 'registered' ) || wp_script_is( 'shyft-dashboard', 'enqueued' ) ) {
			wp_print_footer_scripts( array( 'shyft-dashboard' ) );
			return;
		}

		$dashboard_url = SHYFT_DASHBOARD_URL . 'assets/js/dashboard.js';

		printf(
			'<script src="%1$s?ver=%2$s"></script>' . "\n",
			esc_url( $dashboard_url ),
			esc_attr( SHYFT_DASHBOARD_VERSION )
		);

		printf(
			'<script>var shyftDashboard = %s;</script>' . "\n",
			wp_json_encode(
				array(
					'theme' => array(
						'storageKey' => 'shyft_dashboard_theme',
					),
				)
			)
		);
	}

	/**
	 * Enqueues dashboard assets and renders the template.
	 */
	private static function render_dashboard(): void {
		$current_user      = wp_get_current_user();
		$logo_url          = Shyft_Dashboard_Settings::get_logo_url();
		$show_website_link = Shyft_Dashboard_Roles::can_edit_website( $current_user );
		$website_url       = home_url( '/' );
		$logout_url        = wp_logout_url( home_url( '/' ) );
		$form_action       = admin_url( 'admin-post.php' );
		$period_days       = Shyft_Dashboard_Period::get_days();
		$period_label      = Shyft_Dashboard_Period::get_label();

		$new_leads_count       = 0;
		$recent_leads          = array();
		$status                = array(
			'ssl'     => false,
			'updates' => 0,
			'php'     => PHP_VERSION,
			'backup'  => false,
		);
		$analytics             = array( 'available' => false );
		$matomo_stats_url      = home_url( '/wp-content/plugins/matomo/app/index.php' );
		$recent_plugin_updates = array();
		$categories            = array();
		$flash                 = null;
		$latest_activities     = array();

		try {
			$leads           = new Shyft_Dashboard_Leads();
			$site_status     = new Shyft_Dashboard_Site_Status();
			$matomo          = new Shyft_Dashboard_Matomo( $period_days );
			$plugin_updates  = new Shyft_Dashboard_Plugin_Updates();
			$change_req      = new Shyft_Dashboard_Change_Request();
			$recent_activity = new Shyft_Dashboard_Recent_Activity();

			$new_leads_count       = $leads->count_new_submissions();
			$recent_leads          = $leads->get_recent_submissions( 5 );
			$status                = $site_status->get_status();
			$analytics             = $matomo->get_analytics_data();
			$matomo_stats_url      = $matomo->get_stats_url();
			$recent_plugin_updates = $plugin_updates->get_recent_updates( 5 );
			$categories            = $change_req->get_categories();
			$flash                 = $change_req->get_flash_message();
			$latest_activities     = $recent_activity->get_display_activities();
		} catch ( Throwable $exception ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SHYFT Dashboard render error: ' . $exception->getMessage() );
			}
		}

		$template = SHYFT_DASHBOARD_PATH . 'templates/dashboard.php';

		if ( ! file_exists( $template ) ) {
			wp_die( esc_html__( 'Dashboard-Vorlage nicht gefunden.', 'shyft-dashboard' ) );
		}

		include $template;
	}

	/**
	 * Enqueues dashboard assets and runs wp_enqueue_scripts once (theme hooks never fire on this route).
	 */
	private static function ensure_dashboard_assets_enqueued(): void {
		self::enqueue_dashboard_assets();

		if ( ! did_action( 'wp_enqueue_scripts' ) ) {
			do_action( 'wp_enqueue_scripts' );
		}
	}

	/**
	 * Enqueues CSS/JS only on the dashboard page.
	 */
	public static function enqueue_dashboard_assets(): void {
		if ( ! self::is_dashboard_request() ) {
			return;
		}

		wp_enqueue_style(
			'shyft-dashboard-fonts',
			'https://fonts.googleapis.com/css2?family=Anton&family=Roboto:wght@300;400;500;600;700&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'shyft-dashboard',
			SHYFT_DASHBOARD_URL . 'assets/css/dashboard.css',
			array( 'shyft-dashboard-fonts' ),
			SHYFT_DASHBOARD_VERSION
		);

		wp_enqueue_script(
			'shyft-dashboard',
			SHYFT_DASHBOARD_URL . 'assets/js/dashboard.js',
			array(),
			SHYFT_DASHBOARD_VERSION,
			true
		);

		wp_localize_script(
			'shyft-dashboard',
			'shyftDashboard',
			array(
				'theme' => array(
					'storageKey' => 'shyft_dashboard_theme',
				),
			)
		);
	}

	/**
	 * Removes theme and unrelated WordPress assets on the dashboard route.
	 */
	public static function isolate_dashboard_assets(): void {
		if ( ! self::is_dashboard_request() ) {
			return;
		}

		global $wp_styles, $wp_scripts;

		$allowed_styles = array(
			'shyft-dashboard-fonts',
			'shyft-dashboard',
		);

		$allowed_scripts = array(
			'shyft-dashboard',
		);

		if ( $wp_styles instanceof WP_Dependencies ) {
			$style_queue = (array) $wp_styles->queue;

			foreach ( $style_queue as $handle ) {
				if ( ! in_array( $handle, $allowed_styles, true ) ) {
					wp_dequeue_style( $handle );
					wp_deregister_style( $handle );
				}
			}
		}

		if ( $wp_scripts instanceof WP_Dependencies ) {
			$script_queue = (array) $wp_scripts->queue;

			foreach ( $script_queue as $handle ) {
				if ( ! in_array( $handle, $allowed_scripts, true ) ) {
					wp_dequeue_script( $handle );
					wp_deregister_script( $handle );
				}
			}
		}
	}
}
