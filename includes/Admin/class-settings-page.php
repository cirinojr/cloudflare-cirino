<?php
/**
 * Admin settings page.
 *
 * @package CloudflareCirino
 */

namespace Cloudflare_Cirino\Admin;

use Cloudflare_Cirino\Activity_Log;
use Cloudflare_Cirino\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Renders plugin admin screen.
 */
class Settings_Page {

	/**
	 * Flash notice transient key.
	 */
	public const NOTICE_TRANSIENT = 'cloudflare_cirino_admin_notice';

	/**
	 * Admin page slug.
	 */
	public const PAGE_SLUG = 'cloudflare-cirino';

	/**
	 * Options repository.
	 *
	 * @var Options
	 */
	private $options;

	/**
	 * Activity log.
	 *
	 * @var Activity_Log
	 */
	private $activity_log;

	/**
	 * Constructor.
	 *
	 * @param Options      $options Options repository.
	 * @param Activity_Log $activity_log Activity log.
	 */
	public function __construct( Options $options, Activity_Log $activity_log ) {
		$this->options      = $options;
		$this->activity_log = $activity_log;
	}

	/**
	 * Register page and assets hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register submenu page.
	 *
	 * @return void
	 */
	public function register_page(): void {
		add_submenu_page(
			'tools.php',
			__( 'Cloudflare Cirino', 'cloudflare-cirino' ),
			__( 'Cloudflare Cirino', 'cloudflare-cirino' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'tools_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'cloudflare-cirino-admin',
			CLOUDFLARE_CIRINO_URL . 'assets/css/admin.css',
			array(),
			CLOUDFLARE_CIRINO_VERSION
		);

		wp_enqueue_script(
			'cloudflare-cirino-admin',
			CLOUDFLARE_CIRINO_URL . 'assets/js/admin.js',
			array(),
			CLOUDFLARE_CIRINO_VERSION,
			true
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'cloudflare-cirino' ) );
		}

		$this->render_flash_notice();

		$settings = $this->options->get_settings();
		$runtime  = $this->options->get_runtime();
		$activity = $this->activity_log->recent( 6 );

		$status = $this->connection_status( $settings, $runtime );
		?>
		<div class="wrap cf-cirino-wrap">
			<div class="cf-cirino-hero">
				<div>
					<h1><?php esc_html_e( 'Cloudflare Cirino', 'cloudflare-cirino' ); ?></h1>
					<p><?php esc_html_e( 'Automatic cache invalidation for WordPress content changes, with manual controls when needed.', 'cloudflare-cirino' ); ?></p>
				</div>
				<div class="cf-cirino-hero-actions">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'cloudflare_cirino_test_connection' ); ?>
						<input type="hidden" name="action" value="cloudflare_cirino_test_connection" />
						<button type="submit" class="button cf-cirino-button-secondary"><?php esc_html_e( 'Test Connection', 'cloudflare-cirino' ); ?></button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'cloudflare_cirino_purge_now' ); ?>
						<input type="hidden" name="action" value="cloudflare_cirino_purge_now" />
						<button type="submit" class="button button-primary cf-cirino-button-primary" data-cloudflare-cirino-confirm="1" data-cloudflare-cirino-confirm-text="<?php echo esc_attr__( 'Run cache purge now?', 'cloudflare-cirino' ); ?>"><?php esc_html_e( 'Purge Now', 'cloudflare-cirino' ); ?></button>
					</form>
				</div>
			</div>

			<?php settings_errors( 'cloudflare_cirino' ); ?>

			<div class="cf-cirino-grid">
				<section class="cf-cirino-card">
					<h2><?php esc_html_e( 'Connection Status', 'cloudflare-cirino' ); ?></h2>
					<p class="cf-cirino-status-line">
						<span class="cf-cirino-badge cf-cirino-badge--<?php echo esc_attr( $status['type'] ); ?>"><?php echo esc_html( $status['label'] ); ?></span>
						<span><?php echo esc_html( $status['description'] ); ?></span>
					</p>
					<p>
						<strong><?php esc_html_e( 'Last test:', 'cloudflare-cirino' ); ?></strong>
						<?php echo esc_html( $this->format_timestamp( (int) $runtime['last_test_at'] ) ); ?>
					</p>
					<p>
						<strong><?php esc_html_e( 'Last purge:', 'cloudflare-cirino' ); ?></strong>
						<?php echo esc_html( $this->format_timestamp( (int) $runtime['last_purge_at'] ) ); ?>
					</p>
				</section>

				<section class="cf-cirino-card">
					<h2><?php esc_html_e( 'Last Purge Result', 'cloudflare-cirino' ); ?></h2>
					<?php if ( ! empty( $runtime['last_purge_message'] ) ) : ?>
						<p><?php echo esc_html( (string) $runtime['last_purge_message'] ); ?></p>
					<?php else : ?>
						<p class="cf-cirino-empty"><?php esc_html_e( 'No purge activity has run yet.', 'cloudflare-cirino' ); ?></p>
					<?php endif; ?>
				</section>
			</div>

			<section class="cf-cirino-card cf-cirino-card--form">
				<h2><?php esc_html_e( 'Configuration', 'cloudflare-cirino' ); ?></h2>
				<form method="post" action="options.php" autocomplete="off">
					<?php settings_fields( 'cloudflare_cirino_settings_group' ); ?>

					<div class="cf-cirino-field-grid">
						<div class="cf-cirino-field">
							<label for="cloudflare-cirino-auth-mode"><?php esc_html_e( 'Authentication Mode', 'cloudflare-cirino' ); ?></label>
							<select id="cloudflare-cirino-auth-mode" name="cloudflare_cirino_settings[auth_mode]" data-cloudflare-cirino-auth-mode="1">
								<option value="token" <?php selected( (string) $settings['auth_mode'], 'token' ); ?>><?php esc_html_e( 'API Token (recommended)', 'cloudflare-cirino' ); ?></option>
								<option value="legacy" <?php selected( (string) $settings['auth_mode'], 'legacy' ); ?>><?php esc_html_e( 'Legacy Email + Global API Key', 'cloudflare-cirino' ); ?></option>
							</select>
						</div>

						<div class="cf-cirino-field">
							<label for="cloudflare-cirino-zone-id"><?php esc_html_e( 'Zone ID', 'cloudflare-cirino' ); ?></label>
							<input id="cloudflare-cirino-zone-id" name="cloudflare_cirino_settings[zone_id]" type="text" value="<?php echo esc_attr( (string) $settings['zone_id'] ); ?>" autocomplete="off" />
						</div>

						<div class="cf-cirino-field" data-cloudflare-cirino-auth-panel="token">
							<label for="cloudflare-cirino-token"><?php esc_html_e( 'API Token', 'cloudflare-cirino' ); ?></label>
							<input id="cloudflare-cirino-token" name="cloudflare_cirino_settings[api_token]" type="password" value="" placeholder="<?php echo ! empty( $settings['api_token'] ) ? esc_attr__( 'Saved token will be kept if left blank', 'cloudflare-cirino' ) : ''; ?>" autocomplete="new-password" />
							<p class="description"><?php esc_html_e( 'Use a token with Zone > Cache Purge permission.', 'cloudflare-cirino' ); ?></p>
						</div>

						<div class="cf-cirino-field">
							<label for="cloudflare-cirino-purge-mode"><?php esc_html_e( 'Purge Mode', 'cloudflare-cirino' ); ?></label>
							<select id="cloudflare-cirino-purge-mode" name="cloudflare_cirino_settings[purge_mode]">
								<option value="targeted" <?php selected( (string) $settings['purge_mode'], 'targeted' ); ?>><?php esc_html_e( 'Targeted URLs (permalink + related pages)', 'cloudflare-cirino' ); ?></option>
								<option value="everything" <?php selected( (string) $settings['purge_mode'], 'everything' ); ?>><?php esc_html_e( 'Purge Everything', 'cloudflare-cirino' ); ?></option>
							</select>
						</div>
					</div>

					<details class="cf-cirino-advanced" data-cloudflare-cirino-auth-panel="legacy">
						<summary><?php esc_html_e( 'Advanced: Legacy Authentication', 'cloudflare-cirino' ); ?></summary>
						<div class="cf-cirino-field-grid">
							<div class="cf-cirino-field">
								<label for="cloudflare-cirino-email"><?php esc_html_e( 'Cloudflare Email', 'cloudflare-cirino' ); ?></label>
								<input id="cloudflare-cirino-email" name="cloudflare_cirino_settings[legacy_email]" type="email" value="<?php echo esc_attr( (string) $settings['legacy_email'] ); ?>" autocomplete="off" />
							</div>
							<div class="cf-cirino-field">
								<label for="cloudflare-cirino-global-key"><?php esc_html_e( 'Global API Key', 'cloudflare-cirino' ); ?></label>
								<input id="cloudflare-cirino-global-key" name="cloudflare_cirino_settings[legacy_api_key]" type="password" value="" placeholder="<?php echo ! empty( $settings['legacy_api_key'] ) ? esc_attr__( 'Saved key will be kept if left blank', 'cloudflare-cirino' ) : ''; ?>" autocomplete="new-password" />
							</div>
						</div>
					</details>

					<?php submit_button( __( 'Save Settings', 'cloudflare-cirino' ), 'primary cf-cirino-button-primary', 'submit', false ); ?>
				</form>
			</section>

			<section class="cf-cirino-card">
				<h2><?php esc_html_e( 'Recent Activity', 'cloudflare-cirino' ); ?></h2>
				<?php if ( empty( $activity ) ) : ?>
					<p class="cf-cirino-empty"><?php esc_html_e( 'No activity yet. Save settings and trigger a content update to see events here.', 'cloudflare-cirino' ); ?></p>
				<?php else : ?>
					<ul class="cf-cirino-activity-list">
						<?php foreach ( $activity as $item ) : ?>
							<?php
							$type      = isset( $item['type'] ) ? (string) $item['type'] : 'info';
							$message   = isset( $item['message'] ) ? (string) $item['message'] : '';
							$timestamp = isset( $item['timestamp'] ) ? (int) $item['timestamp'] : 0;
							?>
							<li class="cf-cirino-activity-item">
								<span class="cf-cirino-badge cf-cirino-badge--<?php echo esc_attr( $type ); ?>"><?php echo esc_html( ucfirst( $type ) ); ?></span>
								<span class="cf-cirino-activity-message"><?php echo esc_html( $message ); ?></span>
								<time datetime="<?php echo esc_attr( gmdate( 'c', $timestamp ) ); ?>"><?php echo esc_html( $this->format_timestamp( $timestamp ) ); ?></time>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		</div>
		<?php
	}

	/**
	 * Get page URL.
	 *
	 * @return string
	 */
	public function get_page_url(): string {
		return admin_url( 'tools.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Register transient flash notice.
	 *
	 * @param string $type success|error
	 * @param string $message Notice text.
	 * @return void
	 */
	public function set_flash_notice( string $type, string $message ): void {
		$type = ( 'error' === $type ) ? 'error' : 'success';

		set_transient(
			self::NOTICE_TRANSIENT,
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Render transient flash notice.
	 *
	 * @return void
	 */
	private function render_flash_notice(): void {
		$notice = get_transient( self::NOTICE_TRANSIENT );
		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		$type = ( isset( $notice['type'] ) && 'error' === $notice['type'] ) ? 'error' : 'updated';
		add_settings_error( 'cloudflare_cirino', 'cloudflare_cirino_flash', sanitize_text_field( (string) $notice['message'] ), $type );
		delete_transient( self::NOTICE_TRANSIENT );
	}

	/**
	 * Build connection status summary.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 * @param array<string,mixed> $runtime Runtime metadata.
	 * @return array<string,string>
	 */
	private function connection_status( array $settings, array $runtime ): array {
		$last_test_status  = isset( $runtime['last_test_status'] ) ? (string) $runtime['last_test_status'] : 'unknown';
		$last_test_message = isset( $runtime['last_test_message'] ) ? (string) $runtime['last_test_message'] : '';

		if ( '' === (string) $settings['zone_id'] ) {
			return array(
				'type'        => 'info',
				'label'       => __( 'Not configured', 'cloudflare-cirino' ),
				'description' => __( 'Add credentials and run a connection test.', 'cloudflare-cirino' ),
			);
		}

		if ( 'success' === $last_test_status ) {
			return array(
				'type'        => 'success',
				'label'       => __( 'Connected', 'cloudflare-cirino' ),
				'description' => __( 'Cloudflare API access validated.', 'cloudflare-cirino' ),
			);
		}

		if ( 'error' === $last_test_status ) {
			return array(
				'type'        => 'error',
				'label'       => __( 'Connection failed', 'cloudflare-cirino' ),
				'description' => $last_test_message,
			);
		}

		return array(
			'type'        => 'info',
			'label'       => __( 'Unknown', 'cloudflare-cirino' ),
			'description' => __( 'Connection has not been tested yet.', 'cloudflare-cirino' ),
		);
	}

	/**
	 * Format timestamp for admin display.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	private function format_timestamp( int $timestamp ): string {
		if ( $timestamp <= 0 ) {
			return __( 'Never', 'cloudflare-cirino' );
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}
}
