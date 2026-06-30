<?php
/**
 * Dashboard navigation tabs.
 *
 * @package ShyftDashboard
 *
 * @var string $active_view Current view slug: overview|angebote|buttons.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_view = $active_view ?? 'overview';
?>
<nav class="shyft-dashboard-nav" aria-label="<?php esc_attr_e( 'Dashboard-Bereiche', 'shyft-dashboard' ); ?>">
	<a
		class="shyft-dashboard-nav__link<?php echo 'overview' === $active_view ? ' is-active' : ''; ?>"
		href="<?php echo esc_url( Shyft_Dashboard_Routing::get_dashboard_url() ); ?>"
	>
		<?php esc_html_e( 'Übersicht', 'shyft-dashboard' ); ?>
	</a>
	<?php if ( Shyft_Dashboard_Offers::can_manage() ) : ?>
		<a
			class="shyft-dashboard-nav__link<?php echo 'angebote' === $active_view ? ' is-active' : ''; ?>"
			href="<?php echo esc_url( Shyft_Dashboard_Offers::get_dashboard_url() ); ?>"
		>
			<?php esc_html_e( 'Angebote', 'shyft-dashboard' ); ?>
		</a>
	<?php endif; ?>
	<?php if ( Shyft_Dashboard_Buttons::can_manage() ) : ?>
		<a
			class="shyft-dashboard-nav__link<?php echo 'buttons' === $active_view ? ' is-active' : ''; ?>"
			href="<?php echo esc_url( Shyft_Dashboard_Buttons::get_dashboard_url() ); ?>"
		>
			<?php esc_html_e( 'Buttons', 'shyft-dashboard' ); ?>
		</a>
	<?php endif; ?>
</nav>
