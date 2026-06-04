<?php
/**
 * Dashboard warmup gate (preload + redirect).
 *
 * @package ShyftDashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$config = Shyft_Dashboard_Warmup::get_script_config( 'redirect' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php esc_html_e( 'Dashboard wird vorbereitet …', 'shyft-dashboard' ); ?></title>
	<style>
		body {
			margin: 0;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			font-family: Roboto, system-ui, sans-serif;
			background: #0f1419;
			color: #e8eaed;
		}
		.shyft-warmup {
			text-align: center;
			padding: 2rem;
			max-width: 22rem;
		}
		.shyft-warmup__spinner {
			width: 2.5rem;
			height: 2.5rem;
			margin: 0 auto 1.25rem;
			border: 3px solid rgba(255, 255, 255, 0.15);
			border-top-color: #7dd3a8;
			border-radius: 50%;
			animation: shyft-warmup-spin 0.8s linear infinite;
		}
		@keyframes shyft-warmup-spin {
			to { transform: rotate(360deg); }
		}
		.shyft-warmup__hint {
			font-size: 0.875rem;
			opacity: 0.75;
			margin-top: 0.75rem;
		}
	</style>
</head>
<body>
	<div class="shyft-warmup" role="status" aria-live="polite">
		<div class="shyft-warmup__spinner" aria-hidden="true"></div>
		<p><?php esc_html_e( 'Dashboard wird vorbereitet …', 'shyft-dashboard' ); ?></p>
		<p class="shyft-warmup__hint"><?php esc_html_e( 'Einmal täglich – danach öffnet sich alles sofort.', 'shyft-dashboard' ); ?></p>
	</div>
	<script>var shyftDashboardWarmup = <?php echo wp_json_encode( $config ); ?>;</script>
	<script src="<?php echo esc_url( SHYFT_DASHBOARD_URL . 'assets/js/dashboard-warmup.js?ver=' . SHYFT_DASHBOARD_VERSION ); ?>"></script>
</body>
</html>
