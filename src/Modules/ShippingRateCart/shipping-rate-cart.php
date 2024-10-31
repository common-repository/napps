<?php

use Automattic\WooCommerce\Utilities\NumberUtil;


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'WC_Shipping_Flat_Rate_Cart' ) ) {

    class WC_Shipping_Flat_Rate_Cart extends WC_Shipping_Flat_Rate {

        /**
         * Min amount to be valid.
         *
         * @var integer
         */
        public $cart_min_amount = 0;

        /**
         * Requires option.
         *
         * @var string
         */
        public $requires = '';

        /**
         * Shipping weekday option.
         *
         * @var string
         */
        public $shipping_weekday = '';

        /**
         * Shipping x days before option.
         *
         * @var integer
         */
        public $shipping_x_days_before = 0;

        public function __construct($instance_id = 0)
        {
            $this->id                 = 'flat_rate_cart';
            $this->instance_id        = absint( $instance_id );
            $this->method_title       = __( 'Flat rate Cart', 'napps' );
            $this->method_description = __( 'Lets you charge a fixed rate for shipping with cart requirements', 'napps' );
            $this->supports           = array(
                'shipping-zones',
                'instance-settings',
                'instance-settings-modal',
            );
            $this->title = "Flat rate cart";
            $this->init();

            add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'admin_footer', array( $this, 'enqueue_admin_js' ), 10 );
        }

        public function init() {
            parent::init();
            
            $this->init_form_fields();
            $this->init_settings();

            $this->cart_min_amount = floatval( preg_replace( '#[^\d.]#', '', $this->get_option( 'cart_min_amount', 0 ) ) );
            $this->requires = $this->get_option( 'requires' );
            $this->shipping_weekday = $this->get_option( 'shipping_weekday' );
            $this->shipping_x_days_before = intval( preg_replace( '#[^\d.]#', '', $this->get_option( 'shipping_x_days_before', 0 ) ) );
        }

        public function init_form_fields() {
            if(array_key_exists("title", $this->instance_form_fields)) {
                $this->instance_form_fields['title']['description'] = __( 'This is display on checkout page for the client, use %day% (current day) or %weekday% (ex: Saturday) to display shipping day', 'napps' );
            }
            
            $this->instance_form_fields['requires'] = array(
                'title'   => __( 'Requires', 'napps' ),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'default' => '',
                'options' => array(
                    ''           => __( 'N/A', 'napps' ),
                    'cart_min_amount' => __( 'Number of items in cart', 'napps' ),
                    'cart_money_min_amount' => __( 'Total amount (€) in cart', 'napps' ),
                ),
            );

            $this->instance_form_fields['cart_min_amount'] = array(
                'title'       => __( 'Minimum cart items / amount', 'napps' ),
                'type'        => 'price',
                'placeholder' => wc_format_localized_price( 0 ),
                'description' => __( 'Users need to have x (amount of items in cart / amount in cart) to use this shipping method', 'napps' ),
                'default'     => '0',
                'desc_tip'    => true,
            );

            $this->instance_form_fields['shipping_weekday'] = array(
                'title'   => __( 'Shipping day of the week', 'napps' ),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'description' => __( 'Select the day of the week that you ship your orders, so we can inform the customer when the order is expected to ship', 'napps' ),
                'default' => 'none',
                'options' => array(
                    'none'    => __( 'Not defined', 'napps' ),
                    'monday' => __( 'Monday', 'napps' ),
                    'tuesday' => __( 'Tuesday', 'napps' ),
                    'wednesday' => __( 'Wednesday', 'napps' ),
                    'thursday' => __( 'Thursday', 'napps' ),
                    'friday' => __( 'Friday', 'napps' ),
                    'saturday' => __( 'Saturday', 'napps' ),
                    'sunday' => __( 'Sunday', 'napps' ),
                ),
                'desc_tip'    => true,
            );

            $this->instance_form_fields['shipping_x_days_before'] = array(
                'title'       => __( 'Order x days before shipping day', 'napps' ),
                'type'        => 'number',
                'placeholder' => 0,
                'description' => __( 'Users need to order x number of days before shipping day in order to recieve the order this week, otherwise the order will be ship on the next week', 'napps' ),
                'default'     => 0,
                'desc_tip'    => true,
            );

        }
        
        protected function get_cart_subtotal() {
            $total = WC()->cart->get_displayed_subtotal();

            if ( WC()->cart->display_prices_including_tax() ) {
                $total = $total - WC()->cart->get_discount_tax();
            }

            $total = NumberUtil::round( $total, wc_get_price_decimals() );
            return $total;
        }

        public function is_available( $package ) {

            $session = WC()->session;
            if(!$session) {
                return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', false, $package, $this );
            }

            // By default we dont block checkout page
            $session->set( 'shipping-rate-cart-disable-checkout', 0 );
            $session->set( 'shipping-rate-cart', 0 );

            $isAvailable = parent::is_available($package);
            if(!$isAvailable) {
                return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', false, $package, $this );
            }

            switch ( $this->requires ) {
                case 'cart_min_amount':
                    $is_available = $this->get_package_item_qty($package) >= $this->cart_min_amount;
                    break;
                case 'cart_money_min_amount':
                    $is_available = $this->get_cart_subtotal() >= $this->cart_min_amount;
                    break;
                default:
                    $is_available = true;
                    break;
            }
            
            $minAmount = $is_available ? 0 : $this->cart_min_amount;
            $choosedMethod = $session->get('chosen_shipping_methods');

            if(is_array($choosedMethod) && count($choosedMethod) > 0) {
            
                // Get the selected shipping method
                $choosedMethod = $choosedMethod[0];

                // If the selected method is a flat rate cart 
                // block checkout if minAmount is not 0
                if(strpos($choosedMethod, $this->id) === true) {
                    $session->set( 'shipping-rate-cart-disable-checkout', $minAmount );
                }
            }

            $session->set( 'shipping-rate-cart', $minAmount );
            $session->set( 'shipping-rate-cart-requires', $this->requires );
            
            return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package, $this );
        }

        public static function enqueue_admin_js() {
            wc_enqueue_js(
                "jQuery( function( $ ) {
                    function flatRateCartHideFields( el ) {
                        const form = $( el ).closest( 'form' );
                        const xDaysBefore = $( '#woocommerce_flat_rate_cart_shipping_x_days_before', form ).closest( 'tr' );
                        if ( 'none' === $( el ).val() || '' === $( el ).val() ) {
                            xDaysBefore.hide();
                        } else {
                            xDaysBefore.show();
                        }
                    }
    
                    $( document.body ).on( 'change', '#woocommerce_flat_rate_cart_shipping_weekday', function() {
                        flatRateCartHideFields( this );
                    });
    
                    // Change while load.
                    $( '#woocommerce_flat_rate_cart_shipping_weekday' ).trigger( 'change' );
                    $( document.body ).on( 'wc_backbone_modal_loaded', function( evt, target ) {
                        if ( 'wc-modal-shipping-method-settings' === target ) {
                            flatRateCartHideFields( $( '#wc-backbone-modal-dialog #woocommerce_flat_rate_cart_shipping_weekday', evt.currentTarget ) );
                        }
                    } );
                });"
            );
        }

    }
}