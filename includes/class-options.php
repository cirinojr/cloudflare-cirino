<?php
/**
 * Options repository.
 *
 * @package CloudflareCirino
 */

namespace Cloudflare_Cirino;

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates plugin options.
 */
class Options {

	/**
	 * Settings option key.
	 */
	public const SETTINGS_OPTION = 'cloudflare_cirino_settings';

	/**
	 * Runtime option key.
	 */
	public const RUNTIME_OPTION = 'cloudflare_cirino_runtime';

	/**
	 * Get default settings.
	 *
	 * @return array<string,mixed>
	 */
	public function get_default_settings(): array {
		return array(
			'auth_mode'       => 'token',
			'zone_id'         => '',
			'api_token'       => '',
			'legacy_email'    => '',
			'legacy_api_key'  => '',
			'purge_mode'      => 'targeted',
		);
	}

	/**
	 * Get plugin settings.
	 *
	 * @return array<string,mixed>
	 */
	public function get_settings(): array {
		$stored = get_option( self::SETTINGS_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, $this->get_default_settings() );
	}

	/**
	 * Get default runtime metadata.
	 *
	 * @return array<string,mixed>
	 */
	public function get_default_runtime(): array {
		return array(
			'last_purge_status'  => 'never',
			'last_purge_message' => '',
			'last_purge_at'      => 0,
			'last_test_status'   => 'unknown',
			'last_test_message'  => '',
			'last_test_at'       => 0,
		);
	}

	/**
	 * Get runtime metadata.
	 *
	 * @return array<string,mixed>
	 */
	public function get_runtime(): array {
		$stored = get_option( self::RUNTIME_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, $this->get_default_runtime() );
	}

	/**
	 * Update runtime metadata with partial data.
	 *
	 * @param array<string,mixed> $payload Runtime values.
	 * @return void
	 */
	public function update_runtime( array $payload ): void {
		$current = $this->get_runtime();
		$next    = array_merge( $current, $payload );
		update_option( self::RUNTIME_OPTION, $next, false );
	}
}
