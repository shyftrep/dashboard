<?php
/**
 * Customer offers – standard and time-limited promotions.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and resolves website offers for customers.
 */
final class Shyft_Dashboard_Offers {

	public const POST_TYPE              = 'shyft_angebot';
	public const CAP_MANAGE             = 'shyft_manage_offers';
	public const ACTION_SAVE            = 'shyft_dashboard_save_offer';
	public const ACTION_DELETE          = 'shyft_dashboard_delete_offer';
	public const NONCE_SAVE             = 'shyft_dashboard_save_offer';
	public const NONCE_DELETE           = 'shyft_dashboard_delete_offer';
	public const FLASH_TRANSIENT_PREFIX = 'shyft_dashboard_offer_flash_';

	public const TYPE_STANDARD = 'standard';
	public const TYPE_TIMED    = 'timed';

	private const META_TYPE          = '_shyft_offer_type';
	private const META_ACTIVE        = '_shyft_offer_active';
	private const META_STARTS_AT     = '_shyft_offer_starts_at';
	private const META_ENDS_AT       = '_shyft_offer_ends_at';
	private const META_IMAGE_ID      = '_shyft_offer_image_id';
	private const META_HEADLINE      = '_shyft_offer_headline';
	private const META_TEXT          = '_shyft_offer_text';
	private const META_ICONS         = '_shyft_offer_icons';
	private const META_BUTTON_LABEL  = '_shyft_offer_button_label';
	private const META_BUTTON_URL    = '_shyft_offer_button_url';

	/**
	 * Registers CPT, caps, and dashboard handlers.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_post_type' ), 5 );
		add_action( 'init', array( self::class, 'ensure_capabilities' ), 6 );
		add_action( 'admin_post_' . self::ACTION_SAVE, array( self::class, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_DELETE, array( self::class, 'handle_delete' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_SAVE, array( self::class, 'reject_unauthenticated' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_DELETE, array( self::class, 'reject_unauthenticated' ) );
	}

	/**
	 * Adds the manage capability to dashboard roles.
	 */
	public static function ensure_capabilities(): void {
		$roles = array( 'administrator', Shyft_Dashboard_Roles::ROLE_KUNDE, Shyft_Dashboard_Roles::ROLE_EDITOR );

		foreach ( $roles as $role_slug ) {
			$role = get_role( $role_slug );

			if ( $role && ! $role->has_cap( self::CAP_MANAGE ) ) {
				$role->add_cap( self::CAP_MANAGE );
			}
		}
	}

	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Angebote', 'shyft-dashboard' ),
					'singular_name' => __( 'Angebot', 'shyft-dashboard' ),
				),
				'public'              => false,
				'show_ui'             => current_user_can( 'manage_options' ),
				'show_in_menu'        => current_user_can( 'manage_options' ),
				'menu_icon'           => 'dashicons-megaphone',
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'page-attributes' ),
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
			)
		);
	}

	public static function can_manage( ?WP_User $user = null ): bool {
		$user = $user ?? wp_get_current_user();

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}

		return user_can( $user, self::CAP_MANAGE ) || user_can( $user, 'manage_options' );
	}

	public static function get_dashboard_url(): string {
		return home_url( '/dashboard/angebote/' );
	}

	public static function reject_unauthenticated(): void {
		wp_safe_redirect( wp_login_url( self::get_dashboard_url() ) );
		exit;
	}

	/**
	 * Offers visible on the public website.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function get_public_offers(): array {
		$timed = self::get_active_timed_offers();

		if ( ! empty( $timed ) ) {
			return $timed;
		}

		return self::get_active_standard_offers();
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function get_all_offers(): array {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

		return array_map( array( self::class, 'format_offer' ), $posts );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get_offer( int $offer_id ): ?array {
		$post = get_post( $offer_id );

		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}

		return self::format_offer( $post );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function get_active_standard_offers(): array {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => self::META_TYPE,
						'value' => self::TYPE_STANDARD,
					),
					array(
						'key'   => self::META_ACTIVE,
						'value' => '1',
					),
				),
			)
		);

		return array_map( array( self::class, 'format_offer' ), $posts );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function get_active_timed_offers(): array {
		$now   = time();
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'   => self::META_TYPE,
						'value' => self::TYPE_TIMED,
					),
				),
			)
		);

		$active = array();

		foreach ( $posts as $post ) {
			$start = (int) get_post_meta( $post->ID, self::META_STARTS_AT, true );
			$end   = (int) get_post_meta( $post->ID, self::META_ENDS_AT, true );

			if ( $start > 0 && $end > 0 && $start <= $now && $end >= $now ) {
				$active[] = self::format_offer( $post );
			}
		}

		return $active;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function format_offer( WP_Post $post ): array {
		$image_id  = (int) get_post_meta( $post->ID, self::META_IMAGE_ID, true );
		$image_url = $image_id > 0 ? (string) wp_get_attachment_image_url( $image_id, 'large' ) : '';
		$type      = (string) get_post_meta( $post->ID, self::META_TYPE, true );

		if ( '' === $type ) {
			$type = self::TYPE_STANDARD;
		}

		return array(
			'id'            => $post->ID,
			'title'         => get_the_title( $post ),
			'type'          => $type,
			'active'        => '1' === (string) get_post_meta( $post->ID, self::META_ACTIVE, true ),
			'starts_at'     => (int) get_post_meta( $post->ID, self::META_STARTS_AT, true ),
			'ends_at'       => (int) get_post_meta( $post->ID, self::META_ENDS_AT, true ),
			'image_id'      => $image_id,
			'image_url'     => $image_url,
			'headline'      => (string) get_post_meta( $post->ID, self::META_HEADLINE, true ),
			'text'          => (string) get_post_meta( $post->ID, self::META_TEXT, true ),
			'icons'         => self::decode_icons( (string) get_post_meta( $post->ID, self::META_ICONS, true ) ),
			'button_label'  => (string) get_post_meta( $post->ID, self::META_BUTTON_LABEL, true ),
			'button_url'    => esc_url_raw( (string) get_post_meta( $post->ID, self::META_BUTTON_URL, true ) ),
			'sort'          => (int) $post->menu_order,
			'is_timed_live' => self::TYPE_TIMED === $type && self::is_timed_live( $post->ID ),
		);
	}

	public static function is_timed_live( int $offer_id ): bool {
		$now   = time();
		$start = (int) get_post_meta( $offer_id, self::META_STARTS_AT, true );
		$end   = (int) get_post_meta( $offer_id, self::META_ENDS_AT, true );

		return $start > 0 && $end > 0 && $start <= $now && $end >= $now;
	}

	/**
	 * @return list<array{icon: string, label: string}>
	 */
	private static function decode_icons( string $raw ): array {
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			return array();
		}

		$icons = array();

		foreach ( $data as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$icon  = sanitize_text_field( (string) ( $item['icon'] ?? '' ) );
			$label = sanitize_text_field( (string) ( $item['label'] ?? '' ) );

			if ( '' === $icon && '' === $label ) {
				continue;
			}

			$icons[] = array(
				'icon'  => $icon,
				'label' => $label,
			);
		}

		return $icons;
	}

	public static function handle_save(): void {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'shyft-dashboard' ) );
		}

		check_admin_referer( self::NONCE_SAVE );

		$offer_id = isset( $_POST['offer_id'] ) ? absint( wp_unslash( (string) $_POST['offer_id'] ) ) : 0;
		$type     = isset( $_POST['offer_type'] ) ? sanitize_key( wp_unslash( (string) $_POST['offer_type'] ) ) : self::TYPE_STANDARD;

		if ( ! in_array( $type, array( self::TYPE_STANDARD, self::TYPE_TIMED ), true ) ) {
			$type = self::TYPE_STANDARD;
		}

		$headline = isset( $_POST['offer_headline'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['offer_headline'] ) ) : '';
		$text     = isset( $_POST['offer_text'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['offer_text'] ) ) : '';

		if ( '' === trim( $headline ) ) {
			self::set_flash( 'error', __( 'Bitte eine Überschrift angeben.', 'shyft-dashboard' ) );
			self::redirect_to_dashboard( $offer_id );
		}

		$post_data = array(
			'post_type'   => self::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => $headline,
			'menu_order'  => isset( $_POST['offer_sort'] ) ? absint( wp_unslash( (string) $_POST['offer_sort'] ) ) : 0,
		);

		if ( $offer_id > 0 ) {
			$post_data['ID'] = $offer_id;
			$result          = wp_update_post( $post_data, true );
		} else {
			$result = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $result ) ) {
			self::set_flash( 'error', $result->get_error_message() );
			self::redirect_to_dashboard( $offer_id );
		}

		$offer_id = (int) $result;

		update_post_meta( $offer_id, self::META_TYPE, $type );
		update_post_meta( $offer_id, self::META_HEADLINE, $headline );
		update_post_meta( $offer_id, self::META_TEXT, $text );
		update_post_meta( $offer_id, self::META_ACTIVE, isset( $_POST['offer_active'] ) ? '1' : '0' );
		update_post_meta( $offer_id, self::META_IMAGE_ID, isset( $_POST['offer_image_id'] ) ? absint( wp_unslash( (string) $_POST['offer_image_id'] ) ) : 0 );
		update_post_meta( $offer_id, self::META_BUTTON_LABEL, isset( $_POST['offer_button_label'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['offer_button_label'] ) ) : '' );
		update_post_meta( $offer_id, self::META_BUTTON_URL, isset( $_POST['offer_button_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['offer_button_url'] ) ) : '' );
		update_post_meta( $offer_id, self::META_ICONS, wp_json_encode( self::sanitize_icons_from_request() ) );

		if ( self::TYPE_TIMED === $type ) {
			update_post_meta( $offer_id, self::META_STARTS_AT, self::parse_datetime_local( 'offer_starts_at' ) );
			update_post_meta( $offer_id, self::META_ENDS_AT, self::parse_datetime_local( 'offer_ends_at' ) );
		} else {
			delete_post_meta( $offer_id, self::META_STARTS_AT );
			delete_post_meta( $offer_id, self::META_ENDS_AT );
		}

		self::set_flash( 'success', __( 'Angebot gespeichert.', 'shyft-dashboard' ) );
		self::redirect_to_dashboard();
	}

	public static function handle_delete(): void {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'shyft-dashboard' ) );
		}

		$offer_id = isset( $_GET['offer_id'] ) ? absint( wp_unslash( (string) $_GET['offer_id'] ) ) : 0;

		check_admin_referer( self::NONCE_DELETE . '_' . $offer_id );

		$post = get_post( $offer_id );

		if ( $post instanceof WP_Post && self::POST_TYPE === $post->post_type ) {
			wp_trash_post( $offer_id );
			self::set_flash( 'success', __( 'Angebot gelöscht.', 'shyft-dashboard' ) );
		}

		self::redirect_to_dashboard();
	}

	/**
	 * @return list<array{icon: string, label: string}>
	 */
	private static function sanitize_icons_from_request(): array {
		$icons  = array();
		$raw    = $_POST['offer_icons'] ?? array();
		$labels = $_POST['offer_icon_labels'] ?? array();

		if ( ! is_array( $raw ) || ! is_array( $labels ) ) {
			return array();
		}

		$count = min( 8, max( count( $raw ), count( $labels ) ) );

		for ( $i = 0; $i < $count; $i++ ) {
			$icon  = sanitize_text_field( wp_unslash( (string) ( $raw[ $i ] ?? '' ) ) );
			$label = sanitize_text_field( wp_unslash( (string) ( $labels[ $i ] ?? '' ) ) );

			if ( '' === $icon && '' === $label ) {
				continue;
			}

			$icons[] = array(
				'icon'  => $icon,
				'label' => $label,
			);
		}

		return $icons;
	}

	private static function parse_datetime_local( string $field ): int {
		if ( ! isset( $_POST[ $field ] ) ) {
			return 0;
		}

		$value = sanitize_text_field( wp_unslash( (string) $_POST[ $field ] ) );

		if ( '' === $value ) {
			return 0;
		}

		$timestamp = strtotime( $value );

		return false === $timestamp ? 0 : $timestamp;
	}

	public static function format_datetime_local( int $timestamp ): string {
		if ( $timestamp <= 0 ) {
			return '';
		}

		return wp_date( 'Y-m-d\TH:i', $timestamp );
	}

	public static function format_datetime_label( int $timestamp ): string {
		if ( $timestamp <= 0 ) {
			return '—';
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/**
	 * @return array{type: string, message: string}|null
	 */
	public static function get_flash_message(): ?array {
		$user_id = get_current_user_id();
		$key     = self::FLASH_TRANSIENT_PREFIX . $user_id;
		$flash   = get_transient( $key );

		if ( ! is_array( $flash ) ) {
			return null;
		}

		delete_transient( $key );

		return array(
			'type'    => (string) ( $flash['type'] ?? 'success' ),
			'message' => (string) ( $flash['message'] ?? '' ),
		);
	}

	private static function set_flash( string $type, string $message ): void {
		set_transient(
			self::FLASH_TRANSIENT_PREFIX . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);
	}

	private static function redirect_to_dashboard( int $edit_id = 0 ): void {
		$url = self::get_dashboard_url();

		if ( $edit_id > 0 ) {
			$url = add_query_arg( 'edit', (string) $edit_id, $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Removes all offers on uninstall.
	 */
	public static function delete_all(): void {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $posts as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
}
