<?php
/**
 * Task tracker for change requests (Änderungswünsche).
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists and completes dashboard tasks created from the change request form.
 */
final class Shyft_Dashboard_Tasks {

	public const META_COMPLETED = '_shyft_completed';
	public const ACTION_TOGGLE  = 'shyft_dashboard_toggle_task';
	public const NONCE_TOGGLE   = 'shyft_dashboard_toggle_task';

	/**
	 * Registers task handler hooks.
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION_TOGGLE, array( self::class, 'handle_toggle' ) );
	}

	/**
	 * Marks a change request as an open task.
	 */
	public static function mark_open( int $post_id ): void {
		update_post_meta( $post_id, self::META_COMPLETED, '0' );
	}

	/**
	 * Whether the user can mark tasks as done.
	 */
	public static function can_manage_tasks( ?WP_User $user = null ): bool {
		$user = $user ?? wp_get_current_user();

		return $user instanceof WP_User && $user->exists() && user_can( $user, 'manage_options' );
	}

	/**
	 * Notice shown near the top when open tasks exist.
	 *
	 * @return array{message: string, count: int, anchor: string}|null
	 */
	public static function get_notice( ?WP_User $user = null ): ?array {
		$user = $user ?? wp_get_current_user();

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return null;
		}

		$open_count = self::count_open_tasks( $user );

		if ( $open_count <= 0 ) {
			return null;
		}

		if ( self::can_manage_tasks( $user ) ) {
			$message = sprintf(
				/* translators: %d: number of open tasks */
				_n(
					'%d offene Aufgabe wartet auf Erledigung.',
					'%d offene Aufgaben warten auf Erledigung.',
					$open_count,
					'shyft-dashboard'
				),
				$open_count
			);
		} else {
			$message = sprintf(
				/* translators: %d: number of open tasks */
				_n(
					'Du hast %d Änderungswunsch in Bearbeitung.',
					'Du hast %d Änderungswünsche in Bearbeitung.',
					$open_count,
					'shyft-dashboard'
				),
				$open_count
			);
		}

		return array(
			'message' => $message,
			'count'   => $open_count,
			'anchor'  => 'shyft-tasks',
		);
	}

	/**
	 * Task lists for the dashboard tracker section.
	 *
	 * @return array{open: list<array<string, mixed>>, done: list<array<string, mixed>>, can_manage: bool}
	 */
	public static function get_tracker_data( ?WP_User $user = null ): array {
		$user       = $user ?? wp_get_current_user();
		$can_manage = self::can_manage_tasks( $user );
		$posts      = self::query_tasks( $user );
		$categories = ( new Shyft_Dashboard_Change_Request() )->get_categories();

		$open = array();
		$done = array();

		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$task = self::format_task( $post, $categories, $can_manage );

			if ( ! empty( $task['completed'] ) ) {
				$done[] = $task;
			} else {
				$open[] = $task;
			}
		}

		return array(
			'open'        => $open,
			'done'        => $done,
			'can_manage'  => $can_manage,
		);
	}

	/**
	 * Toggles task completion (admin only).
	 */
	public static function handle_toggle(): void {
		if ( ! is_user_logged_in() || ! self::can_manage_tasks() ) {
			wp_die( esc_html__( 'Nicht autorisiert.', 'shyft-dashboard' ) );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_TOGGLE ) ) {
			wp_die( esc_html__( 'Sicherheitsprüfung fehlgeschlagen.', 'shyft-dashboard' ) );
		}

		$post_id = isset( $_POST['task_id'] ) ? absint( wp_unslash( (string) $_POST['task_id'] ) ) : 0;

		if ( $post_id <= 0 ) {
			self::redirect_to_tracker();
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || Shyft_Dashboard_Change_Request::POST_TYPE !== $post->post_type ) {
			wp_die( esc_html__( 'Aufgabe nicht gefunden.', 'shyft-dashboard' ) );
		}

		$mark_done = ! empty( $_POST['completed'] );
		update_post_meta( $post_id, self::META_COMPLETED, $mark_done ? '1' : '0' );

		self::redirect_to_tracker();
	}

	/**
	 * @param WP_User|null $user User object.
	 */
	private static function count_open_tasks( ?WP_User $user = null ): int {
		$user = $user ?? wp_get_current_user();

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return 0;
		}

		$query = self::build_query_args( $user );
		$query['fields']         = 'ids';
		$query['posts_per_page'] = -1;
		$query['meta_query']     = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'relation' => 'OR',
			array(
				'key'     => self::META_COMPLETED,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => self::META_COMPLETED,
				'value'   => '1',
				'compare' => '!=',
			),
		);

		$posts = get_posts( $query );

		return is_array( $posts ) ? count( $posts ) : 0;
	}

	/**
	 * @return list<WP_Post>
	 */
	private static function query_tasks( ?WP_User $user = null ): array {
		$user  = $user ?? wp_get_current_user();
		$query = self::build_query_args( $user );
		$posts = get_posts( $query );

		return is_array( $posts ) ? $posts : array();
	}

	/**
	 * @param WP_User $user User object.
	 * @return array<string, mixed>
	 */
	private static function build_query_args( WP_User $user ): array {
		$args = array(
			'post_type'      => Shyft_Dashboard_Change_Request::POST_TYPE,
			'post_status'    => 'private',
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! self::can_manage_tasks( $user ) ) {
			$args['author'] = $user->ID;
		}

		return $args;
	}

	/**
	 * @param WP_Post               $post       Change request post.
	 * @param array<string, string> $categories Category labels.
	 * @param bool                  $can_manage Whether the viewer can toggle completion.
	 * @return array<string, mixed>
	 */
	private static function format_task( WP_Post $post, array $categories, bool $can_manage ): array {
		$completed    = '1' === (string) get_post_meta( $post->ID, self::META_COMPLETED, true );
		$category_key = (string) get_post_meta( $post->ID, '_shyft_category', true );
		$attachment_id = (int) get_post_meta( $post->ID, '_shyft_attachment_id', true );
		$timestamp    = get_post_timestamp( $post );
		$customer     = (string) get_post_meta( $post->ID, '_shyft_customer_name', true );

		if ( '' === $customer ) {
			$author = get_user_by( 'id', (int) $post->post_author );
			$customer = $author instanceof WP_User ? ( $author->display_name ?: $author->user_login ) : '';
		}

		$message = wp_trim_words( wp_strip_all_tags( $post->post_content ), 24, '…' );

		return array(
			'id'             => $post->ID,
			'title'          => $post->post_title,
			'message'        => $message,
			'category'       => $categories[ $category_key ] ?? $category_key,
			'customer'       => $customer,
			'date'           => $timestamp ? wp_date( 'd.m.Y H:i', $timestamp ) : '',
			'date_raw'       => $timestamp ? wp_date( 'c', $timestamp ) : '',
			'completed'      => $completed,
			'attachment_url' => $attachment_id > 0 ? ( wp_get_attachment_url( $attachment_id ) ?: '' ) : '',
			'can_manage'     => $can_manage,
		);
	}

	private static function redirect_to_tracker(): void {
		wp_safe_redirect( Shyft_Dashboard_Routing::get_dashboard_url() . '#shyft-tasks' );
		exit;
	}
}
