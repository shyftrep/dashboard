<?php
/**
 * Uninstall handler for SHYFT Dashboard.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-roles.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-google-reviews.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-offers.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-buttons.php';

Shyft_Dashboard_Roles::remove_role();

delete_option( 'shyft_dashboard_matomo_token' );
delete_option( 'shyft_dashboard_github_token' );
delete_option( 'shyft_dashboard_matomo_url' );
delete_option( 'shyft_dashboard_matomo_site_id' );
delete_option( 'shyft_dashboard_agency_email' );
delete_option( 'shyft_dashboard_logo_url' );
delete_option( 'shyft_dashboard_google_place_id' );
delete_option( 'shyft_dashboard_google_api_key' );
delete_option( 'shyft_dashboard_google_reviews_custom_css' );
delete_option( 'shyft_dashboard_offers_custom_css' );
delete_option( 'shyft_dashboard_buttons_custom_css' );
delete_option( Shyft_Dashboard_Google_Reviews::OPTION_DATA );
delete_option( Shyft_Dashboard_Google_Reviews::OPTION_LAST_SEEN );

Shyft_Dashboard_Offers::delete_all();
Shyft_Dashboard_Buttons::delete_all();

wp_clear_scheduled_hook( Shyft_Dashboard_Google_Reviews::CRON_HOOK );

delete_option( 'shyft_dashboard_plugin_updates' );

delete_transient( 'shyft_dashboard_site_status' );
delete_transient( 'shyft_dashboard_site_status_v2' );
delete_transient( 'shyft_dashboard_matomo_data' );
delete_transient( 'shyft_dashboard_seo_status_v2' );
delete_transient( 'shyft_dashboard_seo_status_v3' );
delete_transient( 'shyft_dashboard_seo_status' );
delete_transient( 'shyft_dashboard_activity_display' );
