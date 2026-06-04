<?php
/**
 * Matomo Reporting API integration.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches and caches Matomo analytics data server-side.
 */
final class Shyft_Dashboard_Matomo {

	private const CACHE_KEY_PREFIX     = 'shyft_dashboard_matomo_data_v6_';
	private const MAX_OUTLINK_DOMAINS  = 10;
	private const CACHE_TTL              = HOUR_IN_SECONDS;
	private const ERROR_TTL   = 5 * MINUTE_IN_SECONDS;
	private const API_TIMEOUT = 15;

	/** @var list<string> */
	private const WHATSAPP_URL_MATCHERS = array(
		'wa.me',
		'api.whatsapp.com',
		'web.whatsapp.com',
		'whatsapp.com',
	);

	private int $period_days;

	/**
	 * @param int|null $period_days Reporting window in days (7, 30, or 90).
	 */
	public function __construct( ?int $period_days = null ) {
		$days = $period_days ?? Shyft_Dashboard_Period::get_days();

		if ( ! in_array( $days, Shyft_Dashboard_Period::ALLOWED_DAYS, true ) ) {
			$days = Shyft_Dashboard_Period::DEFAULT_DAYS;
		}

		$this->period_days = $days;
	}

	/** @var list<string> */
	private const WHATSAPP_OUTLINK_SEGMENTS = array(
		'outlinkUrl=@wa.me',
		'outlinkUrl=@api.whatsapp.com',
		'outlinkUrl=@web.whatsapp.com',
		'outlinkUrl=@whatsapp.com',
	);

	/**
	 * Registers hooks (none required at runtime).
	 */
	public static function register(): void {
		// Analytics are fetched on demand when rendering the dashboard.
	}

	/**
	 * Returns the URL to the full Matomo statistics dashboard.
	 */
	public function get_stats_url(): string {
		$site_id = $this->resolve_site_id() ?? (int) Shyft_Dashboard_Settings::get_matomo_site_id();

		$url = add_query_arg(
			array(
				'module' => 'CoreHome',
				'action' => 'index',
				'idSite' => max( 1, $site_id ),
				'period' => 'day',
				'date'   => 'today',
			),
			home_url( '/wp-content/plugins/matomo/app/index.php' )
		);

		return $url . '#?period=range&date=' . rawurlencode( $this->get_date_range() ) . '&category=Dashboard_Dashboard&subcategory=1';
	}

	/**
	 * Returns analytics summary and chart data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_analytics_data(): array {
		$cache_key = $this->get_cache_key();
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$data = $this->fetch_analytics_data();

		if ( ! empty( $data['available'] ) ) {
			set_transient( $cache_key, $data, self::CACHE_TTL );
		} else {
			set_transient( $cache_key, $data, self::ERROR_TTL );
		}

		return $data;
	}

	/**
	 * Clears cached analytics (e.g. after settings change).
	 */
	public static function clear_cache(): void {
		delete_transient( 'shyft_dashboard_matomo_data' );
		delete_transient( 'shyft_dashboard_matomo_data_v2' );
		delete_transient( 'shyft_dashboard_matomo_data_v3' );
		delete_transient( 'shyft_dashboard_matomo_data_v4' );
		delete_transient( 'shyft_dashboard_matomo_data_v5' );

		foreach ( Shyft_Dashboard_Period::ALLOWED_DAYS as $days ) {
			delete_transient( self::CACHE_KEY_PREFIX . $days );
		}
	}

	/**
	 * Matomo API date parameter for the active period.
	 */
	private function get_date_range(): string {
		return 'last' . $this->period_days;
	}

	/**
	 * Transient key scoped to the reporting period.
	 */
	private function get_cache_key(): string {
		return self::CACHE_KEY_PREFIX . $this->period_days;
	}

	/**
	 * Fetches data from Matomo using embedded API, REST API, or HTTP API.
	 *
	 * @return array<string, mixed>
	 */
	private function fetch_analytics_data(): array {
		$this->ensure_matomo_plugin_loaded();

		$site_id = $this->resolve_site_id();

		if ( null === $site_id ) {
			return $this->error_response( esc_html__( 'Matomo-Site nicht gefunden.', 'shyft-dashboard' ) );
		}

		$embedded = $this->fetch_via_embedded_matomo( $site_id );
		if ( null !== $embedded ) {
			return $embedded;
		}

		$rest = $this->fetch_via_rest_api( $site_id );
		if ( null !== $rest ) {
			return $rest;
		}

		return $this->fetch_via_http_api( $site_id );
	}

	/**
	 * Loads the Matomo for WordPress plugin if it is active but not yet bootstrapped.
	 */
	private function ensure_matomo_plugin_loaded(): void {
		if ( class_exists( '\WpMatomo\Bootstrap' ) ) {
			return;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$candidates = array(
			'matomo/matomo.php',
			'matomo-for-wordpress/matomo.php',
		);

		foreach ( $candidates as $plugin_file ) {
			if ( is_plugin_active( $plugin_file ) ) {
				$path = WP_PLUGIN_DIR . '/' . $plugin_file;
				if ( is_readable( $path ) ) {
					include_once $path;
				}
				break;
			}
		}
	}

	/**
	 * Resolves the Matomo site ID from the WordPress plugin or settings.
	 */
	private function resolve_site_id(): ?int {
		if ( class_exists( '\WpMatomo\Site' ) ) {
			$site = new \WpMatomo\Site();
			$id   = (int) $site->get_current_matomo_site_id();

			if ( $id > 0 ) {
				return $id;
			}
		}

		$id = (int) Shyft_Dashboard_Settings::get_matomo_site_id();

		return $id > 0 ? $id : null;
	}

	/**
	 * Checks whether the Matomo for WordPress plugin is available.
	 */
	private function can_use_embedded_matomo(): bool {
		return class_exists( '\WpMatomo\Bootstrap' ) && class_exists( '\Piwik\API\Request' );
	}

	/**
	 * Loads analytics via the embedded Matomo PHP API (no token required).
	 *
	 * @param int $site_id Matomo site ID.
	 * @return array<string, mixed>|null
	 */
	private function fetch_via_embedded_matomo( int $site_id ): ?array {
		if ( ! $this->can_use_embedded_matomo() ) {
			return null;
		}

		try {
			\WpMatomo\Bootstrap::do_bootstrap();
			$this->grant_matomo_api_access();

			$summary = $this->process_embedded_request(
				'VisitsSummary.get',
				array(
					'idSite' => $site_id,
					'period' => 'range',
					'date'   => $this->get_date_range(),
				)
			);

			$chart = $this->process_embedded_request(
				'VisitsSummary.get',
				array(
					'idSite' => $site_id,
					'period' => 'day',
					'date'   => $this->get_date_range(),
				)
			);

			if ( empty( $summary ) && empty( $chart ) ) {
				return null;
			}

			$outlink_metrics = $this->resolve_outlink_metrics(
				$site_id,
				function ( array $params ) {
					return $this->process_embedded_request( 'Actions.getOutlinks', $params );
				}
			);

			return $this->build_success_response( $summary, $chart, $outlink_metrics );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Grants full API access for server-side dashboard requests.
	 */
	private function grant_matomo_api_access(): void {
		if ( ! class_exists( '\Piwik\Access' ) ) {
			return;
		}

		$access = \Piwik\Access::getInstance();
		$access->setSuperUserAccess( true );
	}

	/**
	 * Executes a Matomo API request through the embedded plugin.
	 *
	 * @param string               $method API method.
	 * @param array<string, mixed> $params Request parameters.
	 * @return array<string, mixed>
	 */
	private function process_embedded_request( string $method, array $params ): array {
		$params['format'] = 'original';

		$result = \Piwik\API\Request::processRequest( $method, $params, array() );

		if ( class_exists( '\Piwik\API\ResponseBuilder' ) ) {
			$builder = new \Piwik\API\ResponseBuilder( 'json', $params );
			$builder->disableDataTablePostProcessor();
			$json = $builder->getResponse( $result );

			if ( is_string( $json ) ) {
				$decoded = json_decode( $json, true );
				if ( is_array( $decoded ) ) {
					if ( array_key_exists( 'value', $decoded ) && 1 === count( $decoded ) ) {
						return is_array( $decoded['value'] ) ? $decoded['value'] : $decoded;
					}

					return $decoded;
				}
			}
		}

		return $this->normalize_api_data( $result );
	}

	/**
	 * Ensures Matomo REST routes are registered.
	 */
	private function ensure_rest_routes(): void {
		if ( function_exists( 'rest_get_server' ) ) {
			rest_get_server();
		}
	}

	/**
	 * Loads analytics via the Matomo WordPress REST API.
	 *
	 * @param int $site_id Matomo site ID.
	 * @return array<string, mixed>|null
	 */
	private function fetch_via_rest_api( int $site_id ): ?array {
		if ( ! function_exists( 'rest_do_request' ) ) {
			return null;
		}

		$this->ensure_rest_routes();

		$previous_user = get_current_user_id();
		$rest_user_id  = $this->resolve_rest_user_id();

		if ( $rest_user_id <= 0 ) {
			return null;
		}

		wp_set_current_user( $rest_user_id );

		try {
			$summary = $this->rest_visits_summary_get(
				array(
					'period' => 'range',
					'date'   => $this->get_date_range(),
				)
			);

			$chart = $this->rest_visits_summary_get(
				array(
					'period' => 'day',
					'date'   => $this->get_date_range(),
				)
			);

			if ( null === $summary && null === $chart ) {
				return null;
			}

			$outlink_metrics = $this->resolve_outlink_metrics(
				$site_id,
				function ( array $params ) use ( $site_id ) {
					if ( isset( $params['apiModule'] ) ) {
						$report = $this->rest_processed_report( $params );

						if ( ! empty( $report ) ) {
							return $report;
						}
					}

					return $this->process_embedded_outlinks( $site_id );
				},
				true
			);

			return $this->build_success_response(
				$summary ?? array(),
				$chart ?? array(),
				$outlink_metrics
			);
		} catch ( \Throwable $e ) {
			return null;
		} finally {
			wp_set_current_user( $previous_user );
		}
	}

	/**
	 * Returns a user ID that can access Matomo REST endpoints.
	 */
	private function resolve_rest_user_id(): int {
		$current = get_current_user_id();

		if ( $current > 0 && ( user_can( $current, 'view_matomo' ) || user_can( $current, 'manage_options' ) ) ) {
			return $current;
		}

		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => array( 'ID' ),
			)
		);

		if ( ! empty( $admins[0]->ID ) ) {
			return (int) $admins[0]->ID;
		}

		return 0;
	}

	/**
	 * Calls VisitsSummary.get through the Matomo REST API.
	 *
	 * @param array<string, mixed> $params Query parameters.
	 * @return array<string, mixed>|null
	 */
	private function rest_visits_summary_get( array $params ): ?array {
		$routes = array(
			'/matomo/v1/visits_summary/get',
			'/matomo/v1/visits_summary/get/',
		);

		foreach ( $routes as $route ) {
			$request = new WP_REST_Request( 'GET', $route );

			foreach ( $params as $key => $value ) {
				$request->set_param( (string) $key, $value );
			}

			$response = rest_do_request( $request );

			if ( $response->is_error() ) {
				continue;
			}

			$data = $response->get_data();

			if ( ! is_array( $data ) ) {
				continue;
			}

			return $this->normalize_api_data( $data );
		}

		return null;
	}

	/**
	 * Loads analytics via the Matomo HTTP API (fallback when token is configured).
	 *
	 * @param int $site_id Matomo site ID.
	 * @return array<string, mixed>
	 */
	private function fetch_via_http_api( int $site_id ): array {
		$base_url = Shyft_Dashboard_Settings::get_matomo_url();
		$token    = Shyft_Dashboard_Settings::get_matomo_token();

		if ( '' === $base_url || '' === $token ) {
			return $this->error_response();
		}

		$summary = $this->api_request(
			$base_url,
			array(
				'module'     => 'API',
				'method'     => 'VisitsSummary.get',
				'idSite'     => (string) $site_id,
				'period'     => 'range',
				'date'       => $this->get_date_range(),
				'format'     => 'json',
				'token_auth' => $token,
			)
		);

		$chart = $this->api_request(
			$base_url,
			array(
				'module'     => 'API',
				'method'     => 'VisitsSummary.get',
				'idSite'     => (string) $site_id,
				'period'     => 'day',
				'date'       => $this->get_date_range(),
				'format'     => 'json',
				'token_auth' => $token,
			)
		);

		if ( is_wp_error( $summary ) || is_wp_error( $chart ) ) {
			return $this->error_response();
		}

		$summary_data = $this->normalize_api_data( $summary );
		$chart_data   = $this->normalize_api_data( $chart );

		if ( empty( $summary_data ) && empty( $chart_data ) ) {
			return $this->error_response();
		}

		$outlink_metrics = $this->resolve_outlink_metrics(
			$site_id,
			function ( array $params ) use ( $base_url, $token, $site_id ) {
				if ( isset( $params['apiModule'] ) ) {
					unset( $params['apiModule'], $params['apiAction'] );
				}

				$response = $this->api_request(
					$base_url,
					array_merge(
						$params,
						array(
							'module'     => 'API',
							'method'     => 'Actions.getOutlinks',
							'idSite'     => (string) $site_id,
							'format'     => 'json',
							'token_auth' => $token,
						)
					)
				);

				return is_wp_error( $response ) ? array() : $this->normalize_api_data( $response );
			}
		);

		return $this->build_success_response( $summary_data, $chart_data, $outlink_metrics );
	}

	/**
	 * Calls a Matomo report through the WordPress REST processed_report endpoint.
	 *
	 * @param array<string, mixed> $params Query parameters.
	 * @return array<string, mixed>|null
	 */
	private function rest_processed_report( array $params ): ?array {
		if ( ! function_exists( 'rest_do_request' ) ) {
			return null;
		}

		$routes = array(
			'/matomo/v1/api/processed_report',
			'/matomo/v1/api/processed_report/',
		);

		foreach ( $routes as $route ) {
			$request = new WP_REST_Request( 'GET', $route );

			foreach ( $params as $key => $value ) {
				$request->set_param( (string) $key, $value );
			}

			$response = rest_do_request( $request );

			if ( $response->is_error() ) {
				continue;
			}

			$data = $response->get_data();

			if ( ! is_array( $data ) ) {
				continue;
			}

			return $this->normalize_api_data( $data );
		}

		return null;
	}

	/**
	 * Performs a Matomo HTTP API request.
	 *
	 * @param string               $base_url Base Matomo URL.
	 * @param array<string, mixed> $params   Query parameters.
	 * @return array<string, mixed>|WP_Error
	 */
	private function api_request( string $base_url, array $params ) {
		$url = add_query_arg( $params, trailingslashit( $base_url ) . 'index.php' );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => self::API_TIMEOUT,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code || '' === $body ) {
			return new WP_Error( 'shyft_matomo_http', 'Invalid Matomo response.' );
		}

		if ( false !== stripos( $body, '<html' ) || false !== stripos( $body, 'wp-login' ) ) {
			return new WP_Error( 'shyft_matomo_auth', 'Matomo HTTP API requires authentication.' );
		}

		$data = json_decode( $body, true );

		if ( null === $data ) {
			return new WP_Error( 'shyft_matomo_json', 'Invalid Matomo JSON.' );
		}

		if ( isset( $data['result'] ) && 'error' === $data['result'] ) {
			return new WP_Error(
				'shyft_matomo_api',
				(string) ( $data['message'] ?? 'Matomo API error.' )
			);
		}

		return is_array( $data ) ? $data : new WP_Error( 'shyft_matomo_format', 'Unexpected Matomo format.' );
	}

	/**
	 * Builds a successful analytics payload.
	 *
	 * @param array<string, mixed> $summary Summary response.
	 * @param array<string, mixed> $chart   Daily chart response.
	 * @param array{wa_me_clicks: int, outlink_clicks: int, outlink_domains: list<array{domain: string, clicks: int}>} $outlink_metrics Outlink metrics.
	 * @return array<string, mixed>
	 */
	private function build_success_response( array $summary, array $chart, array $outlink_metrics = array() ): array {
		$metrics = $this->extract_summary_metrics( $summary, $chart );

		return array(
			'available'       => true,
			'visits'          => $metrics['visits'],
			'pageviews'       => $metrics['pageviews'],
			'wa_me_clicks'    => (int) ( $outlink_metrics['wa_me_clicks'] ?? 0 ),
			'outlink_clicks'  => (int) ( $outlink_metrics['outlink_clicks'] ?? 0 ),
			'outlink_domains' => is_array( $outlink_metrics['outlink_domains'] ?? null )
				? $outlink_metrics['outlink_domains']
				: array(),
			'chart'           => $this->parse_chart_data( $chart ),
			'error'           => null,
		);
	}

	/**
	 * Resolves total and WhatsApp outlink clicks for the last 90 days.
	 *
	 * @param callable(array<string, mixed>): array<string, mixed> $fetch_outlinks Outlinks fetcher.
	 * @return array{wa_me_clicks: int, outlink_clicks: int, outlink_domains: list<array{domain: string, clicks: int}>}
	 */
	private function resolve_outlink_metrics( int $site_id, callable $fetch_outlinks, bool $for_rest = false ): array {
		$outlinks = $fetch_outlinks( $this->get_outlinks_report_params( $site_id, $for_rest ) );

		$metrics = array(
			'outlink_clicks'  => $this->sum_outlink_hits( $outlinks ),
			'wa_me_clicks'    => $this->extract_wa_me_clicks_from_outlinks( $outlinks ),
			'outlink_domains' => $this->group_outlinks_by_domain( $outlinks ),
		);

		if ( $metrics['wa_me_clicks'] > 0 ) {
			return $metrics;
		}

		foreach ( self::WHATSAPP_OUTLINK_SEGMENTS as $segment ) {
			$params = array_merge(
				$this->get_outlinks_report_params( $site_id, $for_rest ),
				array(
					'segment' => $segment,
				)
			);

			$segment_clicks = $this->sum_outlink_hits(
				$fetch_outlinks( $params )
			);

			if ( $segment_clicks > 0 ) {
				$metrics['wa_me_clicks'] = $segment_clicks;
				break;
			}
		}

		return $metrics;
	}

	/**
	 * Sums all outlink hits from a report payload.
	 *
	 * @param array<string, mixed> $outlinks Outlinks report.
	 */
	private function sum_outlink_hits( array $outlinks ): int {
		$total = 0;

		foreach ( $this->flatten_outlink_rows( $outlinks ) as $row ) {
			$total += (int) ( $row['nb_hits'] ?? $row['nb_visits'] ?? $row['nb_actions'] ?? 0 );
		}

		return $total;
	}

	/**
	 * Groups outlink rows by domain and sums click counts.
	 *
	 * @param array<string, mixed> $outlinks Outlinks report.
	 * @return list<array{domain: string, clicks: int}>
	 */
	private function group_outlinks_by_domain( array $outlinks ): array {
		$domains = array();

		foreach ( $this->flatten_outlink_rows( $outlinks ) as $row ) {
			$label = (string) ( $row['label'] ?? '' );
			$hits  = (int) ( $row['nb_hits'] ?? $row['nb_visits'] ?? $row['nb_actions'] ?? 0 );

			if ( '' === $label || $hits <= 0 ) {
				continue;
			}

			$domain = $this->extract_domain_from_outlink( $label );

			if ( '' === $domain ) {
				continue;
			}

			if ( ! isset( $domains[ $domain ] ) ) {
				$domains[ $domain ] = 0;
			}

			$domains[ $domain ] += $hits;
		}

		if ( empty( $domains ) ) {
			return array();
		}

		arsort( $domains, SORT_NUMERIC );

		$grouped = array();

		foreach ( $domains as $domain => $clicks ) {
			$grouped[] = array(
				'domain' => (string) $domain,
				'clicks' => (int) $clicks,
			);
		}

		return array_slice( $grouped, 0, self::MAX_OUTLINK_DOMAINS );
	}

	/**
	 * Extracts a normalized domain from an outlink URL label.
	 */
	private function extract_domain_from_outlink( string $url ): string {
		$url = trim( rawurldecode( $url ) );

		if ( '' === $url ) {
			return '';
		}

		if ( ! str_contains( $url, '://' ) ) {
			if ( str_starts_with( $url, '//' ) ) {
				$url = 'https:' . $url;
			} elseif ( preg_match( '#^([a-z0-9.-]+\.[a-z]{2,})#i', $url, $matches ) ) {
				return $this->normalize_outlink_domain( $matches[1] );
			}
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! is_string( $host ) || '' === $host ) {
			return '';
		}

		return $this->normalize_outlink_domain( $host );
	}

	/**
	 * Normalizes a domain for display.
	 */
	private function normalize_outlink_domain( string $host ): string {
		$host = strtolower( trim( $host ) );

		if ( str_starts_with( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		return $host;
	}

	/**
	 * Returns common parameters for the Matomo outlinks report.
	 *
	 * @return array<string, mixed>
	 */
	private function get_outlinks_report_params( int $site_id, bool $for_rest = false ): array {
		$params = array(
			'period'       => 'range',
			'date'         => $this->get_date_range(),
			'flat'         => 1,
			'filter_limit' => -1,
		);

		if ( $for_rest ) {
			$params['apiModule'] = 'Actions';
			$params['apiAction'] = 'getOutlinks';
		} else {
			$params['idSite'] = $site_id;
		}

		return $params;
	}

	/**
	 * Loads outlinks via the embedded Matomo API.
	 *
	 * @return array<string, mixed>
	 */
	private function process_embedded_outlinks( int $site_id ): array {
		if ( ! $this->can_use_embedded_matomo() ) {
			return array();
		}

		try {
			\WpMatomo\Bootstrap::do_bootstrap();
			$this->grant_matomo_api_access();

			return $this->process_embedded_request(
				'Actions.getOutlinks',
				$this->get_outlinks_report_params( $site_id )
			);
		} catch ( \Throwable $e ) {
			return array();
		}
	}

	/**
	 * Sums outlink clicks for URLs containing wa.me.
	 *
	 * @param array<string, mixed> $outlinks Normalized outlinks report.
	 */
	private function extract_wa_me_clicks_from_outlinks( array $outlinks ): int {
		$rows   = $this->flatten_outlink_rows( $outlinks );
		$total  = 0;

		foreach ( $rows as $row ) {
			$label = strtolower( (string) ( $row['label'] ?? '' ) );

			if ( ! $this->is_whatsapp_outlink( $label ) ) {
				continue;
			}

			$total += (int) ( $row['nb_hits'] ?? $row['nb_visits'] ?? $row['nb_actions'] ?? 0 );
		}

		return $total;
	}

	/**
	 * Flattens nested Matomo outlink report structures into plain rows.
	 *
	 * @param array<string, mixed> $outlinks Raw or normalized outlinks data.
	 * @return list<array<string, mixed>>
	 */
	private function flatten_outlink_rows( array $outlinks ): array {
		if ( empty( $outlinks ) ) {
			return array();
		}

		$normalized = $this->normalize_api_data( $outlinks );
		$rows       = array();
		$this->collect_outlink_rows( $normalized, $rows );

		return $rows;
	}

	/**
	 * Recursively collects outlink rows from nested API payloads.
	 *
	 * @param mixed                     $node Current node.
	 * @param list<array<string, mixed>> $rows Collected rows.
	 * @param string|null               $label_hint Optional row label from the array key.
	 */
	private function collect_outlink_rows( $node, array &$rows, ?string $label_hint = null ): void {
		if ( ! is_array( $node ) ) {
			return;
		}

		if ( $this->is_outlink_row( $node ) ) {
			if ( empty( $node['label'] ) && null !== $label_hint && '' !== $label_hint ) {
				$node['label'] = $label_hint;
			}

			$rows[] = $node;

			if ( isset( $node['subtable'] ) && is_array( $node['subtable'] ) ) {
				foreach ( $node['subtable'] as $child ) {
					$this->collect_outlink_rows( $child, $rows );
				}
			}

			return;
		}

		foreach ( array( 'reportData', 'data', 'rows', 'subtable' ) as $key ) {
			if ( ! isset( $node[ $key ] ) || ! is_array( $node[ $key ] ) ) {
				continue;
			}

			if ( array_is_list( $node[ $key ] ) ) {
				foreach ( $node[ $key ] as $child ) {
					$this->collect_outlink_rows( $child, $rows );
				}
				continue;
			}

			$this->collect_outlink_rows( $node[ $key ], $rows );
		}

		if ( isset( $node['reportData'] ) || isset( $node['data'] ) || isset( $node['rows'] ) || isset( $node['subtable'] ) ) {
			return;
		}

		foreach ( $node as $key => $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}

			$key_label = is_string( $key ) && ! is_numeric( $key ) ? (string) $key : null;
			$this->collect_outlink_rows( $value, $rows, $key_label );
		}
	}

	/**
	 * Checks whether a row belongs to the outlinks report.
	 *
	 * @param array<string, mixed> $row Row data.
	 */
	private function is_outlink_row( array $row ): bool {
		return isset( $row['nb_hits'] )
			|| ( isset( $row['label'] ) && ( isset( $row['nb_visits'] ) || isset( $row['nb_actions'] ) ) );
	}

	/**
	 * Checks whether an outlink URL points to WhatsApp.
	 */
	private function is_whatsapp_outlink( string $label ): bool {
		if ( '' === $label ) {
			return false;
		}

		$decoded = strtolower( rawurldecode( $label ) );

		foreach ( self::WHATSAPP_URL_MATCHERS as $matcher ) {
			if ( str_contains( $decoded, $matcher ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalizes Matomo API responses into a plain array.
	 *
	 * @param mixed $result Raw API response.
	 * @return array<string, mixed>
	 */
	private function normalize_api_data( $result ): array {
		if ( is_string( $result ) ) {
			$decoded = json_decode( $result, true );

			return is_array( $decoded ) ? $this->normalize_api_data( $decoded ) : array();
		}

		if ( ! is_array( $result ) ) {
			if ( is_object( $result ) && method_exists( $result, 'getRows' ) ) {
				return $this->normalize_api_data_from_datatable( $result );
			}

			return array();
		}

		if ( isset( $result['reportData'] ) && is_array( $result['reportData'] ) ) {
			return $this->normalize_api_data( $result['reportData'] );
		}

		if ( isset( $result['data'] ) && is_array( $result['data'] ) ) {
			return $this->normalize_api_data( $result['data'] );
		}

		if ( $this->is_metrics_row( $result ) ) {
			return $result;
		}

		if ( $this->is_list_of_metric_rows( $result ) ) {
			$normalized = array();

			foreach ( $result as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$label = isset( $row['label'] ) ? (string) $row['label'] : null;

				if ( null !== $label && '' !== $label ) {
					$normalized[ $label ] = $row;
					continue;
				}

				$normalized[] = $row;
			}

			if ( 1 === count( $normalized ) && isset( $normalized[0] ) && is_array( $normalized[0] ) ) {
				if ( $this->is_metrics_row( $normalized[0] ) && ! $this->is_outlink_row( $normalized[0] ) ) {
					return $normalized[0];
				}
			}

			return $normalized;
		}

		return $result;
	}

	/**
	 * Converts a Matomo DataTable into a plain array.
	 *
	 * @param object $result DataTable instance.
	 * @return array<string, mixed>
	 */
	private function normalize_api_data_from_datatable( object $result ): array {
		$normalized = array();

		foreach ( $result->getRows() as $row ) {
			if ( ! is_object( $row ) || ! method_exists( $row, 'getColumns' ) ) {
				continue;
			}

			$columns = $row->getColumns();
			if ( ! is_array( $columns ) ) {
				continue;
			}

			$label = method_exists( $row, 'getColumn' ) ? $row->getColumn( 'label' ) : null;

			if ( null !== $label && '' !== $label ) {
				$columns['label'] = (string) $label;
				$normalized[ (string) $label ] = $columns;
				continue;
			}

			if ( isset( $columns['label'] ) ) {
				$normalized[ (string) $columns['label'] ] = $columns;
				continue;
			}

			$normalized[] = $columns;
		}

		if ( 1 === count( $normalized ) && isset( $normalized[0] ) && is_array( $normalized[0] ) ) {
			if ( $this->is_metrics_row( $normalized[0] ) && ! $this->is_outlink_row( $normalized[0] ) ) {
				return $normalized[0];
			}
		}

		return $normalized;
	}

	/**
	 * Checks whether an array looks like a metrics row.
	 *
	 * @param array<string, mixed> $data Input data.
	 */
	private function is_metrics_row( array $data ): bool {
		return isset( $data['nb_visits'] ) || isset( $data['nb_actions'] ) || isset( $data['nb_pageviews'] );
	}

	/**
	 * Checks whether an array is a list of metric rows.
	 *
	 * @param array<int|string, mixed> $data Input data.
	 */
	private function is_list_of_metric_rows( array $data ): bool {
		if ( empty( $data ) || $this->is_metrics_row( $data ) ) {
			return false;
		}

		foreach ( $data as $row ) {
			if ( is_array( $row ) && ( isset( $row['label'] ) || isset( $row['nb_visits'] ) || isset( $row['nb_hits'] ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extracts visit and pageview totals from summary or chart data.
	 *
	 * @param array<string, mixed> $summary Summary response.
	 * @param array<string, mixed> $chart   Daily chart response.
	 * @return array{visits: int, pageviews: int}
	 */
	private function extract_summary_metrics( array $summary, array $chart ): array {
		if ( $this->is_metrics_row( $summary ) ) {
			return array(
				'visits'    => (int) ( $summary['nb_visits'] ?? 0 ),
				'pageviews' => (int) ( $summary['nb_actions'] ?? $summary['nb_pageviews'] ?? 0 ),
			);
		}

		$visits    = 0;
		$pageviews = 0;

		foreach ( $chart as $key => $metrics ) {
			if ( ! is_array( $metrics ) ) {
				continue;
			}

			$visits    += (int) ( $metrics['nb_visits'] ?? 0 );
			$pageviews += (int) ( $metrics['nb_actions'] ?? $metrics['nb_pageviews'] ?? 0 );
		}

		return array(
			'visits'    => $visits,
			'pageviews' => $pageviews,
		);
	}

	/**
	 * Parses daily visit data for Chart.js.
	 *
	 * @param array<string, mixed> $chart Raw API response.
	 * @return array{labels: array<int, string>, values: array<int, int>}
	 */
	private function parse_chart_data( array $chart ): array {
		$labels = array();
		$values = array();

		if ( $this->is_metrics_row( $chart ) ) {
			return array(
				'labels' => array(),
				'values' => array(),
			);
		}

		if ( $this->is_list_of_metric_rows( $chart ) ) {
			$chart = $this->normalize_api_data( $chart );
		}

		ksort( $chart );

		foreach ( $chart as $date => $metrics ) {
			if ( ! is_array( $metrics ) ) {
				continue;
			}

			$date_key = is_string( $date ) ? $date : (string) ( $metrics['label'] ?? '' );

			if ( '' === $date_key || ! preg_match( '/^\d{4}-\d{2}-\d{2}/', $date_key ) ) {
				continue;
			}

			$labels[] = wp_date( 'd.m.', strtotime( $date_key ) );
			$values[] = (int) ( $metrics['nb_visits'] ?? 0 );
		}

		return array(
			'labels' => $labels,
			'values' => $values,
		);
	}

	/**
	 * Returns a safe fallback response when data is unavailable.
	 *
	 * @param string|null $message Optional error message.
	 * @return array<string, mixed>
	 */
	private function error_response( ?string $message = null ): array {
		return array(
			'available'    => false,
			'visits'       => 0,
			'pageviews'    => 0,
			'wa_me_clicks'    => 0,
			'outlink_clicks'  => 0,
			'outlink_domains' => array(),
			'chart'        => array(
				'labels' => array(),
				'values' => array(),
			),
			'error'        => $message ?? esc_html__( 'Daten aktuell nicht verfügbar', 'shyft-dashboard' ),
		);
	}
}
