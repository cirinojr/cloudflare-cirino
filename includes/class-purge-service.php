<?php
/**
 * Purge orchestration.
 *
 * @package CloudflareCirino
 */

namespace Cloudflare_Cirino;

defined( 'ABSPATH' ) || exit;

/**
 * Purge service for content invalidation.
 */
class Purge_Service {

	/**
	 * Options repository.
	 *
	 * @var Options
	 */
	private $options;

	/**
	 * API client.
	 *
	 * @var API_Client
	 */
	private $api_client;

	/**
	 * Activity logger.
	 *
	 * @var Activity_Log
	 */
	private $activity_log;

	/**
	 * Constructor.
	 *
	 * @param Options      $options      Options repository.
	 * @param API_Client   $api_client   API client.
	 * @param Activity_Log $activity_log Activity log.
	 */
	public function __construct( Options $options, API_Client $api_client, Activity_Log $activity_log ) {
		$this->options      = $options;
		$this->api_client   = $api_client;
		$this->activity_log = $activity_log;
	}

	/**
	 * Trigger purge manually.
	 *
	 * @return array<string,mixed>
	 */
	public function purge_now(): array {
		$settings = $this->options->get_settings();
		$mode     = (string) $settings['purge_mode'];
		$mode     = (string) apply_filters( 'cloudflare_cirino_purge_mode', $mode, 0, 'manual' );

		if ( 'everything' === $mode ) {
			$result = $this->api_client->purge_everything();
			$this->store_result( $result, 'manual', array( 'mode' => 'everything' ) );
			return $result;
		}

		$urls = $this->build_manual_targeted_urls();
		$urls = apply_filters( 'cloudflare_cirino_manual_purge_urls', $urls );
		$urls = is_array( $urls ) ? $urls : array();
		$urls = array_values( array_unique( array_filter( array_map( 'esc_url_raw', $urls ) ) ) );

		if ( empty( $urls ) ) {
			$result = array(
				'success' => false,
				'message' => __( 'No URLs were available for targeted manual purge.', 'cloudflare-cirino' ),
				'data'    => array(),
			);
			$this->store_result( $result, 'manual', array( 'mode' => 'targeted' ) );
			return $result;
		}

		$result = $this->perform_targeted_purge( $urls, 0, 'manual', null, $mode );

		$this->store_result( $result, 'manual', array( 'mode' => 'targeted', 'urls' => $urls ) );
		return $result;
	}

	/**
	 * Purge in response to a post event.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $event Event key.
	 * @return array<string,mixed>
	 */
	public function purge_post( int $post_id, string $event ): array {
		$settings = $this->options->get_settings();
		$mode     = (string) $settings['purge_mode'];
		$mode     = (string) apply_filters( 'cloudflare_cirino_purge_mode', $mode, $post_id, $event );
		$post     = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			$result = array(
				'success' => false,
				'message' => __( 'Purge aborted: content was not found.', 'cloudflare-cirino' ),
				'data'    => array(),
			);
			$this->store_result( $result, $event );
			return $result;
		}

		if ( 'everything' === $mode ) {
			$result = $this->api_client->purge_everything();
			$this->store_result( $result, $event, array( 'post_id' => $post_id ) );
			return $result;
		}

		$urls = $this->build_targeted_urls( $post );
		$urls = apply_filters( 'cloudflare_cirino_purge_urls', $urls, $post_id, $event, $post );
		$urls = is_array( $urls ) ? $urls : array();
		$urls = array_values( array_unique( array_filter( array_map( 'esc_url_raw', $urls ) ) ) );

		if ( empty( $urls ) ) {
			$result = $this->api_client->purge_everything();
			$this->store_result( $result, $event, array( 'fallback' => 'purge_everything' ) );
			return $result;
		}

		$result = $this->perform_targeted_purge( $urls, $post_id, $event, $post, $mode );

		$this->store_result( $result, $event, array( 'post_id' => $post_id, 'urls' => $urls ) );
		return $result;
	}

	/**
	 * Perform targeted purge for a URL collection.
	 *
	 * @param array<int,string> $urls Target URLs.
	 * @param int               $post_id Post ID context.
	 * @param string            $event Event key.
	 * @param \WP_Post|null     $post Post object context.
	 * @param string            $mode Purge mode.
	 * @return array<string,mixed>
	 */
	private function perform_targeted_purge( array $urls, int $post_id, string $event, ?\WP_Post $post, string $mode ): array {
		$payload = array( 'files' => $urls );
		$payload = apply_filters( 'cloudflare_cirino_purge_payload', $payload, $post_id, $event, $post, $mode );
		$payload = is_array( $payload ) ? $payload : array( 'files' => $urls );

		$response = $this->api_client->purge_payload( $payload );
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
				'data'    => array(),
			);
		}

		/* translators: %d: Number of URLs. */
		$message = sprintf( __( 'Cloudflare cache purged for %d URL(s).', 'cloudflare-cirino' ), count( $urls ) );

		return array(
			'success' => true,
			'message' => $message,
			'data'    => array( 'urls' => $urls ),
		);
	}

	/**
	 * Build targeted URL list for a post.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<int,string>
	 */
	private function build_targeted_urls( \WP_Post $post ): array {
		$urls      = array();
		$permalink = get_permalink( $post );
		if ( is_string( $permalink ) && '' !== $permalink ) {
			$urls[] = $permalink;
		}

		$related_urls = array( home_url( '/' ) );
		$post_type    = get_post_type_object( $post->post_type );
		if ( $post_type instanceof \WP_Post_Type && ! empty( $post_type->has_archive ) ) {
			$archive_link = get_post_type_archive_link( $post->post_type );
			if ( is_string( $archive_link ) && '' !== $archive_link ) {
				$related_urls[] = $archive_link;
			}
		}

		$taxonomies = get_object_taxonomies( $post->post_type, 'objects' );
		if ( is_array( $taxonomies ) ) {
			foreach ( $taxonomies as $taxonomy ) {
				if ( ! $taxonomy instanceof \WP_Taxonomy || empty( $taxonomy->public ) ) {
					continue;
				}

				$terms = wp_get_post_terms( $post->ID, $taxonomy->name );
				if ( is_wp_error( $terms ) || empty( $terms ) ) {
					continue;
				}

				foreach ( $terms as $term ) {
					$link = get_term_link( $term );
					if ( is_string( $link ) && '' !== $link ) {
						$related_urls[] = $link;
					}
				}
			}
		}

		$related_urls = apply_filters( 'cloudflare_cirino_related_urls', $related_urls, $post->ID, $post );
		$related_urls = is_array( $related_urls ) ? $related_urls : array();

		$urls = array_merge( $urls, $related_urls );
		return array_values( array_unique( array_filter( array_map( 'esc_url_raw', $urls ) ) ) );
	}

	/**
	 * Build targeted URL list for manual purge.
	 *
	 * @return array<int,string>
	 */
	private function build_manual_targeted_urls(): array {
		$urls   = array( home_url( '/' ) );
		$types  = get_post_types( array( 'public' => true ), 'objects' );

		if ( is_array( $types ) ) {
			foreach ( $types as $post_type ) {
				if ( ! $post_type instanceof \WP_Post_Type || empty( $post_type->has_archive ) ) {
					continue;
				}

				$archive_link = get_post_type_archive_link( $post_type->name );
				if ( is_string( $archive_link ) && '' !== $archive_link ) {
					$urls[] = $archive_link;
				}
			}
		}

		$page_for_posts = (int) get_option( 'page_for_posts' );
		if ( $page_for_posts > 0 ) {
			$posts_page_url = get_permalink( $page_for_posts );
			if ( is_string( $posts_page_url ) && '' !== $posts_page_url ) {
				$urls[] = $posts_page_url;
			}
		}

		return array_values( array_unique( array_filter( array_map( 'esc_url_raw', $urls ) ) ) );
	}

	/**
	 * Store purge result in runtime and activity log.
	 *
	 * @param array<string,mixed> $result Result payload.
	 * @param string              $event Event key.
	 * @param array<string,mixed> $context Context data.
	 * @return void
	 */
	private function store_result( array $result, string $event, array $context = array() ): void {
		$success = ! empty( $result['success'] );
		$message = isset( $result['message'] ) ? (string) $result['message'] : '';

		$this->options->update_runtime(
			array(
				'last_purge_status'  => $success ? 'success' : 'error',
				'last_purge_message' => $message,
				'last_purge_at'      => current_time( 'timestamp' ),
			)
		);

		$context['event'] = $event;
		$this->activity_log->add( $success ? 'success' : 'error', $message, $context );
	}
}
