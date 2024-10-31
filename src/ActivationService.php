<?php

namespace NAPPS;

use NAPPS\Utils\SingletonTrait;

class ActivationService {

    use SingletonTrait;

    public function init() {

        // Hook for when this plugin is activated
        register_activation_hook( NAPPS_PLUGIN_FILE, array( $this, 'on_plugin_activation' ) );

        if(is_admin()) {
            add_action( 'activated_plugin', array( $this, 'on_plugin_activated' ), 10, 2 );
            add_action( 'deactivated_plugin', array( $this, 'on_plugin_deactivated' ), 10, 2 );
        }

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

        if($plugin != NAPPS_PLUGIN_BASENAME) {
            return;
        }
        
        $this->disable_webhooks_from_napps();
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

        if($plugin != NAPPS_PLUGIN_BASENAME) {
            return;
        }

        $this->enable_webhooks_from_napps();
    }

    /**
     * Get Plugin info like name, version and id (path)
     *
     * @param  string $plugin
     * @return null|array
     */
    protected function getPluginInfo($plugin) {

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

   
    public function get_active_webhooks() {
        global $wpdb;
        return $wpdb->get_results( "SELECT webhook_id, delivery_url FROM {$wpdb->prefix}wc_webhooks WHERE `status` = 'active' AND `delivery_url` LIKE 'https://%.napps-solutions.com/%'" );
    }

    public function get_inactive_webhooks() {
        global $wpdb;
        return $wpdb->get_results( "SELECT webhook_id, delivery_url FROM {$wpdb->prefix}wc_webhooks WHERE `status` != 'active' AND `delivery_url` LIKE 'https://%.napps-solutions.com/%'" );
    }
    
    protected function enable_webhooks_from_napps() {

        if(!class_exists("WC_Webhook")) {
            return;
        }

        $results = $this->get_inactive_webhooks();
        foreach($results as $result)
        {
            $wh = new \WC_Webhook($result->webhook_id);
            $wh->set_status('active');
            $wh->save();
        }

    }

    protected function disable_webhooks_from_napps() {

        if(!class_exists("WC_Webhook")) {
            return;
        }

        $results = $this->get_active_webhooks();
        foreach($results as $result)
        {
            $wh = new \WC_Webhook($result->webhook_id);
            $wh->set_status('paused');
            $wh->save();
        }

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

                $this->create_booking_if_new();
                add_option( 'Activated_NAPPS', true );
            }
        }
    }

    protected function create_booking_if_new() {

        // Check if already have a booking created
        $alreadyHaveBooking = get_option('NAPPS_BOOKING_CREATED');
        if(!$alreadyHaveBooking) {

            $body = [
                'platform'  => 'wordpress',
                'email' => 'info@napps.io',
                'fullName' => 'Wordpress Plugin',
                'phone' => 'Not defined',
                'storeUrl' => get_site_url(),
            ];
            
            $body = wp_json_encode( $body );
            $options = [
                'body' => $body,
                'headers'     => [
                    'Content-Type' => 'application/json',
                ],
                'timeout'     => 5,
            ];
            $response = wp_remote_post("https://master.napps-solutions.com/contact", $options);
            $isOk = wp_remote_retrieve_response_code($response) == "200";
            if($isOk) {
                add_option('NAPPS_BOOKING_CREATED', "1");
            }
        }
    }

    /**
    *   Try to get valid secret key
    *   If we did not found one, we are gonna 
    *   create a jwt secret key and save it to database options table
    *
    *   @return bool
    */
    protected function check_napps_key_is_set() {

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
    protected function bail_on_activation() {
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