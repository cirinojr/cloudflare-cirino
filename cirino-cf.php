<?php
/**
 * Plugin Name: Cloudflare Cirino
 * Plugin URI: https://github.com/cirinojr/cloudflare-cirino
 * Description: Automatically purges Cloudflare cache when public WordPress content changes.
 * Version: 1.0.1
 * Author: Claudio Cirino Jr
 * Author URI: https://github.com/cirinojr
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cloudflare-cirino
 * Requires at least: 6.1
 * Requires PHP: 7.4
 *
 * @package CloudflareCirino
 */

defined( 'ABSPATH' ) || exit;

define( 'CLOUDFLARE_CIRINO_VERSION', '1.0.1' );
define( 'CLOUDFLARE_CIRINO_FILE', __FILE__ );
define( 'CLOUDFLARE_CIRINO_BASENAME', plugin_basename( __FILE__ ) );
define( 'CLOUDFLARE_CIRINO_PATH', plugin_dir_path( __FILE__ ) );
define( 'CLOUDFLARE_CIRINO_URL', plugin_dir_url( __FILE__ ) );

require_once CLOUDFLARE_CIRINO_PATH . 'includes/class-plugin.php';

\Cloudflare_Cirino\Plugin::instance()->boot();
