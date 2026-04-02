<?php
/**
 * Main plugin bootstrapper.
 *
 * @package CloudflareCirino
 */

namespace Cloudflare_Cirino;

use Cloudflare_Cirino\Admin\Actions;
use Cloudflare_Cirino\Admin\Settings;
use Cloudflare_Cirino\Admin\Settings_Page;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boot plugin.
	 *
	 * @return void
	 */
	public function boot(): void {
		$this->load_dependencies();

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		$options       = new Options();
		$activity_log  = new Activity_Log();
		$api_client    = new API_Client( $options );
		$purge_service = new Purge_Service( $options, $api_client, $activity_log );

		$settings      = new Settings( $options );
		$settings_page = new Settings_Page( $options, $activity_log );
		$actions       = new Actions( $purge_service, $api_client, $settings_page, $options, $activity_log );
		$content_hooks = new Content_Hooks( $purge_service );

		$settings->register();
		$settings_page->register();
		$actions->register();
		$content_hooks->register();
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'cloudflare-cirino', false, dirname( CLOUDFLARE_CIRINO_BASENAME ) . '/languages' );
	}

	/**
	 * Load class files.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		require_once CLOUDFLARE_CIRINO_PATH . 'includes/class-options.php';
		require_once CLOUDFLARE_CIRINO_PATH . 'includes/class-activity-log.php';
		require_once CLOUDFLARE_CIRINO_PATH . 'includes/class-api-client.php';
		require_once CLOUDFLARE_CIRINO_PATH . 'includes/class-purge-service.php';
		require_once CLOUDFLARE_CIRINO_PATH . 'includes/class-content-hooks.php';
		require_once CLOUDFLARE_CIRINO_PATH . 'includes/Admin/class-settings.php';
		require_once CLOUDFLARE_CIRINO_PATH . 'includes/Admin/class-settings-page.php';
		require_once CLOUDFLARE_CIRINO_PATH . 'includes/Admin/class-actions.php';
	}
}
