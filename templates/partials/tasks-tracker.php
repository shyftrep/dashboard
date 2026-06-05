<?php
/**
 * Task tracker for change requests.
 *
 * @package ShyftDashboard
 *
 * @var array{open: list<array<string, mixed>>, done: list<array<string, mixed>>, can_manage: bool} $tasks_tracker
 * @var string                                                                                      $form_action
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tasks_tracker = $tasks_tracker ?? array(
	'open'       => array(),
	'done'       => array(),
	'can_manage' => false,
);
$form_action   = $form_action ?? admin_url( 'admin-post.php' );

$open_tasks  = $tasks_tracker['open'] ?? array();
$done_tasks  = $tasks_tracker['done'] ?? array();
$can_manage  = ! empty( $tasks_tracker['can_manage'] );
$has_tasks   = ! empty( $open_tasks ) || ! empty( $done_tasks );
?>
<section id="shyft-tasks" class="shyft-dashboard__section shyft-dashboard__tasks" aria-label="<?php esc_attr_e( 'Aufgaben-Tracker', 'shyft-dashboard' ); ?>">
	<article class="shyft-card shyft-card--panel">
		<h2 class="shyft-card__heading"><?php esc_html_e( 'Aufgaben-Tracker', 'shyft-dashboard' ); ?></h2>
		<p class="shyft-card__description">
			<?php
			if ( ! empty( $tasks_tracker['can_manage'] ) ) {
				esc_html_e( 'Änderungswünsche aus dem Formular – abhaken, sobald sie erledigt sind.', 'shyft-dashboard' );
			} else {
				esc_html_e( 'Status deiner eingereichten Änderungswünsche.', 'shyft-dashboard' );
			}
			?>
		</p>

		<?php if ( ! $has_tasks ) : ?>
			<p class="shyft-empty"><?php esc_html_e( 'Noch keine Aufgaben vorhanden.', 'shyft-dashboard' ); ?></p>
		<?php else : ?>
			<?php if ( ! empty( $open_tasks ) ) : ?>
				<h3 class="shyft-tasks__subheading"><?php esc_html_e( 'Offen', 'shyft-dashboard' ); ?></h3>
				<ul class="shyft-tasks-list">
					<?php foreach ( $open_tasks as $task ) : ?>
						<?php include SHYFT_DASHBOARD_PATH . 'templates/partials/task-item.php'; ?>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( ! empty( $done_tasks ) ) : ?>
				<h3 class="shyft-tasks__subheading shyft-tasks__subheading--done"><?php esc_html_e( 'Erledigt', 'shyft-dashboard' ); ?></h3>
				<ul class="shyft-tasks-list shyft-tasks-list--done">
					<?php foreach ( $done_tasks as $task ) : ?>
						<?php include SHYFT_DASHBOARD_PATH . 'templates/partials/task-item.php'; ?>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endif; ?>
	</article>
</section>
