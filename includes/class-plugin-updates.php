<?php
/**
 * Logs and displays recently updated plugins.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks plugin updates via the WordPress upgrader hook.
 */
final class Shyft_Dashboard_Plugin_Updates {

	private const OPTION_KEY = 'shyft_dashboard_plugin_updates';
	private const MAX_LOG      = 20;

	/**
	 * Registers update logging hooks.
	 */
	public static function register(): void {
		add_action( 'upgrader_process_complete', array( self::class, 'handle_upgrade' ), 10, 2 );
	}

	/**
	 * Records plugin updates after WordPress finishes upgrading.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $data     Upgrade context.
	 */
	public static function handle_upgrade( $upgrader, array $data ): void {
		if ( empty( $data['type'] ) || 'plugin' !== $data['type'] ) {
			return;
		}

		if ( empty( $data['action'] ) || 'update' !== $data['action'] ) {
			return;
		}

		$plugin_files = array();

		if ( ! empty( $data['plugins'] ) && is_array( $data['plugins'] ) ) {
			$plugin_files = $data['plugins'];
		} elseif ( ! empty( $data['plugin'] ) && is_string( $data['plugin'] ) ) {
			$plugin_files = array( $data['plugin'] );
		}

		if ( empty( $plugin_files ) ) {
			return;
		}

		$instance = new self();

		foreach ( $plugin_files as $plugin_file ) {
			if ( ! is_string( $plugin_file ) || '' === $plugin_file ) {
				continue;
			}

			$instance->log_plugin_update( $plugin_file );
		}

		delete_transient( 'shyft_dashboard_site_status_v2' );
	}

	/**
	 * Returns the most recent plugin updates for display.
	 *
	 * @param int $limit Maximum number of entries.
	 * @return array<int, array{name: string, version: string, date: string, date_raw: string}>
	 */
	public function get_recent_updates( int $limit = 5 ): array {
		$log = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $log ) || empty( $log ) ) {
			$log = $this->bootstrap_from_filemtime();
		}

		$log = $this->normalize_log( $log );

		usort(
			$log,
			static function ( array $a, array $b ): int {
				return (int) ( $b['updated_at'] ?? 0 ) <=> (int) ( $a['updated_at'] ?? 0 );
			}
		);

		$recent = array_slice( $log, 0, max( 1, $limit ) );

		return array_map( array( $this, 'format_for_display' ), $recent );
	}

	/**
	 * Adds or refreshes a plugin entry in the update log.
	 *
	 * @param string $plugin_file Plugin basename.
	 */
	private function log_plugin_update( string $plugin_file ): void {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();

		if ( empty( $plugins[ $plugin_file ] ) ) {
			return;
		}

		$plugin = $plugins[ $plugin_file ];
		$log    = $this->normalize_log( get_option( self::OPTION_KEY, array() ) );
		$now    = time();

		$entry = array(
			'file'       => $plugin_file,
			'name'       => (string) ( $plugin['Name'] ?? $plugin_file ),
			'version'    => (string) ( $plugin['Version'] ?? '' ),
			'updated_at' => $now,
		);

		$log = array_values(
			array_filter(
				$log,
				static function ( array $item ) use ( $plugin_file ): bool {
					return ( $item['file'] ?? '' ) !== $plugin_file;
				}
			)
		);

		array_unshift( $log, $entry );
		$log = array_slice( $log, 0, self::MAX_LOG );

		update_option( self::OPTION_KEY, $log, false );
	}

	/**
	 * Seeds the log from plugin file modification times when no history exists yet.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function bootstrap_from_filemtime(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		$entries = array();

		foreach ( $plugins as $file => $data ) {
			$path = WP_PLUGIN_DIR . '/' . $file;

			if ( ! is_readable( $path ) ) {
				continue;
			}

			$mtime = filemtime( $path );

			if ( false === $mtime ) {
				continue;
			}

			$entries[] = array(
				'file'       => $file,
				'name'       => (string) ( $data['Name'] ?? $file ),
				'version'    => (string) ( $data['Version'] ?? '' ),
				'updated_at' => (int) $mtime,
			);
		}

		usort(
			$entries,
			static function ( array $a, array $b ): int {
				return (int) ( $b['updated_at'] ?? 0 ) <=> (int) ( $a['updated_at'] ?? 0 );
			}
		);

		$entries = array_slice( $entries, 0, self::MAX_LOG );

		if ( ! empty( $entries ) ) {
			update_option( self::OPTION_KEY, $entries, false );
		}

		return $entries;
	}

	/**
	 * Ensures stored log entries have the expected shape.
	 *
	 * @param mixed $log Raw option value.
	 * @return array<int, array{file: string, name: string, version: string, updated_at: int}>
	 */
	private function normalize_log( $log ): array {
		if ( ! is_array( $log ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $log as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$file = sanitize_text_field( (string) ( $entry['file'] ?? '' ) );
			$name = sanitize_text_field( (string) ( $entry['name'] ?? '' ) );

			if ( '' === $file || '' === $name ) {
				continue;
			}

			$normalized[] = array(
				'file'       => $file,
				'name'       => $name,
				'version'    => sanitize_text_field( (string) ( $entry['version'] ?? '' ) ),
				'updated_at' => (int) ( $entry['updated_at'] ?? 0 ),
			);
		}

		return $normalized;
	}

	/**
	 * Formats a log entry for the dashboard template.
	 *
	 * @param array{file: string, name: string, version: string, updated_at: int} $entry Log entry.
	 * @return array{name: string, version: string, date: string, date_raw: string}
	 */
	private function format_for_display( array $entry ): array {
		$timestamp = (int) ( $entry['updated_at'] ?? 0 );

		if ( $timestamp <= 0 ) {
			$timestamp = time();
		}

		return array(
			'name'     => $entry['name'],
			'version'  => $entry['version'],
			'date'     => wp_date( 'd.m.Y', $timestamp ),
			'date_raw' => wp_date( 'Y-m-d', $timestamp ),
		);
	}
}
