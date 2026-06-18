<?php
/**
 * Google Reviews frontend: shortcode, assets, Schema.org markup.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders cached Google reviews on the public site.
 */
final class Shyft_Dashboard_Google_Reviews_Display {

	private static bool $assets_enqueued = false;

	/**
	 * Registers shortcode and asset hooks.
	 */
	public static function register(): void {
		add_shortcode( 'clicklabs_reviews', array( self::class, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'register_assets' ) );
	}

	/**
	 * Registers styles/scripts (enqueued on demand).
	 */
	public static function register_assets(): void {
		wp_register_style(
			'shyft-google-reviews',
			SHYFT_DASHBOARD_URL . 'assets/css/google-reviews.css',
			array(),
			SHYFT_DASHBOARD_VERSION
		);

		wp_register_script(
			'shyft-google-reviews',
			SHYFT_DASHBOARD_URL . 'assets/js/google-reviews.js',
			array(),
			SHYFT_DASHBOARD_VERSION,
			true
		);
	}

	/**
	 * Shortcode [clicklabs_reviews].
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public static function render_shortcode( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'limit'       => '5',
				'slider'      => '1',
				'schema'      => '1',
				'header'      => '1',
				'write_button'=> '1',
			),
			is_array( $atts ) ? $atts : array(),
			'clicklabs_reviews'
		);

		$args = array(
			'limit'        => max( 1, min( 10, (int) $atts['limit'] ) ),
			'slider'       => '1' === $atts['slider'] || 'true' === $atts['slider'],
			'schema'       => '1' === $atts['schema'] || 'true' === $atts['schema'],
			'header'       => '1' === $atts['header'] || 'true' === $atts['header'],
			'write_button' => '1' === $atts['write_button'] || 'true' === $atts['write_button'],
		);

		return self::render( $args );
	}

	/**
	 * Renders the reviews widget markup.
	 *
	 * @param array<string, mixed> $args Display options.
	 */
	public static function render( array $args = array() ): string {
		$args = wp_parse_args(
			$args,
			array(
				'limit'        => 5,
				'slider'       => true,
				'schema'       => true,
				'header'       => true,
				'write_button' => true,
			)
		);

		$data = Shyft_Dashboard_Google_Reviews::get_stored_data();

		if ( empty( $data['available'] ) ) {
			return '';
		}

		self::enqueue_assets();

		$reviews = array_slice( (array) ( $data['reviews'] ?? array() ), 0, (int) $args['limit'] );

		if ( empty( $reviews ) ) {
			return '';
		}

		$rating      = (float) ( $data['rating'] ?? 0 );
		$total       = (int) ( $data['total'] ?? 0 );
		$place_name  = (string) ( $data['place_name'] ?? '' );
		$write_url   = (string) ( $data['write_review_url'] ?? Shyft_Dashboard_Google_Reviews::get_write_review_url() );
		$slider      = ! empty( $args['slider'] ) && count( $reviews ) > 1;
		$widget_id   = 'shyft-reviews-' . wp_unique_id();

		ob_start();

		if ( ! empty( $args['schema'] ) ) {
			self::print_schema_markup( $data, $reviews, $place_name );
		}
		?>
		<div
			class="shyft-reviews<?php echo $slider ? ' shyft-reviews--slider' : ''; ?>"
			id="<?php echo esc_attr( $widget_id ); ?>"
			data-shyft-reviews-slider="<?php echo $slider ? '1' : '0'; ?>"
		>
			<?php if ( ! empty( $args['header'] ) ) : ?>
				<header class="shyft-reviews__header">
					<div class="shyft-reviews__summary">
						<p class="shyft-reviews__rating" aria-label="<?php echo esc_attr( self::format_rating_label( $rating, $total ) ); ?>">
							<span class="shyft-reviews__rating-value"><?php echo esc_html( number_format_i18n( $rating, 1 ) ); ?></span>
							<span class="shyft-reviews__stars" aria-hidden="true"><?php echo esc_html( self::render_stars_text( $rating ) ); ?></span>
						</p>
						<p class="shyft-reviews__count">
							<?php
							printf(
								/* translators: %s: number of Google reviews */
								esc_html( _n( '%s Google-Bewertung', '%s Google-Bewertungen', $total, 'shyft-dashboard' ) ),
								esc_html( number_format_i18n( $total ) )
							);
							?>
						</p>
					</div>
					<?php if ( ! empty( $args['write_button'] ) && '' !== $write_url ) : ?>
						<a class="shyft-reviews__cta" href="<?php echo esc_url( $write_url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Jetzt bewerten', 'shyft-dashboard' ); ?>
						</a>
					<?php endif; ?>
				</header>
			<?php endif; ?>

			<div class="shyft-reviews__track-wrap">
				<div class="shyft-reviews__track" data-shyft-reviews-track>
					<?php foreach ( $reviews as $index => $review ) : ?>
						<article class="shyft-reviews__card" data-shyft-reviews-slide="<?php echo esc_attr( (string) $index ); ?>">
							<header class="shyft-reviews__card-header">
								<?php if ( ! empty( $review['photo'] ) ) : ?>
									<img class="shyft-reviews__avatar" src="<?php echo esc_url( (string) $review['photo'] ); ?>" alt="" width="40" height="40" loading="lazy" decoding="async">
								<?php else : ?>
									<span class="shyft-reviews__avatar shyft-reviews__avatar--placeholder" aria-hidden="true">
										<?php echo esc_html( self::get_author_initial( (string) ( $review['author'] ?? '' ) ) ); ?>
									</span>
								<?php endif; ?>
								<p class="shyft-reviews__author"><?php echo esc_html( (string) ( $review['author'] ?? '' ) ); ?></p>
							</header>
							<p class="shyft-reviews__stars-small" aria-hidden="true"><?php echo esc_html( self::render_stars_text( (float) ( $review['rating'] ?? 0 ) ) ); ?></p>
							<?php if ( ! empty( $review['text'] ) ) : ?>
								<p class="shyft-reviews__text"><?php echo esc_html( (string) $review['text'] ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $review['relative_time'] ) ) : ?>
								<footer class="shyft-reviews__card-footer">
									<p class="shyft-reviews__time"><?php echo esc_html( (string) $review['relative_time'] ); ?></p>
								</footer>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			</div>

			<?php if ( $slider ) : ?>
				<div class="shyft-reviews__controls">
					<div class="shyft-reviews__nav-group">
						<button type="button" class="shyft-reviews__nav shyft-reviews__nav--prev" aria-label="<?php esc_attr_e( 'Vorherige Bewertung', 'shyft-dashboard' ); ?>" data-shyft-reviews-prev>&larr;</button>
						<button type="button" class="shyft-reviews__nav shyft-reviews__nav--next" aria-label="<?php esc_attr_e( 'Nächste Bewertung', 'shyft-dashboard' ); ?>" data-shyft-reviews-next>&rarr;</button>
					</div>
					<div class="shyft-reviews__dots" data-shyft-reviews-dots aria-hidden="true"></div>
				</div>
			<?php endif; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, mixed>        $data    Stored payload.
	 * @param list<array<string, mixed>>  $reviews Review rows.
	 */
	private static function print_schema_markup( array $data, array $reviews, string $place_name ): void {
		$schema_reviews = array();

		foreach ( $reviews as $review ) {
			if ( empty( $review['text'] ) ) {
				continue;
			}

			$item = array(
				'@type'         => 'Review',
				'reviewRating'  => array(
					'@type'       => 'Rating',
					'ratingValue' => (string) (int) ( $review['rating'] ?? 0 ),
					'bestRating'  => '5',
				),
				'author'        => array(
					'@type' => 'Person',
					'name'  => (string) ( $review['author'] ?? __( 'Google-Nutzer', 'shyft-dashboard' ) ),
				),
				'reviewBody'    => (string) $review['text'],
			);

			if ( ! empty( $review['time'] ) ) {
				$item['datePublished'] = gmdate( 'c', (int) $review['time'] );
			}

			$schema_reviews[] = $item;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'LocalBusiness',
			'name'     => $place_name ?: get_bloginfo( 'name' ),
			'url'      => home_url( '/' ),
			'aggregateRating' => array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) ( $data['rating'] ?? 0 ),
				'reviewCount' => (string) (int) ( $data['total'] ?? 0 ),
				'bestRating'  => '5',
				'worstRating' => '1',
			),
		);

		if ( ! empty( $schema_reviews ) ) {
			$schema['review'] = $schema_reviews;
		}

		printf(
			'<script type="application/ld+json">%s</script>',
			wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);
	}

	private static function enqueue_assets(): void {
		if ( self::$assets_enqueued ) {
			return;
		}

		wp_enqueue_style( 'shyft-google-reviews' );

		$custom_css = Shyft_Dashboard_Settings::get_google_reviews_custom_css();

		if ( '' !== $custom_css ) {
			wp_add_inline_style( 'shyft-google-reviews', $custom_css );
		}

		wp_enqueue_script( 'shyft-google-reviews' );
		self::$assets_enqueued = true;
	}

	private static function render_stars_text( float $rating ): string {
		$full  = (int) floor( $rating );
		$half  = ( $rating - $full ) >= 0.5 ? 1 : 0;
		$empty = max( 0, 5 - $full - $half );

		return str_repeat( '★', $full ) . ( $half ? '½' : '' ) . str_repeat( '☆', $empty );
	}

	private static function format_rating_label( float $rating, int $total ): string {
		return sprintf(
			/* translators: 1: rating value, 2: review count */
			__( 'Bewertung %1$s von 5, %2$d Bewertungen', 'shyft-dashboard' ),
			number_format_i18n( $rating, 1 ),
			$total
		);
	}

	private static function get_author_initial( string $author ): string {
		$author = trim( $author );

		if ( '' === $author ) {
			return '?';
		}

		if ( function_exists( 'mb_substr' ) ) {
			return mb_strtoupper( mb_substr( $author, 0, 1 ) );
		}

		return strtoupper( substr( $author, 0, 1 ) );
	}
}
