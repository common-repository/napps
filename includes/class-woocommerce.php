<?php
/**
 * Setup Woocommerce.
 *
 * @package napps
 */

namespace NAPPS;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WC_Webhook;
use WC_Order;
use WC_Cart;
use WC_Session_Handler;
use WC_Customer;

if (!class_exists('NappsWooCommerce')) {

	/**
	 * Woocommerce
	 */
	class NappsWooCommerce {

		private $initiated = false;
		private $qtranslateModule;

		/**
		 * Setup action & filter hooks.
		 */
		public function __construct() {
			if (!$this->initiated) {
				$this->init_hooks();
			}
		}
		

		/**
		 * Init hooks
		 *
		 * @return void
		 */
		private function init_hooks()
		{
			$this->initiated = true;

			//Filter to add new topics / resources / hooks to woocommerce
			add_filter( 'woocommerce_webhook_topics', array($this, 'add_new_webhook_topics') );
			add_filter( 'woocommerce_valid_webhook_resources', array($this, 'add_new_resource') );
			add_filter( 'woocommerce_webhook_topic_hooks', array($this, 'add_new_topic_hooks') );

			add_action( 'rest_api_init', array( $this, 'on_rest_api_init' ));

			add_action( 'woocommerce_rest_insert_shop_order_object', array( $this, 'on_rest_new_order' ), 10, 2);

			// Shipping rate cart hooks
			add_action( 'woocommerce_shipping_init', array($this, 'woocoomerceInitShipping' ));
			add_filter( 'woocommerce_shipping_methods', array($this, 'woocoomerceLoadShipping' ) );

			require_once 'modules/shipping-rate-cart-frontend.php';
			
			// Check if we need to include qtranslate module
			if(in_array('qtranslate-xt/qtranslate.php', apply_filters('active_plugins', get_option('active_plugins')))){ 
				require_once 'modules/qtranslate.php';
				$this->qtranslateModule = new NappsQtranslate();
			}

			if(is_admin()) 
			{
				//Actions for attributes field crud
				add_action( 'added_term_meta', array( $this, 'on_attribute_field' ), 10, 4);
				add_action( 'edited_terms', array( $this, 'on_attribute_field_edit' ), 10, 2);
				add_action( 'deleted_term_meta', array( $this, 'on_attribute_field_delete' ), 10, 4);

				add_action( 'created_product_cat', array( $this, 'created_product_cat' ), 10, 2);
				add_action( 'edited_product_cat', array( $this, 'saved_product_cat' ), 10, 2);
				add_action( 'delete_product_cat', array( $this, 'delete_product_cat' ), 10, 4);
				
				add_action( 'delete_product_shipping_class', array( $this, 'delete_shipping_class' ), 10, 4);

				add_action( 'woocommerce_init', array( $this, 'woocommerce_init' ), 10, 0 ); 
				add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'woocommerce_is_napps_admin_order_fields'), 10, 1 );

			} 

			if(is_admin() || napps_doing_cron()) {
				add_action( 'woocommerce_webhook_payload', array( $this, 'woocommerce_webhook_payload' ), 10, 4);
			}
		}

		/**
		 * Event when new order is saved, only trigged when rest api is inited
		 * In case order request fails because a coupon is invalid,
		 * the order is still created but without coupons
		 * 
		 * Send on a new header the order id so the client can do something with the order
		 * (decide to delete it or warn the user that the order was create without coupons)
		 */
		public function on_rest_new_order_save($order, $dataStore) {

			if( $order instanceof WC_Order ){
				$order_id = $order->get_id();
			} else {
				$order_id = $order->ID;
			}

			add_filter( 'rest_post_dispatch', function ( $response ) use($order_id) {

				$headers = $response->get_headers();
				$headers['OrderID'] = $order_id;
			
				$response->set_headers( $headers );
			
				return $response;
			} );
		}

		/*
		*	Integration with third party plugins
		*	Some plugins only take in account orders created using website checkout
		*	Trigger this event for orders created from the rest api
		*/
		public function on_rest_new_order($order, $request) {
			
			$customer_id = -1;

			if( $order instanceof WC_Order ){
				$order_id = $order->get_id();
				$customer_id = $order->get_customer_id();
			} else{
				$order_id = $order->ID;
			}

			if($customer_id == -1) {
				return;
			}

			wc()->frontend_includes();
			WC()->session = new WC_Session_Handler();
			WC()->session->init();
			WC()->customer = new WC_Customer( $customer_id, true );
			WC()->cart = new WC_Cart();

			do_action('woocommerce_checkout_order_processed', $order_id, $request, $order);
		}

		/*
		*	Show if order is from napps mobile app on order details page
		*/
		public function woocommerce_is_napps_admin_order_fields($order) {
			?> 
			
			<div style="width: 100%;" class="order_data_column">
				<h3><?php _e( 'NAPPS' ); ?></h3>
				<p>

					<input 
						name="admin_bar_front" 
						type="checkbox" 
						id="admin_bar_front" disabled 
						<?php if ($order->get_meta( '_is_napps' )) echo 'checked="checked"'; ?>
					/>Order from mobile app	
	
				</p>
			</div>

			<?php
		}

		/*
		*	Woocommerce init event
		*/
		public function woocommerce_init() {

			//Trigger for when tax rate (postalcode) is updated
			if(array_key_exists("action", $_GET) && $_GET["action"] == 'woocommerce_tax_rates_save_changes') {
				add_action( "set_transient_shipping-transient-version", array( $this, 'on_tax_rate_postalcode_updated' ), 10, 3);
			}

			if(!class_exists("WC_Shipping_Zones")) {
				return;
			}

			$zones = WC_Shipping_Zones::get_zones();

			$restOfTheWorld = new WC_Shipping_Zone(0);
			$restOfTheWorldZone = $restOfTheWorld->get_data();
			$restOfTheWorldZone['zone_id'] = $restOfTheWorld->get_id();
			$restOfTheWorldZone['formatted_zone_location'] = $restOfTheWorld->get_formatted_location();
			$restOfTheWorldZone['shipping_methods']        = $restOfTheWorld->get_shipping_methods( false, 'admin' );

			array_push($zones, $restOfTheWorldZone);
			//woocommerce_flat_rate_9_settings
			foreach($zones as $zone) {
				foreach($zone["shipping_methods"] as $sm) {
					add_action( "update_option_woocommerce_{$sm->id}_{$sm->instance_id}_settings", array( $this, 'on_shipping_zone_method_save' ), 10, 3);
					
					//Needed when we create a shipping method and edit the name of it
					add_action( "add_option_woocommerce_{$sm->id}_{$sm->instance_id}_settings", array( $this, 'on_shipping_zone_method_update' ), 10, 2);
				}
			}

		}

		/*
		*	New woocommerce shipping method
		*/
		public function woocoomerceLoadShipping($methods) {
			$methods['flat_rate_cart'] = 'WC_Shipping_Flat_Rate_Cart';
			return $methods;
		}

		/*
		*	Event woocommerce when shipping is available
		*/
		public function woocoomerceInitShipping() {
			require_once 'modules/shipping-rate-cart.php';
		}


		public function on_tax_rate_postalcode_updated($value, $expiration, $transient) {

			if(array_key_exists("changes", $_POST)) {
				$changes = $_POST["changes"];

				foreach($changes as $taxRateUpdated) {
					if(array_key_exists("tax_rate_id", $taxRateUpdated)) {
						do_action( 'woocommerce_tax_rate_updated', sanitize_text_field($taxRateUpdated["tax_rate_id"]), null );
					}
				}
			}
			
		}

		public function on_shipping_zone_method_update($option, $old_value) {
			$this->on_shipping_zone_method_save($option, $old_value, null);
		}

		public function on_shipping_zone_method_save($option, $old_value, $value ) {
			if($_POST != null && array_key_exists("instance_id", $_POST)) {
				$zoneChanged = sanitize_text_field($_POST["instance_id"]);
				do_action( 'woocommerce_shipping_zone_method_saved', $zoneChanged );
			}
		}

		/*
		*	Return resource_id changed in woocommerce_webhook_process_delivery_new_topics function
		*	If we dont have this function, it will return an empty array
		*/
		public function woocommerce_webhook_payload($payload, $resource, $resource_id, $this_id) {

			if(!class_exists("WC_Webhook") || !class_exists("WC_Shipping_Zones"))
				return $payload;

			if($resource == "category") {
				return $resource_id;
			} else if($resource == "action") {
				$webhook = (new WC_Webhook($this_id));

				switch($webhook->get_event()) {
					//Fetch General or Tax settings from api
					case "woocommerce_settings_saved":
						return $resource_id;
					//Get Data from shippingZone
					case "woocommerce_after_shipping_zone_object_save":
						return $resource_id;
					case "woocommerce_delete_shipping_zone":
						return $resource_id;
					case "woocommerce_shipping_zone_method_deleted":
						return $resource_id;
					//Fetch Tax from api
					case "woocommerce_tax_rate_added":
						return $resource_id;
					case "woocommerce_tax_rate_updated":
						return $resource_id;
					//Fetch ShippingZone from api
					case "woocommerce_shipping_zone_method_saved":
					case "woocommerce_shipping_zone_method_added":
						$zone = WC_Shipping_Zones::get_zone_by( 'instance_id', $resource_id);
						$zoneId = $zone->get_id();
						$arg = array(
							"zone_id" => $zoneId,
							"method_id" => $resource_id
						);
						return $arg;
					case "woocommerce_attribute_added":
					case "woocommerce_attribute_updated":
					case "woocommerce_attribute_deleted":
						return $resource_id;
				}
			} else if($resource == "product") {

				// If is not a parent product
				if($payload["parent_id"] != 0) {
					$webhook = (new WC_Webhook($this_id));
					$payload["parent_product"] = $webhook->build_payload($payload["parent_id"]);
					$payload["shipping_class_from_parent"] = empty(wp_get_post_terms( $payload["id"], 'product_shipping_class' )) ? true : false; 
					$payload["tax_class_from_parent"] = get_post_meta( $payload["id"], '_tax_class', true ) == "parent" ? true : false; 
				}
			}
			return $payload;
		}
		
		public function on_rest_api_init() {
			add_filter( 'woocommerce_rest_prepare_product_variation_object', array( $this, 'woocommerce_rest_product_object' ), 20, 3);
			add_action( 'woocommerce_after_order_object_save', array( $this, 'on_rest_new_order_save' ), 10, 2);
		}

		/*
		*	Additional information returned on rest api about product information
		*
		*	Send information about shipping class and tax class (if is equal to parent)
		*/
		public function woocommerce_rest_product_object($response, $item, $request) {
			$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		    if($context != 'view'){
				return $response;
		    }

			$data = $response->get_data();
			if(!array_key_exists('id', $data) || !$item) {
				return $response;
			}

			$parentId = $item->get_parent_id();
			if($parentId != 0) {
				$data["shipping_class_from_parent"] = empty(wp_get_post_terms( $data["id"], 'product_shipping_class' )) ? true : false; 
				$data["tax_class_from_parent"] = get_post_meta( $data["id"], '_tax_class', true ) == "parent" ? true : false; 
			}

			$response->set_data($data);
		    return $response;
		}

		/*
		*	Add new Resource to woocommerce webhook list
		*/
		public function add_new_resource( $topic_events ) {

			// New events to be used for resources.
			$new_events = array(
				'category',
				'shipping_zone_method'
			);
		
			return array_merge( $topic_events, $new_events );
		}

		/*
		*	Show new option on select from webhook page
		*/
		public function add_new_webhook_topics( $topics ) {

			// New topic array to add to the list, must match hooks being created.
			$new_topics = array( 
				'category.created' => __( 'Category Created', 'woocommerce' ),
				'category.updated' => __( 'Category Updated', 'woocommerce' ),
				'category.deleted' => __( 'Category Deleted', 'woocommerce' ),
			);
		
			return array_merge( $topics, $new_topics );
		}

		/*
		*	Add new hook to the topic
		*/
		public function add_new_topic_hooks( $topic_hooks ) {

			// Array that has the topic as resource.event with arrays of actions that call that topic.
			$new_hooks = array(
				'category.created' => array(
					'category_created',
				),
				'category.updated' => array(
					'category_updated',
				),
				'category.deleted' => array(
					'category_deleted',
				),
				'action.updated' => array(
					'shipping_zone_method_saved',
					'stock_alert'
				),
			);
		
			return array_merge( $topic_hooks, $new_hooks );
		}

		public function saved_product_cat($term_id, $tt_id) {
			do_action( 'category_updated', $term_id );
		}

		public function created_product_cat($term_id, $tt_id) {
			do_action( 'category_created', $term_id );
		}

		public function delete_product_cat($term, $tt_id, $deleted_term, $object_ids) {
			do_action( 'category_deleted', $term );
		}

		/*
		*	On new attribute field
		*/
		public function on_attribute_field($mid, $object_id, $meta_key, $_meta_value) {
			if(!strpos($meta_key, "order_pa_") === 0)
				return;
			
			$name = str_replace("order_pa_", "", $meta_key);
			$id = wc_attribute_taxonomy_id_by_name($name);
			if($id == 0)
				return;
			do_action( 'woocommerce_attribute_field_added', $id, []);
		}

		public function on_attribute_field_edit($term_id, $taxonomy) {
			$id = wc_attribute_taxonomy_id_by_name($taxonomy);
			if($id == 0)
				return;
			do_action( 'woocommerce_attribute_field_updated', $id, []);
		}

		/*
		*	On new attribute delete
		*/
		public function on_attribute_field_delete($mid, $object_id, $meta_key, $_meta_value) {
			if(!strpos($meta_key, "order_pa_") === 0)
				return;
			
			$name = str_replace("order_pa_", "", $meta_key);
			$id = wc_attribute_taxonomy_id_by_name($name);
			if($id == 0)
				return;
			do_action( 'woocommerce_attribute_field_deleted', $id, []);
		}

		public function delete_shipping_class($term, $tt_id, $deleted_term, $object_ids) {
			do_action( 'woocommerce_shipping_classes_deleted');
		}
	}
}
