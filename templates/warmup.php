<?php
/**
 * Dashboard warmup gate (preload + redirect).
 *
 * @package ShyftDashboard
 *
 * @var string $logo_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$config   = Shyft_Dashboard_Warmup::get_script_config( 'redirect' );
$logo_url = $logo_url ?? Shyft_Dashboard_Settings::get_logo_url();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php esc_html_e( 'Dashboard wird vorbereitet …', 'shyft-dashboard' ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( SHYFT_DASHBOARD_FONTS_URL ); ?>" />
	<style>
		body {
			margin: 0;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			font-family: "Bricolage Grotesque", system-ui, sans-serif;
			background: #E0DBD7;
			color: #172A39;
		}
		.shyft-warmup {
			text-align: center;
			padding: 2rem;
			max-width: 22rem;
		}
		.shyft-warmup__logo {
			display: block;
			width: auto;
			height: 28px;
			margin: 0 auto 1.5rem;
			object-fit: contain;
		}
		.shyft-warmup__spinner {
			width: 2.25rem;
			height: 2.25rem;
			margin: 0 auto 1.25rem;
			border: 2px solid rgba(23, 42, 57, 0.12);
			border-top-color: #FC573B;
			border-radius: 50%;
			animation: shyft-warmup-spin 0.8s linear infinite;
		}
		@keyframes shyft-warmup-spin {
			to { transform: rotate(360deg); }
		}
		.shyft-warmup__hint {
			font-size: 0.875rem;
			color: rgba(23, 42, 57, 0.58);
			margin-top: 0.75rem;
		}
	</style>
</head>
<body>
	<div class="shyft-warmup" role="status" aria-live="polite">
		<img
			class="shyft-warmup__logo"
			src="<?php echo esc_url( $logo_url ); ?>"
			alt="<?php esc_attr_e( 'SHYFT', 'shyft-dashboard' ); ?>"
			width="96"
			height="28"
		>
		<div class="shyft-warmup__spinner" aria-hidden="true"></div>
		<p><?php esc_html_e( 'Dashboard wird vorbereitet …', 'shyft-dashboard' ); ?></p>
		<p class="shyft-warmup__hint"><?php esc_html_e( 'Einmal täglich – danach öffnet sich alles sofort.', 'shyft-dashboard' ); ?></p>
	</div>
	<script>var shyftDashboardWarmup = <?php echo wp_json_encode( $config ); ?>;</script>
	<script src="<?php echo esc_url( SHYFT_DASHBOARD_URL . 'assets/js/dashboard-warmup.js?ver=' . SHYFT_DASHBOARD_VERSION ); ?>"></script>
</body>
</html>
