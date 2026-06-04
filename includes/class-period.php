<?php
/**
 * Dashboard reporting period (7 / 30 / 90 days).
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves and exposes the active analytics period for the customer dashboard.
 */
final class Shyft_Dashboard_Period {

	public const QUERY_VAR = 'shyft_period';

	/** @var list<int> */
	public const ALLOWED_DAYS = array( 7, 30, 90 );

	public const DEFAULT_DAYS = 90;

	/** @var int|null */
	private static ?int $current_days = null;

	/**
	 * Returns the active period in days (from ?shyft_period= on /dashboard).
	 */
	public static function get_days(): int {
		if ( null !== self::$current_days ) {
			return self::$current_days;
		}

		$days = self::DEFAULT_DAYS;

		$query_days = get_query_var( self::QUERY_VAR );

		if ( is_string( $query_days ) && '' !== $query_days ) {
			$days = absint( $query_days );
		} elseif ( isset( $_GET[ self::QUERY_VAR ] ) ) {
			$days = absint( wp_unslash( (string) $_GET[ self::QUERY_VAR ] ) );
		}

		if ( ! in_array( $days, self::ALLOWED_DAYS, true ) ) {
			$days = self::DEFAULT_DAYS;
		}

		self::$current_days = $days;

		return $days;
	}

	/**
	 * Human-readable label, e.g. "90 Tage".
	 */
	public static function get_label(): string {
		return sprintf(
			/* translators: %d: number of days */
			__( '%d Tage', 'shyft-dashboard' ),
			self::get_days()
		);
	}

	/**
	 * Matomo date range token, e.g. last30.
	 */
	public static function get_matomo_range(): string {
		return 'last' . self::get_days();
	}

	/**
	 * Dashboard URL with the given period query argument.
	 *
	 * @param int $days Period in days (7, 30, or 90).
	 */
	public static function get_dashboard_url( int $days ): string {
		if ( ! in_array( $days, self::ALLOWED_DAYS, true ) ) {
			$days = self::DEFAULT_DAYS;
		}

		return add_query_arg(
			self::QUERY_VAR,
			(string) $days,
			Shyft_Dashboard_Routing::get_dashboard_url()
		);
	}
}
