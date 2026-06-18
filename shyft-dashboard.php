<?php
/**
 * Plugin Name:       SHYFT Dashboard
 * Plugin URI:        https://shyft.rocks
 * Description:       Gebrandetes Kunden-Dashboard unter /dashboard – Anfragen, Status, Matomo und Änderungswünsche.
 * Version:           2.2.4
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            SHYFT / clicklabs
 * Author URI:        https://shyft.rocks
 * Text Domain:       shyft-dashboard
 * Domain Path:       /languages
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SHYFT_DASHBOARD_VERSION', '2.2.4' );
define( 'SHYFT_DASHBOARD_FONTS_URL', 'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;500;600;700&family=Instrument+Serif:ital@0;1&display=swap' );
define( 'SHYFT_DASHBOARD_SLUG', 'shyft-dashboard' );
define( 'SHYFT_DASHBOARD_FILE', __FILE__ );
define( 'SHYFT_DASHBOARD_PATH', plugin_dir_path( __FILE__ ) );
define( 'SHYFT_DASHBOARD_URL', plugin_dir_url( __FILE__ ) );
define( 'SHYFT_DASHBOARD_BASENAME', plugin_basename( __FILE__ ) );

require_once SHYFT_DASHBOARD_PATH . 'includes/class-dashboard-request.php';
shyft_dashboard_send_early_headers();

require_once SHYFT_DASHBOARD_PATH . 'includes/class-shyft-dashboard.php';

/**
 * Sends HTML headers as early as possible on dashboard routes.
 */
function shyft_dashboard_send_early_headers(): void {
	if ( ! Shyft_Dashboard_Request::matches_uri() || headers_sent() ) {
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

	Shyft_Dashboard_Request::send_html_headers();
}

/**
 * Bootstraps the plugin.
 *
 * @return Shyft_Dashboard
 */
function shyft_dashboard(): Shyft_Dashboard {
	return Shyft_Dashboard::instance();
}

shyft_dashboard();
