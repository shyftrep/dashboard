<?php
/**
 * Recent maintenance activity display for customer-facing dashboard.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates and caches two maintenance activities for display.
 */
final class Shyft_Dashboard_Recent_Activity {

	private const CACHE_KEY        = 'shyft_dashboard_activity_display';
	private const REFRESH_INTERVAL = 6 * WEEK_IN_SECONDS;

	/**
	 * Registers hooks (display is generated on demand).
	 */
	public static function register(): void {
		// Activities are generated from the cached snapshot.
	}

	/**
	 * Returns two cached maintenance activities for display.
	 *
	 * @return array<int, array{message: string, type: string, date: string}>
	 */
	public function get_display_activities(): array {
		$cached = get_transient( self::CACHE_KEY );

		if ( is_array( $cached ) && 2 === count( $cached ) ) {
			return $this->sanitize_display_items( $cached );
		}

		$generated = $this->generate_display_activities();
		set_transient( self::CACHE_KEY, $generated, self::REFRESH_INTERVAL );

		return $generated;
	}

	/**
	 * Builds two activities with random messages and month-based dates.
	 *
	 * @return array<int, array{message: string, type: string, date: string}>
	 */
	private function generate_display_activities(): array {
		$pool      = $this->get_message_pool();
		$pool_keys = array_keys( $pool );
		shuffle( $pool_keys );

		$now           = current_time( 'timestamp' );
		$current_year  = (int) wp_date( 'Y', $now );
		$current_month = (int) wp_date( 'n', $now );

		$previous_timestamp = strtotime( '-1 month', $now );
		$previous_year      = (int) wp_date( 'Y', $previous_timestamp );
		$previous_month     = (int) wp_date( 'n', $previous_timestamp );

		$first = array(
			'item'      => $pool[ $pool_keys[0] ],
			'timestamp' => $this->random_date_in_month( $current_year, $current_month, $now ),
		);
		$second = array(
			'item'      => $pool[ $pool_keys[1] ],
			'timestamp' => $this->random_date_in_month( $previous_year, $previous_month ),
		);

		$entries = array( $first, $second );

		usort(
			$entries,
			static function ( array $a, array $b ): int {
				return (int) $b['timestamp'] <=> (int) $a['timestamp'];
			}
		);

		$activities = array();

		foreach ( $entries as $entry ) {
			$activities[] = $this->build_activity_item( $entry['item'], (int) $entry['timestamp'] );
		}

		return $activities;
	}

	/**
	 * Returns the pool of possible maintenance messages.
	 *
	 * @return array<int, array{message: string, type: string}>
	 */
	private function get_message_pool(): array {
		return array(
			array(
				'message' => __( 'Sicherheitsupdate und Plugin-Wartung durchgeführt', 'shyft-dashboard' ),
				'type'    => 'security',
			),
			array(
				'message' => __( 'WordPress-Kernupdate erfolgreich eingespielt', 'shyft-dashboard' ),
				'type'    => 'update',
			),
			array(
				'message' => __( 'System-Update: Erweiterungen geprüft und aktualisiert', 'shyft-dashboard' ),
				'type'    => 'update',
			),
			array(
				'message' => __( 'SSL-Verschlüsselung geprüft – Verbindung ist sicher', 'shyft-dashboard' ),
				'type'    => 'security',
			),
			array(
				'message' => __( 'Website-Backup erstellt und gesichert', 'shyft-dashboard' ),
				'type'    => 'backup',
			),
			array(
				'message' => __( 'Performance-Check und Ladezeiten optimiert', 'shyft-dashboard' ),
				'type'    => 'performance',
			),
			array(
				'message' => __( 'DSGVO-Einstellungen und Cookie-Hinweise verifiziert', 'shyft-dashboard' ),
				'type'    => 'maintenance',
			),
			array(
				'message' => __( 'Firewall-, Spam-Schutz und Login-Sicherheit überprüft', 'shyft-dashboard' ),
				'type'    => 'security',
			),
			array(
				'message' => __( 'Datenbank-Wartung und System-Optimierung abgeschlossen', 'shyft-dashboard' ),
				'type'    => 'maintenance',
			),
			array(
				'message' => __( 'Mobile Darstellung und Responsive-Design geprüft', 'shyft-dashboard' ),
				'type'    => 'performance',
			),
			array(
				'message' => __( 'Cache, Assets und Frontend-Performance optimiert', 'shyft-dashboard' ),
				'type'    => 'performance',
			),
			array(
				'message' => __( 'Kontaktformular und Lead-Strecke auf Funktion getestet', 'shyft-dashboard' ),
				'type'    => 'maintenance',
			),
		);
	}

	/**
	 * Formats a pool item with a display date.
	 *
	 * @param array{message: string, type: string} $item      Message pool item.
	 * @param int                                  $timestamp Event timestamp.
	 * @return array{message: string, type: string, date: string}
	 */
	private function build_activity_item( array $item, int $timestamp ): array {
		return array(
			'message' => sanitize_text_field( $item['message'] ),
			'type'    => sanitize_key( $item['type'] ),
			'date'    => wp_date( 'd.m.Y', $timestamp ),
		);
	}

	/**
	 * Picks a random valid day within a given month.
	 *
	 * @param int      $year          Four-digit year.
	 * @param int      $month         Month number.
	 * @param int|null $max_timestamp Optional upper bound (e.g. today for current month).
	 */
	private function random_date_in_month( int $year, int $month, ?int $max_timestamp = null ): int {
		$month_start   = mktime( 0, 0, 0, $month, 1, $year );
		$days_in_month = (int) wp_date( 't', $month_start );
		$max_day       = $days_in_month;

		if ( null !== $max_timestamp ) {
			$max_year  = (int) wp_date( 'Y', $max_timestamp );
			$max_month = (int) wp_date( 'n', $max_timestamp );

			if ( $year === $max_year && $month === $max_month ) {
				$max_day = min( $max_day, (int) wp_date( 'j', $max_timestamp ) );
			}
		}

		$day = wp_rand( 1, max( 1, $max_day ) );

		return mktime( 12, 0, 0, $month, $day, $year );
	}

	/**
	 * Sanitizes cached activity items before output.
	 *
	 * @param array<int, mixed> $items Cached items.
	 * @return array<int, array{message: string, type: string, date: string}>
	 */
	private function sanitize_display_items( array $items ): array {
		$sanitized = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['message'] ) ) {
				continue;
			}

			$sanitized[] = array(
				'message' => sanitize_text_field( (string) $item['message'] ),
				'type'    => sanitize_key( (string) ( $item['type'] ?? 'maintenance' ) ),
				'date'    => sanitize_text_field( (string) ( $item['date'] ?? '' ) ),
			);
		}

		return $sanitized;
	}
}
