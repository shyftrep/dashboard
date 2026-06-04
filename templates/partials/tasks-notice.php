<?php
/**
 * Open tasks notice (shown below intro, above metrics).
 *
 * @package ShyftDashboard
 *
 * @var array{message: string, count: int, anchor: string} $tasks_notice
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tasks_notice = $tasks_notice ?? null;

if ( empty( $tasks_notice['message'] ) ) {
	return;
}

$anchor = ! empty( $tasks_notice['anchor'] ) ? (string) $tasks_notice['anchor'] : 'shyft-tasks';
?>
<div class="shyft-dashboard__notice shyft-dashboard__notice--tasks" role="status">
	<p class="shyft-tasks-notice__text"><?php echo esc_html( (string) $tasks_notice['message'] ); ?></p>
	<a class="shyft-tasks-notice__link" href="#<?php echo esc_attr( $anchor ); ?>">
		<?php esc_html_e( 'Zum Aufgaben-Tracker', 'shyft-dashboard' ); ?>
	</a>
</div>
