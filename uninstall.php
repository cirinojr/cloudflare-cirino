<?php
/**
 * Plugin uninstall cleanup.
 *
 * @package CloudflareCirino
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'cloudflare_cirino_settings' );
delete_option( 'cloudflare_cirino_runtime' );
delete_option( 'cloudflare_cirino_activity' );
delete_transient( 'cloudflare_cirino_admin_notice' );
