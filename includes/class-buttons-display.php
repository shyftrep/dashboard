<?php
/**
 * Public CTA button display (shortcode + assets).
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders customer buy buttons on the website.
 */
final class Shyft_Dashboard_Buttons_Display {

	private static bool $assets_enqueued = false;

	/** @var array<int, bool> */
	private static array $inline_styles_added = array();

	public static function register(): void {
		self::register_shortcodes();
		add_action( 'wp_enqueue_scripts', array( self::class, 'register_assets' ) );
		add_filter( 'elementor/widget/render_content', array( self::class, 'filter_elementor_widget_content' ), 10, 2 );
	}

	public static function register_shortcodes(): void {
		add_shortcode( 'clicklabs_button', array( self::class, 'render_shortcode' ) );
		add_shortcode( 'shyft_button', array( self::class, 'render_shortcode' ) );
	}

	public static function register_assets(): void {
		wp_register_style(
			'shyft-button-cta',
			SHYFT_DASHBOARD_URL . 'assets/css/button.css',
			array(),
			SHYFT_DASHBOARD_VERSION
		);
	}

	/**
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public static function render_shortcode( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'id' => '0',
			),
			is_array( $atts ) ? $atts : array(),
			'clicklabs_button'
		);

		$button_id = absint( $atts['id'] );

		if ( $button_id <= 0 ) {
			return '';
		}

		$button = Shyft_Dashboard_Buttons::get_public_button( $button_id );

		if ( null === $button ) {
			return '';
		}

		return self::render_button( $button );
	}

	/**
	 * @param array<string, mixed> $button Button payload.
	 */
	public static function render_button( array $button ): string {
		$button_id = (int) ( $button['id'] ?? 0 );
		$text      = (string) ( $button['text'] ?? Shyft_Dashboard_Buttons::DEFAULT_LABEL );
		$url       = (string) ( $button['url'] ?? '' );
		$scope     = (string) ( $button['scope_class'] ?? Shyft_Dashboard_Buttons::get_scope_class( $button_id ) );

		if ( '' === $url || $button_id <= 0 ) {
			return '';
		}

		self::enqueue_assets( $button_id, (string) ( $button['custom_css'] ?? '' ) );

		ob_start();
		?>
		<span class="shyft-button-cta <?php echo esc_attr( $scope ); ?>">
			<a class="shyft-button-cta__link" href="<?php echo esc_url( $url ); ?>">
				<?php echo esc_html( $text ); ?>
			</a>
		</span>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Runs shortcodes inside Elementor HTML/text widgets.
	 *
	 * @param string              $content Widget HTML.
	 * @param \Elementor\Widget_Base $widget Elementor widget instance.
	 */
	public static function filter_elementor_widget_content( string $content, $widget ): string {
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) ) {
			return $content;
		}

		$name = (string) $widget->get_name();

		if ( ! in_array( $name, array( 'html', 'text-editor', 'shortcode' ), true ) ) {
			return $content;
		}

		if ( 'shortcode' === $name ) {
			return $content;
		}

		return do_shortcode( $content );
	}

	private static function enqueue_assets( int $button_id, string $custom_css ): void {
		wp_enqueue_style( 'shyft-button-cta' );

		if ( ! self::$assets_enqueued ) {
			$global_css = Shyft_Dashboard_Settings::get_buttons_custom_css();

			if ( '' !== $global_css ) {
				wp_add_inline_style( 'shyft-button-cta', $global_css );
			}

			self::$assets_enqueued = true;
		}

		if ( '' !== $custom_css && empty( self::$inline_styles_added[ $button_id ] ) ) {
			wp_add_inline_style( 'shyft-button-cta', $custom_css );
			self::$inline_styles_added[ $button_id ] = true;
		}
	}
}
