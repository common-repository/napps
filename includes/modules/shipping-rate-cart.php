<?php

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

        public function __construct($instance_id = 0)
        {
            $this->id                 = 'flat_rate_cart';
            $this->instance_id        = absint( $instance_id );
            $this->method_title       = __( 'Flat rate Cart', 'woocommerce' );
            $this->method_description = __( 'Lets you charge a fixed rate for shipping with cart requirements', 'woocommerce' );
            $this->supports           = array(
                'shipping-zones',
                'instance-settings',
                'instance-settings-modal',
            );
            $this->title = "Flat rate cart";
            $this->init();

            add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        public function init() {
            parent::init();
            
            $this->init_form_fields();
            $this->init_settings();

            $this->cart_min_amount = $this->get_option( 'cart_min_amount', 0 );
            $this->requires = $this->get_option( 'requires' );
        }

        public function init_form_fields() {
            $this->instance_form_fields['requires'] = array(
                'title'   => __( 'Requires', 'woocommerce' ),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'default' => '',
                'options' => array(
                    ''           => __( 'N/A', 'woocommerce' ),
                    'cart_min_amount' => __( 'Number of items in cart', 'woocommerce' ),
                ),
            );

            $this->instance_form_fields['cart_min_amount'] = array(
                'title'       => __( 'Minimum cart items', 'woocommerce' ),
                'type'        => 'price',
                'placeholder' => wc_format_localized_price( 0 ),
                'description' => __( 'Users need to have x amount of items in cart to use this shipping method', 'woocommerce' ),
                'default'     => '0',
                'desc_tip'    => true,
            );

        }
        
        public function is_available( $package ) {

            $isAvailable = parent::is_available($package);
            if(!$isAvailable) {
                return false;
            }

            switch ( $this->requires ) {
                case 'cart_min_amount':
                    $is_available = $this->get_package_item_qty($package) >= $this->cart_min_amount;
                    break;
                default:
                    $is_available = true;
                    break;
            }

            $minAmount = $is_available ? 0 : $this->cart_min_amount;

            $choosedMethod = WC()->session->get('chosen_shipping_methods');
            if(is_array($choosedMethod) && count($choosedMethod) > 0) {

                // Get the selected shipping method
                $choosedMethod = $choosedMethod[0];

                // If the selected method is a flat rate cart set minAmount
                if(strpos($choosedMethod, $this->id) === true || !$choosedMethod) {
                    WC()->session->set( 'shipping-rate-cart', $minAmount );
                } else {
                    // Otherwise if the selected shipping method if not a flat rate cart
                    // dont show a warning
                    WC()->session->set( 'shipping-rate-cart', 0 );
                }
            } else {
                // If we dont have a shipping metho selected 
                // Set a minAmount to show a popup
                WC()->session->set( 'shipping-rate-cart', $minAmount );
            }

            return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package, $this );
        }
    }
}