<?php

namespace NAPPS\Modules\ShippingRateCart;

use NAPPS\Contracts\ModuleImplementation;

class ShippingRateCartModule implements ModuleImplementation
{

    public function __construct()
    {
        require_once 'shipping-rate-cart-frontend.php';

        // Shipping rate cart hooks
        add_action('woocommerce_shipping_init', array($this, 'woocoomerceInitShipping'));
        add_filter('woocommerce_shipping_methods', array($this, 'woocoomerceLoadShipping'));
    }

    /*
    *	New woocommerce shipping method
    */
    public function woocoomerceLoadShipping($methods)
    {
        $methods['flat_rate_cart'] = 'WC_Shipping_Flat_Rate_Cart';
        return $methods;
    }

    /*
    *	Event woocommerce when shipping is available
    */
    public function woocoomerceInitShipping()
    {
        require_once 'shipping-rate-cart.php';
    }
}
