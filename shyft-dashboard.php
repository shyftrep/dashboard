<?php
/**
 * Plugin Name:       SHYFT Dashboard
 * Plugin URI:        https://shyft.rocks
 * Description:       Gebrandetes Kunden-Dashboard unter /dashboard – Anfragen, Status, Matomo und Änderungswünsche.
 * Version:           1.0.3
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

define( 'SHYFT_DASHBOARD_VERSION', '1.0.3' );
define( 'SHYFT_DASHBOARD_FILE', __FILE__ );
define( 'SHYFT_DASHBOARD_PATH', plugin_dir_path( __FILE__ ) );
define( 'SHYFT_DASHBOARD_URL', plugin_dir_url( __FILE__ ) );
define( 'SHYFT_DASHBOARD_BASENAME', plugin_basename( __FILE__ ) );

require_once SHYFT_DASHBOARD_PATH . 'includes/class-shyft-dashboard.php';

/**
 * Bootstraps the plugin.
 *
 * @return Shyft_Dashboard
 */
function shyft_dashboard(): Shyft_Dashboard {
	return Shyft_Dashboard::instance();
}

shyft_dashboard();
