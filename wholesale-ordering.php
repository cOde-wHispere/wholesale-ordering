<?php
/**
 * Plugin Name: Wholesale Ordering
 * Plugin URI: https://github.com/cOde-wHispere/wholesale-ordering
 * Description: Wholesale ordering functionality for WooCommerce.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.3
 * Author: cOde-wHispere
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wholesale-ordering
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WHOLESALE_ORDERING_VERSION' ) ) {
    define( 'WHOLESALE_ORDERING_VERSION', '0.1.0' );
}

if ( ! defined( 'WHOLESALE_ORDERING_FILE' ) ) {
    define( 'WHOLESALE_ORDERING_FILE', __FILE__ );
}

if ( ! defined( 'WHOLESALE_ORDERING_DIR' ) ) {
    define( 'WHOLESALE_ORDERING_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WHOLESALE_ORDERING_URL' ) ) {
    define( 'WHOLESALE_ORDERING_URL', plugin_dir_url( __FILE__ ) );
}

$autoload = WHOLESALE_ORDERING_DIR . 'vendor/autoload.php';

if ( ! file_exists( $autoload ) ) {
    return;
}

require_once $autoload;
\WholesaleOrdering\Plugin::init();