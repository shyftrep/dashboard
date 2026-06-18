<?php
/**
 * Elementor widget for customer offers.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor widget: clicklabs Angebot.
 */
final class Shyft_Dashboard_Elementor_Offers_Widget extends \Elementor\Widget_Base {

	public function get_name(): string {
		return 'clicklabs_angebot';
	}

	public function get_title(): string {
		return __( 'Angebot (clicklabs)', 'shyft-dashboard' );
	}

	public function get_icon(): string {
		return 'eicon-price-table';
	}

	/**
	 * @return list<string>
	 */
	public function get_categories(): array {
		return array( 'general' );
	}

	/**
	 * @return list<string>
	 */
	public function get_keywords(): array {
		return array( 'angebot', 'offer', 'promo', 'clicklabs', 'shyft' );
	}

	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Anzeige', 'shyft-dashboard' ),
			)
		);

		$this->add_control(
			'info',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => __( 'Angebote werden im SHYFT Dashboard unter „Angebote“ verwaltet. Aktive Standard-Angebote oder laufende zeitliche Angebote erscheinen hier automatisch.', 'shyft-dashboard' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->end_controls_section();
	}

	protected function render(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in display class.
		echo Shyft_Dashboard_Offers_Display::render();
	}
}
