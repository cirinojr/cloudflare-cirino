<?php
/**
 * Admin actions handlers.
 *
 * @package CloudflareCirino
 */

namespace Cloudflare_Cirino\Admin;

use Cloudflare_Cirino\API_Client;
use Cloudflare_Cirino\Options;
use Cloudflare_Cirino\Purge_Service;
use Cloudflare_Cirino\Activity_Log;

defined( 'ABSPATH' ) || exit;

/**
 * Handles privileged admin actions.
 */
class Actions {

	/**
	 * Purge service.
	 *
	 * @var Purge_Service
	 */
	private $purge_service;

	/**
	 * API client.
	 *
	 * @var API_Client
	 */
	private $api_client;

	/**
	 * Settings page.
	 *
	 * @var Settings_Page
	 */
	private $settings_page;

	/**
	 * Options repository.
	 *
	 * @var Options
	 */
	private $options;

	/**
	 * Activity logger.
	 *
	 * @var Activity_Log
	 */
	private $activity_log;

	/**
	 * Constructor.
	 *
	 * @param Purge_Service $purge_service Purge service.
	 * @param API_Client    $api_client API client.
	 * @param Settings_Page $settings_page Settings page.
	 * @param Options       $options Options repository.
	 * @param Activity_Log  $activity_log Activity logger.
	 */
	public function __construct( Purge_Service $purge_service, API_Client $api_client, Settings_Page $settings_page, Options $options, Activity_Log $activity_log ) {
		$this->purge_service = $purge_service;
		$this->api_client    = $api_client;
		$this->settings_page = $settings_page;
		$this->options       = $options;
		$this->activity_log  = $activity_log;
	}

	/**
	 * Register action handlers.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_cloudflare_cirino_test_connection', array( $this, 'handle_test_connection' ) );
		add_action( 'admin_post_cloudflare_cirino_purge_now', array( $this, 'handle_purge_now' ) );
	}

	/**
	 * Process test connection action.
	 *
	 * @return void
	 */
	public function handle_test_connection(): void {
		$this->guard_request( 'cloudflare_cirino_test_connection' );

		$result  = $this->api_client->test_connection();
		$success = ! empty( $result['success'] );
		$message = isset( $result['message'] ) ? (string) $result['message'] : '';

		$this->options->update_runtime(
			array(
				'last_test_status'  => $success ? 'success' : 'error',
				'last_test_message' => $message,
				'last_test_at'      => current_time( 'timestamp' ),
			)
		);

		$this->activity_log->add( $success ? 'success' : 'error', $message, array( 'event' => 'test_connection' ) );

		$this->settings_page->set_flash_notice( $success ? 'success' : 'error', $message );
		$this->redirect_back();
	}

	/**
	 * Process manual purge action.
	 *
	 * @return void
	 */
	public function handle_purge_now(): void {
		$this->guard_request( 'cloudflare_cirino_purge_now' );

		$result  = $this->purge_service->purge_now();
		$success = ! empty( $result['success'] );
		$message = isset( $result['message'] ) ? (string) $result['message'] : '';

		$this->settings_page->set_flash_notice( $success ? 'success' : 'error', $message );
		$this->redirect_back();
	}

	/**
	 * Guard privileged request.
	 *
	 * @param string $nonce_action Nonce action.
	 * @return void
	 */
	private function guard_request( string $nonce_action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'cloudflare-cirino' ) );
		}

		check_admin_referer( $nonce_action );
	}

	/**
	 * Redirect back to plugin settings page.
	 *
	 * @return void
	 */
	private function redirect_back(): void {
		wp_safe_redirect( $this->settings_page->get_page_url() );
		exit;
	}
}
