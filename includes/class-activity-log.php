<?php
/**
 * Activity logging.
 *
 * @package CloudflareCirino
 */

namespace Cloudflare_Cirino;

defined( 'ABSPATH' ) || exit;

/**
 * Stores short admin activity history.
 */
class Activity_Log {

	/**
	 * Option key.
	 */
	private const OPTION = 'cloudflare_cirino_activity';

	/**
	 * Add an activity item.
	 *
	 * @param string               $type    success|error|info
	 * @param string               $message Activity message.
	 * @param array<string,mixed>  $context Optional context.
	 * @return void
	 */
	public function add( string $type, string $message, array $context = array() ): void {
		$type = in_array( $type, array( 'success', 'error', 'info' ), true ) ? $type : 'info';

		$items = $this->get_items();
		array_unshift(
			$items,
			array(
			'type'      => $type,
			'message'   => wp_strip_all_tags( $message ),
			'context'   => $context,
			'timestamp' => current_time( 'timestamp' ),
			)
		);

		$items = array_slice( $items, 0, 15 );

		update_option( self::OPTION, array_values( $items ), false );
	}

	/**
	 * Get activity items.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_items(): array {
		$items = get_option( self::OPTION, array() );
		if ( ! is_array( $items ) ) {
			return array();
		}

		return $items;
	}

	/**
	 * Return recent items.
	 *
	 * @param int $limit Max items.
	 * @return array<int,array<string,mixed>>
	 */
	public function recent( int $limit = 6 ): array {
		$items = $this->get_items();
		if ( $limit < 1 ) {
			return $items;
		}

		return array_slice( $items, 0, $limit );
	}
}
