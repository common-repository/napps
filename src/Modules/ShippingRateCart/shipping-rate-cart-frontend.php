<?php

add_action( 'woocommerce_proceed_to_checkout', 'disable_checkout_button_no_shipping', 1 );
function disable_checkout_button_no_shipping() {

    $session = WC()->session;
    if(!$session) {
        return;
    }

    $minAmount = $session->get( 'shipping-rate-cart' );
    if(!$minAmount || $minAmount == 0) {
        return;
    }

    $chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
    // removes empty values from the array
    $chosen_shipping_methods = array_filter( $chosen_shipping_methods );
    if ( empty( $chosen_shipping_methods ) ) {
        remove_action( 'woocommerce_proceed_to_checkout','woocommerce_button_proceed_to_checkout', 20);
    }

}

/**
*   Redirect user back of cart page if user does not have a min amount of product in cart
*/
add_action( 'template_redirect' , 'napps_checkout_validation' );
function napps_checkout_validation(){

    $session = WC()->session;
    if(!$session) {
        return;
    }

    $disableCheckout = $session->get( 'shipping-rate-cart-disable-checkout' );
    if(!$disableCheckout) {
        return;
    }

	if(is_checkout()){
		wp_safe_redirect( get_permalink( get_option('woocommerce_cart_page_id') ) );
        exit;
	}

}

add_filter( 'woocommerce_shipping_rate_label', 'filter_shipping_method_label', 10, 2);
function filter_shipping_method_label($label, $shippingMethod) {
    // If it not a shipping flat rate cart return original label
    if(!$shippingMethod || $shippingMethod->get_method_id() !== "flat_rate_cart") {
        return $label;
    }

    // If shipping label does not contain %day% or %weekday% return original label
    if(!strpos($label, "%day%") && !strpos($label, "%weekday%")) {
        return $label; 
    }

    $shippingMethodRate = new \WC_Shipping_Flat_Rate_Cart($shippingMethod->get_instance_id());
    if(!$shippingMethodRate) {
        return $label;
    }

    $weekday = $shippingMethodRate->shipping_weekday;
    $xDaysBefore = $shippingMethodRate->shipping_x_days_before;
    if($xDaysBefore < 0) {
        return $label;
    }

    // Get next weekday time
    $targetWeekDay = strtotime("next $weekday");
    $targetDate = $targetWeekDay;
    if(!$targetDate) {
        return $label;
    }

    // Get diff from now to next week shipping day
    $datediff = time() - $targetWeekDay;
    $diffInDays = abs($datediff / (60 * 60 * 24));

    // Delay x amount of weeks (user can set 10 days before shipping day) because we cant ship this week
    if($diffInDays - $xDaysBefore < 0) {

        // If diff in days is 8, find next multiplier of 7 (week days), in this case would be 14
        // If diff in days is 2, that would be 7 days. 
        $roundToNear = 7;
        $n = abs($diffInDays);
        $roundUpToAny = (ceil($n)%$roundToNear === 0) ? ceil($n) : round(($n+$roundToNear/2)/$roundToNear)*$roundToNear;
        $roundUpToAny = intval($roundUpToAny);

        // Target date for shipping
        $targetDate = strtotime("+$roundUpToAny days", $targetWeekDay);
    }

    if(!$targetDate) {
        return $label;
    }

    if(strpos($label, "%day%") != -1) {
        $label = str_replace("%day%", date("d", $targetDate), $label);
    }

    if(strpos($label, "%weekday%") != -1) {
        $weekdayName = wp_date( 'l', $targetDate );
        $label = str_replace("%weekday%", $weekdayName, $label);
    }

    return $label;
}

/**
*   Show notice error on top of cart page that we need a min amount of product
*   to be able to use this shipping method
*/
add_action('woocommerce_before_cart', 'woocommerce_before_cart', 1);
function woocommerce_before_cart() {

    $session = WC()->session;
    if(!$session) {
        return;
    }

    $minAmount = $session->get( 'shipping-rate-cart' );
    if(!$minAmount || $minAmount == 0) {
        return;
    }

    $requires = $session->get( 'shipping-rate-cart-requires' );

    $title = "";
    switch($requires) {
        case 'cart_min_amount':
            $title = sanitize_text_field(
                __('You need at least ', 'napps') . $minAmount . __(' items in your cart in order to be able to ship to your destination', 'napps')
            );
            break;
        case 'cart_money_min_amount':
            $title = sanitize_text_field(
                __('Your cart sub-total must be at least ', 'napps') . wc_price($minAmount) . __(' in order to be able to ship to your destination', 'napps')
            );
            break;
        default:
            break;
    }

    if(!$title) {
        return;
    }

    if(!wc_has_notice($title)) {
        wc_add_notice($title, "notice");
    }
}