<?php
/**
 * Elementor form submissions data source.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads leads from Elementor submissions tables.
 */
final class Shyft_Dashboard_Leads {

	/**
	 * Registers hooks (none required at runtime).
	 */
	public static function register(): void {
		// Data is fetched on demand when rendering the dashboard.
	}

	/**
	 * Returns the submissions table name.
	 */
	private function get_submissions_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'e_submissions';
	}

	/**
	 * Returns the submission values table name.
	 */
	private function get_values_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'e_submissions_values';
	}

	/**
	 * Checks whether Elementor submissions tables exist.
	 */
	private function tables_exist(): bool {
		global $wpdb;

		$submissions_table = $this->get_submissions_table();
		$values_table      = $this->get_values_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$submissions_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $submissions_table )
		) === $submissions_table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$values_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $values_table )
		) === $values_table;

		return $submissions_exists && $values_exists;
	}

	/**
	 * Counts unread/new submissions.
	 */
	public function count_new_submissions(): int {
		if ( ! $this->tables_exist() ) {
			return 0;
		}

		global $wpdb;

		$table = $this->get_submissions_table();
		$since = $this->get_period_start_gmt();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE ( status = %s OR is_read = %d ) AND created_at_gmt >= %s",
				'new',
				0,
				$since
			)
		);

		return (int) $count;
	}

	/**
	 * Returns the most recent submissions.
	 *
	 * @param int $limit Number of entries to fetch.
	 * @return array<int, array{id: int, name: string, date: string, date_raw: string, url: string}>
	 */
	public function get_recent_submissions( int $limit = 5 ): array {
		if ( ! $this->tables_exist() ) {
			return array();
		}

		global $wpdb;

		$submissions_table = $this->get_submissions_table();
		$values_table      = $this->get_values_table();
		$since             = $this->get_period_start_gmt();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, created_at_gmt FROM {$submissions_table} WHERE created_at_gmt >= %s ORDER BY created_at_gmt DESC LIMIT %d",
				$since,
				$limit
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$submissions = array();

		foreach ( $rows as $row ) {
			$submission_id = (int) ( $row['id'] ?? 0 );
			$date_gmt      = (string) ( $row['created_at_gmt'] ?? '' );
			$name          = $this->get_submission_name( $submission_id, $values_table );

			$submissions[] = array(
				'id'       => $submission_id,
				'name'     => $name,
				'date'     => $this->format_date( $date_gmt ),
				'date_raw' => $date_gmt,
				'url'      => $this->get_submission_admin_url( $submission_id ),
			);
		}

		return $submissions;
	}

	/**
	 * Returns the Elementor admin URL for a single submission.
	 */
	public function get_submission_admin_url( int $submission_id ): string {
		if ( $submission_id <= 0 ) {
			return '';
		}

		return admin_url( 'admin.php?page=e-form-submissions#/' . $submission_id );
	}

	/**
	 * Returns the GMT datetime string for the start of the reporting period.
	 */
	private function get_period_start_gmt(): string {
		$days = Shyft_Dashboard_Period::get_days();

		return gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
	}

	/**
	 * Resolves a display name from submission field values.
	 *
	 * @param int    $submission_id Submission ID.
	 * @param string $values_table  Values table name.
	 */
	private function get_submission_name( int $submission_id, string $values_table ): string {
		global $wpdb;

		$name_keys = array( 'name', 'vorname', 'field_name', 'your-name', 'email' );

		foreach ( $name_keys as $key ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$value = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT value FROM {$values_table} WHERE submission_id = %d AND `key` = %s LIMIT 1",
					$submission_id,
					$key
				)
			);

			if ( ! empty( $value ) ) {
				return sanitize_text_field( (string) $value );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$fallback = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT value FROM {$values_table} WHERE submission_id = %d AND value <> '' ORDER BY id ASC LIMIT 1",
				$submission_id
			)
		);

		if ( ! empty( $fallback ) ) {
			return sanitize_text_field( (string) $fallback );
		}

		/* translators: %d: submission ID */
		return sprintf( __( 'Anfrage #%d', 'shyft-dashboard' ), $submission_id );
	}

	/**
	 * Formats a GMT datetime for display.
	 *
	 * @param string $date_gmt GMT datetime string.
	 */
	private function format_date( string $date_gmt ): string {
		if ( '' === $date_gmt ) {
			return esc_html__( 'Unbekannt', 'shyft-dashboard' );
		}

		$timestamp = strtotime( $date_gmt . ' UTC' );

		if ( false === $timestamp ) {
			return esc_html__( 'Unbekannt', 'shyft-dashboard' );
		}

		return wp_date( 'd.m.Y, H:i', $timestamp );
	}
}
