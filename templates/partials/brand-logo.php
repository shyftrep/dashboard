<?php
/**
 * Dashboard brand logo (theme-aware when using bundled assets).
 *
 * @package ShyftDashboard
 *
 * @var string $logo_modifier Optional BEM modifier, e.g. "warmup".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$logo_modifier = $logo_modifier ?? '';
$logo_class    = 'shyft-dashboard__logo';

if ( '' !== $logo_modifier ) {
	$logo_class .= ' shyft-dashboard__logo--' . sanitize_html_class( $logo_modifier );
}

$uses_bundled = Shyft_Dashboard_Settings::uses_bundled_logo();
?>
<?php if ( $uses_bundled ) : ?>
	<img
		class="<?php echo esc_attr( $logo_class ); ?> shyft-dashboard__logo--theme-light"
		src="<?php echo esc_url( Shyft_Dashboard_Settings::get_bundled_logo_url( 'dark' ) ); ?>"
		alt="<?php esc_attr_e( 'SHYFT', 'shyft-dashboard' ); ?>"
		width="120"
		height="40"
		decoding="async"
	>
	<img
		class="<?php echo esc_attr( $logo_class ); ?> shyft-dashboard__logo--theme-dark"
		src="<?php echo esc_url( Shyft_Dashboard_Settings::get_bundled_logo_url( 'light' ) ); ?>"
		alt="<?php esc_attr_e( 'SHYFT', 'shyft-dashboard' ); ?>"
		width="120"
		height="40"
		decoding="async"
	>
<?php else : ?>
	<img
		class="<?php echo esc_attr( $logo_class ); ?>"
		src="<?php echo esc_url( Shyft_Dashboard_Settings::get_logo_url() ); ?>"
		alt="<?php esc_attr_e( 'SHYFT', 'shyft-dashboard' ); ?>"
		width="120"
		height="40"
		decoding="async"
	>
<?php endif; ?>
