<?php
/**
 * Cloudflare API client.
 *
 * @package CloudflareCirino
 */

namespace Cloudflare_Cirino;

defined( 'ABSPATH' ) || exit;

/**
 * Handles requests to Cloudflare API.
 */
class API_Client {

	/**
	 * Cloudflare API base.
	 */
	private const API_BASE = 'https://api.cloudflare.com/client/v4';

	/**
	 * Cache rules phase name.
	 */
	private const CACHE_RULES_PHASE = 'http_request_cache_settings';

	/**
	 * Zone cache ruleset name.
	 */
	private const CACHE_RULESET_NAME = 'Cloudflare Cirino Cache Rules';

	/**
	 * Zone cache ruleset description.
	 */
	private const CACHE_RULESET_DESCRIPTION = 'Managed by Cloudflare Cirino';

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
	 * Test connection against zone endpoint.
	 *
	 * @return array<string,mixed>
	 */
	public function test_connection(): array {
		$zone_id = $this->get_zone_id();

		if ( '' === $zone_id ) {
			return $this->result( false, __( 'Zone ID is required.', 'cloudflare-cirino' ) );
		}

		$response = $this->request( 'GET', '/zones/' . rawurlencode( $zone_id ) );
		if ( is_wp_error( $response ) ) {
			return $this->result( false, $response->get_error_message() );
		}

		return $this->result( true, __( 'Cloudflare connection succeeded.', 'cloudflare-cirino' ) );
	}

	/**
	 * Purge all zone cache.
	 *
	 * @return array<string,mixed>
	 */
	public function purge_everything(): array {
		$response = $this->purge_payload( array( 'purge_everything' => true ) );

		if ( is_wp_error( $response ) ) {
			return $this->result( false, $response->get_error_message() );
		}

		return $this->result( true, __( 'Cloudflare cache purged for the full zone.', 'cloudflare-cirino' ) );
	}

	/**
	 * Purge specific URLs.
	 *
	 * @param array<int,string> $urls URLs to purge.
	 * @return array<string,mixed>
	 */
	public function purge_urls( array $urls ): array {
		$urls = array_values( array_filter( array_map( 'esc_url_raw', $urls ) ) );
		if ( empty( $urls ) ) {
			return $this->result( false, __( 'No URLs were provided for targeted purge.', 'cloudflare-cirino' ) );
		}

		$response = $this->purge_payload( array( 'files' => $urls ) );

		if ( is_wp_error( $response ) ) {
			return $this->result( false, $response->get_error_message() );
		}

		/* translators: %d: Number of URLs. */
		$message = sprintf( __( 'Cloudflare cache purged for %d URL(s).', 'cloudflare-cirino' ), count( $urls ) );

		return $this->result( true, $message, array( 'urls' => $urls ) );
	}

	/**
	 * Purge cache using a custom payload.
	 *
	 * @param array<string,mixed> $payload Purge payload.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function purge_payload( array $payload ) {
		return $this->request(
			'POST',
			'/zones/' . rawurlencode( $this->get_zone_id() ) . '/purge_cache',
			$payload
		);
	}

	/**
	 * List all rulesets for the configured zone.
	 *
	 * @return array<string,mixed>
	 */
	public function list_zone_rulesets(): array {
		$response = $this->request( 'GET', '/zones/' . rawurlencode( $this->get_zone_id() ) . '/rulesets' );

		return $this->normalize_api_response( $response );
	}

	/**
	 * Create the cache rules phase entry point ruleset.
	 *
	 * @return array<string,mixed>
	 */
	public function create_zone_cache_ruleset(): array {
		$response = $this->request(
			'POST',
			'/zones/' . rawurlencode( $this->get_zone_id() ) . '/rulesets',
			array(
				'kind'        => 'zone',
				'phase'       => self::CACHE_RULES_PHASE,
				'name'        => self::CACHE_RULESET_NAME,
				'description' => self::CACHE_RULESET_DESCRIPTION,
			)
		);

		return $this->normalize_api_response( $response );
	}

	/**
	 * Add a rule to an existing ruleset.
	 *
	 * @param string              $ruleset_id Ruleset ID.
	 * @param array<string,mixed> $rule Rule payload.
	 * @return array<string,mixed>
	 */
	public function add_ruleset_rule( string $ruleset_id, array $rule ): array {
		$response = $this->request(
			'POST',
			'/zones/' . rawurlencode( $this->get_zone_id() ) . '/rulesets/' . rawurlencode( $ruleset_id ) . '/rules',
			$rule
		);

		return $this->normalize_api_response( $response );
	}

	/**
	 * Update a single rule in an existing ruleset.
	 *
	 * @param string              $ruleset_id Ruleset ID.
	 * @param string              $rule_id Rule ID.
	 * @param array<string,mixed> $rule Rule payload.
	 * @return array<string,mixed>
	 */
	public function update_ruleset_rule( string $ruleset_id, string $rule_id, array $rule ): array {
		$response = $this->request(
			'PATCH',
			'/zones/' . rawurlencode( $this->get_zone_id() ) . '/rulesets/' . rawurlencode( $ruleset_id ) . '/rules/' . rawurlencode( $rule_id ),
			$rule
		);

		return $this->normalize_api_response( $response );
	}

	/**
	 * Get the current zone cache ruleset.
	 *
	 * @return array<string,mixed>
	 */
	public function get_cache_ruleset(): array {
		$rulesets = $this->list_zone_rulesets();
		if ( $this->is_failed_api_response( $rulesets ) ) {
			return $rulesets;
		}

		$items      = isset( $rulesets['result'] ) && is_array( $rulesets['result'] ) ? $rulesets['result'] : array();
		$ruleset_id = '';

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			if ( self::CACHE_RULES_PHASE === ( $item['phase'] ?? '' ) && 'zone' === ( $item['kind'] ?? '' ) ) {
				$ruleset_id = isset( $item['id'] ) ? (string) $item['id'] : '';
				break;
			}
		}

		if ( '' === $ruleset_id ) {
			return $this->api_error_response( __( 'The Cloudflare cache ruleset for this zone was not found.', 'cloudflare-cirino' ) );
		}

		$response = $this->request( 'GET', '/zones/' . rawurlencode( $this->get_zone_id() ) . '/rulesets/' . rawurlencode( $ruleset_id ) );

		return $this->normalize_api_response( $response );
	}

	/**
	 * Execute a Cloudflare API request.
	 *
	 * @param string                   $method HTTP method.
	 * @param string                   $path API path.
	 * @param array<string,mixed>|null $body Optional body.
	 * @return array<string,mixed>|\WP_Error
	 */
	private function request( string $method, string $path, ?array $body = null ) {
		$settings = $this->options->get_settings();
		$zone_id  = $this->get_zone_id();

		if ( '' === $zone_id ) {
			return new \WP_Error( 'cloudflare_cirino_missing_zone', __( 'Zone ID is required before calling Cloudflare.', 'cloudflare-cirino' ) );
		}

		$headers = $this->build_auth_headers( $settings );
		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		$args = array(
			'method'      => strtoupper( $method ),
			'timeout'     => 15,
			'headers'     => $headers,
			'data_format' => 'body',
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( self::API_BASE . $path, $args );
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'cloudflare_cirino_http_error', $response->get_error_message() );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body    = (string) wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $raw_body, true );
		$decoded     = is_array( $decoded ) ? $decoded : array();

		if ( $status_code < 200 || $status_code >= 300 || empty( $decoded['success'] ) ) {
			$message = __( 'Cloudflare API request failed.', 'cloudflare-cirino' );
			if ( ! empty( $decoded['errors'][0]['message'] ) ) {
				$message = sanitize_text_field( (string) $decoded['errors'][0]['message'] );
			}

			return new \WP_Error(
				'cloudflare_cirino_api_error',
				$message,
				array(
					'status_code' => $status_code,
					'body'        => $decoded,
				)
			);
		}

		return $decoded;
	}

	/**
	 * Build authentication headers.
	 *
	 * @param array<string,mixed> $settings Settings payload.
	 * @return array<string,string>|\WP_Error
	 */
	private function build_auth_headers( array $settings ) {
		$auth_mode = (string) $settings['auth_mode'];
		$token     = trim( (string) $settings['api_token'] );
		$email     = sanitize_email( (string) $settings['legacy_email'] );
		$api_key   = trim( (string) $settings['legacy_api_key'] );

		$headers = array(
			'Content-Type' => 'application/json',
		);

		if ( 'legacy' === $auth_mode ) {
			if ( '' === $email || '' === $api_key ) {
				return new \WP_Error( 'cloudflare_cirino_missing_legacy_auth', __( 'Legacy auth requires both email and global API key.', 'cloudflare-cirino' ) );
			}

			$headers['X-Auth-Email'] = $email;
			$headers['X-Auth-Key']   = $api_key;

			return $headers;
		}

		if ( '' === $token ) {
			return new \WP_Error( 'cloudflare_cirino_missing_token', __( 'API token is required when token auth is selected.', 'cloudflare-cirino' ) );
		}

		$headers['Authorization'] = 'Bearer ' . $token;

		return $headers;
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
	 * Get the configured zone ID.
	 *
	 * @return string
	 */
	private function get_zone_id(): string {
		$settings = $this->options->get_settings();

		return (string) $settings['zone_id'];
	}

	/**
	 * Normalize a low-level response into an array payload.
	 *
	 * @param array<string,mixed>|\WP_Error $response Low-level response.
	 * @return array<string,mixed>
	 */
	private function normalize_api_response( $response ): array {
		if ( is_wp_error( $response ) ) {
			return $this->api_error_response( $response->get_error_message(), $response->get_error_data() );
		}

		return $response;
	}

	/**
	 * Build a consistent API error payload.
	 *
	 * @param string $message Error message.
	 * @param mixed  $data Optional error data.
	 * @return array<string,mixed>
	 */
	private function api_error_response( string $message, $data = null ): array {
		return array(
			'success'  => false,
			'result'   => array(),
			'errors'   => array(
				array(
					'message' => $message,
					'data'    => $data,
				),
			),
			'messages' => array(),
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
}