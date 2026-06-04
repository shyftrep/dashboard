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

	/**
	 * Registers the update checker after WordPress loads plugins.
	 */
	public static function register(): void {
		add_action( 'plugins_loaded', array( self::class, 'init' ), 20 );
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

		$update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			self::GITHUB_REPO_URL,
			SHYFT_DASHBOARD_FILE,
			'shyft-dashboard'
		);

		$token = Shyft_Dashboard_Settings::get_github_token();

		if ( '' !== $token ) {
			$update_checker->setAuthentication( $token );
		}

		$update_checker->getVcsApi()->enableReleaseAssets();
	}
}
