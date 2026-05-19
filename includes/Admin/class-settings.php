<?php
/**
 * Settings registration.
 *
 * @package CloudflareCirino
 */

namespace Cloudflare_Cirino\Admin;

use Cloudflare_Cirino\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and sanitizes plugin settings.
 */
class Settings {

	/**
	 * Options repository.
	 *
	 * @var Options
	 */
	private $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options Options repository.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Register setting hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'cloudflare_cirino_settings_group',
			Options::SETTINGS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => $this->options->get_default_settings(),
			)
		);
	}

	/**
	 * Sanitize settings payload.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ): array {
		$current = $this->options->get_settings();
		$input   = is_array( $input ) ? $input : array();

		$clean = $this->options->get_default_settings();

		$clean['auth_mode'] = ( isset( $input['auth_mode'] ) && in_array( $input['auth_mode'], array( 'token', 'legacy' ), true ) )
			? $input['auth_mode']
			: 'token';

		$zone_id = isset( $input['zone_id'] ) ? sanitize_text_field( (string) $input['zone_id'] ) : '';
		$zone_id = preg_replace( '/[^A-Za-z0-9]/', '', $zone_id );
		$clean['zone_id'] = (string) $zone_id;

		$token = isset( $input['api_token'] ) ? trim( sanitize_text_field( (string) $input['api_token'] ) ) : '';
		$clean['api_token'] = '' !== $token ? $token : (string) $current['api_token'];

		$legacy_email_raw = isset( $input['legacy_email'] ) ? (string) $input['legacy_email'] : '';
		$legacy_email     = sanitize_email( $legacy_email_raw );
		$legacy_key   = isset( $input['legacy_api_key'] ) ? trim( sanitize_text_field( (string) $input['legacy_api_key'] ) ) : '';

		$clean['legacy_email']   = '' !== $legacy_email ? $legacy_email : (string) $current['legacy_email'];
		$clean['legacy_api_key'] = '' !== $legacy_key ? $legacy_key : (string) $current['legacy_api_key'];

		$clean['purge_mode'] = ( isset( $input['purge_mode'] ) && in_array( $input['purge_mode'], array( 'everything', 'targeted' ), true ) )
			? $input['purge_mode']
			: 'targeted';

		$clean['cache_rules_enabled'] = ( isset( $input['cache_rules_enabled'] ) && 'yes' === $input['cache_rules_enabled'] )
			? 'yes'
			: (string) $current['cache_rules_enabled'];

		$clean['cache_rules_preset'] = ( isset( $input['cache_rules_preset'] ) && in_array( $input['cache_rules_preset'], array( 'safe', 'recommended', 'aggressive' ), true ) )
			? $input['cache_rules_preset']
			: 'recommended';

		$raw_hostname                  = isset( $input['cache_rules_hostname'] ) ? (string) $input['cache_rules_hostname'] : '';
		$sanitized_hostname            = $this->sanitize_hostname( $raw_hostname );
		$clean['cache_rules_hostname'] = '' !== $sanitized_hostname ? $sanitized_hostname : $this->options->get_default_cache_rules_hostname();

		if ( '' !== trim( $raw_hostname ) && '' === $sanitized_hostname ) {
			add_settings_error( 'cloudflare_cirino', 'cloudflare_cirino_cache_rules_hostname', __( 'Cache rules hostname must be a hostname only, without scheme or path.', 'cloudflare-cirino' ) );
		}

		$clean['cache_rules_edge_ttl'] = $this->preset_to_ttl( $clean['cache_rules_preset'] );

		if ( '' === $clean['zone_id'] ) {
			add_settings_error( 'cloudflare_cirino', 'cloudflare_cirino_zone_id', __( 'Zone ID is required.', 'cloudflare-cirino' ) );
		}

		if ( 'token' === $clean['auth_mode'] && '' === $clean['api_token'] ) {
			add_settings_error( 'cloudflare_cirino', 'cloudflare_cirino_token', __( 'API token is required when token mode is selected.', 'cloudflare-cirino' ) );
		}

		if ( 'legacy' === $clean['auth_mode'] && ( '' === $clean['legacy_email'] || '' === $clean['legacy_api_key'] ) ) {
			add_settings_error( 'cloudflare_cirino', 'cloudflare_cirino_legacy', __( 'Legacy mode requires both email and global API key.', 'cloudflare-cirino' ) );
		}

		if ( '' !== trim( $legacy_email_raw ) && '' === $legacy_email ) {
			add_settings_error( 'cloudflare_cirino', 'cloudflare_cirino_email', __( 'Legacy email format is invalid.', 'cloudflare-cirino' ) );
		}

		return $clean;
	}

	/**
	 * Sanitize a hostname value.
	 *
	 * @param string $value Raw hostname input.
	 * @return string
	 */
	private function sanitize_hostname( string $value ): string {
		$value = sanitize_text_field( wp_unslash( $value ) );
		$value = trim( strtolower( $value ) );

		if ( '' === $value ) {
			return '';
		}

		if ( false !== strpos( $value, '://' ) ) {
			$parsed = wp_parse_url( $value, PHP_URL_HOST );
			$value  = is_string( $parsed ) ? $parsed : '';
		} else {
			$value = preg_replace( '/[\/?#].*$/', '', $value );
		}

		$value = (string) preg_replace( '/:\d+$/', '', (string) $value );
		$value = (string) preg_replace( '/[^a-z0-9.-]/', '', (string) $value );
		$value = trim( $value, ". \t\n\r\0\x0B" );

		return $value;
	}

	/**
	 * Map preset values to supported edge TTLs.
	 *
	 * @param string $preset Cache rules preset.
	 * @return int
	 */
	private function preset_to_ttl( string $preset ): int {
		switch ( $preset ) {
			case 'safe':
				return 7200;

			case 'aggressive':
				return 86400;

			case 'recommended':
			default:
				return 14400;
		}
	}
}
