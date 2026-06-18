<?php
/**
 * Public offer display (shortcode + assets).
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders customer offers on the website.
 */
final class Shyft_Dashboard_Offers_Display {

	private static bool $assets_enqueued = false;

	public static function register(): void {
		add_shortcode( 'clicklabs_angebot', array( self::class, 'render_shortcode' ) );
		add_shortcode( 'shyft_angebot', array( self::class, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'register_assets' ) );
	}

	public static function register_assets(): void {
		wp_register_style(
			'shyft-offer',
			SHYFT_DASHBOARD_URL . 'assets/css/offer.css',
			array(),
			SHYFT_DASHBOARD_VERSION
		);
	}

	/**
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public static function render_shortcode( $atts = array() ): string {
		self::enqueue_assets();

		$offers = Shyft_Dashboard_Offers::get_public_offers();

		if ( empty( $offers ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="shyft-offers">
			<?php foreach ( $offers as $offer ) : ?>
				<?php self::render_offer_card( $offer ); ?>
			<?php endforeach; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, mixed> $offer Offer payload.
	 */
	public static function render_offer_card( array $offer ): void {
		$image_url    = (string) ( $offer['image_url'] ?? '' );
		$headline     = (string) ( $offer['headline'] ?? '' );
		$text         = (string) ( $offer['text'] ?? '' );
		$icons        = is_array( $offer['icons'] ?? null ) ? $offer['icons'] : array();
		$button_label = (string) ( $offer['button_label'] ?? '' );
		$button_url   = (string) ( $offer['button_url'] ?? '' );
		?>
		<article class="shyft-offer">
			<?php if ( '' !== $image_url ) : ?>
				<div class="shyft-offer__media">
					<img class="shyft-offer__image" src="<?php echo esc_url( $image_url ); ?>" alt="" loading="lazy" decoding="async">
				</div>
			<?php endif; ?>
			<div class="shyft-offer__content">
				<?php if ( '' !== $headline ) : ?>
					<h2 class="shyft-offer__headline"><?php echo esc_html( $headline ); ?></h2>
				<?php endif; ?>
				<?php if ( '' !== $text ) : ?>
					<div class="shyft-offer__text">
						<?php echo wp_kses_post( wpautop( esc_html( $text ) ) ); ?>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $icons ) ) : ?>
					<ul class="shyft-offer__icons">
						<?php foreach ( $icons as $label ) : ?>
							<?php
							$label = is_string( $label ) ? $label : '';

							if ( '' === $label ) {
								continue;
							}
							?>
							<li class="shyft-offer__icon-item">
								<span class="shyft-offer__icon" aria-hidden="true">
									<?php self::render_check_icon(); ?>
								</span>
								<span class="shyft-offer__icon-label"><?php echo esc_html( $label ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( '' !== $button_label && '' !== $button_url ) : ?>
					<a class="shyft-offer__button" href="<?php echo esc_url( $button_url ); ?>">
						<?php echo esc_html( $button_label ); ?>
					</a>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}

	private static function render_check_icon(): void {
		echo '<svg class="shyft-offer__check" width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10.5 8 14.5 16 6.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}

	private static function enqueue_assets(): void {
		if ( self::$assets_enqueued ) {
			return;
		}

		wp_enqueue_style( 'shyft-offer' );
		self::$assets_enqueued = true;
	}
}
