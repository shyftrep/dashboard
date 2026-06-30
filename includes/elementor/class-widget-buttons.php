<?php
/**
 * Elementor widget for customer buy buttons.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor widget: clicklabs Kauf-Button.
 */
final class Shyft_Dashboard_Elementor_Buttons_Widget extends \Elementor\Widget_Base {

	public function get_name(): string {
		return 'clicklabs_button';
	}

	public function get_title(): string {
		return __( 'Kauf-Button (clicklabs)', 'shyft-dashboard' );
	}

	public function get_icon(): string {
		return 'eicon-button';
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
		return array( 'button', 'kaufen', 'cta', 'clicklabs', 'shyft' );
	}

	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Button', 'shyft-dashboard' ),
			)
		);

		$this->add_control(
			'button_id',
			array(
				'label'   => __( 'Button auswählen', 'shyft-dashboard' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => self::get_button_options(),
				'default' => '',
			)
		);

		$this->add_control(
			'info',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => __( 'Buttons werden im SHYFT Dashboard unter „Buttons“ verwaltet. Alternativ Shortcode: [clicklabs_button id="1"]', 'shyft-dashboard' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * @return array<string, string>
	 */
	private static function get_button_options(): array {
		$options = array(
			'' => __( '— Button wählen —', 'shyft-dashboard' ),
		);

		foreach ( Shyft_Dashboard_Buttons::get_all_buttons() as $button ) {
			$button_id = (int) ( $button['id'] ?? 0 );

			if ( $button_id <= 0 ) {
				continue;
			}

			$label = (string) ( $button['text'] ?? '' );

			if ( empty( $button['active'] ) ) {
				$label .= ' (' . __( 'inaktiv', 'shyft-dashboard' ) . ')';
			}

			$options[ (string) $button_id ] = $label;
		}

		return $options;
	}

	protected function render(): void {
		$button_id = (int) ( $this->get_settings_for_display( 'button_id' ) ?? 0 );

		if ( $button_id <= 0 ) {
			return;
		}

		$button = Shyft_Dashboard_Buttons::get_public_button( $button_id );

		if ( null === $button && class_exists( '\Elementor\Plugin' ) ) {
			$elementor = \Elementor\Plugin::$instance;

			if ( isset( $elementor->editor ) && $elementor->editor->is_edit_mode() ) {
				$button = Shyft_Dashboard_Buttons::get_button( $button_id );
			}
		}

		if ( null === $button ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in display class.
		echo Shyft_Dashboard_Buttons_Display::render_button( $button );
	}
}
