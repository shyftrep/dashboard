<?php
/**
 * GitHub release updates via Plugin Update Checker.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers automatic updates from https://github.com/shyftrep/dashboard releases.
 */
final class Shyft_Dashboard_Updater {

	public const GITHUB_REPO_URL = 'https://github.com/shyftrep/dashboard/';

	private const STATUS_TRANSIENT  = 'shyft_dashboard_update_check_status';
	private const RELEASE_TRANSIENT = 'shyft_dashboard_github_release';

	/** @var \YahnisElsts\PluginUpdateChecker\v5p7\Plugin\UpdateChecker|null */
	private static $update_checker = null;

	/**
	 * Registers hooks.
	 */
	public static function register(): void {
		add_action( 'plugins_loaded', array( self::class, 'init' ), 20 );
		add_filter( 'site_transient_update_plugins', array( self::class, 'inject_update_transient' ) );
		add_action( 'admin_post_shyft_dashboard_check_updates', array( self::class, 'handle_manual_check_request' ) );
	}

	/**
	 * Bootstraps Plugin Update Checker when the library is present.
	 */
	public static function init(): void {
		if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
			return;
		}

		$library = SHYFT_DASHBOARD_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';

		if ( ! is_readable( $library ) ) {
			return;
		}

		require_once $library;

		$slug = self::get_plugin_slug();

		self::$update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			self::GITHUB_REPO_URL,
			SHYFT_DASHBOARD_FILE,
			$slug
		);

		self::$update_checker->setCheckPeriod( 3 );

		$token = Shyft_Dashboard_Settings::get_github_token();

		if ( '' !== $token ) {
			self::$update_checker->setAuthentication( trim( $token ) );
		}

		// Release-ZIP von GitHub Actions (Ordner shyft-dashboard/ im Archiv).
		self::$update_checker->getVcsApi()->enableReleaseAssets( '/shyft-dashboard\.zip$/i' );
	}

	/**
	 * Ensures WordPress shows updates even when PUC state is stale (GitHub API fallback).
	 *
	 * @param mixed $transient Update plugins transient.
	 * @return mixed
	 */
	public static function inject_update_transient( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}

		$plugin_file    = plugin_basename( SHYFT_DASHBOARD_FILE );
		$release        = self::get_latest_release();
		$remote_version = is_array( $release ) ? (string) ( $release['version'] ?? '' ) : '';

		if ( '' === $remote_version || version_compare( $remote_version, SHYFT_DASHBOARD_VERSION, '<=' ) ) {
			return $transient;
		}

		if ( isset( $transient->response[ $plugin_file ] ) ) {
			return $transient;
		}

		$package = is_array( $release ) ? (string) ( $release['package'] ?? '' ) : '';

		if ( '' === $package ) {
			return $transient;
		}

		$transient->response[ $plugin_file ] = (object) array(
			'id'            => 'github.com/shyftrep/dashboard',
			'slug'          => self::get_plugin_slug(),
			'plugin'        => $plugin_file,
			'new_version'   => $remote_version,
			'url'           => self::GITHUB_REPO_URL,
			'package'       => $package,
			'icons'         => array(),
			'banners'       => array(),
			'banners_rtl'   => array(),
			'tested'        => '',
			'requires_php'  => '8.1',
			'compatibility' => new stdClass(),
		);

		return $transient;
	}

	/**
	 * Clears WordPress plugin update cache.
	 */
	public static function clear_update_cache(): void {
		delete_site_transient( 'update_plugins' );

		if ( null !== self::$update_checker ) {
			self::$update_checker->resetUpdateState();
		}
	}

	/**
	 * Plugin folder slug (must match wp-content/plugins/<slug>/).
	 */
	public static function get_plugin_slug(): string {
		$slug = dirname( SHYFT_DASHBOARD_BASENAME );

		return is_string( $slug ) && '' !== $slug && '.' !== $slug ? $slug : 'shyft-dashboard';
	}

	/**
	 * URL for PUC manual update check on the plugins screen.
	 */
	public static function get_manual_check_url(): string {
		if ( null === self::$update_checker ) {
			return '';
		}

		return wp_nonce_url(
			add_query_arg(
				array(
					'puc_check_for_updates' => 1,
					'puc_slug'              => self::$update_checker->slug,
				),
				self_admin_url( 'plugins.php' )
			),
			'puc_check_for_updates'
		);
	}

	/**
	 * Fetches and caches the latest GitHub release.
	 *
	 * @return array{version: string, package: string, tag: string}|null
	 */
	public static function get_latest_release(): ?array {
		$cached = get_transient( self::RELEASE_TRANSIENT );

		if ( is_array( $cached ) && ! empty( $cached['version'] ) ) {
			return $cached;
		}

		$url  = 'https://api.github.com/repos/shyftrep/dashboard/releases/latest';
		$args = array(
			'timeout' => 15,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'SHYFT-Dashboard-WordPress-Plugin',
			),
		);

		$token = Shyft_Dashboard_Settings::get_github_token();

		if ( '' !== $token ) {
			$args['headers']['Authorization'] = 'Bearer ' . trim( $token );
		}

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
			return null;
		}

		$tag     = (string) ( $body['tag_name'] ?? '' );
		$version = ltrim( $tag, 'v' );
		$package = '';

		if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				if ( ! is_array( $asset ) ) {
					continue;
				}

				$name = (string) ( $asset['name'] ?? '' );

				if ( 'shyft-dashboard.zip' === $name ) {
					$package = (string) ( $asset['browser_download_url'] ?? $asset['url'] ?? '' );
					break;
				}
			}
		}

		if ( '' === $package && ! empty( $body['zipball_url'] ) ) {
			$package = (string) $body['zipball_url'];
		}

		if ( '' === $version || '' === $package ) {
			return null;
		}

		$data = array(
			'version' => $version,
			'package' => $package,
			'tag'     => $tag,
		);

		set_transient( self::RELEASE_TRANSIENT, $data, 15 * MINUTE_IN_SECONDS );

		return $data;
	}

	/**
	 * Tests GitHub API access to the latest release.
	 *
	 * @return array{ok: bool, message: string, version: string}
	 */
	public static function test_github_connection(): array {
		$release = self::get_latest_release();

		if ( null === $release ) {
			delete_transient( self::RELEASE_TRANSIENT );

			$release = self::get_latest_release();
		}

		if ( null === $release ) {
			return array(
				'ok'      => false,
				'message' => __( 'Kein Release gefunden. Prüfe GitHub Actions und ob Tag vX.Y.Z veröffentlicht wurde.', 'shyft-dashboard' ),
				'version' => '',
			);
		}

		return array(
			'ok'      => true,
			'message' => __( 'Verbindung zu GitHub erfolgreich.', 'shyft-dashboard' ),
			'version' => (string) $release['version'],
		);
	}

	/**
	 * Runs an update check and stores the result for the settings page.
	 *
	 * @return array{ok: bool, message: string, remote_version: string}
	 */
	public static function run_update_check(): array {
		self::clear_update_cache();
		delete_transient( self::RELEASE_TRANSIENT );

		$release        = self::get_latest_release();
		$remote_version = is_array( $release ) ? (string) ( $release['version'] ?? '' ) : '';

		if ( null !== self::$update_checker ) {
			self::$update_checker->checkForUpdates();
		}

		wp_update_plugins();

		if ( '' !== $remote_version && version_compare( $remote_version, SHYFT_DASHBOARD_VERSION, '>' ) ) {
			$result = array(
				'ok'             => true,
				'message'        => sprintf(
					/* translators: 1: remote version, 2: installed version */
					__( 'Update verfügbar: %1$s (installiert: %2$s). Unter „Plugins“ sollte es jetzt sichtbar sein.', 'shyft-dashboard' ),
					$remote_version,
					SHYFT_DASHBOARD_VERSION
				),
				'remote_version' => $remote_version,
			);
			set_transient( self::STATUS_TRANSIENT, $result, 5 * MINUTE_IN_SECONDS );

			return $result;
		}

		$result = array(
			'ok'             => true,
			'message'        => sprintf(
				/* translators: 1: github version, 2: installed version */
				__( 'Kein neues Update. GitHub: %1$s, installiert: %2$s.', 'shyft-dashboard' ),
				$remote_version ?: '—',
				SHYFT_DASHBOARD_VERSION
			),
			'remote_version' => $remote_version,
		);
		set_transient( self::STATUS_TRANSIENT, $result, 5 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Returns the last stored update check result.
	 *
	 * @return array{ok: bool, message: string, remote_version: string}|null
	 */
	public static function get_last_check_status(): ?array {
		$status = get_transient( self::STATUS_TRANSIENT );

		return is_array( $status ) ? $status : null;
	}

	/**
	 * Admin handler for the settings-page update check button.
	 */
	public static function handle_manual_check_request(): void {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'shyft-dashboard' ) );
		}

		check_admin_referer( 'shyft_dashboard_check_updates' );

		self::run_update_check();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                 => Shyft_Dashboard_Settings::PAGE_SLUG,
					'shyft_update_checked' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}
}
