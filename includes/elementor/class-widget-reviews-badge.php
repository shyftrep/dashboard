<?php
/**
 * Elementor widget for compact Google Reviews badge.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor widget: compact Google rating badge with optional extra text.
 */
final class Shyft_Dashboard_Elementor_Reviews_Badge_Widget extends \Elementor\Widget_Base {

	public function get_name(): string {
		return 'clicklabs_google_reviews_badge';
	}

	public function get_title(): string {
		return __( 'Google Bewertungs-Badge (clicklabs)', 'shyft-dashboard' );
	}

	public function get_icon(): string {
		return 'eicon-rating';
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
		return array( 'google', 'reviews', 'bewertungen', 'badge', 'sterne', 'clicklabs', 'shyft' );
	}

	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Inhalt', 'shyft-dashboard' ),
			)
		);

		$this->add_control(
			'style',
			array(
				'label'   => __( 'Badge-Stil', 'shyft-dashboard' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'inline',
				'options' => array(
					'inline' => __( 'Kompakt (eine Zeile)', 'shyft-dashboard' ),
					'card'   => __( 'Karte (Trust-Badge)', 'shyft-dashboard' ),
				),
			)
		);

		$this->add_control(
			'extra_text',
			array(
				'label'       => __( 'Zusatztext', 'shyft-dashboard' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => __( '+450 zufriedene Kunden', 'shyft-dashboard' ),
				'label_block' => true,
				'condition'   => array(
					'style' => 'inline',
				),
			)
		);

		$this->add_control(
			'title_text',
			array(
				'label'       => __( 'Titel (fett)', 'shyft-dashboard' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'default'     => '',
				'placeholder' => "Bestbewerteter\nService 2026",
				'rows'        => 3,
				'label_block' => true,
				'condition'   => array(
					'style' => 'card',
				),
			)
		);

		$this->add_control(
			'subtitle_text',
			array(
				'label'       => __( 'Unterzeile (klein)', 'shyft-dashboard' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => __( 'verifiziert von: Google', 'shyft-dashboard' ),
				'label_block' => true,
				'condition'   => array(
					'style' => 'card',
				),
			)
		);

		$this->add_control(
			'link',
			array(
				'label'        => __( 'Mit Google verlinken', 'shyft-dashboard' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => '',
				'return_value' => 'yes',
			)
		);

		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in display class.
		echo Shyft_Dashboard_Google_Reviews_Display::render_badge(
			array(
				'style'         => (string) ( $settings['style'] ?? 'inline' ),
				'extra_text'    => (string) ( $settings['extra_text'] ?? '' ),
				'title_text'    => (string) ( $settings['title_text'] ?? '' ),
				'subtitle_text' => (string) ( $settings['subtitle_text'] ?? '' ),
				'link'          => ( $settings['link'] ?? '' ) === 'yes',
			)
		);
	}
}
