<?php

namespace NAPPS\Controllers;

use NAPPS\Contracts\IController;

class HealthController implements IController {

    public function registerRoutes() {

        register_rest_route(
            NAPPS_REST_PREFIX,
            'status',
            array(
                'methods'  => 'GET',
                'callback' => array( $this, 'status' ),
                'permission_callback' => '__return_true',
            )
        );
        
    }

    /**
     * Status Page
     *
     * @param \WP_REST_Request $request The request.
     * @return \WP_REST_Response The response.
     */
    public function status( \WP_REST_Request $request ) {

        $woocommerce = array_key_exists("woocommerce", $GLOBALS) ? $GLOBALS['woocommerce']->version : -1;
        return new \WP_REST_Response(
            array(
                'success'    => true,
                'plugin_version' => NAPPS_VERSION,
                'woocommerce_version' => $woocommerce,
                'statusCode' => 200
            )
        );
    }

}