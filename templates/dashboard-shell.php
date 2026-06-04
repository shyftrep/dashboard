<?php
/**
 * WordPress template shell for the dashboard (loaded via template_include).
 *
 * @package ShyftDashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

Shyft_Dashboard_Routing::render_dashboard_template();
