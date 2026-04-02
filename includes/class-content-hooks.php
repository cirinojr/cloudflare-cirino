<?php
/**
 * Content change hooks.
 *
 * @package CloudflareCirino
 */

namespace Cloudflare_Cirino;

defined( 'ABSPATH' ) || exit;

/**
 * Maps WordPress content events to purge actions.
 */
class Content_Hooks {

	/**
	 * Purge service.
	 *
	 * @var Purge_Service
	 */
	private $purge_service;

	/**
	 * Request-level duplicate guard.
	 *
	 * @var array<string,bool>
	 */
	private $already_purged = array();

	/**
	 * Constructor.
	 *
	 * @param Purge_Service $purge_service Purge service.
	 */
	public function __construct( Purge_Service $purge_service ) {
		$this->purge_service = $purge_service;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'save_post', array( $this, 'handle_save_post' ), 20, 3 );
		add_action( 'transition_post_status', array( $this, 'handle_transition' ), 20, 3 );
		add_action( 'before_delete_post', array( $this, 'handle_before_delete' ), 20, 1 );
	}

	/**
	 * Handle save_post for updates to public content.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post Post object.
	 * @param bool     $update Whether post already existed.
	 * @return void
	 */
	public function handle_save_post( int $post_id, \WP_Post $post, bool $update ): void {
		if ( ! $update || $this->should_skip( $post ) ) {
			return;
		}

		if ( ! $this->is_public_status( $post->post_status ) ) {
			return;
		}

		$this->purge_once( 'save_update:' . $post_id, $post_id, 'update_published' );
	}

	/**
	 * Handle status transitions.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Previous post status.
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function handle_transition( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( $new_status === $old_status || $this->should_skip( $post ) ) {
			return;
		}

		$was_public = $this->is_public_status( $old_status );
		$is_public  = $this->is_public_status( $new_status );

		if ( ! $was_public && $is_public ) {
			$this->purge_once( 'transition_public:' . $post->ID, (int) $post->ID, 'first_publish' );
			return;
		}

		if ( $was_public && ! $is_public ) {
			$this->purge_once( 'transition_non_public:' . $post->ID, (int) $post->ID, 'became_non_public' );
		}
	}

	/**
	 * Handle permanent delete events.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function handle_before_delete( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || $this->should_skip( $post ) ) {
			return;
		}

		if ( ! $this->is_public_status( $post->post_status ) ) {
			return;
		}

		$this->purge_once( 'delete:' . $post_id, $post_id, 'delete' );
	}

	/**
	 * Run purge only once per request key.
	 *
	 * @param string $key Unique key.
	 * @param int    $post_id Post ID.
	 * @param string $event Event key.
	 * @return void
	 */
	private function purge_once( string $key, int $post_id, string $event ): void {
		if ( isset( $this->already_purged[ $key ] ) ) {
			return;
		}

		$this->already_purged[ $key ] = true;
		$this->purge_service->purge_post( $post_id, $event );
	}

	/**
	 * Determine if post should be ignored.
	 *
	 * @param \WP_Post $post Post object.
	 * @return bool
	 */
	private function should_skip( \WP_Post $post ): bool {
		if ( wp_is_post_autosave( $post->ID ) || wp_is_post_revision( $post->ID ) ) {
			return true;
		}

		if ( 'auto-draft' === $post->post_status ) {
			return true;
		}

		if ( ! in_array( $post->post_type, $this->get_supported_post_types(), true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Supported post types.
	 *
	 * @return array<int,string>
	 */
	private function get_supported_post_types(): array {
		$types = get_post_types( array( 'public' => true ), 'names' );
		$types = array_values( array_diff( $types, array( 'attachment' ) ) );

		$types = apply_filters( 'cloudflare_cirino_supported_post_types', $types );

		return is_array( $types ) ? $types : array();
	}

	/**
	 * Check whether a post status is publicly cacheable.
	 *
	 * @param string $status Post status.
	 * @return bool
	 */
	private function is_public_status( string $status ): bool {
		$public_statuses = apply_filters( 'cloudflare_cirino_public_statuses', array( 'publish' ) );
		if ( ! is_array( $public_statuses ) ) {
			$public_statuses = array( 'publish' );
		}

		return in_array( $status, $public_statuses, true );
	}
}
