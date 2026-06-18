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
		add_action( 'init', array( self::class, 'register_shortcodes' ), 5 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'register_assets' ) );
	}

	public static function register_shortcodes(): void {
		add_shortcode( 'clicklabs_angebot', array( self::class, 'render_shortcode' ) );
		add_shortcode( 'shyft_angebot', array( self::class, 'render_shortcode' ) );
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
		return self::render();
	}

	public static function render(): string {
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
		$offer_type   = (string) ( $offer['type'] ?? '' );
		$ends_at      = (int) ( $offer['ends_at'] ?? 0 );
		$end_label    = Shyft_Dashboard_Offers::TYPE_TIMED === $offer_type
			? Shyft_Dashboard_Offers::format_public_end_label( $ends_at )
			: '';
		?>
		<article class="shyft-offer<?php echo '' !== $end_label ? ' shyft-offer--timed' : ''; ?>">
			<?php if ( '' !== $image_url ) : ?>
				<div class="shyft-offer__media">
					<img class="shyft-offer__image" src="<?php echo esc_url( $image_url ); ?>" alt="" loading="lazy" decoding="async">
				</div>
			<?php endif; ?>
			<div class="shyft-offer__content">
				<?php if ( '' !== $end_label ) : ?>
					<p class="shyft-offer__deadline"><?php echo esc_html( $end_label ); ?></p>
				<?php endif; ?>
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
				<?php if ( '' !== $button_url ) : ?>
					<a class="shyft-offer__button shyft-offer__button--whatsapp" href="<?php echo esc_url( $button_url ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="shyft-offer__button-icon" aria-hidden="true">
							<?php self::render_whatsapp_icon(); ?>
						</span>
						<span class="shyft-offer__button-label">
							<?php echo esc_html( '' !== $button_label ? $button_label : __( 'WhatsApp', 'shyft-dashboard' ) ); ?>
						</span>
					</a>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}

	private static function render_check_icon(): void {
		echo '<svg class="shyft-offer__check" width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10.5 8 14.5 16 6.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}

	private static function render_whatsapp_icon(): void {
		echo '<svg viewBox="0 0 24 24" role="img" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>';
	}

	private static function enqueue_assets(): void {
		if ( self::$assets_enqueued ) {
			return;
		}

		wp_enqueue_style( 'shyft-offer' );
		self::$assets_enqueued = true;
	}
}
