<?php

namespace NAPPS\Controllers;

use NAPPS\Contracts\IController;
use NAPPS\Services\AuthService;

class WooCommerceController implements IController {

    /**
     * AuthService
     *
     * @var AuthService
     */
    private $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function registerRoutes() {
        register_rest_route(
            NAPPS_REST_PREFIX,
            'woocommerce/checkout',
            array(
                'methods'  => 'GET', 
                'callback' => array( $this, 'get_woocommerce_checkout' ),
                'permission_callback' => array( $this->authService, 'checkValidClient' ),
            )
        );

        register_rest_route(
            NAPPS_REST_PREFIX,
            'woocommerce/cart',
            array(
                'methods'  => 'POST', 
                'callback' => array( $this, 'get_woocommerce_cart' ),
                'permission_callback' => array( $this->authService, 'checkValidClient' ),
            )
        );

        register_rest_route(
            NAPPS_REST_PREFIX,
            'woocommerce/cart',
            array(
                'methods'  => 'GET', 
                'callback' => array( $this, 'open_woocommerce_checkout' ),
                'permission_callback' => array( $this->authService, 'checkValidClient' ),
            )
        );
    }
        
    /**
     * Create a cart on current customer session
     *
     * @param  \WP_REST_Request $request
     * @return void
     */
    public function get_woocommerce_cart ( $request) {

        // Get current auth user
        $customer = get_current_user_id();
        
        /**
         *  Check if user is not loggedIn, we should not need to do this
         *  permission_callback on register routes already does this, making sure
         */
        if(!$customer) {
            return new \WP_REST_Response(null, 401);
        }

        $request = json_decode($request->get_body(), true);

        // Set customer billing address, user does not have to fill it on the checkout page
        if(array_key_exists('billing', $request)) {
            $billing = $request['billing'];

            update_user_meta($customer,'billing_first_name', wc_clean( $billing['firstName'] )); 
            update_user_meta($customer,'billing_last_name', wc_clean( $billing['lastName'] )); 
            update_user_meta($customer,'billing_address_1', wc_clean( $billing['line1'] )); 
            update_user_meta($customer,'billing_address_2', wc_clean( $billing['line2'] )); 
            update_user_meta($customer,'billing_city', wc_clean( $billing['city'] )); 
            update_user_meta($customer,'billing_state', wc_clean( $billing['province'] )); 
            update_user_meta($customer,'billing_postcode', wc_clean( $billing['postalCode'] )); 
            update_user_meta($customer,'billing_phone', wc_clean( $billing['phone'] )); 
            update_user_meta($customer,'billing_country', wc_clean( $billing['country'] )); 

        }

        // Set customer shipping address, user does not have to fill it on the checkout page
        if(array_key_exists('shipping', $request)) {
            $shipping = $request['shipping'];

            update_user_meta($customer,'shipping_first_name', wc_clean( $shipping['firstName'] )); 
            update_user_meta($customer,'shipping_last_name', wc_clean( $shipping['lastName'] )); 
            update_user_meta($customer,'shipping_address_1', wc_clean( $shipping['line1'] )); 
            update_user_meta($customer,'shipping_address_2', wc_clean( $shipping['line2'] )); 
            update_user_meta($customer,'shipping_city', wc_clean( $shipping['city'] )); 
            update_user_meta($customer,'shipping_state', wc_clean( $shipping['province'] )); 
            update_user_meta($customer,'shipping_postcode', wc_clean( $shipping['postalCode'] )); 
            update_user_meta($customer,'shipping_phone', wc_clean( $shipping['phone'] )); 
            update_user_meta($customer,'shipping_country', wc_clean( $shipping['country'] )); 
        }

        WC()->frontend_includes();
        WC()->session = new \WC_Session_Handler();
        WC()->session->init();
        WC()->customer = new \WC_Customer( $customer, true );
        WC()->cart = new \WC_Cart();
        WC()->cart->empty_cart();

        // Cart created from napps, soo if a order is created using this session
        // We can know that order was created using the mobile app
        WC()->session->set('napps_cart', true);

        
        if(!array_key_exists('line_items', $request) || !array_key_exists('cart_total', $request)) {
            return new \WP_REST_Response(null, 400);
        }

        $validProducts = array();
        foreach($request['line_items'] as $productData) {

            if(!array_key_exists('id', $productData) || !array_key_exists('quantity', $productData)) {
                continue;
            }

            $product = wc_get_product( $productData['id'] );
            $variation_id = 0;
            $product_id = $productData['id'];
            $attributes = array();

            if(!$product || 'publish' !== $product->get_status() || !is_array($productData['properties'])) {
                continue;
            }

            if ( 'variation' === $product->get_type() && array_key_exists('properties', $productData)) {
                $variation_id = $productData['id'];
                $product_id   = $product->get_parent_id();

                $properties = $productData['properties'];

                // Retrieve parent product attributes and loop though them
                // Attributes sent to add_to_cart needs to be a slug (variant value)
                $parent_data        = wc_get_product( $product->get_parent_id() );
                foreach ( $parent_data->get_attributes() as $attribute ) {
                    if ( ! $attribute['is_variation'] ) {
						continue;
					}

                    // Check if user sent the attribute
                    $attribute_id = $attribute['id'];
                    if(!isset($properties[$attribute_id])) {
                        continue;
                    }

                    // Get term using variant value and option name
                    $value = get_term_by( 'name', $properties[$attribute_id], $attribute->get_name());
                    if(!$value || !is_a($value, 'WP_Term')) {
                        continue;
                    }

                    $attributeName = 'attribute_' . sanitize_title( $attribute['name'] );
                    $attributes[$attributeName] = $value->slug;
                }
            }

            
            $addedToCart = WC()->cart->add_to_cart($product_id, $productData['quantity'], $variation_id, $attributes);
            if($addedToCart) {
                $validProducts[] = $product_id;
            }

        }

        if(count($validProducts) == 0) {
            return new \WP_REST_Response(["valid_products" => $validProducts ], 401);
        }


        $applied_coupons = [];
        if(array_key_exists('coupon', $request)) {
           
            foreach($request['coupon'] as $coupon_code) {
                if(!is_string($coupon_code)) {
                    continue;
                }

                $coupon = new \WC_Coupon( $coupon_code );
                if ( $coupon->get_code() !== $coupon_code ) {
                    continue;
                }
            
                if(!$coupon->is_valid()) {
                    continue;
                }

                if($coupon->get_individual_use()) {

                    $applied_coupons = [$coupon_code];
                    break;
                }

                $applied_coupons[] = $coupon_code;
            }
        }

        WC()->cart->set_applied_coupons( $applied_coupons );
        WC()->cart->calculate_totals();

        $total = WC()->cart->get_cart_contents_total() + WC()->cart->get_cart_contents_tax();
        $parsedTotal = round(floatval($total), 2);
        $clientTotal = round(floatval($request['cart_total']), 2);

        // Make sure that cart total matches the cart total sent from client
        if(($clientTotal < $parsedTotal - 0.03) || ($clientTotal > $parsedTotal + 0.03)) {
            return new \WP_REST_Response(["cart_total" => $total ], 401);
        }

        WC()->session->save_data();

        return new \WP_REST_Response([
            "cart_url" => site_url() . "/wp-json/" . NAPPS_REST_PREFIX . "/woocommerce/cart",
            "valid_products" => $validProducts,
            'valid_coupons' => $applied_coupons,
        ], 200);
    }

    /**
     * From a order query param, auth current user and redirect to the payment page if allowed
     *
     * @param \WP_REST_Request $request The request.
     * @return \WP_REST_Response The response.
     */
    public function get_woocommerce_checkout( $request ) {

        // Get order id
        $orderID = $request->get_param("order");

        // Get current auth user
        $customer = get_current_user_id();
        
        // Check if user is not loggedIn
        if($orderID == null || !$customer) {
            wp_redirect(home_url('/napps/order/failed'));
            exit;
        }
        
        // If order is not from auth user
        $order = wc_get_order($orderID);
        if(!$order || $customer != $order->get_customer_id()) {
            wp_redirect(home_url('/napps/order/failed'));
            exit;
        }


        // Auth current customer based on his jwt token
        try {
            wp_clear_auth_cookie();
            wp_set_current_user( $customer );
            
            $expiration = time() + apply_filters( 'auth_cookie_expiration', 2 * DAY_IN_SECONDS, $customer, false );
            $authCookie = wp_generate_auth_cookie($customer, $expiration, 'logged_in');
            setcookie( LOGGED_IN_COOKIE, $authCookie, 0, COOKIEPATH, COOKIE_DOMAIN, true, true );
            
        } catch(\Exception $e) {
               wp_redirect(home_url('/napps/order/failed'));
            exit;
        }

        // If we failed to set cookie for napps_mobile ignore
        // Some wordpress sites were reporting that COOKIEPATH was not correctly set
        try {
            setcookie('napps_mobile', 'true', strtotime('+1 day'), COOKIEPATH, COOKIE_DOMAIN, true, true);
        } catch(\Exception $e) {

        }

        if( $order->has_status( 'pending' ) || $order->has_status( 'unpaid' ) ) {

            try {
                // Retrieve order payment url
                $pay_now_url = wc_get_endpoint_url( 'order-pay', $orderID, wc_get_checkout_url() );
                $pay_now_url = add_query_arg(
                    array(
                        'pay_for_order' => 'true',
                        'key'           => $order->get_order_key(),
                    ),
                    $pay_now_url
                );

                // Redirect to payment page
                wp_redirect($pay_now_url);
                exit;
            } catch(\Exception $ex) {
                wp_redirect(home_url('/napps/order/failed'));
                exit;
            }
            
        } else {
            wp_redirect($order->get_view_order_url());
            exit;
        }
    }

    public function open_woocommerce_checkout() {
        $customer = get_current_user_id();
        
        // Check if user is not loggedIn
        if(!$customer) {
            wp_redirect(home_url('/napps/order/failed'));
            exit;
        }
        
        // Auth current customer based on his jwt token
        try {
            wp_clear_auth_cookie();
            wp_set_current_user( $customer );
            
            $expiration = time() + apply_filters( 'auth_cookie_expiration', 2 * DAY_IN_SECONDS, $customer, false );
            $authCookie = wp_generate_auth_cookie($customer, $expiration, 'logged_in');
            setcookie( LOGGED_IN_COOKIE, $authCookie, 0, COOKIEPATH, COOKIE_DOMAIN, true, true );
            
        } catch(\Exception $e) {
            wp_redirect(home_url('/napps/order/failed'));
            exit;
        }

        // If we failed to set cookie for napps_mobile ignore
        // Some wordpress sites were reporting that COOKIEPATH was not correctly set
        try {
            setcookie('napps_mobile', 'true', strtotime('+1 day'), COOKIEPATH, COOKIE_DOMAIN, true, true);
        } catch(\Exception $e) {

        }

        wp_redirect(wc_get_checkout_url() . "/#order_review_heading");
        exit;
    }
}