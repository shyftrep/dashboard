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
		add_shortcode( 'clicklabs_reviews_badge', array( self::class, 'render_badge_shortcode' ) );
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
				'slider'       => '1',
				'schema'       => '1',
				'header'       => '1',
				'write_button' => '1',
			),
			is_array( $atts ) ? $atts : array(),
			'clicklabs_reviews'
		);

		$args = array(
			'slider'       => '1' === $atts['slider'] || 'true' === $atts['slider'],
			'schema'       => '1' === $atts['schema'] || 'true' === $atts['schema'],
			'header'       => '1' === $atts['header'] || 'true' === $atts['header'],
			'write_button' => '1' === $atts['write_button'] || 'true' === $atts['write_button'],
		);

		return self::render( $args );
	}

	/**
	 * Shortcode [clicklabs_reviews_badge].
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public static function render_badge_shortcode( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'style'    => 'inline',
				'text'     => '',
				'title'    => '',
				'subtitle' => '',
				'link'     => '0',
			),
			is_array( $atts ) ? $atts : array(),
			'clicklabs_reviews_badge'
		);

		return self::render_badge(
			array(
				'style'        => $atts['style'],
				'extra_text'   => $atts['text'],
				'title_text'   => $atts['title'],
				'subtitle_text'=> $atts['subtitle'],
				'link'         => '1' === $atts['link'] || 'true' === $atts['link'],
			)
		);
	}

	/**
	 * Renders the compact Google rating badge (no review cards).
	 *
	 * @param array<string, mixed> $args Display options.
	 */
	public static function render_badge( array $args = array() ): string {
		$args = wp_parse_args(
			$args,
			array(
				'style'         => 'inline',
				'extra_text'    => '',
				'title_text'    => '',
				'subtitle_text' => '',
				'link'          => false,
			)
		);

		$data = Shyft_Dashboard_Google_Reviews::get_stored_data();

		if ( empty( $data['available'] ) ) {
			return '';
		}

		$rating = (float) ( $data['rating'] ?? 0 );
		$total  = (int) ( $data['total'] ?? 0 );

		if ( $rating <= 0 || $total <= 0 ) {
			return '';
		}

		self::enqueue_badge_assets();

		$link_url = '';

		if ( ! empty( $args['link'] ) ) {
			$link_url = (string) ( $data['place_url'] ?? '' );

			if ( '' === $link_url ) {
				$link_url = Shyft_Dashboard_Google_Reviews::get_write_review_url();
			}
		}

		if ( 'card' === $args['style'] ) {
			return self::render_badge_card( $rating, $args, $link_url );
		}

		return self::render_badge_inline( $rating, $total, $args, $link_url );
	}

	/**
	 * Inline badge: Google · Sterne · Schnitt · Anzahl · Zusatztext.
	 *
	 * @param array<string, mixed> $args Display options.
	 */
	private static function render_badge_inline( float $rating, int $total, array $args, string $link_url ): string {
		$extra_text    = trim( (string) $args['extra_text'] );
		$summary_label = self::format_rating_label( $rating, $total );

		ob_start();
		?>
		<div class="shyft-reviews-badge shyft-reviews-badge--inline">
			<?php if ( '' !== $link_url ) : ?>
				<a
					class="shyft-reviews-badge__summary shyft-reviews-badge__summary--link"
					href="<?php echo esc_url( $link_url ); ?>"
					target="_blank"
					rel="noopener noreferrer"
					aria-label="<?php echo esc_attr( $summary_label ); ?>"
				>
					<?php self::print_badge_summary_markup( $rating, $total ); ?>
				</a>
			<?php else : ?>
				<div class="shyft-reviews-badge__summary" aria-label="<?php echo esc_attr( $summary_label ); ?>">
					<?php self::print_badge_summary_markup( $rating, $total ); ?>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $extra_text ) : ?>
				<span class="shyft-reviews-badge__extra"><?php echo esc_html( $extra_text ); ?></span>
			<?php endif; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Card badge: Google + Stern links, Freitext rechts (Titel + Unterzeile).
	 *
	 * @param array<string, mixed> $args Display options.
	 */
	private static function render_badge_card( float $rating, array $args, string $link_url ): string {
		$title_html    = self::format_badge_title_lines( (string) $args['title_text'] );
		$subtitle_text = trim( (string) $args['subtitle_text'] );
		$summary_label = sprintf(
			/* translators: %s: rating value */
			__( 'Google-Bewertung %s von 5', 'shyft-dashboard' ),
			number_format_i18n( $rating, 1 )
		);

		$tag        = '' !== $link_url ? 'a' : 'div';
		$link_attrs = '';

		if ( '' !== $link_url ) {
			$link_attrs = sprintf(
				' href="%s" target="_blank" rel="noopener noreferrer"',
				esc_url( $link_url )
			);
		}

		ob_start();
		?>
		<div class="shyft-reviews-badge shyft-reviews-badge--card">
			<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped fragments. ?>
			<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="shyft-reviews-badge__card-inner"<?php echo $link_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-label="<?php echo esc_attr( $summary_label ); ?>">
				<div class="shyft-reviews-badge__card-side">
					<span class="shyft-reviews-badge__icon shyft-reviews-badge__icon--card" aria-hidden="true">
						<?php self::print_google_icon_svg( 26 ); ?>
					</span>
					<span class="shyft-reviews-badge__card-rating">
						<span class="shyft-reviews-badge__card-star" aria-hidden="true"><?php self::print_star_icon_svg(); ?></span>
						<span class="shyft-reviews-badge__card-rating-value"><?php echo esc_html( number_format_i18n( $rating, 1 ) ); ?></span>
					</span>
				</div>
				<span class="shyft-reviews-badge__card-divider" aria-hidden="true"></span>
				<div class="shyft-reviews-badge__card-content">
					<?php if ( '' !== $title_html ) : ?>
						<p class="shyft-reviews-badge__card-title"><?php echo $title_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					<?php endif; ?>
					<?php if ( '' !== $subtitle_text ) : ?>
						<p class="shyft-reviews-badge__card-subtitle"><?php echo esc_html( $subtitle_text ); ?></p>
					<?php endif; ?>
				</div>
			</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		</div>
		<?php

		return (string) ob_get_clean();
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

		$reviews = array_slice(
			(array) ( $data['reviews'] ?? array() ),
			0,
			Shyft_Dashboard_Google_Reviews::DISPLAY_REVIEW_LIMIT
		);

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
		$place_name   = $place_name ?: get_bloginfo( 'name' );
		$place_url    = esc_url_raw( (string) ( $data['place_url'] ?? '' ) );
		$place_website = esc_url_raw( (string) ( $data['place_website'] ?? '' ) );
		$canonical_url = $place_website ?: $place_url ?: home_url( '/' );
		$item_reviewed = array_filter(
			array(
				'@type' => 'LocalBusiness',
				'name'  => $place_name,
				'url'   => $canonical_url,
			)
		);

		$schema_reviews = array();

		foreach ( $reviews as $review ) {
			if ( empty( $review['text'] ) ) {
				continue;
			}

			$item = array(
				'@type'        => 'Review',
				'itemReviewed' => $item_reviewed,
				'reviewRating' => array(
					'@type'       => 'Rating',
					'ratingValue' => (string) (int) ( $review['rating'] ?? 0 ),
					'bestRating'  => '5',
				),
				'author'       => array(
					'@type' => 'Person',
					'name'  => (string) ( $review['author'] ?? __( 'Google-Nutzer', 'shyft-dashboard' ) ),
				),
				'reviewBody'   => (string) $review['text'],
			);

			if ( ! empty( $review['time'] ) ) {
				$item['datePublished'] = gmdate( 'c', (int) $review['time'] );
			}

			$schema_reviews[] = $item;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'LocalBusiness',
			'name'     => $place_name,
			'url'      => $canonical_url,
			'aggregateRating' => array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) ( $data['rating'] ?? 0 ),
				'reviewCount' => (string) (int) ( $data['total'] ?? 0 ),
				'bestRating'  => '5',
				'worstRating' => '1',
			),
		);

		$place_address = $data['place_address'] ?? array();

		if ( is_array( $place_address ) && ! empty( $place_address ) ) {
			$schema['address'] = $place_address;
		}

		$place_lat = $data['place_lat'] ?? null;
		$place_lng = $data['place_lng'] ?? null;

		if ( is_numeric( $place_lat ) && is_numeric( $place_lng ) ) {
			$schema['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $place_lat,
				'longitude' => (float) $place_lng,
			);
		}

		$same_as = array();

		foreach ( array( $place_url, $place_website ) as $profile_url ) {
			if ( '' !== $profile_url && $profile_url !== $canonical_url ) {
				$same_as[] = $profile_url;
			}
		}

		$same_as = array_values( array_unique( $same_as ) );

		if ( ! empty( $same_as ) ) {
			$schema['sameAs'] = $same_as;
		}

		if ( ! empty( $schema_reviews ) ) {
			$schema['review'] = $schema_reviews;
		}

		printf(
			'<script type="application/ld+json">%s</script>',
			wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);
	}

	private static function enqueue_assets(): void {
		self::enqueue_badge_assets();
		wp_enqueue_script( 'shyft-google-reviews' );
	}

	private static function enqueue_badge_assets(): void {
		if ( self::$assets_enqueued ) {
			return;
		}

		wp_enqueue_style( 'shyft-google-reviews' );

		$custom_css = Shyft_Dashboard_Settings::get_google_reviews_custom_css();

		if ( '' !== $custom_css ) {
			wp_add_inline_style( 'shyft-google-reviews', $custom_css );
		}

		self::$assets_enqueued = true;
	}

	private static function print_badge_summary_markup( float $rating, int $total ): void {
		?>
		<span class="shyft-reviews-badge__icon" aria-hidden="true"><?php self::print_google_icon_svg(); ?></span>
		<span class="shyft-reviews-badge__stars" aria-hidden="true"><?php echo esc_html( self::render_stars_text( $rating ) ); ?></span>
		<span class="shyft-reviews-badge__rating"><?php echo esc_html( number_format_i18n( $rating, 1 ) ); ?></span>
		<span class="shyft-reviews-badge__sep" aria-hidden="true">|</span>
		<span class="shyft-reviews-badge__count">
			<?php
			printf(
				/* translators: %s: number of Google reviews */
				esc_html( _n( '%s Bewertung', '%s Bewertungen', $total, 'shyft-dashboard' ) ),
				esc_html( number_format_i18n( $total ) )
			);
			?>
		</span>
		<?php
	}

	private static function print_google_icon_svg( int $size = 20 ): void {
		?>
		<svg class="shyft-reviews-badge__google" viewBox="0 0 24 24" width="<?php echo esc_attr( (string) $size ); ?>" height="<?php echo esc_attr( (string) $size ); ?>" role="img" focusable="false">
			<title><?php esc_html_e( 'Google', 'shyft-dashboard' ); ?></title>
			<path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
			<path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
			<path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
			<path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
		</svg>
		<?php
	}

	private static function print_star_icon_svg(): void {
		?>
		<svg class="shyft-reviews-badge__star-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
			<path fill="#FBBC04" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
		</svg>
		<?php
	}

	/**
	 * Formats multiline title text (newline or | as line break).
	 */
	private static function format_badge_title_lines( string $text ): string {
		$text = trim( $text );

		if ( '' === $text ) {
			return '';
		}

		$text  = str_replace( '|', "\n", $text );
		$lines = preg_split( '/\r\n|\r|\n/', $text );
		$lines = is_array( $lines ) ? $lines : array( $text );
		$html  = '';

		foreach ( $lines as $line ) {
			$line = trim( (string) $line );

			if ( '' === $line ) {
				continue;
			}

			$html .= '<span class="shyft-reviews-badge__card-title-line">' . esc_html( $line ) . '</span>';
		}

		return $html;
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
