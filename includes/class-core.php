<?php
/**
 * Setup napps.
 *
 * @package napps
 */

namespace NAPPS;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

use Exception;
use WP_REST_Request;
use WP_REST_Response;
use WC_Data_Store;
use WC_Webhook;

if (!class_exists('NappsCore')) {

    /**
     *  NappsCore 
     */
    class NappsCore {

        public function __construct() {

        }

        /**
		 * From a order query param, auth current user and redirect to the payment page if allowed
         * User is retrived from the determine_current_user on the auth class (Authorization header)
		 *
		 * @param WP_REST_Request $request The request.
		 * @return WP_REST_Response The response.
		 */
		public function get_woocommerce_checkout( WP_REST_Request $request ) {

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
				
            } catch(Exception $e) {
               	wp_redirect(home_url('/napps/order/failed'));
                exit;
            }

            // If we failed to set cookie for napps_mobile ignore
            // Some wordpress sites were reporting that COOKIEPATH was not correctly set
            try {
                setcookie('napps_mobile', 'true', strtotime('+1 day'), COOKIEPATH, COOKIE_DOMAIN, true, true);
            } catch(Exception $e) {}

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
                } catch(Exception $ex) {
                    wp_redirect(home_url('/napps/order/failed'));
                    exit;
                }
                
			} else {
                wp_redirect($order->get_view_order_url());
                exit;
			}
		}
                
        /**
         * Get Plugin info like name, version and id (path)
         *
         * @param  string $plugin
         * @return array
         */
        private function getPluginInfo($plugin) {

            $pluginData = get_plugin_data(WP_PLUGIN_DIR  . '/' . $plugin, false, false);
            if(empty($pluginData["Name"]) || empty($pluginData["Version"]) || empty("Title")) {
                return null;
            }

            return [
                "id" => $plugin,
                "name" => $pluginData["Name"],
                "version" => $pluginData["Version"]
            ];
        }

        /**
         * Hook when plugin is disabled
         * Trigger action in order to tell our webhooks that this plugin has changed status
         *
         * @param  string $plugin
         * @param  bool $network_activation
         * @return void
         */
        public function on_plugin_deactivated( $plugin, $network_activation ) {
            $info = $this->getPluginInfo($plugin);
            if($info == null) {
                return;
            }

            $info["action"] = "deactivated";
            do_action( 'woocommerce_on_plugin_change', $info);

            $this->disableWebhooksFromNapps();
        }

        private function enableWebhooksFromNapps() {

            if(!class_exists("WC_Webhook")) {
                return;
            }

            global $wpdb;
            $results = $wpdb->get_results( "SELECT webhook_id, delivery_url FROM {$wpdb->prefix}wc_webhooks WHERE `status` != 'active' AND `delivery_url` LIKE 'https://nappspt.napps-solutions.com/%'" );
            foreach($results as $result)
            {
                $wh = new WC_Webhook($result->webhook_id);
                $wh->set_status('active');
                $wh->save();
            }

        }

        private function disableWebhooksFromNapps() {

            if(!class_exists("WC_Webhook")) {
                return;
            }

            global $wpdb;
            $results = $wpdb->get_results( "SELECT webhook_id, delivery_url FROM {$wpdb->prefix}wc_webhooks WHERE `status` = 'active' AND `delivery_url` LIKE 'https://nappspt.napps-solutions.com/%'" );
            foreach($results as $result)
            {
                $wh = new WC_Webhook($result->webhook_id);
                $wh->set_status('paused');
                $wh->save();
            }

        }
        /**
         * Hook when plugin is activated
         * Trigger action in order to tell our webhooks that this plugin has changed status
         *
         * @param  string $plugin
         * @param  bool $network_activation
         * @return void
         */
        public function on_plugin_activated( $plugin, $network_activation ) {
            $info = $this->getPluginInfo($plugin);
            if($info == null) {
                return;
            }

            $info["action"] = "activated";
            do_action( 'woocommerce_on_plugin_change', $info);

            $this->enableWebhooksFromNapps();
        }


        /**
         * On plugin activation hook, so we can check if current shop meet min requirements for our system.
         * The napps secret key used for the auth system is also set on this hook if one is not present
         *
         * @return void
         */
        public function on_plugin_activation() {

            if (!empty($_SERVER['SCRIPT_NAME']) && false !== strpos($_SERVER['SCRIPT_NAME'], '/wp-admin/plugins.php')) {

                $woocommerce = array_key_exists("woocommerce", $GLOBALS) ? $GLOBALS['woocommerce']->version : -1;
                
                if(!class_exists("WC_Shipping_Zones") || !class_exists("WC_Webhook")) {
                    $this->bail_on_activation();
                }
                
                if (version_compare(phpversion(), NAPPS_MINIMUM_PHP_VERSION, '<' )) {
                    $this->bail_on_activation();
                } 

                //If current wordpress version or woocommerce version does not meet requirements
                if (version_compare($GLOBALS['wp_version'], NAPPS_MINIMUM_WP_VERSION, '<' )
                        || version_compare( $woocommerce, NAPPS_MINIMUM_WC_VERSION, '<' ) ) {
                    $this->bail_on_activation();
                } 
                
                if ( ! empty( $_SERVER['SCRIPT_NAME'] ) && false !== strpos( $_SERVER['SCRIPT_NAME'], '/wp-admin/plugins.php' ) ) {
                    if(!$this->check_napps_key_is_set()) {
                        $this->bail_on_activation();
                    }
                    add_option( 'Activated_NAPPS', true );
                }
            }
        }

        /*
        *   Try to get valid secret key
        *   If we did not found one, we are gonna 
        *   create a jwt secret key and save it to database options table
        */
        private function check_napps_key_is_set() {

            $nappsAuthKey = get_option('NAPPS_AUTH_SECRET_KEY');
            if(!$nappsAuthKey) {
                $nappsAuthKey = md5(microtime().rand());
                $isKeySet = add_option('NAPPS_AUTH_SECRET_KEY', $nappsAuthKey);
                if(!$isKeySet)
                    return false; 
            }
            
            return true;
        }
        
        /**
         * Bail on plugin activation when requirements are not meet
         *
         * @return void
         */
        private function bail_on_activation() {
            $message = 'Your current version of wordpress or woocommerce does not meet the necessary requirements';
            ?>
                <!doctype html>
                <html>
                <head>
                <meta charset="<?php bloginfo( 'charset' ); ?>">
                <style>
                * {
                    text-align: center;
                    margin: 0;
                    padding: 0;
                    font-family: "Lucida Grande",Verdana,Arial,"Bitstream Vera Sans",sans-serif;
                }
                p {
                    margin-top: 1em;
                    font-size: 18px;
                }
                </style>
                <body>
                <p><?php echo esc_html( $message ); ?></p>
                </body>
                </html>
            <?php
        
            deactivate_plugins( plugin_basename( __FILE__ ) );
            exit;
        }
    }
}
