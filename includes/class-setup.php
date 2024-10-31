<?php
namespace NAPPS;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

use WP_REST_Request;
use WP_REST_Response;

require_once 'class-auth.php';
require_once 'class-woocommerce.php';
require_once 'class-smartbanner.php';
require_once 'class-core.php';
require_once 'class-exclusivecoupons.php';

if (!class_exists('NappsSetup')) {
	/**
	 * Setup Class
	 */
	class NappsSetup {

		private $auth;
		private $core;
		private $smartBanner;
		private $initiated = false;

		/**
		 * Setup action & filter hooks.
		 */
		public function __construct() {
			
			if (!$this->initiated) {
				new NappsWooCommerce(); 
				$this->auth = new NappsAuth();
				$this->core = new NappsCore();
				$this->smartBanner = new NappsSmartBanner();
				new NappsExclusiveCoupons();
				$this->init_hooks();
			}
		}
		
		private function init_hooks()
		{
			$this->initiated = true;

			// Hook for when this plugin is activated
			register_activation_hook( NAPPS_PLUGIN_FILE, array( $this->core, 'on_plugin_activation' ) );

			// Register rest routes
			add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

			add_filter( 'determine_current_user', array( $this->auth, 'determine_current_user' ) );

			if(is_admin()) {
				add_action( 'activated_plugin', array( $this->core, 'on_plugin_activated' ), 10, 2 );
				add_action( 'deactivated_plugin', array( $this->core, 'on_plugin_deactivated' ), 10, 2 );
			}
		}

		public function load_textdomain() {
			load_plugin_textdomain( 'napps', false, dirname( NAPPS_PLUGIN_BASENAME ) . '/lang' );
		}

		/**
		 * Add the endpoints to the API
		 */
		public function register_rest_routes() {

			// Status info for this plugin
			register_rest_route(
				NAPPS_REST_PREFIX,
				'status',
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'status' ),
					'permission_callback' => '__return_true',
				)
			);
			
			//Reset password request for users
			register_rest_route(
				NAPPS_REST_PREFIX,
				'reset-password',
				array(
					'methods'  => 'POST',
					'callback' => array( $this->auth, 'resetPassword' ),
					'permission_callback' => '__return_true',
				)
			);

			//Login request
			register_rest_route(
				NAPPS_REST_PREFIX,
				'token',
				array(
					'methods'  => 'POST',
					'callback' => array( $this->auth, 'get_token' ),
					'permission_callback' => '__return_true',
				)
			);
			
			register_rest_route(
				NAPPS_REST_PREFIX,
				'smartbanner',
				array(
					'methods'  => 'POST',
					'callback' => array( $this->smartBanner, 'smartbanner_info' ),
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				NAPPS_REST_PREFIX,
				'manifest',
				array(
					'methods'  => 'GET',
					'callback' => array( $this->smartBanner, 'manifest' ),
					'permission_callback' => '__return_true',
				)
			);
			

			//Retrieve payment page from one order id if user is auth
			register_rest_route(
				NAPPS_REST_PREFIX,
				'woocommerce/checkout',
				array(
					'methods'  => 'GET', 
					'callback' => array( $this->core, 'get_woocommerce_checkout' ),
					'permission_callback' => '__return_true',
				)
			);
		}

		/**
		 * Status Page
		 *
		 * @param WP_REST_Request $request The request.
		 * @return WP_REST_Response The response.
		 */
		public function status( WP_REST_Request $request ) {

			$woocommerce = array_key_exists("woocommerce", $GLOBALS) ? $GLOBALS['woocommerce']->version : -1;
			return new WP_REST_Response(
				array(
					'success'    => true,
					'plugin_version' => NAPPS_VERSION,
					'woocommerce_version' => $woocommerce,
					'statusCode' => 200
				)
			);
		}
		
	}
	new NappsSetup();
}
