<?php
/**
 * Theme switch partial.
 *
 * @package ShyftDashboard
 *
 * @var string $theme_switch_modifier Modifier suffix, e.g. header or intro.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$theme_switch_modifier = isset( $theme_switch_modifier ) ? sanitize_html_class( (string) $theme_switch_modifier ) : '';
$switch_class          = 'shyft-theme-switch shyft-theme-switch--' . $theme_switch_modifier;
?>
<div class="<?php echo esc_attr( $switch_class ); ?>" role="group" aria-label="<?php esc_attr_e( 'Farbschema', 'shyft-dashboard' ); ?>">
	<button type="button" class="shyft-theme-switch__btn is-active" data-theme="light" aria-pressed="true" aria-label="<?php esc_attr_e( 'Helles Design', 'shyft-dashboard' ); ?>">
		<svg class="shyft-theme-switch__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
			<circle cx="12" cy="12" r="4"></circle>
			<path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
		</svg>
	</button>
	<button type="button" class="shyft-theme-switch__btn" data-theme="dark" aria-pressed="false" aria-label="<?php esc_attr_e( 'Dunkles Design', 'shyft-dashboard' ); ?>">
		<svg class="shyft-theme-switch__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
			<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
		</svg>
	</button>
</div>
