<?php
/**
 * Dashboard fullscreen template.
 *
 * @package ShyftDashboard
 *
 * @var int    $new_leads_count
 * @var array  $recent_leads
 * @var array  $status
 * @var array  $analytics
 * @var string $matomo_stats_url
 * @var array  $recent_plugin_updates
 * @var array  $categories
 * @var string $logout_url
 * @var string $form_action
 * @var array<int, array{message: string, type: string, date: string}> $latest_activities
 * @var WP_User $current_user
 * @var string $logo_url
 * @var array{type: string, message: string}|null $flash
 * @var bool   $show_website_link
 * @var string $website_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$new_leads_count       = $new_leads_count ?? 0;
$recent_leads          = $recent_leads ?? array();
$status                = $status ?? array();
$analytics             = $analytics ?? array( 'available' => false );
$matomo_stats_url      = $matomo_stats_url ?? '';
$recent_plugin_updates = $recent_plugin_updates ?? array();
$categories            = $categories ?? array();
$logout_url            = $logout_url ?? wp_logout_url( home_url( '/' ) );
$form_action           = $form_action ?? admin_url( 'admin-post.php' );
$current_user          = $current_user ?? wp_get_current_user();
$logo_url              = $logo_url ?? '';
$flash                 = $flash ?? null;
$latest_activities     = $latest_activities ?? array();
$show_website_link     = $show_website_link ?? false;
$website_url           = $website_url ?? home_url( '/' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> data-theme="light">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html__( 'Dashboard', 'shyft-dashboard' ); ?> · <?php bloginfo( 'name' ); ?></title>
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
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'SHYFT', 'shyft-dashboard' ); ?>" class="shyft-dashboard__logo" width="120" height="40">
			</div>
			<?php
			$theme_switch_modifier = 'header';
			include SHYFT_DASHBOARD_PATH . 'templates/partials/theme-switch.php';
			?>
		</div>
		<div class="shyft-dashboard__header-actions">
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
			<div class="shyft-dashboard__intro-row">
				<h1 class="shyft-dashboard__title"><?php esc_html_e( 'Dein Website-Dashboard', 'shyft-dashboard' ); ?></h1>
				<?php
				$theme_switch_modifier = 'intro';
				include SHYFT_DASHBOARD_PATH . 'templates/partials/theme-switch.php';
				?>
			</div>
			<p class="shyft-dashboard__subtitle"><?php esc_html_e( 'Alle wichtigen Kennzahlen und Anfragen auf einen Blick.', 'shyft-dashboard' ); ?></p>
		</div>

		<?php if ( ! empty( $flash ) ) : ?>
			<div class="shyft-dashboard__notice shyft-dashboard__notice--<?php echo esc_attr( $flash['type'] ); ?>" role="status">
				<?php echo esc_html( $flash['message'] ); ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $latest_activities ) ) : ?>
			<section class="shyft-dashboard__activity" aria-label="<?php esc_attr_e( 'Letzte Wartung', 'shyft-dashboard' ); ?>">
				<h2 class="shyft-dashboard__activity-heading"><?php esc_html_e( 'Zuletzt erledigt', 'shyft-dashboard' ); ?></h2>
				<div class="shyft-dashboard__activity-grid">
					<?php foreach ( $latest_activities as $activity ) : ?>
						<article class="shyft-activity-card shyft-activity-card--<?php echo esc_attr( $activity['type'] ); ?>">
							<div class="shyft-activity-card__icon" aria-hidden="true"></div>
							<div class="shyft-activity-card__content">
								<p class="shyft-activity-card__message"><?php echo esc_html( $activity['message'] ); ?></p>
								<?php if ( ! empty( $activity['date'] ) ) : ?>
									<p class="shyft-activity-card__meta">
										<time><?php echo esc_html( $activity['date'] ); ?></time>
									</p>
								<?php endif; ?>
							</div>
							<span class="shyft-activity-card__badge"><?php esc_html_e( 'SHYFT Care', 'shyft-dashboard' ); ?></span>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<section class="shyft-dashboard__metrics" aria-label="<?php esc_attr_e( 'Kennzahlen', 'shyft-dashboard' ); ?>">
			<article class="shyft-card shyft-card--metric">
				<p class="shyft-card__label"><?php esc_html_e( 'Neue Anfragen (90 Tage)', 'shyft-dashboard' ); ?></p>
				<p class="shyft-card__value"><?php echo esc_html( (string) $new_leads_count ); ?></p>
			</article>

			<article class="shyft-card shyft-card--metric">
				<p class="shyft-card__label"><?php esc_html_e( 'Besucher (90 Tage)', 'shyft-dashboard' ); ?></p>
				<p class="shyft-card__value">
					<?php
					echo esc_html(
						! empty( $analytics['available'] )
							? number_format_i18n( (int) $analytics['visits'] )
							: '—'
					);
					?>
				</p>
			</article>

			<article class="shyft-card shyft-card--metric">
				<p class="shyft-card__label"><?php esc_html_e( 'Seitenaufrufe (90 Tage)', 'shyft-dashboard' ); ?></p>
				<p class="shyft-card__value">
					<?php
					echo esc_html(
						! empty( $analytics['available'] )
							? number_format_i18n( (int) $analytics['pageviews'] )
							: '—'
					);
					?>
				</p>
				<a class="shyft-card__link" href="<?php echo esc_url( $matomo_stats_url ); ?>">
					<?php esc_html_e( 'Ausführliche Matomo-Statistiken', 'shyft-dashboard' ); ?>
				</a>
			</article>

			<article class="shyft-card shyft-card--metric">
				<p class="shyft-card__label"><?php esc_html_e( 'WhatsApp-Klicks (90 Tage)', 'shyft-dashboard' ); ?></p>
				<p class="shyft-card__value">
					<?php
					echo esc_html(
						! empty( $analytics['available'] )
							? number_format_i18n( (int) ( $analytics['wa_me_clicks'] ?? 0 ) )
							: '—'
					);
					?>
				</p>
			</article>

			<article class="shyft-card shyft-card--metric">
				<p class="shyft-card__label"><?php esc_html_e( 'Externe Link-Klicks (90 Tage)', 'shyft-dashboard' ); ?></p>
				<p class="shyft-card__value">
					<?php
					echo esc_html(
						! empty( $analytics['available'] )
							? number_format_i18n( (int) ( $analytics['outlink_clicks'] ?? 0 ) )
							: '—'
					);
					?>
				</p>
				<?php if ( ! empty( $analytics['available'] ) && ! empty( $analytics['outlink_domains'] ) ) : ?>
					<ul class="shyft-outlink-domains">
						<?php foreach ( $analytics['outlink_domains'] as $outlink_domain ) : ?>
							<li class="shyft-outlink-domains__item">
								<span class="shyft-outlink-domains__domain"><?php echo esc_html( (string) $outlink_domain['domain'] ); ?></span>
								<span class="shyft-outlink-domains__clicks"><?php echo esc_html( number_format_i18n( (int) $outlink_domain['clicks'] ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</article>

			<article class="shyft-card shyft-card--metric">
				<p class="shyft-card__label"><?php esc_html_e( 'Offene Updates', 'shyft-dashboard' ); ?></p>
				<p class="shyft-card__value <?php echo ( (int) $status['updates'] > 0 ) ? 'shyft-card__value--warning' : ''; ?>">
					<?php echo esc_html( (string) (int) $status['updates'] ); ?>
				</p>
			</article>
		</section>

		<section class="shyft-dashboard__grid">
			<article class="shyft-card shyft-card--panel">
				<h2 class="shyft-card__heading"><?php esc_html_e( 'Website-Status', 'shyft-dashboard' ); ?></h2>
				<ul class="shyft-status-list">
					<li class="shyft-status-list__item">
						<span class="shyft-status-list__label"><?php esc_html_e( 'SSL', 'shyft-dashboard' ); ?></span>
						<span class="shyft-status-list__value <?php echo ! empty( $status['ssl']['active'] ) ? 'is-ok' : 'is-warn'; ?>">
							<?php echo esc_html( (string) $status['ssl']['label'] ); ?>
						</span>
					</li>
					<li class="shyft-status-list__item">
						<span class="shyft-status-list__label"><?php esc_html_e( 'PHP-Version', 'shyft-dashboard' ); ?></span>
						<span class="shyft-status-list__value"><?php echo esc_html( (string) $status['php_version'] ); ?></span>
					</li>
					<li class="shyft-status-list__item">
						<span class="shyft-status-list__label"><?php esc_html_e( 'Updates', 'shyft-dashboard' ); ?></span>
						<span class="shyft-status-list__value <?php echo ( (int) $status['updates'] > 0 ) ? 'is-warn' : 'is-ok'; ?>">
							<?php
							if ( (int) $status['updates'] > 0 ) {
								printf(
									/* translators: %d: number of updates */
									esc_html( _n( '%d Update ausstehend', '%d Updates ausstehend', (int) $status['updates'], 'shyft-dashboard' ) ),
									(int) $status['updates']
								);
							} else {
								esc_html_e( 'Alles aktuell', 'shyft-dashboard' );
							}
							?>
						</span>
					</li>
					<li class="shyft-status-list__item">
						<span class="shyft-status-list__label"><?php esc_html_e( 'Indexierung', 'shyft-dashboard' ); ?></span>
						<span class="shyft-status-list__value <?php echo ! empty( $status['indexable']['active'] ) ? 'is-ok' : 'is-warn'; ?>">
							<?php echo esc_html( (string) $status['indexable']['label'] ); ?>
						</span>
					</li>
					<li class="shyft-status-list__item">
						<span class="shyft-status-list__label"><?php esc_html_e( 'Sitemap', 'shyft-dashboard' ); ?></span>
						<span class="shyft-status-list__value <?php echo ! empty( $status['sitemap']['active'] ) ? 'is-ok' : 'is-warn'; ?>">
							<?php echo esc_html( (string) $status['sitemap']['label'] ); ?>
						</span>
					</li>
					<?php if ( ! empty( $status['last_backup']['available'] ) ) : ?>
						<li class="shyft-status-list__item">
							<span class="shyft-status-list__label"><?php esc_html_e( 'Letztes Backup', 'shyft-dashboard' ); ?></span>
							<span class="shyft-status-list__value is-ok"><?php echo esc_html( (string) $status['last_backup']['label'] ); ?></span>
						</li>
					<?php endif; ?>
				</ul>
			</article>

			<article class="shyft-card shyft-card--panel">
				<h2 class="shyft-card__heading"><?php esc_html_e( 'Letzte Anfragen', 'shyft-dashboard' ); ?></h2>
				<?php if ( empty( $recent_leads ) ) : ?>
					<p class="shyft-empty"><?php esc_html_e( 'Noch keine Anfragen vorhanden.', 'shyft-dashboard' ); ?></p>
				<?php else : ?>
					<ul class="shyft-leads-list">
						<?php foreach ( $recent_leads as $lead ) : ?>
							<li class="shyft-leads-list__item">
								<?php if ( ! empty( $lead['url'] ) ) : ?>
									<a class="shyft-leads-list__link" href="<?php echo esc_url( $lead['url'] ); ?>">
										<?php echo esc_html( $lead['name'] ); ?>
									</a>
								<?php else : ?>
									<span class="shyft-leads-list__name"><?php echo esc_html( $lead['name'] ); ?></span>
								<?php endif; ?>
								<time class="shyft-leads-list__date" datetime="<?php echo esc_attr( $lead['date_raw'] ); ?>">
									<?php echo esc_html( $lead['date'] ); ?>
								</time>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</article>

			<article class="shyft-card shyft-card--panel">
				<h2 class="shyft-card__heading"><?php esc_html_e( 'Zuletzt aktualisierte Plugins', 'shyft-dashboard' ); ?></h2>
				<?php if ( empty( $recent_plugin_updates ) ) : ?>
					<p class="shyft-empty"><?php esc_html_e( 'Noch keine Plugin-Updates protokolliert.', 'shyft-dashboard' ); ?></p>
				<?php else : ?>
					<ul class="shyft-leads-list">
						<?php foreach ( $recent_plugin_updates as $plugin_update ) : ?>
							<li class="shyft-leads-list__item">
								<span class="shyft-leads-list__name">
									<?php echo esc_html( $plugin_update['name'] ); ?>
									<?php if ( ! empty( $plugin_update['version'] ) ) : ?>
										<span class="shyft-plugin-update__version"><?php echo esc_html( $plugin_update['version'] ); ?></span>
									<?php endif; ?>
								</span>
								<time class="shyft-leads-list__date" datetime="<?php echo esc_attr( $plugin_update['date_raw'] ); ?>">
									<?php echo esc_html( $plugin_update['date'] ); ?>
								</time>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</article>
		</section>

		<section class="shyft-dashboard__request">
			<article class="shyft-card shyft-card--panel">
				<h2 class="shyft-card__heading"><?php esc_html_e( 'Änderungswunsch', 'shyft-dashboard' ); ?></h2>
				<p class="shyft-card__description"><?php esc_html_e( 'Teile uns mit, was wir für deine Website anpassen sollen.', 'shyft-dashboard' ); ?></p>

				<form class="shyft-form" method="post" action="<?php echo esc_url( $form_action ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="<?php echo esc_attr( Shyft_Dashboard_Change_Request::ACTION ); ?>">
					<?php wp_nonce_field( Shyft_Dashboard_Change_Request::NONCE_ACTION ); ?>

					<div class="shyft-form__row">
						<label class="shyft-form__label" for="shyft_category"><?php esc_html_e( 'Kategorie', 'shyft-dashboard' ); ?></label>
						<select class="shyft-form__input" id="shyft_category" name="shyft_category" required>
							<?php foreach ( $categories as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="shyft-form__row">
						<label class="shyft-form__label" for="shyft_subject"><?php esc_html_e( 'Betreff', 'shyft-dashboard' ); ?></label>
						<input class="shyft-form__input" type="text" id="shyft_subject" name="shyft_subject" required maxlength="200">
					</div>

					<div class="shyft-form__row">
						<label class="shyft-form__label" for="shyft_message"><?php esc_html_e( 'Nachricht', 'shyft-dashboard' ); ?></label>
						<textarea class="shyft-form__input shyft-form__textarea" id="shyft_message" name="shyft_message" rows="5" required></textarea>
					</div>

					<div class="shyft-form__row">
						<label class="shyft-form__label" for="shyft_attachment"><?php esc_html_e( 'Anhang (optional)', 'shyft-dashboard' ); ?></label>
						<input class="shyft-form__input shyft-form__file" type="file" id="shyft_attachment" name="shyft_attachment" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx">
						<p class="shyft-form__hint" id="shyft_attachment_name"><?php esc_html_e( 'Screenshot, PDF oder Dokument – max. 5 MB', 'shyft-dashboard' ); ?></p>
					</div>

					<button type="submit" class="shyft-button"><?php esc_html_e( 'Wunsch absenden', 'shyft-dashboard' ); ?></button>
				</form>
			</article>
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
