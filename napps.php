<?php
/**
 * Plugin Name: NAPPS - Mobile app builder
 * Description: Plugin to complement the napps E-commerce solution. More on https://napps.io
 * Version:     1.0.27
 * Text Domain: napps
 * Author:      nappssolutions
 * Author URI:  https://napps.io
 *
 * @package napps
 */

if ( ! function_exists( 'add_action' )) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

if (!function_exists('napps_doing_cron')) {
	function napps_doing_cron() {
		return defined( 'DOING_CRON' ) && DOING_CRON;
	}
}

if (!function_exists('napps_doing_ajax')) {
	function napps_doing_ajax() {
		return defined( 'DOING_AJAX' ) && DOING_AJAX;
	}
}

defined( 'ABSPATH' ) || die( "Can't access directly" );
define( 'NAPPS_MINIMUM_WP_VERSION', '4.7.0' );
define( 'NAPPS_MINIMUM_PHP_VERSION', '7.4' );
define( 'NAPPS_MINIMUM_WC_VERSION', '3.5.0' );
define( 'NAPPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NAPPS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'NAPPS_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'NAPPS_PLUGIN_FILE',  __FILE__ );
define( 'NAPPS_VERSION', '1.0.27' );
define( 'NAPPS_REST_PREFIX', 'napps/v1' );

// Require composer.
require_once( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' );

use NAPPS\Loader;

Loader::init();