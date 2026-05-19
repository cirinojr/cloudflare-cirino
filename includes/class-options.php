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
			'cache_rules_enabled'  => 'no',
			'cache_rules_preset'   => 'recommended',
			'cache_rules_hostname' => $this->get_default_cache_rules_hostname(),
			'cache_rules_edge_ttl' => 14400,
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
			'last_cache_rules_status'     => 'never',
			'last_cache_rules_message'    => '',
			'last_cache_rules_at'         => 0,
			'last_cache_rules_ruleset_id' => '',
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

	/**
	 * Update settings with partial data.
	 *
	 * @param array<string,mixed> $payload Settings values.
	 * @return void
	 */
	public function update_settings( array $payload ): void {
		$current = $this->get_settings();
		$next    = array_merge( $current, $payload );
		update_option( self::SETTINGS_OPTION, $next, false );
	}

	/**
	 * Get the default hostname for cache rule matching.
	 *
	 * @return string
	 */
	public function get_default_cache_rules_hostname(): string {
		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		return is_string( $host ) ? strtolower( $host ) : '';
	}
}
