<?php
/**
 * Custom user role handling.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages dashboard-related user roles.
 */
final class Shyft_Dashboard_Roles {

	public const ROLE_KUNDE  = 'kunde';
	public const ROLE_EDITOR = 'editor';

	/** @deprecated Use ROLE_KUNDE */
	public const ROLE = self::ROLE_KUNDE;

	/**
	 * Registers role-related hooks.
	 */
	public static function register(): void {
		// Role is created on activation; no runtime hooks required.
	}

	/**
	 * Creates the customer role with minimal capabilities.
	 */
	public static function create_role(): void {
		remove_role( self::ROLE_KUNDE );

		add_role(
			self::ROLE_KUNDE,
			__( 'Kunde', 'shyft-dashboard' ),
			array(
				'read'                => true,
				'shyft_manage_offers' => true,
			)
		);
	}

	/**
	 * Removes the customer role on uninstall.
	 */
	public static function remove_role(): void {
		remove_role( self::ROLE_KUNDE );
	}

	/**
	 * Checks whether the given user has the customer role.
	 *
	 * @param WP_User|null $user User object.
	 */
	public static function is_kunde( ?WP_User $user = null ): bool {
		return self::user_has_role( self::ROLE_KUNDE, $user );
	}

	/**
	 * Checks whether the given user has the editor (Redakteur) role.
	 *
	 * @param WP_User|null $user User object.
	 */
	public static function is_editor( ?WP_User $user = null ): bool {
		return self::user_has_role( self::ROLE_EDITOR, $user );
	}

	/**
	 * Users who land on the dashboard after login (excluding administrators).
	 *
	 * @param WP_User|null $user User object.
	 */
	public static function uses_dashboard( ?WP_User $user = null ): bool {
		$user = $user ?? wp_get_current_user();

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}

		if ( user_can( $user, 'manage_options' ) ) {
			return false;
		}

		return self::is_kunde( $user ) || self::is_editor( $user );
	}

	/**
	 * Whether the user can open the website frontend to edit with Elementor.
	 *
	 * @param WP_User|null $user User object.
	 */
	public static function can_edit_website( ?WP_User $user = null ): bool {
		$user = $user ?? wp_get_current_user();

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}

		return user_can( $user, 'edit_pages' ) || user_can( $user, 'edit_posts' );
	}

	/**
	 * Checks whether a user has a specific role slug.
	 *
	 * @param string       $role Role slug.
	 * @param WP_User|null $user User object.
	 */
	private static function user_has_role( string $role, ?WP_User $user = null ): bool {
		$user = $user ?? wp_get_current_user();

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}

		return in_array( $role, (array) $user->roles, true );
	}
}
