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

	private const STATUS_TRANSIENT = 'shyft_dashboard_update_check_status';

	/** @var \YahnisElsts\PluginUpdateChecker\v5p7\Plugin\UpdateChecker|null */
	private static $update_checker = null;

	/**
	 * Registers hooks.
	 */
	public static function register(): void {
		add_action( 'plugins_loaded', array( self::class, 'init' ), 20 );
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

		$token = Shyft_Dashboard_Settings::get_github_token();

		if ( '' !== $token ) {
			self::$update_checker->setAuthentication( trim( $token ) );
		}

		$vcs = self::$update_checker->getVcsApi();
		$vcs->enableReleaseAssets( '/\.zip$/i' );
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
	 * Tests GitHub API access to the latest release.
	 *
	 * @return array{ok: bool, message: string, version: string}
	 */
	public static function test_github_connection(): array {
		$url = 'https://api.github.com/repos/shyftrep/dashboard/releases/latest';
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
			return array(
				'ok'      => false,
				'message' => $response->get_error_message(),
				'version' => '',
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 404 === $code ) {
			$message = empty( $token )
				? __( 'Kein Release gefunden oder privates Repo ohne Token.', 'shyft-dashboard' )
				: __( 'Kein Release gefunden. Prüfe GitHub Actions und ob vX.Y.Z veröffentlicht wurde.', 'shyft-dashboard' );

			return array(
				'ok'      => false,
				'message' => $message,
				'version' => '',
			);
		}

		if ( $code < 200 || $code >= 300 ) {
			$api_message = is_array( $body ) && ! empty( $body['message'] )
				? (string) $body['message']
				: sprintf(
					/* translators: %d: HTTP status code */
					__( 'GitHub API Fehler (HTTP %d).', 'shyft-dashboard' ),
					$code
				);

			return array(
				'ok'      => false,
				'message' => $api_message,
				'version' => '',
			);
		}

		$tag = is_array( $body ) && ! empty( $body['tag_name'] ) ? (string) $body['tag_name'] : '';

		return array(
			'ok'      => true,
			'message' => __( 'Verbindung zu GitHub erfolgreich.', 'shyft-dashboard' ),
			'version' => ltrim( $tag, 'v' ),
		);
	}

	/**
	 * Runs an update check and stores the result for the settings page.
	 *
	 * @return array{ok: bool, message: string, remote_version: string}
	 */
	public static function run_update_check(): array {
		if ( null === self::$update_checker ) {
			return array(
				'ok'             => false,
				'message'        => __( 'Update-Checker nicht geladen (vendor/ fehlt?). Bitte Plugin neu installieren.', 'shyft-dashboard' ),
				'remote_version' => '',
			);
		}

		$update = self::$update_checker->checkForUpdates();
		$errors = self::$update_checker->getLastRequestApiErrors();

		if ( null !== $update && ! empty( $update->version ) ) {
			$result = array(
				'ok'             => true,
				'message'        => sprintf(
					/* translators: 1: remote version, 2: installed version */
					__( 'Update verfügbar: %1$s (installiert: %2$s).', 'shyft-dashboard' ),
					(string) $update->version,
					SHYFT_DASHBOARD_VERSION
				),
				'remote_version' => (string) $update->version,
			);
			set_transient( self::STATUS_TRANSIENT, $result, 5 * MINUTE_IN_SECONDS );

			return $result;
		}

		if ( ! empty( $errors ) ) {
			$wp_error = $errors[0]['error'] ?? null;
			$message  = $wp_error instanceof WP_Error
				? $wp_error->get_error_message()
				: __( 'Unbekannter Fehler bei der Update-Prüfung.', 'shyft-dashboard' );

			$result = array(
				'ok'             => false,
				'message'        => $message,
				'remote_version' => '',
			);
			set_transient( self::STATUS_TRANSIENT, $result, 5 * MINUTE_IN_SECONDS );

			return $result;
		}

		$github = self::test_github_connection();
		$remote = $github['version'] ?? '';

		if ( '' !== $remote && version_compare( $remote, SHYFT_DASHBOARD_VERSION, '>' ) ) {
			$result = array(
				'ok'             => true,
				'message'        => sprintf(
					/* translators: 1: remote version, 2: installed version */
					__( 'Neuere Version auf GitHub: %1$s (installiert: %2$s). Seite „Plugins“ neu laden.', 'shyft-dashboard' ),
					$remote,
					SHYFT_DASHBOARD_VERSION
				),
				'remote_version' => $remote,
			);
		} else {
			$result = array(
				'ok'             => true,
				'message'        => sprintf(
					/* translators: %s: installed version */
					__( 'Kein neues Update. Installiert: %s.', 'shyft-dashboard' ),
					SHYFT_DASHBOARD_VERSION
				),
				'remote_version' => $remote,
			);
		}

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
