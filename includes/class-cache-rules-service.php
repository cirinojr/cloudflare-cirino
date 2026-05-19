<?php
/**
 * Cache rules orchestration service.
 *
 * @package CloudflareCirino
 */

namespace Cloudflare_Cirino;

defined( 'ABSPATH' ) || exit;

/**
 * Applies plugin-managed Cloudflare Cache Rules.
 */
class Cache_Rules_Service {

	/**
	 * Plugin-managed bypass rule description.
	 */
	private const MANAGED_BYPASS_DESCRIPTION = 'Cloudflare Cirino - WordPress Bypass';

	/**
	 * Plugin-managed cache rule description.
	 */
	private const MANAGED_CACHE_DESCRIPTION = 'Cloudflare Cirino - WordPress Full Page Cache';

	/**
	 * API client.
	 *
	 * @var API_Client
	 */
	private $api_client;

	/**
	 * Constructor.
	 *
	 * @param API_Client $api_client API client.
	 */
	public function __construct( API_Client $api_client ) {
		$this->api_client = $api_client;
	}

	/**
	 * Apply or update the plugin-managed cache rules.
	 *
	 * @param string $hostname Hostname to target.
	 * @param string $preset Cache preset.
	 * @return array<string,mixed>
	 */
	public function apply_recommended_cache_rules( string $hostname, string $preset ): array {
		$hostname = $this->normalize_hostname( $hostname );
		$preset   = in_array( $preset, array( 'safe', 'recommended', 'aggressive' ), true ) ? $preset : 'recommended';

		if ( '' === $hostname ) {
			return $this->result( false, __( 'A valid hostname is required before applying cache rules.', 'cloudflare-cirino' ) );
		}

		$ruleset = $this->api_client->get_cache_ruleset();
		if ( $this->is_failed_api_response( $ruleset ) ) {
			$ruleset = $this->api_client->create_zone_cache_ruleset();
			if ( $this->is_failed_api_response( $ruleset ) ) {
				return $this->result( false, $this->extract_rulesets_error_message( $ruleset ) );
			}
		}

		$ruleset_result = isset( $ruleset['result'] ) && is_array( $ruleset['result'] ) ? $ruleset['result'] : array();
		$ruleset_id     = isset( $ruleset_result['id'] ) ? (string) $ruleset_result['id'] : '';

		if ( '' === $ruleset_id ) {
			return $this->result( false, __( 'Cloudflare returned an invalid cache ruleset response.', 'cloudflare-cirino' ) );
		}

		$rules       = isset( $ruleset_result['rules'] ) && is_array( $ruleset_result['rules'] ) ? $ruleset_result['rules'] : array();
		$bypass_rule = $this->find_managed_rule( $rules, self::MANAGED_BYPASS_DESCRIPTION );
		$cache_rule  = $this->find_managed_rule( $rules, self::MANAGED_CACHE_DESCRIPTION );

		$bypass_response = isset( $bypass_rule['id'] )
			? $this->api_client->update_ruleset_rule( $ruleset_id, (string) $bypass_rule['id'], $this->build_bypass_rule_payload( $hostname ) )
			: $this->api_client->add_ruleset_rule( $ruleset_id, $this->build_bypass_rule_payload( $hostname ) );

		if ( $this->is_failed_api_response( $bypass_response ) ) {
			return $this->result( false, $this->extract_rulesets_error_message( $bypass_response ), array( 'ruleset_id' => $ruleset_id ) );
		}

		$updated_bypass_rule = $this->find_managed_rule_from_response( $bypass_response, self::MANAGED_BYPASS_DESCRIPTION );

		$cache_response = isset( $cache_rule['id'] )
			? $this->api_client->update_ruleset_rule( $ruleset_id, (string) $cache_rule['id'], $this->build_cache_rule_payload( $hostname, $preset ) )
			: $this->api_client->add_ruleset_rule( $ruleset_id, $this->build_cache_rule_payload( $hostname, $preset ) );

		if ( $this->is_failed_api_response( $cache_response ) ) {
			return $this->result( false, $this->extract_rulesets_error_message( $cache_response ), array( 'ruleset_id' => $ruleset_id ) );
		}

		$updated_cache_rule = $this->find_managed_rule_from_response( $cache_response, self::MANAGED_CACHE_DESCRIPTION );

		if (
			isset( $updated_bypass_rule['id'], $updated_cache_rule['id'] )
			&& ! $this->is_rule_before(
				(string) $updated_bypass_rule['id'],
				(string) $updated_cache_rule['id'],
				$this->extract_rules_from_response( $cache_response )
			)
		) {
			$reorder_response = $this->api_client->update_ruleset_rule(
				$ruleset_id,
				(string) $updated_bypass_rule['id'],
				array(
					'position' => array(
						'before' => (string) $updated_cache_rule['id'],
					),
				)
			);

			if ( $this->is_failed_api_response( $reorder_response ) ) {
				return $this->result( false, $this->extract_rulesets_error_message( $reorder_response ), array( 'ruleset_id' => $ruleset_id ) );
			}
		}

		/* translators: 1: hostname, 2: preset label. */
		$message = sprintf( __( 'Cloudflare cache rules applied for %1$s using the %2$s preset.', 'cloudflare-cirino' ), $hostname, $this->preset_label( $preset ) );

		return $this->result(
			true,
			$message,
			array(
				'ruleset_id'     => $ruleset_id,
				'hostname'       => $hostname,
				'preset'         => $preset,
				'bypass_rule_id' => isset( $updated_bypass_rule['id'] ) ? (string) $updated_bypass_rule['id'] : '',
				'cache_rule_id'  => isset( $updated_cache_rule['id'] ) ? (string) $updated_cache_rule['id'] : '',
			)
		);
	}

	/**
	 * Build a standard result payload.
	 *
	 * @param bool                $success Request success.
	 * @param string              $message Message.
	 * @param array<string,mixed> $data Extra data.
	 * @return array<string,mixed>
	 */
	private function result( bool $success, string $message, array $data = array() ): array {
		return array(
			'success' => $success,
			'message' => $message,
			'data'    => $data,
		);
	}

	/**
	 * Check whether an API response represents a failure.
	 *
	 * @param array<string,mixed> $response Response payload.
	 * @return bool
	 */
	private function is_failed_api_response( array $response ): bool {
		return isset( $response['success'] ) && false === $response['success'];
	}

	/**
	 * Extract a user-facing error message for ruleset actions.
	 *
	 * @param array<string,mixed> $response Response payload.
	 * @return string
	 */
	private function extract_rulesets_error_message( array $response ): string {
		$message     = __( 'Cloudflare cache rules request failed.', 'cloudflare-cirino' );
		$status_code = 0;

		if ( ! empty( $response['errors'][0]['message'] ) ) {
			$message = sanitize_text_field( (string) $response['errors'][0]['message'] );
		}

		if ( isset( $response['errors'][0]['data']['status_code'] ) ) {
			$status_code = (int) $response['errors'][0]['data']['status_code'];
		} elseif ( isset( $response['errors'][0]['data']['body']['errors'][0]['message'] ) ) {
			$message = sanitize_text_field( (string) $response['errors'][0]['data']['body']['errors'][0]['message'] );
		}

		$permission_message = __( 'Update the API token so it includes Cache Rules / Rulesets edit permissions for this zone.', 'cloudflare-cirino' );

		if ( 403 === $status_code ) {
			return sprintf(
				/* translators: 1: Cloudflare API error message, 2: Permission guidance. */
				__( 'Cloudflare error: %1$s %2$s', 'cloudflare-cirino' ),
				$message,
				$permission_message
			);
		}

		$normalized = strtolower( $message );
		if ( false !== strpos( $normalized, 'permission' ) || false !== strpos( $normalized, 'scope' ) || false !== strpos( $normalized, 'forbidden' ) || false !== strpos( $normalized, 'token' ) ) {
			return sprintf(
				/* translators: 1: Cloudflare API error message, 2: Permission guidance. */
				__( 'Cloudflare error: %1$s %2$s', 'cloudflare-cirino' ),
				$message,
				$permission_message
			);
		}

		return $message;
	}

	/**
	 * Normalize a hostname for Cloudflare rule matching.
	 *
	 * @param string $hostname Raw hostname.
	 * @return string
	 */
	private function normalize_hostname( string $hostname ): string {
		$hostname = trim( strtolower( sanitize_text_field( $hostname ) ) );

		if ( false !== strpos( $hostname, '://' ) ) {
			$parsed   = wp_parse_url( $hostname, PHP_URL_HOST );
			$hostname = is_string( $parsed ) ? $parsed : '';
		}

		$hostname = (string) preg_replace( '/[\/?#].*$/', '', $hostname );
		$hostname = (string) preg_replace( '/:\d+$/', '', $hostname );
		$hostname = (string) preg_replace( '/[^a-z0-9.-]/', '', $hostname );

		return trim( $hostname, '.' );
	}

	/**
	 * Find a plugin-managed rule by description.
	 *
	 * @param array<int,mixed> $rules Rules list.
	 * @param string           $description Rule description.
	 * @return array<string,mixed>
	 */
	private function find_managed_rule( array $rules, string $description ): array {
		foreach ( $rules as $rule ) {
			if ( is_array( $rule ) && $description === ( $rule['description'] ?? '' ) ) {
				return $rule;
			}
		}

		return array();
	}

	/**
	 * Find a plugin-managed rule in a ruleset response.
	 *
	 * @param array<string,mixed> $response Ruleset response.
	 * @param string              $description Rule description.
	 * @return array<string,mixed>
	 */
	private function find_managed_rule_from_response( array $response, string $description ): array {
		return $this->find_managed_rule( $this->extract_rules_from_response( $response ), $description );
	}

	/**
	 * Extract rules from a ruleset response.
	 *
	 * @param array<string,mixed> $response Ruleset response.
	 * @return array<int,mixed>
	 */
	private function extract_rules_from_response( array $response ): array {
		if ( isset( $response['result']['rules'] ) && is_array( $response['result']['rules'] ) ) {
			return $response['result']['rules'];
		}

		return array();
	}

	/**
	 * Determine whether one rule appears before another in the ruleset.
	 *
	 * @param string           $first_rule_id First rule ID.
	 * @param string           $second_rule_id Second rule ID.
	 * @param array<int,mixed> $rules Rules list.
	 * @return bool
	 */
	private function is_rule_before( string $first_rule_id, string $second_rule_id, array $rules ): bool {
		$first_index  = null;
		$second_index = null;

		foreach ( $rules as $index => $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['id'] ) ) {
				continue;
			}

			if ( $first_rule_id === (string) $rule['id'] ) {
				$first_index = $index;
			}

			if ( $second_rule_id === (string) $rule['id'] ) {
				$second_index = $index;
			}
		}

		if ( null === $first_index || null === $second_index ) {
			return false;
		}

		return $first_index < $second_index;
	}

	/**
	 * Build the bypass rule payload.
	 *
	 * @param string $hostname Hostname to match.
	 * @return array<string,mixed>
	 */
	private function build_bypass_rule_payload( string $hostname ): array {
		return array(
			'action'            => 'set_cache_settings',
			'description'       => self::MANAGED_BYPASS_DESCRIPTION,
			'enabled'           => true,
			'expression'        => $this->build_bypass_expression( $hostname ),
			'action_parameters' => array(
				'cache' => false,
			),
		);
	}

	/**
	 * Build the full-page cache rule payload.
	 *
	 * @param string $hostname Hostname to match.
	 * @param string $preset Cache preset.
	 * @return array<string,mixed>
	 */
	private function build_cache_rule_payload( string $hostname, string $preset ): array {
		$ttl = $this->preset_ttl( $preset );

		return array(
			'action'            => 'set_cache_settings',
			'description'       => self::MANAGED_CACHE_DESCRIPTION,
			'enabled'           => true,
			'expression'        => $this->build_cache_expression( $hostname ),
			'action_parameters' => array(
				'cache'       => true,
				'edge_ttl'    => array(
					'mode'            => 'override_origin',
					'default'         => $ttl,
					'status_code_ttl' => array(
						array(
							'status_code_range' => array(
								'from' => 200,
								'to'   => 299,
							),
							'value'             => $ttl,
						),
						array(
							'status_code_range' => array(
								'from' => 300,
								'to'   => 399,
							),
							'value'             => 600,
						),
						array(
							'status_code_range' => array(
								'from' => 400,
								'to'   => 499,
							),
							'value'             => 0,
						),
						array(
							'status_code_range' => array(
								'from' => 500,
								'to'   => 599,
							),
							'value'             => 0,
						),
					),
				),
				'browser_ttl' => array(
					'mode' => 'respect_origin',
				),
				'cache_key'   => array(
					'ignore_query_strings_order' => true,
					'cache_deception_armor'      => true,
				),
			),
		);
	}

	/**
	 * Build the WordPress bypass expression.
	 *
	 * @param string $hostname Hostname to match.
	 * @return string
	 */
	private function build_bypass_expression( string $hostname ): string {
		$hostname = $this->escape_expression_string( $hostname );

		return '(http.host eq "' . $hostname . '" and ('
			. 'starts_with(http.request.uri.path, "/wp-admin") '
			. 'or http.request.uri.path eq "/wp-login.php" '
			. 'or http.request.uri.path eq "/wp-comments-post.php" '
			. 'or http.request.uri.path eq "/wp-cron.php" '
			. 'or http.request.uri.path eq "/xmlrpc.php" '
			. 'or starts_with(http.request.uri.path, "/wp-json") '
			. 'or starts_with(http.request.uri.path, "/cart") '
			. 'or starts_with(http.request.uri.path, "/checkout") '
			. 'or starts_with(http.request.uri.path, "/my-account") '
			. 'or http.request.uri.query contains "preview=true" '
			. 'or http.request.uri.query contains "elementor-preview" '
			. 'or http.cookie contains "wordpress_logged_in_" '
			. 'or http.cookie contains "wp-postpass_" '
			. 'or http.cookie contains "comment_author_" '
			. 'or http.cookie contains "woocommerce_items_in_cart" '
			. 'or http.cookie contains "wp_woocommerce_session_"'
			. '))';
	}

	/**
	 * Build the WordPress full-page cache expression.
	 *
	 * @param string $hostname Hostname to match.
	 * @return string
	 */
	private function build_cache_expression( string $hostname ): string {
		$hostname = $this->escape_expression_string( $hostname );
		$static_extensions = array(
			'.css',
			'.js',
			'.mjs',
			'.map',
			'.json',
			'.xml',
			'.txt',
			'.ico',
			'.jpg',
			'.jpeg',
			'.png',
			'.gif',
			'.webp',
			'.avif',
			'.svg',
			'.woff',
			'.woff2',
			'.ttf',
			'.eot',
			'.otf',
			'.mp4',
			'.webm',
			'.pdf',
			'.zip',
		);
		$static_checks = array();

		foreach ( $static_extensions as $extension ) {
			$static_checks[] = 'ends_with(lower(http.request.uri.path), "' . $extension . '")';
		}

		return '(http.host eq "' . $hostname . '" '
			. 'and http.request.method in {"GET" "HEAD"} '
			. 'and not starts_with(http.request.uri.path, "/wp-admin") '
			. 'and http.request.uri.path ne "/wp-login.php" '
			. 'and http.request.uri.path ne "/wp-comments-post.php" '
			. 'and http.request.uri.path ne "/wp-cron.php" '
			. 'and http.request.uri.path ne "/xmlrpc.php" '
			. 'and not starts_with(http.request.uri.path, "/wp-json") '
			. 'and not starts_with(http.request.uri.path, "/cart") '
			. 'and not starts_with(http.request.uri.path, "/checkout") '
			. 'and not starts_with(http.request.uri.path, "/my-account") '
			. 'and not http.request.uri.query contains "preview=true" '
			. 'and not http.request.uri.query contains "elementor-preview" '
			. 'and not http.cookie contains "wordpress_logged_in_" '
			. 'and not http.cookie contains "wp-postpass_" '
			. 'and not http.cookie contains "comment_author_" '
			. 'and not http.cookie contains "woocommerce_items_in_cart" '
			. 'and not http.cookie contains "wp_woocommerce_session_" '
			. 'and not (' . implode( ' or ', $static_checks ) . ')'
			. ')';
	}

	/**
	 * Escape a string used inside a Cloudflare expression.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function escape_expression_string( string $value ): string {
		return str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $value );
	}

	/**
	 * Convert a preset to its edge TTL.
	 *
	 * @param string $preset Cache preset.
	 * @return int
	 */
	private function preset_ttl( string $preset ): int {
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

	/**
	 * Get a translated preset label.
	 *
	 * @param string $preset Cache preset.
	 * @return string
	 */
	private function preset_label( string $preset ): string {
		switch ( $preset ) {
			case 'safe':
				return __( 'Safe', 'cloudflare-cirino' );

			case 'aggressive':
				return __( 'Aggressive', 'cloudflare-cirino' );

			case 'recommended':
			default:
				return __( 'Recommended', 'cloudflare-cirino' );
		}
	}
}