<?php
/**
 * Change request form, email and CPT storage.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles customer change requests from the dashboard.
 */
final class Shyft_Dashboard_Change_Request {

	public const POST_TYPE              = 'aenderungswunsch';
	public const ACTION                 = 'shyft_dashboard_change_request';
	public const NONCE_ACTION           = 'shyft_dashboard_change_request';
	public const FLASH_TRANSIENT_PREFIX = 'shyft_dashboard_flash_';
	public const NOTIFY_EMAIL           = 'hallo@shyft.rocks';
	public const MAX_UPLOAD_BYTES       = 5242880; // 5 MB.

	/** @var array<string, string> */
	private const ALLOWED_MIME_TYPES = array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'gif'          => 'image/gif',
		'png'          => 'image/png',
		'webp'         => 'image/webp',
		'pdf'          => 'application/pdf',
		'doc'          => 'application/msword',
		'docx'         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	);

	/**
	 * Registers CPT and form handler hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_post_type' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle_submission' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( self::class, 'reject_unauthenticated' ) );
	}

	/**
	 * Registers the non-public change request post type.
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Änderungswünsche', 'shyft-dashboard' ),
					'singular_name' => __( 'Änderungswunsch', 'shyft-dashboard' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_icon'           => 'dashicons-edit',
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'editor', 'custom-fields' ),
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
			)
		);
	}

	/**
	 * Rejects unauthenticated form submissions.
	 */
	public static function reject_unauthenticated(): void {
		wp_safe_redirect( wp_login_url( Shyft_Dashboard_Routing::get_dashboard_url() ) );
		exit;
	}

	/**
	 * Processes a submitted change request.
	 */
	public static function handle_submission(): void {
		if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
			wp_die( esc_html__( 'Nicht autorisiert.', 'shyft-dashboard' ) );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_ACTION ) ) {
			self::redirect_with_flash(
				'error',
				esc_html__( 'Sicherheitsprüfung fehlgeschlagen. Bitte erneut versuchen.', 'shyft-dashboard' )
			);
		}

		$subject  = isset( $_POST['shyft_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['shyft_subject'] ) ) : '';
		$category = isset( $_POST['shyft_category'] ) ? sanitize_text_field( wp_unslash( $_POST['shyft_category'] ) ) : '';
		$message  = isset( $_POST['shyft_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['shyft_message'] ) ) : '';

		if ( '' === $subject || '' === $message ) {
			self::redirect_with_flash(
				'error',
				esc_html__( 'Bitte fülle Betreff und Nachricht aus.', 'shyft-dashboard' )
			);
		}

		$upload = self::handle_upload();

		if ( is_wp_error( $upload ) ) {
			self::redirect_with_flash( 'error', $upload->get_error_message() );
		}

		$user         = wp_get_current_user();
		$display_name = $user->display_name ?: $user->user_login;
		$post_id      = self::save_request( $subject, $category, $message, $user, $upload );
		$mail_sent    = self::send_notification( $subject, $category, $message, $display_name, $user, $upload );

		if ( false === $post_id ) {
			self::redirect_with_flash(
				'error',
				esc_html__( 'Der Änderungswunsch konnte nicht gespeichert werden.', 'shyft-dashboard' )
			);
		}

		if ( ! $mail_sent ) {
			self::redirect_with_flash(
				'warning',
				esc_html__( 'Wunsch gespeichert, aber die E-Mail konnte nicht versendet werden.', 'shyft-dashboard' )
			);
		}

		self::redirect_with_flash(
			'success',
			esc_html__( 'Dein Änderungswunsch wurde erfolgreich übermittelt.', 'shyft-dashboard' )
		);
	}

	/**
	 * Saves the change request as a custom post type entry.
	 *
	 * @param string  $subject  Request subject.
	 * @param string  $category Request category.
	 * @param string  $message  Request message.
	 * @param WP_User                    $user     Submitting user.
	 * @param array<string, mixed>|null  $upload   Uploaded file data.
	 * @return int|false Post ID on success.
	 */
	private static function save_request( string $subject, string $category, string $message, WP_User $user, ?array $upload = null ) {
		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'private',
				'post_title'   => $subject,
				'post_content' => $message,
				'post_author'  => $user->ID,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return false;
		}

		update_post_meta( $post_id, '_shyft_category', $category );
		update_post_meta( $post_id, '_shyft_customer_name', $user->display_name ?: $user->user_login );
		update_post_meta( $post_id, '_shyft_customer_id', $user->ID );

		Shyft_Dashboard_Tasks::mark_open( (int) $post_id );

		if ( ! empty( $upload['attachment_id'] ) ) {
			update_post_meta( $post_id, '_shyft_attachment_id', (int) $upload['attachment_id'] );
			wp_update_post(
				array(
					'ID'          => (int) $upload['attachment_id'],
					'post_parent' => (int) $post_id,
				)
			);
		}

		return (int) $post_id;
	}

	/**
	 * Sends an email notification to the agency.
	 *
	 * @param string  $subject  Request subject.
	 * @param string  $category Request category.
	 * @param string  $message  Request message.
	 * @param string  $name     Customer display name.
	 * @param WP_User                    $user     Submitting user.
	 * @param array<string, mixed>|null  $upload   Uploaded file data.
	 */
	private static function send_notification( string $subject, string $category, string $message, string $name, WP_User $user, ?array $upload = null ): bool {
		$to = self::NOTIFY_EMAIL;

		$email_subject = sprintf(
			/* translators: 1: site name, 2: request subject */
			__( '[%1$s] Änderungswunsch: %2$s', 'shyft-dashboard' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$subject
		);

		$attachment_note = self::format_attachment_links_for_email( $upload );

		$body = sprintf(
			"%s\n\n%s: %s\n%s: %s\n%s: %s\n\n%s:\n%s%s",
			__( 'Neuer Änderungswunsch über das SHYFT Dashboard', 'shyft-dashboard' ),
			__( 'Kunde', 'shyft-dashboard' ),
			$name,
			__( 'E-Mail', 'shyft-dashboard' ),
			$user->user_email,
			__( 'Kategorie', 'shyft-dashboard' ),
			$category ?: __( 'Allgemein', 'shyft-dashboard' ),
			__( 'Nachricht', 'shyft-dashboard' ),
			$message,
			$attachment_note
		);

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		return (bool) wp_mail( $to, $email_subject, $body, $headers );
	}

	/**
	 * Plain-text block with media library links (no email attachments).
	 *
	 * @param array<string, mixed>|null $upload Uploaded file data.
	 */
	private static function format_attachment_links_for_email( ?array $upload ): string {
		if ( empty( $upload['attachment_id'] ) ) {
			return '';
		}

		$attachment_id = (int) $upload['attachment_id'];
		$file_url        = wp_get_attachment_url( $attachment_id );
		$edit_link       = get_edit_post_link( $attachment_id, 'raw' );
		$file_path       = get_attached_file( $attachment_id );
		$filename        = is_string( $file_path ) ? basename( $file_path ) : '';

		if ( ! is_string( $file_url ) || '' === $file_url ) {
			return '';
		}

		$lines = array(
			'',
			__( 'Anhang (Mediathek)', 'shyft-dashboard' ) . ':',
		);

		if ( '' !== $filename ) {
			$lines[] = __( 'Dateiname', 'shyft-dashboard' ) . ': ' . $filename;
		}

		$lines[] = __( 'Link zur Datei', 'shyft-dashboard' ) . ': ' . $file_url;

		if ( is_string( $edit_link ) && '' !== $edit_link ) {
			$lines[] = __( 'Link in der Mediathek', 'shyft-dashboard' ) . ': ' . $edit_link;
		}

		$lines[] = '';

		return implode( "\n", $lines );
	}

	/**
	 * Validates and stores an optional file upload.
	 *
	 * @return array<string, mixed>|null|WP_Error
	 */
	private static function handle_upload() {
		if ( empty( $_FILES['shyft_attachment'] ) || ! is_array( $_FILES['shyft_attachment'] ) ) {
			return null;
		}

		$file = $_FILES['shyft_attachment'];

		if ( empty( $file['name'] ) || (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) === UPLOAD_ERR_NO_FILE ) {
			return null;
		}

		if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_OK ) ) {
			return new WP_Error(
				'shyft_upload_error',
				esc_html__( 'Die Datei konnte nicht hochgeladen werden.', 'shyft-dashboard' )
			);
		}

		if ( (int) ( $file['size'] ?? 0 ) > self::MAX_UPLOAD_BYTES ) {
			return new WP_Error(
				'shyft_upload_size',
				esc_html__( 'Die Datei ist zu groß (max. 5 MB).', 'shyft-dashboard' )
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$checked = wp_check_filetype_and_ext(
			$file['tmp_name'],
			$file['name'],
			self::ALLOWED_MIME_TYPES
		);

		if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
			return new WP_Error(
				'shyft_upload_type',
				esc_html__( 'Dieser Dateityp ist nicht erlaubt. Erlaubt: JPG, PNG, GIF, WEBP, PDF, DOC, DOCX.', 'shyft-dashboard' )
			);
		}

		$attachment_id = media_handle_upload(
			'shyft_attachment',
			0,
			array(
				'post_title' => sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) ),
			),
			array(
				'test_form' => false,
				'mimes'     => self::ALLOWED_MIME_TYPES,
			)
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$file_path = get_attached_file( $attachment_id );

		return array(
			'attachment_id' => (int) $attachment_id,
			'file'          => is_string( $file_path ) ? $file_path : '',
			'url'           => wp_get_attachment_url( $attachment_id ) ?: '',
			'type'          => get_post_mime_type( $attachment_id ) ?: '',
		);
	}

	/**
	 * Redirects back to the dashboard with a flash message (PRG pattern).
	 *
	 * @param string $type    success|error|warning.
	 * @param string $message User-facing message.
	 */
	private static function redirect_with_flash( string $type, string $message ): void {
		$user_id = get_current_user_id();
		set_transient(
			self::FLASH_TRANSIENT_PREFIX . $user_id,
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS * 5
		);

		wp_safe_redirect( Shyft_Dashboard_Routing::get_dashboard_url() );
		exit;
	}

	/**
	 * Retrieves and clears a one-time flash message for the current user.
	 *
	 * @return array{type: string, message: string}|null
	 */
	public function get_flash_message(): ?array {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return null;
		}

		$key     = self::FLASH_TRANSIENT_PREFIX . $user_id;
		$message = get_transient( $key );

		if ( ! is_array( $message ) || empty( $message['message'] ) ) {
			return null;
		}

		delete_transient( $key );

		return array(
			'type'    => sanitize_key( (string) ( $message['type'] ?? 'info' ) ),
			'message' => sanitize_text_field( (string) $message['message'] ),
		);
	}

	/**
	 * Returns available change request categories.
	 *
	 * @return array<string, string>
	 */
	public function get_categories(): array {
		return array(
			'content'  => __( 'Inhalt & Texte', 'shyft-dashboard' ),
			'design'   => __( 'Design & Layout', 'shyft-dashboard' ),
			'function' => __( 'Funktion & Technik', 'shyft-dashboard' ),
			'other'    => __( 'Sonstiges', 'shyft-dashboard' ),
		);
	}
}
