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
 * @var string $period_label
 * @var array{message: string, count: int, anchor: string}|null $tasks_notice
 * @var array{open: list<array<string, mixed>>, done: list<array<string, mixed>>, can_manage: bool} $tasks_tracker
 * @var array<string, mixed> $google_reviews
 * @var int $google_reviews_new_count
 * @var string $google_reviews_manage_url
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
$period_label          = $period_label ?? Shyft_Dashboard_Period::get_label();
$tasks_notice          = $tasks_notice ?? null;
$tasks_tracker         = $tasks_tracker ?? array(
	'open'       => array(),
	'done'       => array(),
	'can_manage' => false,
);
$google_reviews        = $google_reviews ?? Shyft_Dashboard_Google_Reviews::get_stored_data();
$google_reviews_new_count = $google_reviews_new_count ?? 0;
$google_reviews_manage_url = $google_reviews_manage_url ?? '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> data-theme="light">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>">
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
				<?php include SHYFT_DASHBOARD_PATH . 'templates/partials/brand-logo.php'; ?>
			</div>
			<div class="shyft-dashboard__header-toolbar">
				<?php
				$period_switch_modifier = 'header';
				include SHYFT_DASHBOARD_PATH . 'templates/partials/period-switch.php';
				$theme_switch_modifier = 'header';
				include SHYFT_DASHBOARD_PATH . 'templates/partials/theme-switch.php';
				?>
			</div>
		</div>
		<div class="shyft-dashboard__header-actions">
			<?php
			$active_view = 'overview';
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
			<div class="shyft-dashboard__intro-row">
				<h1 class="shyft-dashboard__title"><?php esc_html_e( 'Dein Website-Dashboard', 'shyft-dashboard' ); ?></h1>
				<div class="shyft-dashboard__toolbar">
					<?php
					$period_switch_modifier = 'intro';
					include SHYFT_DASHBOARD_PATH . 'templates/partials/period-switch.php';
					$theme_switch_modifier = 'intro';
					include SHYFT_DASHBOARD_PATH . 'templates/partials/theme-switch.php';
					?>
				</div>
			</div>
			<p class="shyft-dashboard__subtitle"><?php esc_html_e( 'Alle wichtigen Kennzahlen und Anfragen auf einen Blick.', 'shyft-dashboard' ); ?></p>
		</div>

		<?php if ( ! empty( $flash ) ) : ?>
			<div class="shyft-dashboard__notice shyft-dashboard__notice--<?php echo esc_attr( $flash['type'] ); ?>" role="status">
				<?php echo esc_html( $flash['message'] ); ?>
			</div>
		<?php endif; ?>

		<?php include SHYFT_DASHBOARD_PATH . 'templates/partials/tasks-notice.php'; ?>

		<?php if ( ! empty( $latest_activities ) ) : ?>
			<section class="shyft-dashboard__section shyft-dashboard__activity" aria-label="<?php esc_attr_e( 'Letzte Wartung', 'shyft-dashboard' ); ?>">
				<header class="shyft-section-header">
					<p class="shyft-section-label"><?php esc_html_e( 'SHYFT Care', 'shyft-dashboard' ); ?></p>
					<h2 class="shyft-section-title"><?php esc_html_e( 'Zuletzt erledigt', 'shyft-dashboard' ); ?></h2>
				</header>
				<div class="shyft-activity-grid">
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

		<section class="shyft-dashboard__section shyft-dashboard__section--metrics" aria-label="<?php esc_attr_e( 'Kennzahlen', 'shyft-dashboard' ); ?>">
			<header class="shyft-section-header">
				<p class="shyft-section-label"><?php esc_html_e( 'Überblick', 'shyft-dashboard' ); ?></p>
				<h2 class="shyft-section-title">
					<?php
					printf(
						/* translators: %s: reporting period label, e.g. "90 Tage" */
						esc_html__( 'Kennzahlen · %s', 'shyft-dashboard' ),
						esc_html( $period_label )
					);
					?>
				</h2>
			</header>
			<div class="shyft-metrics-grid">
			<article class="shyft-card shyft-card--metric">
				<p class="shyft-card__label">
					<?php
					printf(
						/* translators: %s: reporting period label, e.g. "90 Tage" */
						esc_html__( 'Neue Anfragen (%s)', 'shyft-dashboard' ),
						esc_html( $period_label )
					);
					?>
				</p>
				<p class="shyft-card__value"><?php echo esc_html( (string) $new_leads_count ); ?></p>
			</article>

			<article class="shyft-card shyft-card--metric">
				<p class="shyft-card__label">
					<?php
					printf(
						/* translators: %s: reporting period label */
						esc_html__( 'Besucher (%s)', 'shyft-dashboard' ),
						esc_html( $period_label )
					);
					?>
				</p>
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
				<p class="shyft-card__label">
					<?php
					printf(
						/* translators: %s: reporting period label */
						esc_html__( 'Seitenaufrufe (%s)', 'shyft-dashboard' ),
						esc_html( $period_label )
					);
					?>
				</p>
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
				<p class="shyft-card__label">
					<?php
					printf(
						/* translators: %s: reporting period label */
						esc_html__( 'WhatsApp-Klicks (%s)', 'shyft-dashboard' ),
						esc_html( $period_label )
					);
					?>
				</p>
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
				<p class="shyft-card__label">
					<?php
					printf(
						/* translators: %s: reporting period label */
						esc_html__( 'Externe Link-Klicks (%s)', 'shyft-dashboard' ),
						esc_html( $period_label )
					);
					?>
				</p>
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

			<?php if ( ! empty( $google_reviews['available'] ) ) : ?>
				<article class="shyft-card shyft-card--metric">
					<p class="shyft-card__label"><?php esc_html_e( 'Google-Bewertung', 'shyft-dashboard' ); ?></p>
					<p class="shyft-card__value"><?php echo esc_html( number_format_i18n( (float) ( $google_reviews['rating'] ?? 0 ), 1 ) ); ?></p>
					<p class="shyft-card__link">
						<a href="#shyft-google-reviews">
							<?php
							printf(
								/* translators: %s: number of reviews */
								esc_html( _n( '%s Bewertung', '%s Bewertungen', (int) ( $google_reviews['total'] ?? 0 ), 'shyft-dashboard' ) ),
								esc_html( number_format_i18n( (int) ( $google_reviews['total'] ?? 0 ) ) )
							);
							?>
						</a>
						<?php if ( $google_reviews_new_count > 0 ) : ?>
							<span class="shyft-metric-badge">
								<?php
								printf(
									/* translators: %d: number of new reviews */
									esc_html( _n( '%d neu', '%d neu', $google_reviews_new_count, 'shyft-dashboard' ) ),
									(int) $google_reviews_new_count
								);
								?>
							</span>
						<?php endif; ?>
					</p>
				</article>
			<?php endif; ?>
			</div>
		</section>

		<section class="shyft-dashboard__section" aria-label="<?php esc_attr_e( 'Details', 'shyft-dashboard' ); ?>">
			<header class="shyft-section-header">
				<p class="shyft-section-label"><?php esc_html_e( 'Details', 'shyft-dashboard' ); ?></p>
				<h2 class="shyft-section-title"><?php esc_html_e( 'Website & Anfragen', 'shyft-dashboard' ); ?></h2>
			</header>
			<div class="shyft-card-grid shyft-dashboard__grid">
			<article class="shyft-card shyft-card--panel">
				<h3 class="shyft-card__heading"><?php esc_html_e( 'Website-Status', 'shyft-dashboard' ); ?></h3>
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
				<h3 class="shyft-card__heading"><?php esc_html_e( 'Letzte Anfragen', 'shyft-dashboard' ); ?></h3>
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
				<h3 class="shyft-card__heading"><?php esc_html_e( 'Zuletzt aktualisierte Plugins', 'shyft-dashboard' ); ?></h3>
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
			</div>
		</section>

		<?php
		$dashboard_reviews = Shyft_Dashboard_Google_Reviews::get_dashboard_reviews( $google_reviews );
		?>
		<?php if ( ! empty( $google_reviews['available'] ) && ! empty( $dashboard_reviews ) ) : ?>
			<section class="shyft-dashboard__section" id="shyft-google-reviews" aria-label="<?php esc_attr_e( 'Google Bewertungen', 'shyft-dashboard' ); ?>">
				<header class="shyft-section-header shyft-section-header--split">
					<div>
						<p class="shyft-section-label"><?php esc_html_e( 'Reputation', 'shyft-dashboard' ); ?></p>
						<h2 class="shyft-section-title">
							<?php esc_html_e( 'Google Bewertungen', 'shyft-dashboard' ); ?>
							<?php if ( $google_reviews_new_count > 0 ) : ?>
								<span class="shyft-section-badge">
									<?php
									printf(
										/* translators: %d: number of new reviews */
										esc_html( _n( '%d neue Bewertung', '%d neue Bewertungen', $google_reviews_new_count, 'shyft-dashboard' ) ),
										(int) $google_reviews_new_count
									);
									?>
								</span>
							<?php endif; ?>
						</h2>
					</div>
					<?php if ( '' !== $google_reviews_manage_url ) : ?>
						<a class="shyft-button shyft-button--secondary" href="<?php echo esc_url( $google_reviews_manage_url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'In Google antworten', 'shyft-dashboard' ); ?>
						</a>
					<?php endif; ?>
				</header>

				<article class="shyft-card shyft-card--panel">
					<ul class="shyft-reviews-dash">
						<?php foreach ( $dashboard_reviews as $review ) : ?>
							<?php
							if ( ! is_array( $review ) ) {
								continue;
							}

							$is_new      = Shyft_Dashboard_Google_Reviews::is_review_new( $review );
							$author      = (string) ( $review['author'] ?? '' );
							$rating      = (int) ( $review['rating'] ?? 0 );
							$text        = (string) ( $review['text'] ?? '' );
							$relative    = (string) ( $review['relative_time'] ?? '' );
							$photo       = (string) ( $review['photo'] ?? '' );
							$review_time = (int) ( $review['time'] ?? 0 );
							$initial     = Shyft_Dashboard_Google_Reviews::get_author_initial( $author );
							?>
							<li class="shyft-reviews-dash__item<?php echo $is_new ? ' is-new' : ''; ?>">
								<div class="shyft-reviews-dash__main">
									<div class="shyft-reviews-dash__head">
										<?php if ( '' !== $photo ) : ?>
											<img class="shyft-reviews-dash__avatar" src="<?php echo esc_url( $photo ); ?>" alt="" width="40" height="40" loading="lazy" decoding="async">
										<?php else : ?>
											<span class="shyft-reviews-dash__avatar shyft-reviews-dash__avatar--placeholder" aria-hidden="true"><?php echo esc_html( $initial ); ?></span>
										<?php endif; ?>
										<div class="shyft-reviews-dash__meta">
											<p class="shyft-reviews-dash__author"><?php echo esc_html( $author ); ?></p>
											<p class="shyft-reviews-dash__stars" aria-hidden="true"><?php echo esc_html( Shyft_Dashboard_Google_Reviews::render_stars( $rating ) ); ?></p>
										</div>
										<?php if ( $is_new ) : ?>
											<span class="shyft-reviews-dash__badge"><?php esc_html_e( 'Neu', 'shyft-dashboard' ); ?></span>
										<?php endif; ?>
									</div>
									<?php if ( '' !== $text ) : ?>
										<p class="shyft-reviews-dash__text"><?php echo esc_html( wp_trim_words( $text, 28, '…' ) ); ?></p>
									<?php endif; ?>
								</div>
								<div class="shyft-reviews-dash__actions">
									<?php if ( '' !== $relative ) : ?>
										<time class="shyft-reviews-dash__time" <?php echo $review_time > 0 ? 'datetime="' . esc_attr( gmdate( 'c', $review_time ) ) . '"' : ''; ?>>
											<?php echo esc_html( $relative ); ?>
										</time>
									<?php endif; ?>
									<?php if ( '' !== $google_reviews_manage_url ) : ?>
										<a class="shyft-reviews-dash__reply" href="<?php echo esc_url( $google_reviews_manage_url ); ?>" target="_blank" rel="noopener noreferrer">
											<?php esc_html_e( 'Jetzt antworten', 'shyft-dashboard' ); ?>
										</a>
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php if ( ! empty( $google_reviews['fetched_at'] ) ) : ?>
						<p class="shyft-reviews-dash__sync">
							<?php
							printf(
								/* translators: %s: datetime */
								esc_html__( 'Zuletzt synchronisiert: %s', 'shyft-dashboard' ),
								esc_html( (string) $google_reviews['fetched_at'] )
							);
							?>
						</p>
					<?php endif; ?>
				</article>
			</section>
			<?php Shyft_Dashboard_Google_Reviews::mark_reviews_seen( $google_reviews ); ?>
		<?php endif; ?>

		<section class="shyft-dashboard__section shyft-dashboard__request" aria-label="<?php esc_attr_e( 'Änderungswunsch', 'shyft-dashboard' ); ?>">
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

		<?php include SHYFT_DASHBOARD_PATH . 'templates/partials/tasks-tracker.php'; ?>
	</main>

	<footer class="shyft-dashboard__footer">
		<p><?php esc_html_e( 'Betreut von SHYFT', 'shyft-dashboard' ); ?></p>
		<p class="shyft-dashboard__version">SHYFT Dashboard <?php echo esc_html( SHYFT_DASHBOARD_VERSION ); ?></p>
	</footer>
</div>
<?php Shyft_Dashboard_Routing::print_footer_assets(); ?>
</body>
</html>
