<?php
/**
 * Period switcher (7 / 30 / 90 days).
 *
 * @package ShyftDashboard
 *
 * @var string $period_switch_modifier Optional BEM modifier.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$period_switch_modifier = isset( $period_switch_modifier ) ? sanitize_html_class( (string) $period_switch_modifier ) : '';
$switch_class           = 'shyft-period-switch shyft-period-switch--' . $period_switch_modifier;
$active_days            = Shyft_Dashboard_Period::get_days();
?>
<nav class="<?php echo esc_attr( $switch_class ); ?>" aria-label="<?php esc_attr_e( 'Auswertungszeitraum', 'shyft-dashboard' ); ?>">
	<?php foreach ( Shyft_Dashboard_Period::ALLOWED_DAYS as $days ) : ?>
		<?php
		$is_active = $active_days === $days;
		$url       = Shyft_Dashboard_Period::get_dashboard_url( $days );
		?>
		<a
			href="<?php echo esc_url( $url ); ?>"
			class="shyft-period-switch__btn<?php echo $is_active ? ' is-active' : ''; ?>"
			<?php echo $is_active ? ' aria-current="page"' : ''; ?>
		>
			<?php
			printf(
				/* translators: %d: number of days */
				esc_html__( '%d Tage', 'shyft-dashboard' ),
				(int) $days
			);
			?>
		</a>
	<?php endforeach; ?>
</nav>
