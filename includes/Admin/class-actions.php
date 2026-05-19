<?php
/**
 * Admin actions handlers.
 *
 * @package CloudflareCirino
 */

namespace Cloudflare_Cirino\Admin;

use Cloudflare_Cirino\API_Client;
use Cloudflare_Cirino\Cache_Rules_Service;
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
	 * Cache rules service.
	 *
	 * @var Cache_Rules_Service
	 */
	private $cache_rules_service;

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
	 * @param Cache_Rules_Service $cache_rules_service Cache rules service.
	 * @param Settings_Page       $settings_page Settings page.
	 * @param Options             $options Options repository.
	 * @param Activity_Log        $activity_log Activity logger.
	 */
	public function __construct( Purge_Service $purge_service, API_Client $api_client, Cache_Rules_Service $cache_rules_service, Settings_Page $settings_page, Options $options, Activity_Log $activity_log ) {
		$this->purge_service       = $purge_service;
		$this->api_client          = $api_client;
		$this->cache_rules_service = $cache_rules_service;
		$this->settings_page       = $settings_page;
		$this->options             = $options;
		$this->activity_log        = $activity_log;
	}

	/**
	 * Register action handlers.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_cloudflare_cirino_test_connection', array( $this, 'handle_test_connection' ) );
		add_action( 'admin_post_cloudflare_cirino_purge_now', array( $this, 'handle_purge_now' ) );
		add_action( 'admin_post_cloudflare_cirino_apply_cache_rules', array( $this, 'handle_apply_cache_rules' ) );
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
	 * Process cache rules action.
	 *
	 * @return void
	 */
	public function handle_apply_cache_rules(): void {
		$this->guard_request( 'cloudflare_cirino_apply_cache_rules' );

		$settings = $this->options->get_settings();
		$hostname = isset( $settings['cache_rules_hostname'] ) ? (string) $settings['cache_rules_hostname'] : '';
		$preset   = isset( $settings['cache_rules_preset'] ) ? (string) $settings['cache_rules_preset'] : 'recommended';

		$result  = $this->cache_rules_service->apply_recommended_cache_rules( $hostname, $preset );
		$success = ! empty( $result['success'] );
		$message = isset( $result['message'] ) ? (string) $result['message'] : '';
		$data    = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array();

		$this->options->update_runtime(
			array(
				'last_cache_rules_status'     => $success ? 'success' : 'error',
				'last_cache_rules_message'    => $message,
				'last_cache_rules_at'         => current_time( 'timestamp' ),
				'last_cache_rules_ruleset_id' => isset( $data['ruleset_id'] ) ? (string) $data['ruleset_id'] : '',
			)
		);

		if ( $success ) {
			$this->options->update_settings( array( 'cache_rules_enabled' => 'yes' ) );
		}

		$this->activity_log->add(
			$success ? 'success' : 'error',
			$message,
			array(
				'event'      => 'apply_cache_rules',
				'hostname'   => isset( $data['hostname'] ) ? (string) $data['hostname'] : $hostname,
				'preset'     => isset( $data['preset'] ) ? (string) $data['preset'] : $preset,
				'ruleset_id' => isset( $data['ruleset_id'] ) ? (string) $data['ruleset_id'] : '',
			)
		);

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
