<?php
/**
 * Elementor widget for Google Reviews.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor widget: clicklabs Google Reviews.
 */
final class Shyft_Dashboard_Elementor_Reviews_Widget extends \Elementor\Widget_Base {

	public function get_name(): string {
		return 'clicklabs_google_reviews';
	}

	public function get_title(): string {
		return __( 'Google Reviews (clicklabs)', 'shyft-dashboard' );
	}

	public function get_icon(): string {
		return 'eicon-review';
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
		return array( 'google', 'reviews', 'bewertungen', 'clicklabs', 'shyft' );
	}

	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Anzeige', 'shyft-dashboard' ),
			)
		);

		$this->add_control(
			'limit',
			array(
				'label'   => __( 'Anzahl Bewertungen', 'shyft-dashboard' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 5,
				'min'     => 1,
				'max'     => 10,
			)
		);

		$this->add_control(
			'slider',
			array(
				'label'        => __( 'Slider', 'shyft-dashboard' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'header',
			array(
				'label'        => __( 'Schnitt & Anzahl', 'shyft-dashboard' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'write_button',
			array(
				'label'        => __( 'Button „Jetzt bewerten“', 'shyft-dashboard' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'schema',
			array(
				'label'        => __( 'Schema.org Markup', 'shyft-dashboard' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in display class.
		echo Shyft_Dashboard_Google_Reviews_Display::render(
			array(
				'limit'        => (int) ( $settings['limit'] ?? 5 ),
				'slider'       => ( $settings['slider'] ?? 'yes' ) === 'yes',
				'header'       => ( $settings['header'] ?? 'yes' ) === 'yes',
				'write_button' => ( $settings['write_button'] ?? 'yes' ) === 'yes',
				'schema'       => ( $settings['schema'] ?? 'yes' ) === 'yes',
			)
		);
	}
}
