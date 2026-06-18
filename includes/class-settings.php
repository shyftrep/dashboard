<?php
/**
 * Admin settings page for SHYFT Dashboard.
 *
 * @package ShyftDashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders plugin settings in wp-admin.
 */
final class Shyft_Dashboard_Settings {

	public const OPTION_GROUP = 'shyft_dashboard_settings';
	public const PAGE_SLUG    = 'shyft-dashboard-settings';

	public const DEFAULT_LOGO  = '';
	public const BUNDLED_LOGO_SOURCE = '';
	public const MATOMO_SITE_ID = '1';

	/**
	 * Registers settings hooks.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_settings_page' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_admin_assets' ) );
		add_action( 'admin_bar_menu', array( self::class, 'add_admin_bar_link' ), 100 );
		add_action( 'update_option_shyft_dashboard_matomo_token', array( self::class, 'clear_matomo_cache' ) );
		add_action( 'update_option_shyft_dashboard_google_place_id', array( self::class, 'on_google_reviews_config_changed' ), 10, 2 );
		add_action( 'update_option_shyft_dashboard_google_api_key', array( self::class, 'on_google_reviews_config_changed' ), 10, 2 );
	}

	/**
	 * Adds the settings page under Settings.
	 */
	public static function add_settings_page(): void {
		add_options_page(
			__( 'SHYFT Dashboard', 'shyft-dashboard' ),
			__( 'SHYFT Dashboard', 'shyft-dashboard' ),
			'manage_options',
			self::PAGE_SLUG,
			array( self::class, 'render_settings_page' )
		);
	}

	/**
	 * Adds a dashboard preview link to the admin bar for administrators.
	 *
	 * @param WP_Admin_Bar $admin_bar Admin bar instance.
	 */
	public static function add_admin_bar_link( WP_Admin_Bar $admin_bar ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$admin_bar->add_node(
			array(
				'id'    => 'shyft-dashboard-preview',
				'title' => esc_html__( 'Kunden-Dashboard', 'shyft-dashboard' ),
				'href'  => Shyft_Dashboard_Warmup::get_dashboard_entry_url(),
				'meta'  => array(
					'title'  => esc_attr__( 'Dashboard-Vorschau öffnen', 'shyft-dashboard' ),
					'target' => '_blank',
				),
			)
		);
	}

	/**
	 * Registers settings fields via the Settings API.
	 */
	public static function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			'shyft_dashboard_matomo_token',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'shyft_dashboard_logo_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitize_url' ),
				'default'           => self::DEFAULT_LOGO,
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'shyft_dashboard_github_token',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'shyft_dashboard_google_place_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'shyft_dashboard_google_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'shyft_dashboard_google_reviews_custom_css',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitize_custom_css' ),
				'default'           => '',
			)
		);

		add_settings_section(
			'shyft_dashboard_main',
			__( 'Dashboard-Einstellungen', 'shyft-dashboard' ),
			array( self::class, 'render_section_description' ),
			self::PAGE_SLUG
		);

		add_settings_section(
			'shyft_dashboard_matomo',
			__( 'Matomo Analytics', 'shyft-dashboard' ),
			array( self::class, 'render_matomo_section_description' ),
			self::PAGE_SLUG
		);

		add_settings_section(
			'shyft_dashboard_updates',
			__( 'Plugin-Updates (GitHub)', 'shyft-dashboard' ),
			array( self::class, 'render_updates_section_description' ),
			self::PAGE_SLUG
		);

		add_settings_section(
			'shyft_dashboard_google_reviews',
			__( 'Google Reviews', 'shyft-dashboard' ),
			array( self::class, 'render_google_reviews_section_description' ),
			self::PAGE_SLUG
		);

		self::add_field( 'shyft_dashboard_logo_url', __( 'Dashboard-Logo', 'shyft-dashboard' ), 'render_logo_field', 'shyft_dashboard_main' );
		self::add_field( 'shyft_dashboard_matomo_token', __( 'Matomo API-Token (optional)', 'shyft-dashboard' ), 'render_matomo_token_field', 'shyft_dashboard_matomo' );
		self::add_field( 'shyft_dashboard_github_token', __( 'GitHub-Zugriffstoken', 'shyft-dashboard' ), 'render_github_token_field', 'shyft_dashboard_updates' );
		self::add_field( 'shyft_dashboard_update_check', __( 'Update-Prüfung', 'shyft-dashboard' ), 'render_update_check_field', 'shyft_dashboard_updates' );
		self::add_field( 'shyft_dashboard_google_place_id', __( 'Google Place ID', 'shyft-dashboard' ), 'render_google_place_id_field', 'shyft_dashboard_google_reviews' );
		self::add_field( 'shyft_dashboard_google_api_key', __( 'Google API-Schlüssel', 'shyft-dashboard' ), 'render_google_api_key_field', 'shyft_dashboard_google_reviews' );
		self::add_field( 'shyft_dashboard_google_reviews_custom_css', __( 'Widget Custom CSS', 'shyft-dashboard' ), 'render_google_reviews_custom_css_field', 'shyft_dashboard_google_reviews' );
		self::add_field( 'shyft_dashboard_google_reviews_sync', __( 'Bewertungen synchronisieren', 'shyft-dashboard' ), 'render_google_reviews_sync_field', 'shyft_dashboard_google_reviews' );
	}

	/**
	 * Adds a settings field helper.
	 *
	 * @param string $option  Option name.
	 * @param string $label   Field label.
	 * @param string $callback Render callback.
	 * @param string $section Settings section ID.
	 */
	private static function add_field( string $option, string $label, string $callback, string $section ): void {
		add_settings_field(
			$option,
			$label,
			array( self::class, $callback ),
			self::PAGE_SLUG,
			$section,
			array(
				'option' => $option,
				'label'  => $label,
			)
		);
	}

	/**
	 * Sanitizes a URL option value.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_url( $value ): string {
		return esc_url_raw( (string) $value );
	}

	/**
	 * Sanitizes custom CSS for the reviews widget.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_custom_css( $value ): string {
		$value = wp_check_invalid_utf8( (string) $value );
		$value = preg_replace( '#</?\s*(script|style)\b[^>]*>#i', '', $value );

		return trim( $value );
	}

	/**
	 * Enqueues media uploader assets on the settings page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue_admin_assets( string $hook_suffix ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'shyft-dashboard-admin',
			SHYFT_DASHBOARD_URL . 'assets/js/admin-settings.js',
			array( 'jquery' ),
			SHYFT_DASHBOARD_VERSION,
			true
		);
	}

	/**
	 * Renders the section description.
	 */
	public static function render_section_description(): void {
		echo '<p>' . esc_html__( 'Konfiguriere Logo und Matomo für das Kunden-Dashboard.', 'shyft-dashboard' ) . '</p>';
		echo '<p>' . esc_html__( 'Änderungswünsche werden per E-Mail gesendet an:', 'shyft-dashboard' ) . ' <strong>' . esc_html( Shyft_Dashboard_Change_Request::NOTIFY_EMAIL ) . '</strong></p>';
	}

	/**
	 * Renders the Matomo section description with auto-detected values.
	 */
	public static function render_matomo_section_description(): void {
		$matomo_active = class_exists( '\WpMatomo\Bootstrap' );
		$site_id       = self::MATOMO_SITE_ID;

		if ( class_exists( '\WpMatomo\Site' ) ) {
			$detected = (int) ( new \WpMatomo\Site() )->get_current_matomo_site_id();
			if ( $detected > 0 ) {
				$site_id = (string) $detected;
			}
		}
		?>
		<p>
			<?php esc_html_e( 'Matomo ist lokal als WordPress-Plugin installiert. URL und Site-ID werden automatisch gesetzt.', 'shyft-dashboard' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Matomo-URL', 'shyft-dashboard' ); ?></th>
				<td><code><?php echo esc_html( self::get_matomo_url() ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Site-ID', 'shyft-dashboard' ); ?></th>
				<td><code><?php echo esc_html( $site_id ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Matomo-Plugin', 'shyft-dashboard' ); ?></th>
				<td>
					<?php if ( $matomo_active ) : ?>
						<span style="color:#008a20;"><?php esc_html_e( 'Aktiv – Daten werden direkt über das Plugin geladen (kein Token nötig).', 'shyft-dashboard' ); ?></span>
					<?php else : ?>
						<span style="color:#b32d2e;"><?php esc_html_e( 'Nicht erkannt – bitte API-Token unten hinterlegen.', 'shyft-dashboard' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Renders the GitHub updates section description.
	 */
	public static function render_updates_section_description(): void {
		?>
		<p>
			<?php
			printf(
				/* translators: %s: GitHub repository URL */
				esc_html__( 'Updates werden von %s geladen (GitHub Releases mit Tag vX.Y.Z, z. B. v1.0.2).', 'shyft-dashboard' ),
				esc_html( Shyft_Dashboard_Updater::GITHUB_REPO_URL )
			);
			?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Öffentliches Repository:', 'shyft-dashboard' ); ?></strong>
			<?php esc_html_e( 'Kein Token nötig – includes/github-token.php leer lassen und das Einstellungsfeld unten leer.', 'shyft-dashboard' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'Nur bei einem privaten Repository einen Token in includes/github-token.php oder in den Einstellungen hinterlegen.', 'shyft-dashboard' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'Unter Plugins → Zeile „SHYFT Dashboard“ → „Auf Updates prüfen“, oder den Button „GitHub-Verbindung & Update jetzt prüfen“ unten.', 'shyft-dashboard' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders update diagnostics and a manual check button.
	 *
	 * @param array<string, string> $args Field arguments.
	 */
	public static function render_update_check_field( array $args ): void {
		unset( $args );

		$plugin_slug   = Shyft_Dashboard_Plugin_Folder::get_installed_slug();
		$expected_slug = Shyft_Dashboard_Plugin_Folder::SLUG;
		$check_url     = admin_url( 'admin-post.php?action=shyft_dashboard_check_updates' );
		$check_url     = wp_nonce_url( $check_url, 'shyft_dashboard_check_updates' );
		$puc_url       = Shyft_Dashboard_Updater::get_manual_check_url();
		$has_vendor    = is_readable( SHYFT_DASHBOARD_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php' );
		$has_token     = '' !== self::get_github_token();
		?>
		<table class="widefat" style="max-width:640px;margin-bottom:12px;">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Installiert', 'shyft-dashboard' ); ?></th>
					<td><code><?php echo esc_html( SHYFT_DASHBOARD_VERSION ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Plugin-Ordner', 'shyft-dashboard' ); ?></th>
					<td>
						<code><?php echo esc_html( $plugin_slug ); ?></code>
						<?php if ( $expected_slug !== $plugin_slug ) : ?>
							<span style="color:#b32d2e;">
								<?php
								printf(
									/* translators: %s: expected folder name */
									esc_html__( ' (sollte „%s“ heißen)', 'shyft-dashboard' ),
									esc_html( $expected_slug )
								);
								?>
							</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Update-Checker', 'shyft-dashboard' ); ?></th>
					<td><?php echo $has_vendor ? '✓' : '✗'; ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'GitHub-Token', 'shyft-dashboard' ); ?></th>
					<td>
						<?php
						if ( $has_token ) {
							echo esc_html( self::get_github_token_source_label() );
						} else {
							esc_html_e( 'nicht nötig (öffentliches Repo)', 'shyft-dashboard' );
						}
						?>
					</td>
				</tr>
			</tbody>
		</table>

		<p>
			<a href="<?php echo esc_url( $check_url ); ?>" class="button button-secondary">
				<?php esc_html_e( 'GitHub-Verbindung & Update jetzt prüfen', 'shyft-dashboard' ); ?>
			</a>
			<?php if ( '' !== $puc_url ) : ?>
				<a href="<?php echo esc_url( $puc_url ); ?>" class="button button-link">
					<?php esc_html_e( 'Auf Updates prüfen (Plugins-Seite)', 'shyft-dashboard' ); ?>
				</a>
			<?php endif; ?>
		</p>

		<?php
		if ( isset( $_GET['shyft_update_checked'] ) ) {
			$github = Shyft_Dashboard_Updater::test_github_connection();
			$status = Shyft_Dashboard_Updater::get_last_check_status();
			?>
			<div class="notice notice-<?php echo $github['ok'] ? 'success' : 'error'; ?> inline" style="margin-top:12px;">
				<p>
					<strong><?php esc_html_e( 'GitHub:', 'shyft-dashboard' ); ?></strong>
					<?php echo esc_html( $github['message'] ); ?>
					<?php if ( ! empty( $github['version'] ) ) : ?>
						<?php
						printf(
							/* translators: %s: latest release version on GitHub */
							esc_html__( ' Neueste Version: %s', 'shyft-dashboard' ),
							esc_html( $github['version'] )
						);
						?>
					<?php endif; ?>
				</p>
				<?php if ( is_array( $status ) ) : ?>
					<p>
						<strong><?php esc_html_e( 'Updater:', 'shyft-dashboard' ); ?></strong>
						<?php echo esc_html( (string) $status['message'] ); ?>
					</p>
				<?php endif; ?>
			</div>
			<?php
		}
	}

	/**
	 * Renders the GitHub token field for private repository updates.
	 *
	 * @param array<string, string> $args Field arguments.
	 */
	public static function render_github_token_field( array $args ): void {
		$option   = $args['option'];
		$locked   = self::is_github_token_locked();
		$plugin_file = SHYFT_DASHBOARD_PATH . 'includes/github-token.php';

		if ( self::has_github_token_plugin_file() ) {
			?>
			<p class="description">
				<strong><?php esc_html_e( 'Token ist in includes/github-token.php hinterlegt.', 'shyft-dashboard' ); ?></strong>
				<?php esc_html_e( 'Das Feld unten ist nur ein optionaler Fallback.', 'shyft-dashboard' ); ?>
			</p>
			<?php
		} elseif ( self::has_github_token_constant() ) {
			?>
			<p class="description">
				<strong><?php esc_html_e( 'Token ist in wp-config.php gesetzt.', 'shyft-dashboard' ); ?></strong>
			</p>
			<?php
		} else {
			?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: path to token file */
					esc_html__( 'Öffne die Datei %s und trage deinen GitHub-Token zwischen die Anführungszeichen ein:', 'shyft-dashboard' ),
					'<code>includes/github-token.php</code>'
				);
				?>
			</p>
			<pre class="shyft-code-hint" style="background:#f6f7f7;padding:12px;max-width:640px;overflow:auto;"><?php echo esc_html( "return 'dein_github_token';" ); ?></pre>
			<?php
		}

		$value = $locked ? '' : (string) get_option( $option, '' );
		printf(
			'<p><label for="%1$s">%2$s</label></p><input type="password" id="%1$s" name="%1$s" value="%3$s" class="regular-text" autocomplete="off"%4$s />',
			esc_attr( $option ),
			esc_html__( 'Fallback: Token in Datenbank (optional)', 'shyft-dashboard' ),
			esc_attr( $value ),
			$locked ? ' disabled' : ''
		);
		?>
		<p class="description">
			<?php
			printf(
				/* translators: %s: file path */
				esc_html__( 'Empfohlen: %s – wird mit jedem Release auf alle Kundenseiten ausgerollt.', 'shyft-dashboard' ),
				'<code>' . esc_html( $plugin_file ) . '</code>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Renders the optional Matomo token field with instructions.
	 *
	 * @param array<string, string> $args Field arguments.
	 */
	public static function render_matomo_token_field( array $args ): void {
		$option = $args['option'];
		$value  = get_option( $option, '' );
		printf(
			'<input type="password" id="%1$s" name="%1$s" value="%2$s" class="regular-text" autocomplete="off" />',
			esc_attr( $option ),
			esc_attr( (string) $value )
		);
		?>
		<p class="description">
			<?php esc_html_e( 'Nur als Fallback nötig, wenn das Matomo-Plugin nicht erkannt wird. Der Token wird ausschließlich serverseitig verwendet.', 'shyft-dashboard' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Für Matomo for WordPress 5.3+: WordPress-Anwendungspasswort im Format „benutzername:anwendungspasswort“ (siehe Matomo FAQ). Die Matomo-Oberfläche unter /wp-content/plugins/matomo/app ist login-geschützt – das Dashboard nutzt die interne Plugin-API.', 'shyft-dashboard' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the logo URL field with media uploader button.
	 *
	 * @param array<string, string> $args Field arguments.
	 */
	public static function render_logo_field( array $args ): void {
		$option = $args['option'];
		$value  = get_option( $option, self::DEFAULT_LOGO );
		$preview = ! empty( $value ) ? (string) $value : self::get_bundled_logo_url();
		?>
		<div class="shyft-logo-field">
			<input type="url" id="<?php echo esc_attr( $option ); ?>" name="<?php echo esc_attr( $option ); ?>" value="<?php echo esc_url( (string) $value ); ?>" class="regular-text shyft-logo-url" />
			<button type="button" class="button shyft-upload-logo"><?php esc_html_e( 'Logo auswählen', 'shyft-dashboard' ); ?></button>
			<div class="shyft-logo-preview" style="margin-top:12px;">
				<img src="<?php echo esc_url( $preview ); ?>" alt="<?php esc_attr_e( 'Dashboard-Logo', 'shyft-dashboard' ); ?>" style="max-height:60px;width:auto;" />
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the settings page markup.
	 */
	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$dashboard_url = Shyft_Dashboard_Routing::get_dashboard_url();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<p>
				<a href="<?php echo esc_url( $dashboard_url ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Kunden-Dashboard öffnen', 'shyft-dashboard' ); ?>
				</a>
				<span class="description" style="margin-left:8px;">
					<?php esc_html_e( 'Als Administrator wirst du nach dem Login nicht automatisch weitergeleitet – nutze diesen Link für die Vorschau.', 'shyft-dashboard' ); ?>
				</span>
			</p>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Returns the Matomo API base URL (local plugin path on main domain).
	 */
	public static function get_matomo_url(): string {
		return home_url( '/wp-content/plugins/matomo/app' );
	}

	/**
	 * Returns the configured Matomo token (fallback only).
	 */
	public static function get_matomo_token(): string {
		return (string) get_option( 'shyft_dashboard_matomo_token', '' );
	}

	/**
	 * Whether the GitHub token is defined via wp-config.php.
	 */
	public static function has_github_token_constant(): bool {
		return defined( 'SHYFT_DASHBOARD_GITHUB_TOKEN' )
			&& is_string( SHYFT_DASHBOARD_GITHUB_TOKEN )
			&& '' !== SHYFT_DASHBOARD_GITHUB_TOKEN;
	}

	/**
	 * Whether a token is set in includes/github-token.php.
	 */
	public static function has_github_token_plugin_file(): bool {
		return '' !== self::get_github_token_from_plugin_file();
	}

	/**
	 * Token from includes/github-token.php (primary for multi-site rollouts).
	 */
	public static function get_github_token_from_plugin_file(): string {
		static $cached = null;

		if ( null !== $cached ) {
			return $cached;
		}

		$file = SHYFT_DASHBOARD_PATH . 'includes/github-token.php';

		if ( ! is_readable( $file ) ) {
			$cached = '';
			return $cached;
		}

		$value = include $file;
		$cached = is_string( $value ) ? trim( $value ) : '';

		return $cached;
	}

	/**
	 * Whether the token is fixed outside the settings form.
	 */
	public static function is_github_token_locked(): bool {
		return self::has_github_token_constant() || self::has_github_token_plugin_file();
	}

	/**
	 * Human-readable label for where the active token comes from.
	 */
	public static function get_github_token_source_label(): string {
		if ( self::has_github_token_constant() ) {
			return (string) __( 'gesetzt (wp-config.php)', 'shyft-dashboard' );
		}

		if ( self::has_github_token_plugin_file() ) {
			return (string) __( 'gesetzt (includes/github-token.php)', 'shyft-dashboard' );
		}

		return (string) __( 'gesetzt (Einstellungen)', 'shyft-dashboard' );
	}

	/**
	 * Returns the GitHub token for private repository updates.
	 *
	 * Priority: wp-config → includes/github-token.php → Einstellungen.
	 */
	public static function get_github_token(): string {
		if ( self::has_github_token_constant() ) {
			return SHYFT_DASHBOARD_GITHUB_TOKEN;
		}

		if ( self::has_github_token_plugin_file() ) {
			return self::get_github_token_from_plugin_file();
		}

		return (string) get_option( 'shyft_dashboard_github_token', '' );
	}

	/**
	 * Returns the Matomo site ID (always 1 for local installs).
	 */
	public static function get_matomo_site_id(): string {
		return self::MATOMO_SITE_ID;
	}

	public static function get_bundled_logo_url( string $variant = 'dark' ): string {
		$file = 'light' === $variant ? 'shyft-logo-light.png' : 'shyft-logo-dark.png';

		return SHYFT_DASHBOARD_URL . 'assets/images/' . $file;
	}

	/**
	 * Whether the default bundled shyft logos are used (no custom URL in settings).
	 */
	public static function uses_bundled_logo(): bool {
		return '' === trim( (string) get_option( 'shyft_dashboard_logo_url', self::DEFAULT_LOGO ) );
	}

	/**
	 * Returns the configured dashboard logo URL (dark logo on light backgrounds by default).
	 */
	public static function get_logo_url(): string {
		$logo = (string) get_option( 'shyft_dashboard_logo_url', self::DEFAULT_LOGO );

		if ( '' === trim( $logo ) ) {
			return self::get_bundled_logo_url();
		}

		return esc_url( $logo ) ?: self::get_bundled_logo_url();
	}

	/**
	 * Clears cached Matomo data after settings change.
	 */
	public static function clear_matomo_cache(): void {
		Shyft_Dashboard_Matomo::clear_cache();
	}

	/**
	 * Triggers review sync when Google settings change.
	 *
	 * @param mixed $old_value Previous value.
	 * @param mixed $value     New value.
	 */
	public static function on_google_reviews_config_changed( $old_value, $value ): void {
		unset( $old_value, $value );
		Shyft_Dashboard_Google_Reviews::on_config_changed( '', '' );
	}

	/**
	 * Google Reviews section intro.
	 */
	public static function render_google_reviews_section_description(): void {
		?>
		<p><?php esc_html_e( 'Bewertungen werden per Cron alle 12 Stunden von Google geladen und lokal gespeichert. Die Website zeigt nur die gespeicherten Daten – keine API-Aufrufe beim Seitenaufruf.', 'shyft-dashboard' ); ?></p>
		<p>
			<?php esc_html_e( 'Shortcode:', 'shyft-dashboard' ); ?>
			<code>[clicklabs_reviews]</code>
			<?php esc_html_e( '· Elementor-Widget „Google Reviews (clicklabs)“', 'shyft-dashboard' ); ?>
		</p>
		<?php
	}

	/**
	 * @param array<string, string> $args Field arguments.
	 */
	public static function render_google_place_id_field( array $args ): void {
		$option = $args['option'];
		$value  = get_option( $option, '' );
		?>
		<input
			type="text"
			id="<?php echo esc_attr( $option ); ?>"
			name="<?php echo esc_attr( $option ); ?>"
			value="<?php echo esc_attr( (string) $value ); ?>"
			class="regular-text"
			placeholder="ChIJxxxxxxxxxxxxxxxxxxxx"
			autocomplete="off"
		/>
		<p class="description">
			<?php
			printf(
				wp_kses(
					/* translators: %s: URL to Google Place ID Finder */
					__( 'Place ID aus der Google Maps URL oder dem <a href="%s" target="_blank" rel="noopener noreferrer">Place ID Finder</a> von Google.', 'shyft-dashboard' ),
					array(
						'a' => array(
							'href'   => array(),
							'target' => array(),
							'rel'    => array(),
						),
					)
				),
				esc_url( 'https://developers.google.com/maps/documentation/javascript/examples/places-placeid-finder' )
			);
			?>
		</p>
		<?php
	}

	/**
	 * @param array<string, string> $args Field arguments.
	 */
	public static function render_google_api_key_field( array $args ): void {
		$option = $args['option'];
		$value  = get_option( $option, '' );
		$locked = self::is_google_api_key_locked();
		?>
		<input
			type="password"
			id="<?php echo esc_attr( $option ); ?>"
			name="<?php echo esc_attr( $option ); ?>"
			value="<?php echo $locked ? '' : esc_attr( (string) $value ); ?>"
			class="regular-text"
			autocomplete="new-password"
			<?php disabled( $locked ); ?>
		/>
		<p class="description">
			<?php
			if ( $locked ) {
				echo esc_html( self::get_google_api_key_source_label() );
			} else {
				esc_html_e( 'Places API muss in der Google Cloud Console aktiviert sein. Alternativ: includes/google-api-key.php oder SHYFT_DASHBOARD_GOOGLE_API_KEY in wp-config.php.', 'shyft-dashboard' );
			}
			?>
		</p>
		<?php
	}

	/**
	 * @param array<string, string> $args Field arguments.
	 */
	public static function render_google_reviews_custom_css_field( array $args ): void {
		$option   = $args['option'];
		$value    = get_option( $option, '' );
		$template = self::get_google_reviews_css_template();
		?>
		<textarea
			id="<?php echo esc_attr( $option ); ?>"
			name="<?php echo esc_attr( $option ); ?>"
			rows="14"
			class="large-text code"
			style="font-family: Consolas, Monaco, monospace; font-size: 12px;"
			spellcheck="false"
		><?php echo esc_textarea( (string) $value ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Optionales CSS für Shortcode und Elementor-Widget. Leer lassen = Kacheln im Google-Stil, Hauptbereich ohne Hintergrund/Rahmen. Wird nach dem Basis-Stylesheet geladen.', 'shyft-dashboard' ); ?>
		</p>
		<details style="margin-top: 12px; max-width: 720px;">
			<summary><?php esc_html_e( 'CSS-Vorlage zum Kopieren', 'shyft-dashboard' ); ?></summary>
			<textarea
				readonly
				rows="14"
				class="large-text code"
				style="margin-top: 8px; font-family: Consolas, Monaco, monospace; font-size: 12px;"
				onfocus="this.select();"
				aria-label="<?php esc_attr_e( 'CSS-Vorlage für Google Reviews Widget', 'shyft-dashboard' ); ?>"
			><?php echo esc_textarea( $template ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Markieren, kopieren und oben einfügen – dann anpassen.', 'shyft-dashboard' ); ?></p>
		</details>
		<?php
	}

	/**
	 * Starter CSS for the Google Reviews widget (copy template).
	 */
	public static function get_google_reviews_css_template(): string {
		return <<<'CSS'
/* Hauptbereich: transparent – Header/CTA per Theme oder hier anpassen */
.shyft-reviews {
	background: transparent;
	border: 0;
	padding: 0;
}

/* Kacheln wie Google Reviews (Screenshot-Vorlage) */
.shyft-reviews {
	--shyft-reviews-tile-bg: #fff;
	--shyft-reviews-tile-border: #e8eaed;
	--shyft-reviews-tile-accent-1: #34a853;
	--shyft-reviews-tile-accent-2: #4285f4;
	--shyft-reviews-tile-accent-3: #a142f4;
	--shyft-reviews-star: #fbbc04;
	--shyft-reviews-tile-radius: 8px;
}

.shyft-reviews__card {
	background: var(--shyft-reviews-tile-bg);
	border: 1px solid var(--shyft-reviews-tile-border);
	border-radius: var(--shyft-reviews-tile-radius);
	box-shadow: 0 1px 2px rgba(60, 64, 67, 0.08);
}

.shyft-reviews__card:nth-child(3n + 1) { border-top: 3px solid var(--shyft-reviews-tile-accent-1); }
.shyft-reviews__card:nth-child(3n + 2) { border-top: 3px solid var(--shyft-reviews-tile-accent-2); }
.shyft-reviews__card:nth-child(3n + 3) { border-top: 3px solid var(--shyft-reviews-tile-accent-3); }

.shyft-reviews__stars,
.shyft-reviews__stars-small {
	color: var(--shyft-reviews-star);
}

/* Optional: Header & Button im Theme-Stil */
/*
.shyft-reviews__header { margin-bottom: 24px; }
.shyft-reviews__cta { ... }
*/
CSS;
	}

	/**
	 * @param array<string, string> $args Field arguments.
	 */
	public static function render_google_reviews_sync_field( array $args ): void {
		unset( $args );

		$data     = Shyft_Dashboard_Google_Reviews::get_stored_data();
		$sync_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=shyft_sync_google_reviews' ),
			'shyft_sync_google_reviews'
		);
		?>
		<table class="widefat" style="max-width:640px;margin-bottom:12px;">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'shyft-dashboard' ); ?></th>
					<td>
						<?php
						if ( ! empty( $data['available'] ) ) {
							printf(
								/* translators: 1: rating, 2: review count */
								esc_html__( '%1$s ★ · %2$s Bewertungen', 'shyft-dashboard' ),
								esc_html( number_format_i18n( (float) ( $data['rating'] ?? 0 ), 1 ) ),
								esc_html( number_format_i18n( (int) ( $data['total'] ?? 0 ) ) )
							);
						} elseif ( Shyft_Dashboard_Google_Reviews::is_configured() ) {
							esc_html_e( 'Noch keine Daten – bitte synchronisieren.', 'shyft-dashboard' );
						} else {
							esc_html_e( 'Place ID und API-Schlüssel erforderlich.', 'shyft-dashboard' );
						}
						?>
					</td>
				</tr>
				<?php if ( ! empty( $data['fetched_at'] ) ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Zuletzt aktualisiert', 'shyft-dashboard' ); ?></th>
						<td><?php echo esc_html( (string) $data['fetched_at'] ); ?></td>
					</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Cron', 'shyft-dashboard' ); ?></th>
					<td><?php esc_html_e( 'Automatisch alle 12 Stunden (twicedaily)', 'shyft-dashboard' ); ?></td>
				</tr>
			</tbody>
		</table>
		<p>
			<a href="<?php echo esc_url( $sync_url ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Jetzt von Google synchronisieren', 'shyft-dashboard' ); ?>
			</a>
		</p>
		<?php
		if ( isset( $_GET['shyft_reviews_synced'] ) ) {
			$ok      = '1' === (string) wp_unslash( (string) $_GET['shyft_reviews_synced'] );
			$message = isset( $_GET['shyft_reviews_message'] ) ? rawurldecode( (string) wp_unslash( (string) $_GET['shyft_reviews_message'] ) ) : '';
			?>
			<div class="notice notice-<?php echo $ok ? 'success' : 'error'; ?> inline" style="margin-top:12px;">
				<p>
					<?php
					if ( $ok ) {
						esc_html_e( 'Google-Bewertungen wurden erfolgreich synchronisiert.', 'shyft-dashboard' );
					} else {
						esc_html_e( 'Synchronisation fehlgeschlagen.', 'shyft-dashboard' );
						if ( '' !== $message ) {
							echo ' ' . esc_html( $message );
						}
					}
					?>
				</p>
			</div>
			<?php
		}
	}

	public static function get_google_place_id(): string {
		return trim( (string) get_option( 'shyft_dashboard_google_place_id', '' ) );
	}

	public static function has_google_api_key_constant(): bool {
		return defined( 'SHYFT_DASHBOARD_GOOGLE_API_KEY' )
			&& is_string( SHYFT_DASHBOARD_GOOGLE_API_KEY )
			&& '' !== SHYFT_DASHBOARD_GOOGLE_API_KEY;
	}

	public static function get_google_api_key_from_plugin_file(): string {
		static $cached = null;

		if ( null !== $cached ) {
			return $cached;
		}

		$file = SHYFT_DASHBOARD_PATH . 'includes/google-api-key.php';

		if ( ! is_readable( $file ) ) {
			$cached = '';
			return $cached;
		}

		$value  = include $file;
		$cached = is_string( $value ) ? trim( $value ) : '';

		return $cached;
	}

	public static function has_google_api_key_plugin_file(): bool {
		return '' !== self::get_google_api_key_from_plugin_file();
	}

	public static function is_google_api_key_locked(): bool {
		return self::has_google_api_key_constant() || self::has_google_api_key_plugin_file();
	}

	public static function get_google_api_key_source_label(): string {
		if ( self::has_google_api_key_constant() ) {
			return (string) __( 'gesetzt (wp-config.php)', 'shyft-dashboard' );
		}

		if ( self::has_google_api_key_plugin_file() ) {
			return (string) __( 'gesetzt (includes/google-api-key.php)', 'shyft-dashboard' );
		}

		return (string) __( 'gesetzt (Einstellungen)', 'shyft-dashboard' );
	}

	public static function get_google_api_key(): string {
		if ( self::has_google_api_key_constant() ) {
			return SHYFT_DASHBOARD_GOOGLE_API_KEY;
		}

		if ( self::has_google_api_key_plugin_file() ) {
			return self::get_google_api_key_from_plugin_file();
		}

		return trim( (string) get_option( 'shyft_dashboard_google_api_key', '' ) );
	}

	public static function get_google_reviews_custom_css(): string {
		return trim( (string) get_option( 'shyft_dashboard_google_reviews_custom_css', '' ) );
	}
}
