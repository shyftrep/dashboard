<?php
/**
 * Main plugin loader.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once SHYFT_DASHBOARD_PATH . 'includes/class-plugin-folder.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-dashboard-request.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-cache-compat.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-period.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-roles.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-routing.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-warmup.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-leads.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-site-status.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-matomo.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-plugin-updates.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-change-request.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-tasks.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-recent-activity.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-google-reviews.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-google-reviews-display.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-elementor-reviews.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-elementor-offers.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-elementor-buttons.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-offers.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-offers-display.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-buttons.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-buttons-display.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-settings.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-updater.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-upgrade.php';

/**
 * Registers hooks and coordinates plugin modules.
 */
final class Shyft_Dashboard {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Returns the singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		register_activation_hook( SHYFT_DASHBOARD_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( SHYFT_DASHBOARD_FILE, array( $this, 'deactivate' ) );

		Shyft_Dashboard_Plugin_Folder::register();
		Shyft_Dashboard_Upgrade::register();

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Loads the plugin text domain.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'shyft-dashboard',
			false,
			dirname( SHYFT_DASHBOARD_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initializes plugin modules.
	 */
	public function init(): void {
		Shyft_Dashboard_Roles::register();
		Shyft_Dashboard_Cache_Compat::register();
		Shyft_Dashboard_Routing::register();
		Shyft_Dashboard_Warmup::register();
		Shyft_Dashboard_Leads::register();
		Shyft_Dashboard_Site_Status::register();
		Shyft_Dashboard_Matomo::register();
		Shyft_Dashboard_Plugin_Updates::register();
		Shyft_Dashboard_Change_Request::register();
		Shyft_Dashboard_Tasks::register();
		Shyft_Dashboard_Recent_Activity::register();
		Shyft_Dashboard_Google_Reviews::register();
		Shyft_Dashboard_Google_Reviews_Display::register();
		Shyft_Dashboard_Elementor_Reviews::register();
		Shyft_Dashboard_Elementor_Offers::register();
		Shyft_Dashboard_Elementor_Buttons::register();
		Shyft_Dashboard_Offers::register();
		Shyft_Dashboard_Offers_Display::register();
		Shyft_Dashboard_Buttons::register();
		Shyft_Dashboard_Buttons_Display::register();
		Shyft_Dashboard_Settings::register();
		Shyft_Dashboard_Updater::register();
	}

	/**
	 * Plugin activation callback.
	 */
	public function activate(): void {
		update_option( Shyft_Dashboard_Upgrade::PENDING_OPTION, '1', false );
		delete_option( Shyft_Dashboard_Upgrade::VERSION_OPTION );

		Shyft_Dashboard_Upgrade::run();
		Shyft_Dashboard_Google_Reviews::maybe_schedule_cron();
		Shyft_Dashboard_Offers::ensure_capabilities();
		Shyft_Dashboard_Buttons::ensure_capabilities();
		update_option( Shyft_Dashboard_Upgrade::VERSION_OPTION, SHYFT_DASHBOARD_VERSION, false );
		delete_option( Shyft_Dashboard_Upgrade::PENDING_OPTION );
	}

	/**
	 * Plugin deactivation callback.
	 */
	public function deactivate(): void {
		Shyft_Dashboard_Google_Reviews::unschedule_cron();
		flush_rewrite_rules();
	}
}
