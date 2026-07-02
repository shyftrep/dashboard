<?php
/**
 * Link from dashboard header to the public website (home).
 *
 * @package ShyftDashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$home_url = home_url( '/' );
?>
<a
	class="shyft-dashboard__home-link"
	href="<?php echo esc_url( $home_url ); ?>"
	aria-label="<?php esc_attr_e( 'Zur Website', 'shyft-dashboard' ); ?>"
	title="<?php esc_attr_e( 'Zur Website', 'shyft-dashboard' ); ?>"
>
	<svg class="shyft-dashboard__home-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
		<path fill="currentColor" d="M12 3l9 8h-3v10h-5v-6H11v6H6V11H3l9-8zm0 2.84L7.1 11H9v8h3v-6h0v6h3v-8h1.9L12 5.84z"/>
	</svg>
</a>
