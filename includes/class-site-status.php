<?php
/**
 * Website status checks with transient caching.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects SSL, update, PHP and backup information.
 */
final class Shyft_Dashboard_Site_Status {

	private const CACHE_KEY = 'shyft_dashboard_site_status_v2';
	private const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Registers hooks (none required at runtime).
	 */
	public static function register(): void {
		// Status is fetched on demand when rendering the dashboard.
	}

	/**
	 * Returns cached or freshly computed status data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_status(): array {
		$cached = get_transient( self::CACHE_KEY );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$data = $this->collect_status();
		set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );

		return $data;
	}

	/**
	 * Runs all status checks.
	 *
	 * @return array<string, mixed>
	 */
	private function collect_status(): array {
		return array(
			'updates'     => $this->get_pending_updates(),
			'php_version' => $this->get_php_version(),
			'ssl'         => $this->get_ssl_status(),
			'indexable'   => $this->get_indexable_status(),
			'sitemap'     => $this->get_sitemap_status(),
			'last_backup' => $this->get_last_backup(),
		);
	}

	/**
	 * Returns the number of pending updates.
	 */
	private function get_pending_updates(): int {
		if ( ! function_exists( 'wp_get_update_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$update_data = wp_get_update_data();

		return (int) ( $update_data['counts']['total'] ?? 0 );
	}

	/**
	 * Returns the current PHP version string.
	 */
	private function get_php_version(): string {
		return phpversion() ?: esc_html__( 'Unbekannt', 'shyft-dashboard' );
	}

	/**
	 * Checks SSL certificate validity for the site domain.
	 *
	 * @return array{active: bool, label: string, expires: string|null}
	 */
	private function get_ssl_status(): array {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( empty( $host ) || ! is_string( $host ) ) {
			return array(
				'active'  => false,
				'label'   => esc_html__( 'SSL-Status nicht ermittelbar', 'shyft-dashboard' ),
				'expires' => null,
			);
		}

		if ( ! is_ssl() && ! $this->is_local_host( $host ) ) {
			return array(
				'active'  => false,
				'label'   => esc_html__( 'SSL nicht aktiv', 'shyft-dashboard' ),
				'expires' => null,
			);
		}

		$context = stream_context_create(
			array(
				'ssl' => array(
					'capture_peer_cert' => true,
					'verify_peer'       => true,
					'verify_peer_name'  => true,
				),
			)
		);

		$connection = @stream_socket_client(
			'ssl://' . $host . ':443',
			$errno,
			$errstr,
			10,
			STREAM_CLIENT_CONNECT,
			$context
		);

		if ( false === $connection ) {
			return array(
				'active'  => false,
				'label'   => esc_html__( 'SSL-Zertifikat nicht abrufbar', 'shyft-dashboard' ),
				'expires' => null,
			);
		}

		$params = stream_context_get_params( $connection );
		fclose( $connection );

		if ( empty( $params['options']['ssl']['peer_certificate'] ) ) {
			return array(
				'active'  => false,
				'label'   => esc_html__( 'SSL-Zertifikat nicht gefunden', 'shyft-dashboard' ),
				'expires' => null,
			);
		}

		$cert   = openssl_x509_parse( $params['options']['ssl']['peer_certificate'] );
		$valid_to = (int) ( $cert['validTo_time_t'] ?? 0 );

		if ( $valid_to <= 0 ) {
			return array(
				'active'  => true,
				'label'   => esc_html__( 'SSL aktiv', 'shyft-dashboard' ),
				'expires' => null,
			);
		}

		$expires_formatted = wp_date( 'd.m.Y', $valid_to );

		return array(
			'active'  => true,
			'label'   => sprintf(
				/* translators: %s: expiry date */
				esc_html__( 'SSL aktiv · gültig bis %s', 'shyft-dashboard' ),
				$expires_formatted
			),
			'expires' => $expires_formatted,
		);
	}

	/**
	 * Checks whether search engines may index the site.
	 *
	 * @return array{active: bool, label: string}
	 */
	private function get_indexable_status(): array {
		$public = (bool) get_option( 'blog_public' );

		return array(
			'active' => $public,
			'label'  => $public
				? esc_html__( 'Indexierung aktiv', 'shyft-dashboard' )
				: esc_html__( 'Indexierung deaktiviert', 'shyft-dashboard' ),
		);
	}

	/**
	 * Checks whether an XML sitemap appears to be available.
	 *
	 * @return array{active: bool, label: string, url: string|null}
	 */
	private function get_sitemap_status(): array {
		$candidates = array(
			home_url( '/sitemap_index.xml' ),
			home_url( '/sitemap.xml' ),
			home_url( '/wp-sitemap.xml' ),
		);

		foreach ( $candidates as $url ) {
			if ( $this->url_is_reachable( $url ) ) {
				return array(
					'active' => true,
					'label'  => esc_html__( 'Sitemap aktiv', 'shyft-dashboard' ),
					'url'    => $url,
				);
			}
		}

		if ( function_exists( 'wp_sitemaps_get_server' ) ) {
			$server = wp_sitemaps_get_server();

			if ( $server && $server->sitemaps_enabled() ) {
				return array(
					'active' => true,
					'label'  => esc_html__( 'WordPress-Sitemap aktiv', 'shyft-dashboard' ),
					'url'    => home_url( '/wp-sitemap.xml' ),
				);
			}
		}

		return array(
			'active' => false,
			'label'  => esc_html__( 'Sitemap nicht erkannt', 'shyft-dashboard' ),
			'url'    => null,
		);
	}

	/**
	 * Performs a lightweight HEAD request to verify a URL responds.
	 *
	 * @param string $url URL to check.
	 */
	private function url_is_reachable( string $url ): bool {
		$response = wp_remote_head(
			$url,
			array(
				'timeout'     => 5,
				'redirection' => 2,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		return $code >= 200 && $code < 400;
	}

	/**
	 * Attempts to detect the last backup timestamp from common plugins.
	 *
	 * @return array{available: bool, label: string|null}
	 */
	private function get_last_backup(): array {
		$updraft = get_option( 'updraft_last_backup' );

		if ( is_array( $updraft ) && ! empty( $updraft['backup_time'] ) ) {
			$timestamp = (int) $updraft['backup_time'];

			return array(
				'available' => true,
				'label'     => wp_date( 'd.m.Y, H:i', $timestamp ),
			);
		}

		$backupbuddy = get_option( 'pb_backupbuddy' );
		if ( is_array( $backupbuddy ) && ! empty( $backupbuddy['status']['last_backup_finish'] ) ) {
			$timestamp = (int) $backupbuddy['status']['last_backup_finish'];

			return array(
				'available' => true,
				'label'     => wp_date( 'd.m.Y, H:i', $timestamp ),
			);
		}

		$last_backup = get_option( 'shyft_dashboard_last_backup' );
		if ( is_string( $last_backup ) && '' !== $last_backup ) {
			return array(
				'available' => true,
				'label'     => sanitize_text_field( $last_backup ),
			);
		}

		return array(
			'available' => false,
			'label'     => null,
		);
	}

	/**
	 * Checks whether the host appears to be local/development.
	 *
	 * @param string $host Hostname.
	 */
	private function is_local_host( string $host ): bool {
		return in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true )
			|| str_ends_with( $host, '.local' )
			|| str_ends_with( $host, '.test' );
	}
}
