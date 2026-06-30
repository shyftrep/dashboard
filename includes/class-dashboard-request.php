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
	 * Whether the current HTTP request targets any plugin frontend route.
	 */
	public static function matches_uri(): bool {
		return self::matches_warmup_uri() || self::matches_dashboard_uri();
	}

	/**
	 * Whether the request targets the warmup gate (/dashboard-warmup/).
	 */
	public static function matches_warmup_uri(): bool {
		return 'dashboard-warmup' === self::get_path_segment();
	}

	/**
	 * Whether the request targets the customer dashboard (/dashboard/…).
	 */
	public static function matches_dashboard_uri(): bool {
		$path = self::get_path_segment();

		if ( 'dashboard' === $path ) {
			return true;
		}

		if ( preg_match( '#^dashboard/angebote$#', $path ) ) {
			return true;
		}

		if ( preg_match( '#^dashboard/buttons$#', $path ) ) {
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

	/**
	 * Whether this request preloads dashboard HTML during the warmup gate (must not redirect).
	 */
	public static function is_preload_request(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Warmup preload marker.
		if ( ! isset( $_GET['shyft_preload'] ) ) {
			return false;
		}

		return '1' === wp_unslash( (string) $_GET['shyft_preload'] );
	}

	/**
	 * Sends HTML Content-Type and no-cache headers for dashboard routes.
	 */
	public static function send_html_headers(): void {
		if ( headers_sent() ) {
			return;
		}

		if ( function_exists( 'header_remove' ) ) {
			header_remove( 'Content-Type' );
		}

		$charset = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'charset' ) : 'UTF-8';

		header( 'Content-Type: text/html; charset=' . $charset, true );
		header( 'X-LiteSpeed-Cache-Control: no-cache', false );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', false );
		header( 'Pragma: no-cache', false );

		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
	}
}
