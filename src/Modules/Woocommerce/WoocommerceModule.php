<?php

namespace NAPPS\Modules\Woocommerce;

use NAPPS\Modules\Woocommerce\Rest;
use NAPPS\Modules\Woocommerce\Admin;
use NAPPS\Modules\Woocommerce\Webhooks;
use NAPPS\Contracts\ModuleImplementation;

class WoocommerceModule implements ModuleImplementation
{

    public function __construct()
    {
        if(is_admin()) {
            new Admin();
        }

        new Rest();
        new Webhooks();

        add_action( 'woocommerce_rest_insert_shop_order_object', array($this, 'on_rest_new_order'), 10, 2);
        add_action( 'woocommerce_checkout_order_created', array($this, 'on_checkout_order'), 20, 2);
        add_action( 'woocommerce_checkout_create_order', array($this, 'before_checkout_order'), 20, 2);

    }
    
    /**
     * Check if order is created from a napps cart
     * Is so, set order created from napps
     *
     * @param  \WC_Order $order
     * @return void
     */
    public function before_checkout_order($order) {
        $session = WC()->session;

        // Check if we have a valid session and is a napps cart
        if(!$session || $session->get('napps_cart') != true) {
            return ;
        }

        $order->update_meta_data( '_is_napps', 'true', true );
    }

    /**
     * Check if order is created from a napps cart
     * Is so, set cookie with order id
     *
     * @param  \WC_Order $order
     * @return void
     */
    public function on_checkout_order($order) {

        $session = WC()->session;

        // Check if we have a valid session and is a napps cart
        if(!$session || $session->get('napps_cart') != true) {
            return ;
        }
        
        if ($order instanceof \WC_Order) {
            $order_id = $order->get_id();
        } else {
            $order_id = $order->ID;
        }
        
        // Inform applications about new order id created
        setcookie('napps_order_id', $order_id, time()+60, "", "", true, true);
    }

    /**
    *	Integration with third party plugins
    *	Some plugins only take in account orders created using website checkout
    *	Trigger this event for orders created from the rest api
    */
    public function on_rest_new_order($order, $request)
    {

        $customer_id = -1;

        if ($order instanceof \WC_Order) {
            $order_id = $order->get_id();
            $customer_id = $order->get_customer_id();
        } else {
            $order_id = $order->ID;
        }

        if ($customer_id == -1) {
            return;
        }

        // Some plugins need to have a empty cart 
        wc()->frontend_includes();
        WC()->session = new \WC_Session_Handler();
        WC()->session->init();
        WC()->customer = new \WC_Customer($customer_id, true);
        WC()->cart = new \WC_Cart();

        do_action('woocommerce_checkout_order_processed', $order_id, $request, $order);
    }
}
