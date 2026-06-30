<?php
/**
 * Customer CTA buttons (KAUFEN) with per-button shortcodes.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and resolves buy buttons for customers.
 */
final class Shyft_Dashboard_Buttons {

	public const POST_TYPE              = 'shyft_button';
	public const CAP_MANAGE             = 'shyft_manage_buttons';
	public const ACTION_SAVE            = 'shyft_dashboard_save_button';
	public const ACTION_DELETE          = 'shyft_dashboard_delete_button';
	public const NONCE_SAVE             = 'shyft_dashboard_save_button';
	public const NONCE_DELETE           = 'shyft_dashboard_delete_button';
	public const FLASH_TRANSIENT_PREFIX = 'shyft_dashboard_button_flash_';
	public const DEFAULT_LABEL          = 'KAUFEN';

	private const META_TEXT       = '_shyft_button_text';
	private const META_URL        = '_shyft_button_url';
	private const META_CUSTOM_CSS = '_shyft_button_custom_css';
	private const META_ACTIVE     = '_shyft_button_active';

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

			if ( ! $role ) {
				continue;
			}

			if ( ! $role->has_cap( self::CAP_MANAGE ) ) {
				$role->add_cap( self::CAP_MANAGE );
			}
		}
	}

	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Buttons', 'shyft-dashboard' ),
					'singular_name' => __( 'Button', 'shyft-dashboard' ),
				),
				'public'              => true,
				'publicly_queryable'  => false,
				'show_in_nav_menus'   => false,
				'show_ui'             => current_user_can( 'manage_options' ),
				'show_in_menu'        => current_user_can( 'manage_options' ),
				'menu_icon'           => 'dashicons-button',
				'capability_type'     => array( 'shyft_button', 'shyft_buttons' ),
				'map_meta_cap'        => true,
				'capabilities'        => array(
					'edit_post'              => self::CAP_MANAGE,
					'read_post'              => 'read',
					'delete_post'            => self::CAP_MANAGE,
					'edit_posts'             => self::CAP_MANAGE,
					'edit_others_posts'      => self::CAP_MANAGE,
					'publish_posts'          => self::CAP_MANAGE,
					'read_private_posts'     => self::CAP_MANAGE,
					'create_posts'           => self::CAP_MANAGE,
					'delete_posts'           => self::CAP_MANAGE,
					'delete_private_posts'   => self::CAP_MANAGE,
					'delete_published_posts' => self::CAP_MANAGE,
					'delete_others_posts'    => self::CAP_MANAGE,
				),
				'supports'            => array( 'title', 'page-attributes' ),
				'has_archive'         => false,
				'exclude_from_search' => true,
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
		return home_url( '/dashboard/buttons/' );
	}

	public static function get_shortcode( int $button_id ): string {
		return '[clicklabs_button id="' . max( 0, $button_id ) . '"]';
	}

	public static function get_scope_class( int $button_id ): string {
		return 'shyft-button-cta--' . max( 0, $button_id );
	}

	public static function reject_unauthenticated(): void {
		wp_safe_redirect( wp_login_url( self::get_dashboard_url() ) );
		exit;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function get_all_buttons(): array {
		return array_map( array( self::class, 'format_button' ), self::get_published_button_posts() );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get_button( int $button_id ): ?array {
		$post = get_post( $button_id );

		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}

		return self::format_button( $post );
	}

	/**
	 * Loads a published button for the frontend (direct DB query for reliability).
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_public_button( int $button_id ): ?array {
		global $wpdb;

		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE ID = %d AND post_type = %s AND post_status = %s LIMIT 1",
				$button_id,
				self::POST_TYPE,
				'publish'
			)
		);

		if ( empty( $post_id ) ) {
			return null;
		}

		$button = self::get_button( (int) $post_id );

		if ( null === $button || empty( $button['active'] ) ) {
			return null;
		}

		return $button;
	}

	/**
	 * @return list<WP_Post>
	 */
	private static function get_published_button_posts(): array {
		global $wpdb;

		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s ORDER BY menu_order ASC, ID ASC",
				self::POST_TYPE,
				'publish'
			)
		);

		if ( empty( $post_ids ) ) {
			return array();
		}

		$posts = array();

		foreach ( $post_ids as $post_id ) {
			$post = get_post( (int) $post_id );

			if ( $post instanceof WP_Post ) {
				$posts[] = $post;
			}
		}

		return $posts;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function format_button( WP_Post $post ): array {
		$text = (string) get_post_meta( $post->ID, self::META_TEXT, true );

		if ( '' === $text ) {
			$text = get_the_title( $post );
		}

		if ( '' === trim( $text ) ) {
			$text = self::DEFAULT_LABEL;
		}

		return array(
			'id'          => $post->ID,
			'title'       => get_the_title( $post ),
			'text'        => $text,
			'url'         => esc_url_raw( (string) get_post_meta( $post->ID, self::META_URL, true ) ),
			'custom_css'  => (string) get_post_meta( $post->ID, self::META_CUSTOM_CSS, true ),
			'active'      => self::is_button_active( $post->ID ),
			'shortcode'   => self::get_shortcode( $post->ID ),
			'scope_class' => self::get_scope_class( $post->ID ),
			'sort'        => (int) $post->menu_order,
		);
	}

	private static function is_button_active( int $button_id ): bool {
		$active = get_post_meta( $button_id, self::META_ACTIVE, true );

		if ( '' === (string) $active ) {
			return true;
		}

		return '1' === (string) $active;
	}

	public static function handle_save(): void {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'shyft-dashboard' ) );
		}

		check_admin_referer( self::NONCE_SAVE );

		$button_id = isset( $_POST['button_id'] ) ? absint( wp_unslash( (string) $_POST['button_id'] ) ) : 0;
		$text      = isset( $_POST['button_text'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['button_text'] ) ) : '';
		$url       = isset( $_POST['button_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['button_url'] ) ) : '';

		if ( '' === trim( $text ) ) {
			$text = self::DEFAULT_LABEL;
		}

		if ( '' === $url ) {
			self::set_flash( 'error', __( 'Bitte einen Link angeben.', 'shyft-dashboard' ) );
			self::redirect_to_dashboard( $button_id );
		}

		$post_data = array(
			'post_type'   => self::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => $text,
			'menu_order'  => isset( $_POST['button_sort'] ) ? absint( wp_unslash( (string) $_POST['button_sort'] ) ) : 0,
		);

		if ( $button_id > 0 ) {
			$post_data['ID'] = $button_id;
			$result          = wp_update_post( $post_data, true );
		} else {
			$result = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $result ) ) {
			self::set_flash( 'error', $result->get_error_message() );
			self::redirect_to_dashboard( $button_id );
		}

		$button_id = (int) $result;
		$custom_css = isset( $_POST['button_custom_css'] )
			? Shyft_Dashboard_Settings::sanitize_custom_css( wp_unslash( (string) $_POST['button_custom_css'] ) )
			: '';

		update_post_meta( $button_id, self::META_TEXT, $text );
		update_post_meta( $button_id, self::META_URL, $url );
		update_post_meta( $button_id, self::META_CUSTOM_CSS, $custom_css );
		update_post_meta( $button_id, self::META_ACTIVE, isset( $_POST['button_active'] ) ? '1' : '0' );

		self::set_flash( 'success', __( 'Button gespeichert.', 'shyft-dashboard' ) );
		self::redirect_to_dashboard();
	}

	public static function handle_delete(): void {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'shyft-dashboard' ) );
		}

		$button_id = isset( $_GET['button_id'] ) ? absint( wp_unslash( (string) $_GET['button_id'] ) ) : 0;

		check_admin_referer( self::NONCE_DELETE . '_' . $button_id );

		$post = get_post( $button_id );

		if ( $post instanceof WP_Post && self::POST_TYPE === $post->post_type ) {
			wp_trash_post( $button_id );
			self::set_flash( 'success', __( 'Button gelöscht.', 'shyft-dashboard' ) );
		}

		self::redirect_to_dashboard();
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
	 * Removes all buttons on uninstall.
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
