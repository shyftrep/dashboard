<?php
/**
 * Dashboard offers management page.
 *
 * @package ShyftDashboard
 *
 * @var WP_User $current_user
 * @var string $logo_url
 * @var string $logout_url
 * @var bool $show_website_link
 * @var string $website_url
 * @var list<array<string, mixed>> $offers
 * @var array<string, mixed>|null $edit_offer
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
$offers            = $offers ?? array();
$edit_offer        = $edit_offer ?? null;
$flash             = $flash ?? null;
$form_action       = $form_action ?? admin_url( 'admin-post.php' );
$is_editing        = is_array( $edit_offer );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> data-theme="light">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html__( 'Angebote', 'shyft-dashboard' ); ?> · <?php bloginfo( 'name' ); ?></title>
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
<body class="shyft-dashboard-body">
<div class="shyft-dashboard">
	<header class="shyft-dashboard__header">
		<div class="shyft-dashboard__header-bar">
			<div class="shyft-dashboard__brand">
				<?php include SHYFT_DASHBOARD_PATH . 'templates/partials/brand-logo.php'; ?>
			</div>
		</div>
		<div class="shyft-dashboard__header-actions">
			<?php
			$active_view = 'angebote';
			include SHYFT_DASHBOARD_PATH . 'templates/partials/dashboard-nav.php';
			?>
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
			<h1 class="shyft-dashboard__title"><?php esc_html_e( 'Angebote verwalten', 'shyft-dashboard' ); ?></h1>
			<p class="shyft-dashboard__subtitle">
				<?php esc_html_e( 'Standard-Angebote sind dauerhaft sichtbar. Zeitlich begrenzte Angebote ersetzen sie automatisch während der Laufzeit.', 'shyft-dashboard' ); ?>
			</p>
			<p class="shyft-dashboard__subtitle">
				<?php esc_html_e( 'Website-Einbindung:', 'shyft-dashboard' ); ?>
				<code>[clicklabs_angebot]</code>
				<?php esc_html_e( '· Elementor-Widget „Angebot (clicklabs)“', 'shyft-dashboard' ); ?>
			</p>
		</div>

		<?php if ( ! empty( $flash ) ) : ?>
			<div class="shyft-dashboard__notice shyft-dashboard__notice--<?php echo esc_attr( $flash['type'] ); ?>" role="status">
				<?php echo esc_html( $flash['message'] ); ?>
			</div>
		<?php endif; ?>

		<section class="shyft-dashboard__section" aria-label="<?php esc_attr_e( 'Angebotsverwaltung', 'shyft-dashboard' ); ?>">
			<div class="shyft-offers-admin">
				<article class="shyft-card shyft-card--panel shyft-offers-admin__list">
					<div class="shyft-offers-admin__list-header">
						<h2 class="shyft-card__heading"><?php esc_html_e( 'Deine Angebote', 'shyft-dashboard' ); ?></h2>
						<a class="shyft-button shyft-button--secondary" href="<?php echo esc_url( Shyft_Dashboard_Offers::get_dashboard_url() ); ?>">
							<?php esc_html_e( 'Neues Angebot', 'shyft-dashboard' ); ?>
						</a>
					</div>

					<?php if ( empty( $offers ) ) : ?>
						<p class="shyft-empty"><?php esc_html_e( 'Noch keine Angebote angelegt.', 'shyft-dashboard' ); ?></p>
					<?php else : ?>
						<ul class="shyft-offers-admin__items">
							<?php foreach ( $offers as $offer ) : ?>
								<li class="shyft-offers-admin__item">
									<div class="shyft-offers-admin__item-main">
										<p class="shyft-offers-admin__item-title"><?php echo esc_html( (string) ( $offer['headline'] ?? '' ) ); ?></p>
										<p class="shyft-offers-admin__item-meta">
											<?php if ( Shyft_Dashboard_Offers::TYPE_TIMED === ( $offer['type'] ?? '' ) ) : ?>
												<span class="shyft-offers-admin__tag shyft-offers-admin__tag--timed"><?php esc_html_e( 'Zeitlich begrenzt', 'shyft-dashboard' ); ?></span>
												<span>
													<?php
													echo esc_html(
														Shyft_Dashboard_Offers::format_datetime_label( (int) ( $offer['starts_at'] ?? 0 ) )
														. ' – '
														. Shyft_Dashboard_Offers::format_datetime_label( (int) ( $offer['ends_at'] ?? 0 ) )
													);
													?>
												</span>
												<?php if ( ! empty( $offer['is_timed_live'] ) ) : ?>
													<span class="shyft-offers-admin__tag shyft-offers-admin__tag--live"><?php esc_html_e( 'Aktiv', 'shyft-dashboard' ); ?></span>
												<?php endif; ?>
											<?php else : ?>
												<span class="shyft-offers-admin__tag"><?php esc_html_e( 'Standard', 'shyft-dashboard' ); ?></span>
												<span><?php echo ! empty( $offer['active'] ) ? esc_html__( 'Aktiv', 'shyft-dashboard' ) : esc_html__( 'Inaktiv', 'shyft-dashboard' ); ?></span>
											<?php endif; ?>
										</p>
									</div>
									<div class="shyft-offers-admin__item-actions">
										<a class="shyft-offers-admin__edit" href="<?php echo esc_url( add_query_arg( 'edit', (string) (int) ( $offer['id'] ?? 0 ), Shyft_Dashboard_Offers::get_dashboard_url() ) ); ?>">
											<?php esc_html_e( 'Bearbeiten', 'shyft-dashboard' ); ?>
										</a>
										<a
											class="shyft-offers-admin__delete"
											href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . Shyft_Dashboard_Offers::ACTION_DELETE . '&offer_id=' . (int) ( $offer['id'] ?? 0 ) ), Shyft_Dashboard_Offers::NONCE_DELETE . '_' . (int) ( $offer['id'] ?? 0 ) ) ); ?>"
											onclick="return confirm('<?php echo esc_js( __( 'Angebot wirklich löschen?', 'shyft-dashboard' ) ); ?>');"
										>
											<?php esc_html_e( 'Löschen', 'shyft-dashboard' ); ?>
										</a>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</article>

				<article class="shyft-card shyft-card--panel shyft-offers-admin__form-card">
					<h2 class="shyft-card__heading">
						<?php echo $is_editing ? esc_html__( 'Angebot bearbeiten', 'shyft-dashboard' ) : esc_html__( 'Neues Angebot', 'shyft-dashboard' ); ?>
					</h2>

					<form class="shyft-form shyft-offer-form" method="post" action="<?php echo esc_url( $form_action ); ?>" enctype="multipart/form-data" data-shyft-offer-form>
						<input type="hidden" name="action" value="<?php echo esc_attr( Shyft_Dashboard_Offers::ACTION_SAVE ); ?>">
						<?php wp_nonce_field( Shyft_Dashboard_Offers::NONCE_SAVE ); ?>
						<input type="hidden" name="offer_id" value="<?php echo esc_attr( $is_editing ? (string) (int) ( $edit_offer['id'] ?? 0 ) : '0' ); ?>">

						<div class="shyft-form__row">
							<label class="shyft-form__label" for="offer_type"><?php esc_html_e( 'Angebotstyp', 'shyft-dashboard' ); ?></label>
							<select class="shyft-form__input" id="offer_type" name="offer_type" data-offer-type>
								<option value="standard" <?php selected( ! $is_editing || Shyft_Dashboard_Offers::TYPE_STANDARD === ( $edit_offer['type'] ?? '' ) ); ?>><?php esc_html_e( 'Standard (dauerhaft)', 'shyft-dashboard' ); ?></option>
								<option value="timed" <?php selected( $is_editing && Shyft_Dashboard_Offers::TYPE_TIMED === ( $edit_offer['type'] ?? '' ) ); ?>><?php esc_html_e( 'Zeitlich begrenzt', 'shyft-dashboard' ); ?></option>
							</select>
						</div>

						<div class="shyft-form__row shyft-offer-form__timed" data-offer-timed-fields <?php echo ( $is_editing && Shyft_Dashboard_Offers::TYPE_TIMED === ( $edit_offer['type'] ?? '' ) ) ? '' : 'hidden'; ?>>
							<div class="shyft-form__grid">
								<div>
									<label class="shyft-form__label" for="offer_starts_at"><?php esc_html_e( 'Start', 'shyft-dashboard' ); ?></label>
									<input class="shyft-form__input" type="datetime-local" id="offer_starts_at" name="offer_starts_at" value="<?php echo esc_attr( Shyft_Dashboard_Offers::format_datetime_local( (int) ( $edit_offer['starts_at'] ?? 0 ) ) ); ?>">
								</div>
								<div>
									<label class="shyft-form__label" for="offer_ends_at"><?php esc_html_e( 'Ende', 'shyft-dashboard' ); ?></label>
									<input class="shyft-form__input" type="datetime-local" id="offer_ends_at" name="offer_ends_at" value="<?php echo esc_attr( Shyft_Dashboard_Offers::format_datetime_local( (int) ( $edit_offer['ends_at'] ?? 0 ) ) ); ?>">
								</div>
							</div>
							<p class="shyft-form__hint"><?php esc_html_e( 'Während dieser Zeit ersetzt das Angebot alle Standard-Angebote auf der Website.', 'shyft-dashboard' ); ?></p>
						</div>

						<div class="shyft-form__row" data-offer-standard-fields <?php echo ( $is_editing && Shyft_Dashboard_Offers::TYPE_TIMED === ( $edit_offer['type'] ?? '' ) ) ? 'hidden' : ''; ?>>
							<label class="shyft-form__label">
								<input type="checkbox" name="offer_active" value="1" <?php checked( ! $is_editing || ! empty( $edit_offer['active'] ) ); ?>>
								<?php esc_html_e( 'Auf der Website anzeigen', 'shyft-dashboard' ); ?>
							</label>
						</div>

						<div class="shyft-form__row">
							<label class="shyft-form__label" for="offer_sort"><?php esc_html_e( 'Reihenfolge', 'shyft-dashboard' ); ?></label>
							<input class="shyft-form__input" type="number" id="offer_sort" name="offer_sort" min="0" step="1" value="<?php echo esc_attr( $is_editing ? (string) (int) ( $edit_offer['sort'] ?? 0 ) : '0' ); ?>">
						</div>

						<div class="shyft-form__row">
							<label class="shyft-form__label"><?php esc_html_e( 'Bild', 'shyft-dashboard' ); ?></label>
							<input type="hidden" name="offer_image_id" value="<?php echo esc_attr( $is_editing ? (string) (int) ( $edit_offer['image_id'] ?? 0 ) : '0' ); ?>" data-offer-image-id>
							<div class="shyft-offer-form__image-preview" data-offer-image-preview>
								<?php if ( $is_editing && ! empty( $edit_offer['image_url'] ) ) : ?>
									<img src="<?php echo esc_url( (string) $edit_offer['image_url'] ); ?>" alt="">
								<?php endif; ?>
							</div>
							<div class="shyft-offer-form__image-actions">
								<label class="shyft-button shyft-button--secondary shyft-offer-form__file-label">
									<?php esc_html_e( 'Bild hochladen', 'shyft-dashboard' ); ?>
									<input type="file" name="offer_image_file" class="shyft-offer-form__file-input" accept="image/jpeg,image/png,image/webp,image/gif" data-offer-image-file>
								</label>
								<button type="button" class="shyft-button shyft-button--secondary" data-offer-remove-image><?php esc_html_e( 'Bild entfernen', 'shyft-dashboard' ); ?></button>
							</div>
						</div>

						<div class="shyft-form__row">
							<label class="shyft-form__label" for="offer_headline"><?php esc_html_e( 'Überschrift', 'shyft-dashboard' ); ?></label>
							<input class="shyft-form__input" type="text" id="offer_headline" name="offer_headline" required maxlength="200" value="<?php echo esc_attr( $is_editing ? (string) ( $edit_offer['headline'] ?? '' ) : '' ); ?>">
						</div>

						<div class="shyft-form__row">
							<label class="shyft-form__label" for="offer_text"><?php esc_html_e( 'Text', 'shyft-dashboard' ); ?></label>
							<textarea class="shyft-form__input shyft-form__textarea" id="offer_text" name="offer_text" rows="5"><?php echo esc_textarea( $is_editing ? (string) ( $edit_offer['text'] ?? '' ) : '' ); ?></textarea>
						</div>

						<div class="shyft-form__row">
							<div class="shyft-offer-form__icons-header">
								<label class="shyft-form__label"><?php esc_html_e( 'Aufzählung', 'shyft-dashboard' ); ?></label>
								<button type="button" class="shyft-button shyft-button--secondary" data-offer-add-feature><?php esc_html_e( 'Punkt hinzufügen', 'shyft-dashboard' ); ?></button>
							</div>
							<p class="shyft-form__hint"><?php esc_html_e( 'Jeder Punkt wird mit einem Häkchen auf der Website angezeigt.', 'shyft-dashboard' ); ?></p>
							<div class="shyft-offer-form__features" data-offer-features>
								<?php
								$feature_rows = $is_editing && ! empty( $edit_offer['icons'] ) ? $edit_offer['icons'] : array( '' );
								foreach ( $feature_rows as $feature_label ) :
									$feature_label = is_string( $feature_label ) ? $feature_label : (string) ( is_array( $feature_label ) ? ( $feature_label['label'] ?? '' ) : '' );
									?>
									<div class="shyft-offer-form__feature-row">
										<span class="shyft-offer-form__feature-check" aria-hidden="true"></span>
										<input type="text" name="offer_feature_labels[]" class="shyft-form__input" placeholder="<?php esc_attr_e( 'Vorteil / Aufzählungspunkt', 'shyft-dashboard' ); ?>" value="<?php echo esc_attr( $feature_label ); ?>">
										<button type="button" class="shyft-offer-form__icon-remove" data-offer-remove-feature aria-label="<?php esc_attr_e( 'Entfernen', 'shyft-dashboard' ); ?>">&times;</button>
									</div>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="shyft-form__row">
							<label class="shyft-form__label" for="offer_button_label"><?php esc_html_e( 'Button-Text', 'shyft-dashboard' ); ?></label>
							<input class="shyft-form__input" type="text" id="offer_button_label" name="offer_button_label" maxlength="120" value="<?php echo esc_attr( $is_editing ? (string) ( $edit_offer['button_label'] ?? '' ) : '' ); ?>">
						</div>

						<div class="shyft-form__row">
							<label class="shyft-form__label" for="offer_button_url"><?php esc_html_e( 'Button-Link', 'shyft-dashboard' ); ?></label>
							<input class="shyft-form__input" type="url" id="offer_button_url" name="offer_button_url" value="<?php echo esc_attr( $is_editing ? (string) ( $edit_offer['button_url'] ?? '' ) : '' ); ?>">
						</div>

						<button type="submit" class="shyft-button"><?php esc_html_e( 'Angebot speichern', 'shyft-dashboard' ); ?></button>
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
