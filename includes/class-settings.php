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

	public const DEFAULT_LOGO  = 'https://shyft.rocks/wp-content/uploads/2026/02/shyft-1-e1769653084451.png';
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
				'href'  => Shyft_Dashboard_Routing::get_dashboard_url(),
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

		self::add_field( 'shyft_dashboard_logo_url', __( 'Dashboard-Logo', 'shyft-dashboard' ), 'render_logo_field', 'shyft_dashboard_main' );
		self::add_field( 'shyft_dashboard_matomo_token', __( 'Matomo API-Token (optional)', 'shyft-dashboard' ), 'render_matomo_token_field', 'shyft_dashboard_matomo' );
		self::add_field( 'shyft_dashboard_github_token', __( 'GitHub-Zugriffstoken', 'shyft-dashboard' ), 'render_github_token_field', 'shyft_dashboard_updates' );
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
			<?php esc_html_e( 'Bei einem öffentlichen Repository ist kein Token nötig. Für ein privates Repository: Fine-grained Personal Access Token mit Lesezugriff auf dieses Repository.', 'shyft-dashboard' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the GitHub token field for private repository updates.
	 *
	 * @param array<string, string> $args Field arguments.
	 */
	public static function render_github_token_field( array $args ): void {
		$option = $args['option'];
		$value  = get_option( $option, '' );
		printf(
			'<input type="password" id="%1$s" name="%1$s" value="%2$s" class="regular-text" autocomplete="off" />',
			esc_attr( $option ),
			esc_attr( (string) $value )
		);
		?>
		<p class="description">
			<?php esc_html_e( 'Nur für private GitHub-Repositories. Token wird nur serverseitig für Update-Prüfungen verwendet.', 'shyft-dashboard' ); ?>
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
		?>
		<div class="shyft-logo-field">
			<input type="url" id="<?php echo esc_attr( $option ); ?>" name="<?php echo esc_attr( $option ); ?>" value="<?php echo esc_url( (string) $value ); ?>" class="regular-text shyft-logo-url" />
			<button type="button" class="button shyft-upload-logo"><?php esc_html_e( 'Logo auswählen', 'shyft-dashboard' ); ?></button>
			<div class="shyft-logo-preview" style="margin-top:12px;">
				<?php if ( ! empty( $value ) ) : ?>
					<img src="<?php echo esc_url( (string) $value ); ?>" alt="<?php esc_attr_e( 'Dashboard-Logo', 'shyft-dashboard' ); ?>" style="max-height:60px;width:auto;" />
				<?php endif; ?>
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
	 * Returns the GitHub token for private repository updates.
	 */
	public static function get_github_token(): string {
		return (string) get_option( 'shyft_dashboard_github_token', '' );
	}

	/**
	 * Returns the Matomo site ID (always 1 for local installs).
	 */
	public static function get_matomo_site_id(): string {
		return self::MATOMO_SITE_ID;
	}

	public static function get_logo_url(): string {
		$logo = get_option( 'shyft_dashboard_logo_url', self::DEFAULT_LOGO );
		return esc_url( (string) $logo ) ?: self::DEFAULT_LOGO;
	}

	/**
	 * Clears cached Matomo data after settings change.
	 */
	public static function clear_matomo_cache(): void {
		Shyft_Dashboard_Matomo::clear_cache();
	}
}
