<?php

namespace NAPPS\Modules\Woocommerce;

class Admin
{

    public function __construct()
    {
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'woocommerce_is_napps_admin_order_fields'), 10, 1);
    }
    
    /**
     * Show if order is from napps mobile app on order details page
     *
     * @param  \WC_Order $order
     * @return void
     */
    public function woocommerce_is_napps_admin_order_fields($order)
    {
        ?>

        <div style="width: 100%;" class="order_data_column">
            <h3><?php _e('NAPPS'); ?></h3>
            <p>

                <input name="admin_bar_front" type="checkbox" id="admin_bar_front" disabled <?php if ($order->get_meta('_is_napps')) echo 'checked="checked"'; ?> /> <?php _e('Order from mobile app', 'napps') ?>

            </p>
        </div>

        <?php
    }
}
