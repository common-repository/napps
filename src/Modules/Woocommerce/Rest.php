<?php

namespace NAPPS\Modules\Woocommerce;

class Rest
{

    public function __construct()
    {
        add_action('rest_api_init', array($this, 'on_rest_api_init'));
    }
    
    /**
     * On wordpress rest api init
     *
     * @return void
     */
    public function on_rest_api_init()
    {
        add_action('woocommerce_after_order_object_save', array($this, 'on_rest_new_order_save'), 10, 2);
    }

    /**
     * Event when new order is saved, only trigged when rest api is inited
     * In case order request fails because a coupon is invalid,
     * the order is still created but without coupons
     * 
     * Send on a new header the order id so the client can do something with the order
     * (decide to delete it or warn the user that the order was create without coupons)
     * 
     * @param  \WC_Order $order
     * @param  mixed $dataStore
     * @return void
     */
    public function on_rest_new_order_save($order, $dataStore)
    {

        if ($order instanceof \WC_Order) {
            $order_id = $order->get_id();
        } else {
            $order_id = $order->ID;
        }

        add_filter('rest_post_dispatch', function ($response) use ($order_id) {

            $headers = $response->get_headers();
            $headers['OrderID'] = $order_id;

            $response->set_headers($headers);

            return $response;
        });
    }
}
