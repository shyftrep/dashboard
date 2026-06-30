<?php
/**
 * Dashboard buttons management page.
 *
 * @package ShyftDashboard
 *
 * @var WP_User $current_user
 * @var string $logo_url
 * @var string $logout_url
 * @var bool $show_website_link
 * @var string $website_url
 * @var list<array<string, mixed>> $buttons
 * @var array<string, mixed>|null $edit_button
 * @var array{type: string, message: string}|null $flash
 * @var string $form_action
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user      = $current_user ?? wp_get_current_user();
$logo_url          = $logo_url ?? Shyft_Dashboard_Settings::get_logo_url();
$logout_url        = $logout_url ?? wp_logout_url( home_url( '/' ) );
$show_website_link = $show_website_link ?? Shyft_Dashboard_Roles::can_edit_website( $current_user );
$website_url       = $website_url ?? home_url( '/' );
$buttons           = $buttons ?? array();
$edit_button       = $edit_button ?? null;
$flash             = $flash ?? null;
$form_action       = $form_action ?? admin_url( 'admin-post.php' );
$is_editing        = is_array( $edit_button );
$edit_scope_class  = $is_editing ? (string) ( $edit_button['scope_class'] ?? '' ) : '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> data-theme="light">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html__( 'Buttons', 'shyft-dashboard' ); ?> · <?php bloginfo( 'name' ); ?></title>
	<script>
		(function () {
			try {
				var theme = localStorage.getItem('shyft_dashboard_theme');
				document.documentElement.setAttribute('data-theme', theme === 'dark' ? 'dark' : 'light');
			} catch (e) {
				document.documentElement.setAttribute('data-theme', 'light');
			}
		})();
	</script>
	<?php Shyft_Dashboard_Routing::print_head_assets(); ?>
</head>
<body class="shyft-dashboard-body<?php echo $is_editing ? ' shyft-dashboard-body--editing-button' : ''; ?>">
<div class="shyft-dashboard">
	<header class="shyft-dashboard__header">
		<div class="shyft-dashboard__header-bar">
			<div class="shyft-dashboard__brand">
				<?php include SHYFT_DASHBOARD_PATH . 'templates/partials/brand-logo.php'; ?>
			</div>
		</div>
		<div class="shyft-dashboard__header-actions">
			<?php
			$active_view = 'buttons';
			include SHYFT_DASHBOARD_PATH . 'templates/partials/dashboard-nav.php';
			?>
			<?php if ( $is_editing ) : ?>
				<button type="submit" form="shyft-button-form" class="shyft-dashboard__button-save shyft-button">
					<?php esc_html_e( 'Button speichern', 'shyft-dashboard' ); ?>
				</button>
			<?php endif; ?>
			<?php if ( ! empty( $show_website_link ) ) : ?>
				<a href="<?php echo esc_url( $website_url ); ?>" class="shyft-dashboard__website-link">
					<?php esc_html_e( 'Website bearbeiten', 'shyft-dashboard' ); ?>
				</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( $logout_url ); ?>" class="shyft-dashboard__logout">
				<?php esc_html_e( 'Abmelden', 'shyft-dashboard' ); ?>
			</a>
		</div>
	</header>

	<main class="shyft-dashboard__main">
		<div class="shyft-dashboard__intro">
			<h1 class="shyft-dashboard__title"><?php esc_html_e( 'Buttons verwalten', 'shyft-dashboard' ); ?></h1>
			<p class="shyft-dashboard__subtitle">
				<?php esc_html_e( 'Lege Kauf-Buttons mit Text und Link an. Jeder Button erhält einen eigenen Shortcode für die Website.', 'shyft-dashboard' ); ?>
			</p>
			<p class="shyft-dashboard__subtitle">
				<?php esc_html_e( 'Beispiel:', 'shyft-dashboard' ); ?>
				<code>[clicklabs_button id="1"]</code>
				<?php esc_html_e( '· Elementor-Widget „Kauf-Button (clicklabs)“', 'shyft-dashboard' ); ?>
			</p>
		</div>

		<?php if ( ! empty( $flash ) ) : ?>
			<div class="shyft-dashboard__notice shyft-dashboard__notice--<?php echo esc_attr( $flash['type'] ); ?>" role="status">
				<?php echo esc_html( $flash['message'] ); ?>
			</div>
		<?php endif; ?>

		<section class="shyft-dashboard__section" aria-label="<?php esc_attr_e( 'Button-Verwaltung', 'shyft-dashboard' ); ?>">
			<div class="shyft-buttons-admin">
				<article class="shyft-card shyft-card--panel shyft-buttons-admin__list">
					<div class="shyft-buttons-admin__list-header">
						<h2 class="shyft-card__heading"><?php esc_html_e( 'Deine Buttons', 'shyft-dashboard' ); ?></h2>
						<a class="shyft-button shyft-button--secondary" href="<?php echo esc_url( Shyft_Dashboard_Buttons::get_dashboard_url() ); ?>">
							<?php esc_html_e( 'Neuer Button', 'shyft-dashboard' ); ?>
						</a>
					</div>

					<?php if ( empty( $buttons ) ) : ?>
						<p class="shyft-empty"><?php esc_html_e( 'Noch keine Buttons angelegt.', 'shyft-dashboard' ); ?></p>
					<?php else : ?>
						<ul class="shyft-buttons-admin__items">
							<?php foreach ( $buttons as $button ) : ?>
								<li class="shyft-buttons-admin__item">
									<div class="shyft-buttons-admin__item-main">
										<p class="shyft-buttons-admin__item-title"><?php echo esc_html( (string) ( $button['text'] ?? '' ) ); ?></p>
										<p class="shyft-buttons-admin__item-meta">
											<span class="shyft-buttons-admin__tag"><?php echo ! empty( $button['active'] ) ? esc_html__( 'Aktiv', 'shyft-dashboard' ) : esc_html__( 'Inaktiv', 'shyft-dashboard' ); ?></span>
											<?php if ( ! empty( $button['url'] ) ) : ?>
												<span class="shyft-buttons-admin__url"><?php echo esc_html( (string) $button['url'] ); ?></span>
											<?php endif; ?>
										</p>
										<p class="shyft-buttons-admin__shortcode">
											<code><?php echo esc_html( (string) ( $button['shortcode'] ?? '' ) ); ?></code>
										</p>
									</div>
									<div class="shyft-buttons-admin__item-actions">
										<a class="shyft-buttons-admin__edit" href="<?php echo esc_url( add_query_arg( 'edit', (string) (int) ( $button['id'] ?? 0 ), Shyft_Dashboard_Buttons::get_dashboard_url() ) ); ?>">
											<?php esc_html_e( 'Bearbeiten', 'shyft-dashboard' ); ?>
										</a>
										<a
											class="shyft-buttons-admin__delete"
											href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . Shyft_Dashboard_Buttons::ACTION_DELETE . '&button_id=' . (int) ( $button['id'] ?? 0 ) ), Shyft_Dashboard_Buttons::NONCE_DELETE . '_' . (int) ( $button['id'] ?? 0 ) ) ); ?>"
											onclick="return confirm('<?php echo esc_js( __( 'Button wirklich löschen?', 'shyft-dashboard' ) ); ?>');"
										>
											<?php esc_html_e( 'Löschen', 'shyft-dashboard' ); ?>
										</a>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</article>

				<article class="shyft-card shyft-card--panel shyft-buttons-admin__form-card">
					<h2 class="shyft-card__heading">
						<?php echo $is_editing ? esc_html__( 'Button bearbeiten', 'shyft-dashboard' ) : esc_html__( 'Neuer Button', 'shyft-dashboard' ); ?>
					</h2>

					<?php if ( $is_editing ) : ?>
						<p class="shyft-form__hint">
							<?php esc_html_e( 'Shortcode:', 'shyft-dashboard' ); ?>
							<code><?php echo esc_html( (string) ( $edit_button['shortcode'] ?? '' ) ); ?></code>
						</p>
					<?php endif; ?>

					<form id="shyft-button-form" class="shyft-form shyft-button-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( Shyft_Dashboard_Buttons::ACTION_SAVE ); ?>">
						<?php wp_nonce_field( Shyft_Dashboard_Buttons::NONCE_SAVE ); ?>
						<input type="hidden" name="button_id" value="<?php echo esc_attr( $is_editing ? (string) (int) ( $edit_button['id'] ?? 0 ) : '0' ); ?>">

						<div class="shyft-form__row">
							<label class="shyft-form__label" for="button_text"><?php esc_html_e( 'Button-Text', 'shyft-dashboard' ); ?></label>
							<input class="shyft-form__input" type="text" id="button_text" name="button_text" value="<?php echo esc_attr( $is_editing ? (string) ( $edit_button['text'] ?? '' ) : Shyft_Dashboard_Buttons::DEFAULT_LABEL ); ?>" required>
						</div>

						<div class="shyft-form__row">
							<label class="shyft-form__label" for="button_url"><?php esc_html_e( 'Link (URL)', 'shyft-dashboard' ); ?></label>
							<input class="shyft-form__input" type="url" id="button_url" name="button_url" value="<?php echo esc_attr( $is_editing ? (string) ( $edit_button['url'] ?? '' ) : '' ); ?>" required>
						</div>

						<div class="shyft-form__row">
							<label class="shyft-form__label" for="button_custom_css"><?php esc_html_e( 'Custom CSS (optional)', 'shyft-dashboard' ); ?></label>
							<textarea class="shyft-form__input shyft-form__input--code" id="button_custom_css" name="button_custom_css" rows="8" spellcheck="false"><?php echo esc_textarea( $is_editing ? (string) ( $edit_button['custom_css'] ?? '' ) : '' ); ?></textarea>
							<p class="shyft-form__hint">
								<?php
								if ( '' !== $edit_scope_class ) {
									echo wp_kses(
										sprintf(
											/* translators: %s: CSS scope class */
											__( 'Beispiel: <code>.%s .shyft-button-cta__link</code> { background: #c5a572; }', 'shyft-dashboard' ),
											esc_html( $edit_scope_class )
										),
										array( 'code' => array() )
									);
								} else {
									esc_html_e( 'Nach dem Speichern siehst du hier die CSS-Klasse für diesen Button.', 'shyft-dashboard' );
								}
								?>
							</p>
						</div>

						<div class="shyft-form__row">
							<label class="shyft-form__checkbox">
								<input type="checkbox" name="button_active" value="1" <?php checked( ! $is_editing || ! empty( $edit_button['active'] ) ); ?>>
								<?php esc_html_e( 'Button aktiv (auf der Website sichtbar)', 'shyft-dashboard' ); ?>
							</label>
						</div>

						<button type="submit" class="shyft-button"><?php esc_html_e( 'Button speichern', 'shyft-dashboard' ); ?></button>
					</form>
				</article>
			</div>
		</section>
	</main>

	<footer class="shyft-dashboard__footer">
		<p><?php esc_html_e( 'Betreut von SHYFT', 'shyft-dashboard' ); ?></p>
		<p class="shyft-dashboard__version">SHYFT Dashboard <?php echo esc_html( SHYFT_DASHBOARD_VERSION ); ?></p>
	</footer>
</div>
<?php Shyft_Dashboard_Routing::print_footer_assets(); ?>
</body>
</html>
