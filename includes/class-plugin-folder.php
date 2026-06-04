<?php
/**
 * Ensures the plugin always lives in wp-content/plugins/shyft-dashboard/.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes legacy install folder names (e.g. 01_shyft-dashboard) after updates.
 */
final class Shyft_Dashboard_Plugin_Folder {

	public const SLUG = 'shyft-dashboard';

	public const PLUGIN_FILE = 'shyft-dashboard/shyft-dashboard.php';

	private const MIGRATE_OPTION = 'shyft_dashboard_migrate_folder';

	/** @var list<string> */
	private const LEGACY_SLUGS = array(
		'01_shyft-dashboard',
		'dashboard',
	);

	/**
	 * Registers folder normalization hooks.
	 */
	public static function register(): void {
		add_action( 'plugins_loaded', array( self::class, 'schedule_folder_migration' ), 2 );
		add_action( 'shutdown', array( self::class, 'run_scheduled_folder_migration' ), 0 );
		add_filter( 'upgrader_source_selection', array( self::class, 'revert_puc_legacy_folder_rename' ), 11, 3 );
	}

	/**
	 * Returns the folder name of the current installation.
	 */
	public static function get_installed_slug(): string {
		$slug = dirname( SHYFT_DASHBOARD_BASENAME );

		if ( ! is_string( $slug ) || '' === $slug || '.' === $slug ) {
			return self::SLUG;
		}

		return $slug;
	}

	/**
	 * Whether the plugin is installed outside the canonical folder.
	 */
	public static function is_legacy_install(): bool {
		return self::get_installed_slug() !== self::SLUG;
	}

	/**
	 * @return list<string> All known plugin basename paths.
	 */
	public static function get_plugin_basenames(): array {
		$basenames = array( self::PLUGIN_FILE );

		foreach ( self::LEGACY_SLUGS as $legacy_slug ) {
			$basenames[] = $legacy_slug . '/shyft-dashboard.php';
		}

		return $basenames;
	}

	/**
	 * @param list<string> $plugins Plugin basenames from the upgrader.
	 */
	public static function is_our_plugin_in_list( array $plugins ): bool {
		foreach ( $plugins as $plugin ) {
			if ( is_string( $plugin ) && in_array( $plugin, self::get_plugin_basenames(), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Queues a legacy-folder rename for shutdown (avoids breaking paths mid-request).
	 */
	public static function schedule_folder_migration(): void {
		if ( ! self::is_legacy_install() ) {
			delete_option( self::MIGRATE_OPTION );
			return;
		}

		update_option( self::MIGRATE_OPTION, '1', false );
	}

	/**
	 * Renames 01_shyft-dashboard → shyft-dashboard after the current request finishes.
	 */
	public static function run_scheduled_folder_migration(): void {
		if ( '1' !== get_option( self::MIGRATE_OPTION, '' ) ) {
			return;
		}

		if ( ! self::is_legacy_install() ) {
			delete_option( self::MIGRATE_OPTION );
			return;
		}

		if ( self::normalize_to_canonical() ) {
			delete_option( self::MIGRATE_OPTION );
		}
	}

	/**
	 * PUC renames the ZIP folder to the installed directory (e.g. 01_shyft-dashboard); undo that.
	 *
	 * @param string            $source        Source directory.
	 * @param string            $remote_source Remote source directory.
	 * @param WP_Upgrader|mixed $upgrader      Upgrader instance.
	 * @return string|WP_Error
	 */
	public static function revert_puc_legacy_folder_rename( $source, $remote_source, $upgrader ) {
		unset( $remote_source );

		if ( ! is_string( $source ) || '' === $source ) {
			return $source;
		}

		if ( ! self::is_updating_our_plugin( $upgrader ) ) {
			return $source;
		}

		$folder = basename( untrailingslashit( $source ) );

		if ( self::SLUG === $folder || ! in_array( $folder, self::LEGACY_SLUGS, true ) ) {
			return $source;
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return $source;
		}

		$canonical = trailingslashit( dirname( untrailingslashit( $source ) ) ) . self::SLUG;

		if ( $wp_filesystem->move( $source, $canonical, true ) ) {
			return $canonical;
		}

		return $source;
	}

	/**
	 * Moves the plugin into wp-content/plugins/shyft-dashboard/.
	 */
	public static function normalize_to_canonical(): bool {
		$installed_slug = self::get_installed_slug();

		if ( self::SLUG === $installed_slug ) {
			return true;
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return false;
		}

		$plugins_dir   = trailingslashit( WP_PLUGIN_DIR );
		$source        = $plugins_dir . $installed_slug;
		$destination   = $plugins_dir . self::SLUG;
		$source_exists = $wp_filesystem->exists( $source );
		$dest_exists   = $wp_filesystem->exists( $destination );

		if ( ! $source_exists ) {
			return false;
		}

		if ( $dest_exists ) {
			$copied = copy_dir( $source, $destination );

			if ( ! $copied ) {
				return false;
			}

			if ( ! $wp_filesystem->delete( $source, true ) ) {
				return false;
			}
		} elseif ( ! $wp_filesystem->move( $source, $destination, true ) ) {
			return false;
		}

		self::update_active_plugin_paths();

		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			wp_clean_plugins_cache( true );
		}

		return true;
	}

	/**
	 * Updates active_plugins entries to the canonical plugin path.
	 */
	private static function update_active_plugin_paths(): void {
		$canonical = self::PLUGIN_FILE;

		if ( ! is_multisite() ) {
			$active = get_option( 'active_plugins', array() );

			if ( ! is_array( $active ) ) {
				return;
			}

			$changed = false;

			foreach ( $active as $index => $plugin_path ) {
				if ( ! is_string( $plugin_path ) || ! self::is_shyft_dashboard_file( $plugin_path ) ) {
					continue;
				}

				if ( $plugin_path !== $canonical ) {
					$active[ $index ] = $canonical;
					$changed          = true;
				}
			}

			if ( $changed ) {
				update_option( 'active_plugins', array_values( array_unique( $active ) ) );
			}

			return;
		}

		$network_active = get_site_option( 'active_sitewide_plugins', array() );

		if ( ! is_array( $network_active ) ) {
			return;
		}

		$changed = false;

		foreach ( array_keys( $network_active ) as $plugin_path ) {
			if ( ! is_string( $plugin_path ) || ! self::is_shyft_dashboard_file( $plugin_path ) ) {
				continue;
			}

			if ( $plugin_path !== $canonical ) {
				$timestamp = $network_active[ $plugin_path ];
				unset( $network_active[ $plugin_path ] );
				$network_active[ $canonical ] = $timestamp;
				$changed                      = true;
			}
		}

		if ( $changed ) {
			update_site_option( 'active_sitewide_plugins', $network_active );
		}
	}

	/**
	 * @param WP_Upgrader|mixed $upgrader Upgrader instance.
	 */
	private static function is_updating_our_plugin( $upgrader ): bool {
		if ( ! is_object( $upgrader ) || ! isset( $upgrader->skin ) ) {
			return false;
		}

		$skin = $upgrader->skin;

		if ( isset( $skin->plugin ) && is_string( $skin->plugin ) && self::is_shyft_dashboard_file( $skin->plugin ) ) {
			return true;
		}

		if ( isset( $skin->options['plugin'] ) && is_string( $skin->options['plugin'] ) && self::is_shyft_dashboard_file( $skin->options['plugin'] ) ) {
			return true;
		}

		return false;
	}

	private static function is_shyft_dashboard_file( string $plugin_path ): bool {
		return str_ends_with( $plugin_path, '/shyft-dashboard.php' );
	}
}
