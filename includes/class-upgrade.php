<?php
/**
 * Runs activation tasks after plugin updates (without manual re-activation).
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ensures rewrite rules, roles and caches are refreshed after each version change.
 */
final class Shyft_Dashboard_Upgrade {

	public const VERSION_OPTION = 'shyft_dashboard_installed_version';
	public const PENDING_OPTION = 'shyft_dashboard_pending_upgrade';

	/**
	 * Registers upgrade hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'maybe_run' ), 99 );
		add_action( 'upgrader_process_complete', array( self::class, 'on_upgrader_complete' ), 10, 2 );
	}

	/**
	 * Runs pending upgrade tasks when the plugin version changed.
	 */
	public static function maybe_run(): void {
		$pending = (bool) get_option( self::PENDING_OPTION, false );

		if ( ! $pending && self::get_stored_version() === SHYFT_DASHBOARD_VERSION ) {
			return;
		}

		self::run();
		self::store_version();
		delete_option( self::PENDING_OPTION );
	}

	/**
	 * Schedules upgrade tasks immediately after this plugin was updated via WordPress.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $options  Upgrade context.
	 */
	public static function on_upgrader_complete( $upgrader, array $options ): void {
		unset( $upgrader );

		if ( ( $options['action'] ?? '' ) !== 'update' || ( $options['type'] ?? '' ) !== 'plugin' ) {
			return;
		}

		$plugins = $options['plugins'] ?? array();

		if ( ! is_array( $plugins ) || ! Shyft_Dashboard_Plugin_Folder::is_our_plugin_in_list( $plugins ) ) {
			return;
		}

		Shyft_Dashboard_Plugin_Folder::normalize_to_canonical();

		update_option( self::PENDING_OPTION, '1', false );

		// Falls der Upgrader noch vor init läuft, direkt ausführen.
		if ( did_action( 'init' ) ) {
			self::maybe_run();
		}
	}

	/**
	 * Same steps as plugin activation — required after ZIP updates.
	 */
	public static function run(): void {
		Shyft_Dashboard_Roles::create_role();
		Shyft_Dashboard_Routing::add_rewrite_rules();
		Shyft_Dashboard_Warmup::add_rewrite_rules();
		flush_rewrite_rules( false );

		update_option( 'shyft_dashboard_rewrite_version', SHYFT_DASHBOARD_VERSION, false );

		Shyft_Dashboard_Matomo::clear_cache();
		Shyft_Dashboard_Updater::clear_update_cache();

		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			wp_clean_plugins_cache( true );
		}
	}

	/**
	 * Returns the last stored installed version.
	 */
	private static function get_stored_version(): string {
		return (string) get_option( self::VERSION_OPTION, '' );
	}

	/**
	 * Persists the current plugin version after a successful upgrade.
	 */
	private static function store_version(): void {
		update_option( self::VERSION_OPTION, SHYFT_DASHBOARD_VERSION, false );
	}
}
