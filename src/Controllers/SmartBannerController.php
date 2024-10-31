<?php

namespace NAPPS\Controllers;

use NAPPS\Contracts\IController;

class SmartBannerController implements IController {

    public function registerRoutes() {

        /**
        *   Use third party woocommerce authentication (api keys)
        *   to secure smart banner info post request   
        *
        *   @see https://github.com/woocommerce/woocommerce/blob/3611d4643791bad87a0d3e6e73e031bb80447417/plugins/woocommerce/includes/class-wc-rest-authentication.php#L65
        */
        register_rest_route(
            'wc-',
            'banner',
            array(
                'methods'  => 'POST',
                'callback' => array( $this, 'register_banner' ),
                'permission_callback' => array( $this, 'check_permission' ),
            )
        );

    }

    public function check_permission() {
        $user = get_current_user_id();
        return $user && current_user_can("manage_woocommerce");

    }


    /**
     * Auth (admin) post in order to update smartbanner info when application goes live
     *
     * @param  \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function register_banner( $request ) {
        
        $scriptSrc    = $request->get_param( 'src' );
        if(!$scriptSrc) {
            delete_option("napps_banner_script");
            return new \WP_REST_Response(array(), 200);
        }

        if (filter_var($scriptSrc, FILTER_VALIDATE_URL) === FALSE) {
            return new \WP_REST_Response(array(), 401);
        }

        update_option('napps_banner_script', sanitize_url($scriptSrc));

        return new \WP_REST_Response(array(), 200);
    }

}