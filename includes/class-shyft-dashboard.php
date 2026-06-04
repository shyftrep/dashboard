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

require_once SHYFT_DASHBOARD_PATH . 'includes/class-roles.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-routing.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-leads.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-site-status.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-matomo.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-plugin-updates.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-change-request.php';
require_once SHYFT_DASHBOARD_PATH . 'includes/class-recent-activity.php';
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
		Shyft_Dashboard_Routing::register();
		Shyft_Dashboard_Leads::register();
		Shyft_Dashboard_Site_Status::register();
		Shyft_Dashboard_Matomo::register();
		Shyft_Dashboard_Plugin_Updates::register();
		Shyft_Dashboard_Change_Request::register();
		Shyft_Dashboard_Recent_Activity::register();
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
		update_option( Shyft_Dashboard_Upgrade::VERSION_OPTION, SHYFT_DASHBOARD_VERSION, false );
		delete_option( Shyft_Dashboard_Upgrade::PENDING_OPTION );
	}

	/**
	 * Plugin deactivation callback.
	 */
	public function deactivate(): void {
		flush_rewrite_rules();
	}
}
