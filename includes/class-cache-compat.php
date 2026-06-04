<?php
/**
 * Page-cache plugin compatibility for the dashboard route.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Disables caching and page optimization on dashboard URLs.
 */
final class Shyft_Dashboard_Cache_Compat {

	/**
	 * Registers cache-related hooks.
	 */
	public static function register(): void {
		add_action( 'litespeed_init', array( self::class, 'disable_litespeed_cache' ), 0 );
		add_filter( 'litespeed_control_cacheable', array( self::class, 'mark_litespeed_not_cacheable' ), 999 );
		add_filter( 'litespeed_can_optm', array( self::class, 'disable_litespeed_optimization' ), 999 );
		add_filter( 'rocket_cache_reject_uri', array( self::class, 'reject_wp_rocket_cache' ), 10, 1 );
		add_filter( 'wp_super_cache_cache_forbidden', array( self::class, 'reject_wp_super_cache' ) );
	}

	/**
	 * LiteSpeed Cache: force non-cacheable before cache lookup.
	 */
	public static function disable_litespeed_cache(): void {
		if ( ! self::is_dashboard_route() ) {
			return;
		}

		do_action( 'litespeed_control_set_nocache', 'shyft-dashboard' );
	}

	/**
	 * @param bool $cacheable Whether LiteSpeed may cache the page.
	 */
	public static function mark_litespeed_not_cacheable( bool $cacheable ): bool {
		if ( self::is_dashboard_route() ) {
			return false;
		}

		return $cacheable;
	}

	/**
	 * @param bool $can_optm Whether LiteSpeed may optimize the page.
	 */
	public static function disable_litespeed_optimization( bool $can_optm ): bool {
		if ( self::is_dashboard_route() ) {
			return false;
		}

		return $can_optm;
	}

	/**
	 * @param array<int, string>|mixed $uris WP Rocket rejected URI patterns.
	 * @return array<int, string>|mixed
	 */
	public static function reject_wp_rocket_cache( $uris ) {
		if ( ! is_array( $uris ) ) {
			$uris = array();
		}

		if ( ! self::is_dashboard_route() ) {
			return $uris;
		}

		$uris[] = '/dashboard(?:/(?:7|30|90))?/?(?:\?.*)?$';

		return $uris;
	}

	/**
	 * @param bool $forbidden Whether WP Super Cache must skip the request.
	 */
	public static function reject_wp_super_cache( bool $forbidden ): bool {
		if ( self::is_dashboard_route() ) {
			return true;
		}

		return $forbidden;
	}

	/**
	 * Whether the current request is a dashboard route.
	 */
	private static function is_dashboard_route(): bool {
		if ( class_exists( 'Shyft_Dashboard_Warmup', false ) && Shyft_Dashboard_Warmup::is_warmup_request() ) {
			return true;
		}

		if ( class_exists( 'Shyft_Dashboard_Routing', false ) && Shyft_Dashboard_Routing::is_dashboard_request() ) {
			return true;
		}

		return Shyft_Dashboard_Request::matches_uri();
	}
}
