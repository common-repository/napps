<?php
/**
 * Setup JWT Auth.
 *
 * @package napps
 */

namespace NAPPS;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

use WC_Coupon;
use WP_Post;

if (!class_exists('NappsExclusiveCoupons')) {

    /**
	 * Exclusive coupons 
	 */
	class NappsExclusiveCoupons {

        private $initiated = false;

		/**
		 * Setup action & filter hooks.
		 */
		public function __construct() {
			if (!$this->initiated) {
				$this->init_hooks();
			}
		}

        private function init_hooks()
		{
            $this->initiated = true;

			if(is_admin()) {
				add_filter( 'woocommerce_coupon_data_tabs', array( $this, 'admin_coupon_options_tabs' ), 20, 1 );
				add_action( 'woocommerce_coupon_data_panels', array( $this, 'admin_coupon_options_panels' ), 10, 2 );
				add_action( 'woocommerce_process_shop_coupon_meta', array( $this, 'process_shop_coupon_meta' ), 10, 2);
			}

            add_action( 'init', array( $this, 'init' ) );

        }

        public function init() {
			add_filter( 'woocommerce_coupon_is_valid', array( $this, 'assert_coupon_is_valid' ), 10, 3 );
			add_filter( 'woocommerce_coupon_error', array( $this, 'coupon_mobile_app_error_message' ), 10, 3 );
		}

		
		private function get_coupon($coupon) {

			if ( $coupon instanceof WP_Post ) {
				$coupon = $coupon->ID;
			}

			if ( ! ( $coupon instanceof WC_Coupon ) ) {
				$coupon = new WC_Coupon( absint( $coupon ) );
			}
			return $coupon;
		}

		/**
		 * Action trigger after coupon is saved
		 * Lets retrieve coupon and set metadata for our custom prop
		 *
		 * @param  mixed $post_id
		 * @param  mixed $post
		 * @return void
		 */
		public function process_shop_coupon_meta($post_id, $post)  {

			$coupon = $this->get_coupon($post);

			$coupon->update_meta_data( "_is_napps",  isset( $_POST['napps_coupon_exlusive'] ));
			$coupon->save();
		}
		
		/**
		 * Set new tab on coupon page
		 *
		 * @param  mixed $tabs
		 * @return void
		 */
		public function admin_coupon_options_tabs( $tabs ) {

			$tabs['extended_features_napps'] = array(
				'label'  => __( 'Mobile App' ),
				'target' => 'napps_coupon_exlusive',
				'class'  => 'napps_coupon_exlusive',
			);

			return $tabs;
		}
		
		/**
		 * Set fields for our custom panel
		 *
		 * @return void
		 */
		public function admin_coupon_options_panels($couponID, $coupon) {

			?>
	
				<div id="napps_coupon_exlusive" class="panel woocommerce_options_panel">
					<?php

						woocommerce_wp_checkbox(
							array(
								'id'          => 'napps_coupon_exlusive',
								'label'       => __( 'Exclusive coupon'),
								'description' => __( 'Check this box if you want a mobile app exclusive coupon.' ),
								'value'       => wc_bool_to_string( $coupon->get_meta("_is_napps") ),
							)
						);

					?>
					<!-- <p class="form-field free_shipping_field ">
						<label for="free_shipping">Exclusive coupon</label>
						<input type="checkbox" class="checkbox" name="free_shipping" id="free_shipping" value="yes"> 
						<span class="description">Check this box if you want a mobile app exclusive coupon</span>
					</p> -->
				</div>
			<?php
		}
        
        /**
         * Override custom message is coupon is not valid (exclusive mobile app)
         *
         * @param  mixed $err
         * @param  mixed $err_code
         * @param  mixed $coupon
         * @return void
         */
        public function coupon_mobile_app_error_message( $err, $err_code, $coupon ) {
			// 100 = WC_COUPON::E_WC_COUPON_INVALID_FILTERED
			if( intval($err_code) == 100 && !$this->assert_coupon_is_valid( false, $coupon ) ) {
				$err = __( "This coupon is valid only for our mobile app", "woocommerce" );
			}
			return $err;
		}

		/**
		 * Extra validation rules for coupons. Throw an exception when not valid.
		 * @param bool $valid
		 * @param WC_Coupon $coupon
		 * @param WC_Discounts $discounts
		 * @return bool True if valid; False if already invalid on function call. In any other case an Exception will be thrown.
		 */
		public function assert_coupon_is_valid( $valid, $coupon, $wc_discounts = null ) {

			if($wc_discounts != null) {
				$order = $wc_discounts->get_object();
				if ( is_a( $order, 'WC_Order' ) && $order->get_created_via() === "rest-api") {
					return $valid;
				}
			}

			if ( ! $valid ) {
				return false;
			}

			$isNapps = $coupon->get_meta("_is_napps");
			if(!$isNapps || empty($isNapps) ) {
				return $valid;
			}

			return false;
		}

    }
}
?>