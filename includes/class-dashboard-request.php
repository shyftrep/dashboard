<?php
/**
 * Dashboard URI detection (usable before WordPress routing is ready).
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects /dashboard and /dashboard/{7|30|90} requests from the request URI.
 */
final class Shyft_Dashboard_Request {

	/**
	 * Whether the current HTTP request targets the customer dashboard route.
	 */
	public static function matches_uri(): bool {
		$path = self::get_path_segment();

		if ( 'dashboard' === $path ) {
			return true;
		}

		if ( preg_match( '#^dashboard/(7|30|90)$#', $path ) ) {
			return true;
		}

		return (bool) preg_match( '#(?:^|/)dashboard/?$#', $path );
	}

	/**
	 * Path segment relative to the site home (e.g. "dashboard" or "dashboard/7").
	 */
	public static function get_path_segment(): string {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		$path = wp_parse_url( wp_unslash( (string) $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );

		if ( ! is_string( $path ) ) {
			return '';
		}

		$path = trim( $path, '/' );

		if ( function_exists( 'home_url' ) ) {
			$home = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
			$home = is_string( $home ) ? trim( $home, '/' ) : '';

			if ( '' !== $home ) {
				if ( $path === $home ) {
					$path = '';
				} elseif ( str_starts_with( $path, $home . '/' ) ) {
					$path = substr( $path, strlen( $home ) + 1 );
				}
			}
		}

		return trim( $path, '/' );
	}
}
