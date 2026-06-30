<?php
/**
 * Elementor integration for customer buy buttons.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the clicklabs button Elementor widget.
 */
final class Shyft_Dashboard_Elementor_Buttons {

	/**
	 * Registers Elementor hooks when Elementor is available.
	 */
	public static function register(): void {
		add_action( 'elementor/widgets/register', array( self::class, 'register_widget' ) );
	}

	/**
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public static function register_widget( $widgets_manager ): void {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		require_once SHYFT_DASHBOARD_PATH . 'includes/elementor/class-widget-buttons.php';

		$widgets_manager->register( new Shyft_Dashboard_Elementor_Buttons_Widget() );
	}
}
