<?php
/**
 * Single task row in the tracker.
 *
 * @package ShyftDashboard
 *
 * @var array<string, mixed> $task
 * @var string               $form_action
 * @var bool                 $can_manage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$task        = $task ?? array();
$form_action = $form_action ?? admin_url( 'admin-post.php' );

$task_id     = (int) ( $task['id'] ?? 0 );
$completed   = ! empty( $task['completed'] );
$can_manage  = ! empty( $can_manage ) || ! empty( $task['can_manage'] );
$item_class  = 'shyft-task' . ( $completed ? ' shyft-task--done' : '' );
?>
<li class="shyft-tasks-list__item">
	<article class="<?php echo esc_attr( $item_class ); ?>">
		<?php if ( $can_manage && $task_id > 0 ) : ?>
			<form class="shyft-task__toggle" method="post" action="<?php echo esc_url( $form_action ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( Shyft_Dashboard_Tasks::ACTION_TOGGLE ); ?>">
				<input type="hidden" name="task_id" value="<?php echo esc_attr( (string) $task_id ); ?>">
				<?php wp_nonce_field( Shyft_Dashboard_Tasks::NONCE_TOGGLE ); ?>
				<label class="shyft-task__checkbox-label">
					<input
						type="checkbox"
						name="completed"
						value="1"
						class="shyft-task__checkbox"
						<?php checked( $completed ); ?>
						onchange="this.form.submit()"
					>
					<span class="shyft-task__checkbox-ui" aria-hidden="true"></span>
					<span class="screen-reader-text">
						<?php
						echo esc_html(
							$completed
								? __( 'Als offen markieren', 'shyft-dashboard' )
								: __( 'Als erledigt markieren', 'shyft-dashboard' )
						);
						?>
					</span>
				</label>
			</form>
		<?php endif; ?>

		<div class="shyft-task__body">
			<div class="shyft-task__header">
				<h4 class="shyft-task__title"><?php echo esc_html( (string) ( $task['title'] ?? '' ) ); ?></h4>
				<?php if ( ! $can_manage ) : ?>
					<span class="shyft-task__status<?php echo $completed ? ' is-done' : ' is-open'; ?>">
						<?php
						echo esc_html(
							$completed
								? __( 'Erledigt', 'shyft-dashboard' )
								: __( 'In Bearbeitung', 'shyft-dashboard' )
						);
						?>
					</span>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $task['message'] ) ) : ?>
				<p class="shyft-task__message"><?php echo esc_html( (string) $task['message'] ); ?></p>
			<?php endif; ?>

			<ul class="shyft-task__meta">
				<?php if ( ! empty( $task['category'] ) ) : ?>
					<li><?php echo esc_html( (string) $task['category'] ); ?></li>
				<?php endif; ?>
				<?php if ( $can_manage && ! empty( $task['customer'] ) ) : ?>
					<li><?php echo esc_html( (string) $task['customer'] ); ?></li>
				<?php endif; ?>
				<?php if ( ! empty( $task['date'] ) ) : ?>
					<li>
						<time datetime="<?php echo esc_attr( (string) ( $task['date_raw'] ?? '' ) ); ?>">
							<?php echo esc_html( (string) $task['date'] ); ?>
						</time>
					</li>
				<?php endif; ?>
			</ul>

			<?php if ( ! empty( $task['attachment_url'] ) ) : ?>
				<p class="shyft-task__attachment">
					<a href="<?php echo esc_url( (string) $task['attachment_url'] ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Anhang ansehen', 'shyft-dashboard' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
	</article>
</li>
