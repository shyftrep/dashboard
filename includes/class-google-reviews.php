<?php
/**
 * Google Reviews – server-side sync, cached storage, no API calls on page views.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches Google Place reviews on a schedule and stores them in wp_options.
 */
final class Shyft_Dashboard_Google_Reviews {

	public const OPTION_DATA     = 'shyft_dashboard_google_reviews_data';
	public const OPTION_LAST_SEEN = 'shyft_dashboard_google_reviews_last_seen_at';
	public const CRON_HOOK       = 'shyft_dashboard_sync_google_reviews';
	public const MAX_STORED_REVIEWS = 10;

	private const API_TIMEOUT = 20;

	/**
	 * Registers cron and admin sync hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'maybe_schedule_cron' ) );
		add_action( self::CRON_HOOK, array( self::class, 'cron_sync' ) );
		add_action( 'admin_post_shyft_sync_google_reviews', array( self::class, 'handle_manual_sync' ) );
	}

	/**
	 * Schedules twice-daily sync (every ~12 hours).
	 */
	public static function maybe_schedule_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', self::CRON_HOOK );
		}
	}

	/**
	 * Clears scheduled cron (deactivation).
	 */
	public static function unschedule_cron(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Cron callback.
	 */
	public static function cron_sync(): void {
		self::sync( false );
	}

	/**
	 * Refetch after Place ID or API key change.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $value   New option value.
	 */
	public static function on_config_changed( $old_value, $value ): void {
		unset( $old_value, $value );
		self::sync( true );
	}

	/**
	 * Manual sync from settings page.
	 */
	public static function handle_manual_sync(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'shyft-dashboard' ) );
		}

		check_admin_referer( 'shyft_sync_google_reviews' );

		$result = self::sync( true );

		$redirect = add_query_arg(
			array(
				'page'                  => Shyft_Dashboard_Settings::PAGE_SLUG,
				'shyft_reviews_synced'  => ! empty( $result['available'] ) ? '1' : '0',
				'shyft_reviews_message' => rawurlencode( (string) ( $result['error'] ?? '' ) ),
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Returns stored review data only (never calls Google).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_stored_data(): array {
		$stored = get_option( self::OPTION_DATA, array() );

		if ( ! is_array( $stored ) ) {
			return self::empty_payload( 'no_data' );
		}

		return $stored;
	}

	/**
	 * Whether reviews are configured and available for display.
	 */
	public static function is_configured(): bool {
		return '' !== Shyft_Dashboard_Settings::get_google_place_id()
			&& '' !== Shyft_Dashboard_Settings::get_google_api_key();
	}

	/**
	 * URL for customers to leave a Google review.
	 */
	public static function get_write_review_url(): string {
		$place_id = Shyft_Dashboard_Settings::get_google_place_id();

		if ( '' === $place_id ) {
			return '';
		}

		return add_query_arg( 'placeid', $place_id, 'https://search.google.com/local/writereview' );
	}

	/**
	 * Fetches reviews from Google Places API and persists them.
	 *
	 * @param bool $force When true, always refetch even if recently synced.
	 * @return array<string, mixed>
	 */
	public static function sync( bool $force = false ): array {
		$place_id = Shyft_Dashboard_Settings::get_google_place_id();
		$api_key  = Shyft_Dashboard_Settings::get_google_api_key();

		if ( '' === trim( $place_id ) || '' === trim( $api_key ) ) {
			$payload = self::empty_payload( 'missing_config' );
			update_option( self::OPTION_DATA, $payload, false );

			return $payload;
		}

		if ( ! $force ) {
			$existing = self::get_stored_data();

			if ( ! empty( $existing['available'] ) && ! empty( $existing['fetched_at'] ) ) {
				$fetched = strtotime( (string) $existing['fetched_at'] );

				if ( false !== $fetched && ( time() - $fetched ) < 11 * HOUR_IN_SECONDS ) {
					return $existing;
				}
			}
		}

		$payload = self::fetch_from_google( $place_id, $api_key );
		update_option( self::OPTION_DATA, $payload, false );

		return $payload;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function fetch_from_google( string $place_id, string $api_key ): array {
		$url = add_query_arg(
			array(
				'place_id'     => $place_id,
				'fields'       => 'rating,user_ratings_total,reviews,name,url,website,formatted_address,address_components,geometry',
				'key'          => $api_key,
				'language'     => self::get_api_language(),
				'reviews_sort' => 'newest',
			),
			'https://maps.googleapis.com/maps/api/place/details/json'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => self::API_TIMEOUT,
			)
		);

		if ( is_wp_error( $response ) ) {
			return self::empty_payload( $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $body ) ) {
			return self::empty_payload(
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Google API Fehler (HTTP %d).', 'shyft-dashboard' ),
					$code
				)
			);
		}

		$status = (string) ( $body['status'] ?? '' );

		if ( 'OK' !== $status ) {
			$message = (string) ( $body['error_message'] ?? $status );

			return self::empty_payload( $message ?: $status );
		}

		$result = $body['result'] ?? array();

		if ( ! is_array( $result ) ) {
			return self::empty_payload( 'invalid_response' );
		}

		$reviews = array();

		foreach ( (array) ( $result['reviews'] ?? array() ) as $review ) {
			if ( ! is_array( $review ) ) {
				continue;
			}

			$reviews[] = array(
				'author'        => (string) ( $review['author_name'] ?? '' ),
				'rating'        => (int) ( $review['rating'] ?? 0 ),
				'text'          => (string) ( $review['text'] ?? '' ),
				'time'          => (int) ( $review['time'] ?? 0 ),
				'relative_time' => (string) ( $review['relative_time_description'] ?? '' ),
				'photo'         => esc_url_raw( (string) ( $review['profile_photo_url'] ?? '' ) ),
			);

			if ( count( $reviews ) >= self::MAX_STORED_REVIEWS ) {
				break;
			}
		}

		return array(
			'available'        => true,
			'rating'           => round( (float) ( $result['rating'] ?? 0 ), 1 ),
			'total'            => (int) ( $result['user_ratings_total'] ?? 0 ),
			'place_name'       => (string) ( $result['name'] ?? '' ),
			'place_url'        => esc_url_raw( (string) ( $result['url'] ?? '' ) ),
			'place_website'    => esc_url_raw( (string) ( $result['website'] ?? '' ) ),
			'place_address'    => self::build_postal_address(
				(string) ( $result['formatted_address'] ?? '' ),
				(array) ( $result['address_components'] ?? array() )
			),
			'place_lat'        => self::extract_coordinate( $result, 'lat' ),
			'place_lng'        => self::extract_coordinate( $result, 'lng' ),
			'write_review_url' => self::get_write_review_url(),
			'reviews'          => $reviews,
			'fetched_at'       => current_time( 'mysql' ),
			'error'            => '',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function empty_payload( string $error ): array {
		return array(
			'available'        => false,
			'rating'           => 0.0,
			'total'            => 0,
			'place_name'       => '',
			'place_url'        => '',
			'place_website'    => '',
			'place_address'    => array(),
			'place_lat'        => null,
			'place_lng'        => null,
			'write_review_url' => self::get_write_review_url(),
			'reviews'          => array(),
			'fetched_at'       => '',
			'error'            => $error,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $components Google address_components.
	 * @return array<string, string>
	 */
	private static function build_postal_address( string $formatted_address, array $components ): array {
		$parts = array(
			'street_number' => '',
			'route'         => '',
			'locality'      => '',
			'postal_code'   => '',
			'country'       => '',
		);

		foreach ( $components as $component ) {
			if ( ! is_array( $component ) ) {
				continue;
			}

			$types = (array) ( $component['types'] ?? array() );
			$long  = (string) ( $component['long_name'] ?? '' );
			$short = (string) ( $component['short_name'] ?? '' );

			if ( in_array( 'street_number', $types, true ) ) {
				$parts['street_number'] = $long;
			}

			if ( in_array( 'route', $types, true ) ) {
				$parts['route'] = $long;
			}

			if ( in_array( 'locality', $types, true ) ) {
				$parts['locality'] = $long;
			} elseif ( in_array( 'postal_town', $types, true ) && '' === $parts['locality'] ) {
				$parts['locality'] = $long;
			}

			if ( in_array( 'postal_code', $types, true ) ) {
				$parts['postal_code'] = $long;
			}

			if ( in_array( 'country', $types, true ) ) {
				$parts['country'] = $short;
			}
		}

		$street = trim( $parts['street_number'] . ' ' . $parts['route'] );
		$address = array(
			'@type' => 'PostalAddress',
		);

		if ( '' !== $street ) {
			$address['streetAddress'] = $street;
		} elseif ( '' !== $formatted_address ) {
			$address['streetAddress'] = $formatted_address;
		}

		if ( '' !== $parts['locality'] ) {
			$address['addressLocality'] = $parts['locality'];
		}

		if ( '' !== $parts['postal_code'] ) {
			$address['postalCode'] = $parts['postal_code'];
		}

		if ( '' !== $parts['country'] ) {
			$address['addressCountry'] = $parts['country'];
		}

		return count( $address ) > 1 ? $address : array();
	}

	/**
	 * @param array<string, mixed> $result Place Details API result.
	 */
	private static function extract_coordinate( array $result, string $axis ): ?float {
		$location = $result['geometry']['location'] ?? null;

		if ( ! is_array( $location ) || ! isset( $location[ $axis ] ) ) {
			return null;
		}

		return is_numeric( $location[ $axis ] ) ? (float) $location[ $axis ] : null;
	}

	/**
	 * Locale for Google Places API (e.g. de).
	 */
	private static function get_api_language(): string {
		$locale = determine_locale();
		$parts  = explode( '_', $locale );

		return strtolower( $parts[0] ?? 'de' );
	}

	/**
	 * Clears stored review data.
	 */
	public static function clear_data(): void {
		delete_option( self::OPTION_DATA );
		delete_option( self::OPTION_LAST_SEEN );
	}

	/**
	 * Unix timestamp of the newest review seen in the dashboard.
	 */
	public static function get_last_seen_at(): int {
		return max( 0, (int) get_option( self::OPTION_LAST_SEEN, 0 ) );
	}

	/**
	 * Reviews that arrived after the last dashboard visit.
	 *
	 * @param array<string, mixed>|null $data Stored payload.
	 * @return list<array<string, mixed>>
	 */
	public static function get_new_reviews( ?array $data = null ): array {
		$data      = $data ?? self::get_stored_data();
		$last_seen = self::get_last_seen_at();

		if ( $last_seen <= 0 || empty( $data['available'] ) ) {
			return array();
		}

		$new_reviews = array();

		foreach ( (array) ( $data['reviews'] ?? array() ) as $review ) {
			if ( ! is_array( $review ) ) {
				continue;
			}

			$time = (int) ( $review['time'] ?? 0 );

			if ( $time > $last_seen ) {
				$new_reviews[] = $review;
			}
		}

		return $new_reviews;
	}

	/**
	 * @param array<string, mixed>|null $data Stored payload.
	 */
	public static function count_new_reviews( ?array $data = null ): int {
		return count( self::get_new_reviews( $data ) );
	}

	/**
	 * Whether a single review should show the "new" badge.
	 *
	 * @param array<string, mixed> $review Review row.
	 */
	public static function is_review_new( array $review ): bool {
		$time = (int) ( $review['time'] ?? 0 );

		if ( $time <= 0 ) {
			return false;
		}

		$last_seen = self::get_last_seen_at();

		if ( $last_seen <= 0 ) {
			return false;
		}

		return $time > $last_seen;
	}

	/**
	 * Marks currently stored reviews as seen in the dashboard.
	 *
	 * @param array<string, mixed>|null $data Stored payload.
	 */
	public static function mark_reviews_seen( ?array $data = null ): void {
		$data    = $data ?? self::get_stored_data();
		$latest  = 0;

		foreach ( (array) ( $data['reviews'] ?? array() ) as $review ) {
			if ( ! is_array( $review ) ) {
				continue;
			}

			$latest = max( $latest, (int) ( $review['time'] ?? 0 ) );
		}

		if ( $latest > 0 ) {
			update_option( self::OPTION_LAST_SEEN, $latest, false );

			return;
		}

		if ( self::get_last_seen_at() <= 0 ) {
			update_option( self::OPTION_LAST_SEEN, time(), false );
		}
	}

	/**
	 * URL to manage and reply to reviews in Google Business Profile.
	 */
	public static function get_manage_reviews_url(): string {
		$data = self::get_stored_data();

		if ( ! empty( $data['place_url'] ) ) {
			return (string) $data['place_url'];
		}

		return 'https://business.google.com/reviews';
	}

	/**
	 * Renders a simple 5-star string for the dashboard.
	 */
	public static function render_stars( int $rating ): string {
		$rating = max( 0, min( 5, $rating ) );

		return str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating );
	}

	/**
	 * First letter for avatar placeholders.
	 */
	public static function get_author_initial( string $author ): string {
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
