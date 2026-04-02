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

		$legacy_email = isset( $input['legacy_email'] ) ? sanitize_email( (string) $input['legacy_email'] ) : '';
		$legacy_key   = isset( $input['legacy_api_key'] ) ? trim( sanitize_text_field( (string) $input['legacy_api_key'] ) ) : '';

		$clean['legacy_email']   = '' !== $legacy_email ? $legacy_email : (string) $current['legacy_email'];
		$clean['legacy_api_key'] = '' !== $legacy_key ? $legacy_key : (string) $current['legacy_api_key'];

		$clean['purge_mode'] = ( isset( $input['purge_mode'] ) && in_array( $input['purge_mode'], array( 'everything', 'targeted' ), true ) )
			? $input['purge_mode']
			: 'targeted';

		if ( '' === $clean['zone_id'] ) {
			add_settings_error( 'cloudflare_cirino', 'cloudflare_cirino_zone_id', __( 'Zone ID is required.', 'cloudflare-cirino' ) );
		}

		if ( 'token' === $clean['auth_mode'] && '' === $clean['api_token'] ) {
			add_settings_error( 'cloudflare_cirino', 'cloudflare_cirino_token', __( 'API token is required when token mode is selected.', 'cloudflare-cirino' ) );
		}

		if ( 'legacy' === $clean['auth_mode'] && ( '' === $clean['legacy_email'] || '' === $clean['legacy_api_key'] ) ) {
			add_settings_error( 'cloudflare_cirino', 'cloudflare_cirino_legacy', __( 'Legacy mode requires both email and global API key.', 'cloudflare-cirino' ) );
		}

		if ( '' !== $legacy_email && ! is_email( $legacy_email ) ) {
			add_settings_error( 'cloudflare_cirino', 'cloudflare_cirino_email', __( 'Legacy email format is invalid.', 'cloudflare-cirino' ) );
		}

		return $clean;
	}
}
