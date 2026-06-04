<?php
/**
 * Background dashboard cache warmup (once per day per user).
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preloads dashboard URLs in the browser so the first click uses a warm cache.
 */
final class Shyft_Dashboard_Warmup {

	public const QUERY_VAR     = 'shyft_dashboard_warmup';
	public const META_KEY      = 'shyft_dashboard_warmup_date';
	public const AJAX_ACTION   = 'shyft_dashboard_warmup_complete';
	public const NONCE_ACTION  = 'shyft_dashboard_warmup';

	/**
	 * Registers warmup hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( self::class, 'add_query_vars' ) );
		add_action( 'parse_request', array( self::class, 'prime_warmup_query' ) );
		add_action( 'template_redirect', array( self::class, 'maybe_prepare_warmup' ), PHP_INT_MIN );
		add_filter( 'template_include', array( self::class, 'filter_warmup_template' ), PHP_INT_MAX );
		add_action( 'wp_enqueue_scripts', array( self::class, 'maybe_enqueue_background_warmup' ), 20 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( self::class, 'handle_warmup_complete' ) );
	}

	/**
	 * Adds the warmup route (shown briefly after login when needed).
	 */
	public static function add_rewrite_rules(): void {
		add_rewrite_rule( '^dashboard-warmup/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * @param array<int, string> $vars Query vars.
	 * @return array<int, string>
	 */
	public static function add_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * @param WP $wp WordPress environment.
	 */
	public static function prime_warmup_query( WP $wp ): void {
		if ( self::matches_warmup_uri() ) {
			$wp->query_vars[ self::QUERY_VAR ] = '1';
		}
	}

	/**
	 * Warmup gate URL (login redirect target when a preload is due).
	 */
	public static function get_warmup_url(): string {
		return home_url( '/dashboard-warmup/' );
	}

	/**
	 * Dashboard URLs to preload (base + each period).
	 *
	 * @return list<string>
	 */
	public static function get_preload_urls(): array {
		$urls = array();

		foreach ( Shyft_Dashboard_Period::ALLOWED_DAYS as $days ) {
			$urls[] = Shyft_Dashboard_Period::get_dashboard_url( $days );
		}

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Whether the user still needs a daily preload.
	 *
	 * @param WP_User|null $user User object.
	 */
	public static function needs_warmup( ?WP_User $user = null ): bool {
		$user = $user ?? wp_get_current_user();

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}

		if ( ! Shyft_Dashboard_Roles::uses_dashboard( $user ) ) {
			return false;
		}

		$last = get_user_meta( $user->ID, self::META_KEY, true );

		return ! is_string( $last ) || $last !== self::get_today_key();
	}

	/**
	 * Marks today's preload as finished for the current user.
	 */
	public static function mark_warmup_complete( ?WP_User $user = null ): void {
		$user = $user ?? wp_get_current_user();

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return;
		}

		update_user_meta( $user->ID, self::META_KEY, self::get_today_key() );
	}

	/**
	 * Whether the current request is the warmup gate page.
	 */
	public static function is_warmup_request(): bool {
		$query = get_query_var( self::QUERY_VAR );

		if ( '1' === (string) $query || 1 === $query ) {
			return true;
		}

		return self::matches_warmup_uri();
	}

	/**
	 * Auth checks before rendering the warmup gate.
	 */
	public static function maybe_prepare_warmup(): void {
		if ( ! self::is_warmup_request() ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			auth_redirect();
			exit;
		}

		if ( ! Shyft_Dashboard_Roles::uses_dashboard() ) {
			wp_safe_redirect( Shyft_Dashboard_Routing::get_dashboard_url() );
			exit;
		}

		if ( ! self::needs_warmup() ) {
			wp_safe_redirect( Shyft_Dashboard_Routing::get_dashboard_url() );
			exit;
		}

		status_header( 200 );

		if ( $GLOBALS['wp_query'] instanceof WP_Query ) {
			$GLOBALS['wp_query']->is_404 = false;
		}
	}

	/**
	 * @param string $template Theme template path.
	 */
	public static function filter_warmup_template( string $template ): string {
		if ( ! self::is_warmup_request() ) {
			return $template;
		}

		$shell = SHYFT_DASHBOARD_PATH . 'templates/warmup-shell.php';

		return file_exists( $shell ) ? $shell : $template;
	}

	/**
	 * Renders the warmup gate (loading screen + preload script).
	 */
	public static function render_warmup_page(): void {
		include SHYFT_DASHBOARD_PATH . 'templates/warmup.php';
	}

	/**
	 * Silent background preload on other frontend pages (once per day).
	 */
	public static function maybe_enqueue_background_warmup(): void {
		if ( is_admin() || ! is_user_logged_in() ) {
			return;
		}

		if ( Shyft_Dashboard_Routing::is_dashboard_request() || self::is_warmup_request() ) {
			return;
		}

		if ( ! Shyft_Dashboard_Roles::uses_dashboard() || ! self::needs_warmup() ) {
			return;
		}

		wp_enqueue_script(
			'shyft-dashboard-warmup',
			SHYFT_DASHBOARD_URL . 'assets/js/dashboard-warmup.js',
			array(),
			SHYFT_DASHBOARD_VERSION,
			true
		);

		wp_localize_script(
			'shyft-dashboard-warmup',
			'shyftDashboardWarmup',
			self::get_script_config( 'silent' )
		);
	}

	/**
	 * @param string $mode "redirect" (login gate) or "silent" (background).
	 * @return array<string, mixed>
	 */
	public static function get_script_config( string $mode ): array {
		return array(
			'mode'        => $mode,
			'urls'        => self::get_preload_urls(),
			'redirectUrl' => Shyft_Dashboard_Routing::get_dashboard_url(),
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'action'      => self::AJAX_ACTION,
			'nonce'       => wp_create_nonce( self::NONCE_ACTION ),
		);
	}

	/**
	 * AJAX: records that today's dashboard URLs were preloaded.
	 */
	public static function handle_warmup_complete(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! is_user_logged_in() || ! Shyft_Dashboard_Roles::uses_dashboard() ) {
			wp_send_json_error( null, 403 );
		}

		self::mark_warmup_complete();
		wp_send_json_success();
	}

	/**
	 * Whether the request URI targets the warmup gate.
	 */
	public static function matches_warmup_uri(): bool {
		return 'dashboard-warmup' === Shyft_Dashboard_Request::get_path_segment();
	}

	/**
	 * Site-local date key for the once-per-day check.
	 */
	private static function get_today_key(): string {
		return wp_date( 'Y-m-d' );
	}
}
