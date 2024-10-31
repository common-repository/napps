<?php
namespace NAPPS;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

use WP_REST_Request;
use WP_REST_Response;

/**
 * Setup Smart Banner
 *
 * @package napps
 */
if (!class_exists('NappsSmartBanner')) {

    class NappsSmartBanner
    {
        private $initiated = false;
        private $options;

        public function __construct()
        {
            if (!$this->initiated) {
                $this->init_hooks();
            }
        }

        /**
         * Initializes WordPress hooks
         */
        private function init_hooks()
        {
            if ( is_admin() || napps_doing_ajax() ||  napps_doing_cron())
                return;

            add_action( 'template_redirect', array( $this, 'check_show_smartbanner' ) );
        }
        
        /**
         * Check if customer is not on payment page before adding actions to show smartbanner
         *
         * @return void
         */
        public function check_show_smartbanner() {

            $this->initiated = true;
            $this->options = get_option('nappsSmartAppBanner');

            $showSmartbanner = true;

            if (isset($_COOKIE['napps_mobile']) && $_COOKIE['napps_mobile'] == 'true') {
                $showSmartbanner = false;
            }

            if($showSmartbanner) {
                
                //Hooks
                add_action('wp_head', array($this, 'add_meta'));
                add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
                add_action('wp_footer', array($this, 'wp_footer'));
            }

        }
        
        /**
         * Generate manifest from options
         *
         * @param  mixed $generateManifestFromOption
         * @return array
         */
        private function generateManifestFromOption($generateManifestFromOption = null) {
            
            $options = $generateManifestFromOption;
            if(!$options) {
                $options = $this->options;
            }

            $manifest = array(
                "name" => "",
                "short_name" => "",
                "icons" => [
                    array(
                        "src" => "launcher-icon-4x.png",
                        "sizes" => "192x192",
                        "type" => "image/png"
                    )
                ],
                "prefer_related_applications" => true,
                "related_applications" => [
                    "platform" => "play",
                    "id" => "",
                ],
                "start_url" => "",
                "display" => "standalone"
            );

            if (!empty($options)) {
                $current_option = $options;

                if (!empty($current_option['appTitle'])) {
                    $manifest["name"] = $current_option["appTitle"];
                }

                if (!empty($current_option['appDesc'])) {
                    $manifest["short_name"] = $current_option["appDesc"];
                }

                if (array_key_exists("appIcon", $current_option) && !empty($current_option['appIcon'])) {
                    for($i = 0; $i < count($manifest["icons"]); $i++) {
                        $manifest["icons"][$i]["src"] = $current_option["appIcon"];
                    }
                }

                
                if (!empty($current_option['androidAppUrl'])) {
                    $androidUrl = esc_url($current_option['androidAppUrl']);
                    if(preg_match("/id=(.*)/", $androidUrl, $match)) {
                        $androidId = $match[1];
                        $manifest["related_applications"]["id"] = $androidId;
                    }

                    
                }
                $manifest["start_url"] = site_url();
            }

            return $manifest;
        }
        
        /**
         * REST api to return manifest for our native application install
         *
         * @param  WP_REST_Request $request
         * @return void
         */
        public function manifest( WP_REST_Request $request ) {

            $manifest = get_transient("napps_smartbanner_manifest");
            if(!$manifest) {
                $manifest = $this->generateManifestFromOption();
            }

            wp_send_json($manifest);
        }

        
        /**
         * Auth (admin) post in order to update smartbanner info when application goes live
         *
         * @param  WP_REST_Request $request
         * @return WP_REST_Response
         */
        public function smartbanner_info( WP_REST_Request $request ) {

            $user = get_current_user_id();
			if(!$user || !current_user_can("manage_woocommerce")) {
				return new WP_REST_Response(null, 401);
			}
            
            $apple_app_url    = $request->get_param( 'appleAppUrl' );
			$android_app_url    = $request->get_param( 'androidAppUrl' );
            $app_title    = $request->get_param( 'appTitle' );
            $app_desc    = $request->get_param( 'appDesc' );

            $nappsSmartAppBanner = array(
                "appTitle" => sanitize_text_field($app_title),
                "appDesc" => sanitize_text_field($app_desc),
                "appIcon" => '',
                "appleAppUrl" => sanitize_text_field($apple_app_url),
                "androidAppUrl" => sanitize_text_field($android_app_url),
            );

            if(isset($_FILES['icon']) && $this->validFile($_FILES['icon'])) {

                if ( ! function_exists( 'media_handle_upload' ) ) {
                    require_once( ABSPATH . 'wp-admin/includes/image.php' );
                    require_once( ABSPATH . 'wp-admin/includes/file.php' );
                    require_once( ABSPATH . 'wp-admin/includes/media.php' );
                }

                // Use the wordpress function to upload
                // 0 means the content is not associated with any other posts
                $uploaded = media_handle_upload('icon', 0);

                // Error checking using WP functions
                if(is_wp_error($uploaded) || !$uploaded) {
                    return new WP_REST_Response(null, 500);
                } else {
                    $image = wp_get_attachment_image_url($uploaded);
                    $nappsSmartAppBanner["appIcon"] = $image;
                }
            }

            $saved = update_option('nappsSmartAppBanner', $nappsSmartAppBanner);
            if(!$saved) {
                return new WP_REST_Response(null, 500);
            }

            set_transient("napps_smartbanner_manifest", $this->generateManifestFromOption($nappsSmartAppBanner));

            return new WP_REST_Response(null, 200);
        }
        
        /**
         * Add smartbanner load component to footer
         *
         * @return void
         */
        public function wp_footer()
        {
            ?>
            <script type="text/javascript">

                document.addEventListener('smartbanner.view', function() { });
                document.addEventListener('smartbanner.exit', function() { });
                document.addEventListener('smartbanner.clickout', function() { });
                let apiHandler = function() { 
                    let content = document.querySelector('meta[name="smartbanner:api"]')
                    if(content) {
                        smartbanner.publish(); 
                    }
                };
                window.onload = apiHandler;
            </script>
            <?php
        }

        /**
         * Enqueue custom script
         *
         * @return void
         */
        public function enqueue_scripts()
        {
            if (!$this->validOptions()) {
                return;
            }
            
            //Styles
            wp_enqueue_style('smartbanner-css', NAPPS_PLUGIN_DIR_URL . 'public/css/smartbanner.min.css', array(), NAPPS_VERSION, 'all');
            wp_enqueue_style('napps-css', NAPPS_PLUGIN_DIR_URL . 'public/css/napps.css', array(), NAPPS_VERSION, 'all');
            
            //Scripts
            wp_enqueue_script('smartbanner-js', NAPPS_PLUGIN_DIR_URL . 'public/js/smartbanner.min.js', array(), NAPPS_VERSION, false);
        }

        /**
         * Adding the meta tag for android and ios app
         *
         * @return void
         */
        public function add_meta()
        {
            // Check if we have valid options (Title, desc, etc) before adding meta tags to the dom
            if ($this->validOptions()) {

                $current_option = $this->options;

                ?>
                    <!-- Start Napps Smart App Banner configuration -->
                    <meta name="smartbanner:api" content="true">
                    <meta name="smartbanner:title" content="<?php esc_attr_e($current_option['appTitle']) ?>">
                    <meta name="smartbanner:author" content="<?php esc_attr_e($current_option['appDesc']) ?>">
                    <meta name="smartbanner:icon-apple" content="<?php echo esc_url($current_option['appIcon']) ?>">
                    <meta name="smartbanner:icon-google" content="<?php echo esc_url($current_option['appIcon']) ?>">
                    <meta name="smartbanner:price" content="<?php _e('FREE', 'napps') ?>">
                    <meta name="smartbanner:price-suffix-apple" content="<?php _e(' - On the App Store', 'napps') ?>">
                    <meta name="smartbanner:price-suffix-google" content="<?php _e(' - In Google Play', 'napps') ?>">
                    <meta name="smartbanner:button" content="<?php _e('VIEW', 'napps') ?>"> 
                    <meta name="smartbanner:close-label" content="<?php _e('Close', 'napps') ?>">
                <?php

                $platforms = [];
                // If we have a apple app url
                if (!empty($current_option['appleAppUrl'])) {
                    
                    array_push($platforms, 'ios');
                    $apple_url = esc_url($current_option['appleAppUrl']);
                    if(preg_match("/id(\d+)/", $apple_url, $match)) {
                        $apple_ID = $match[1];
                    }

                    if (isset($apple_ID)) {
                        ?>
                        <!-- Start Smart banner app for Safari on iOS configuration -->
                        <meta name="apple-itunes-app" content="app-id='<?php esc_attr_e($apple_ID) ?>">
                        <!-- End Smart banner app for Safari on iOS configuration -->
                        <?php
                    }

                    ?> <meta name="smartbanner:button-url-apple" content="<?php echo esc_url($current_option['appleAppUrl']) ?>"> <?php
                }

                // If we have a android app url
                if (!empty($current_option['androidAppUrl'])) {

                    array_push($platforms, 'android');
                    ?> <meta name="smartbanner:button-url-google" content="<?php echo esc_url($current_option['androidAppUrl']) ?>"> <?php

                }

                if(count($platforms) > 0) {
                    ?> <meta name="smartbanner:enabled-platforms" content="<?php echo implode(',', $platforms) ?>"> <?php
                }
                
                ?><!-- End Napps Smart App Banner configuration --><?php
            } 
        }
        
        /**
         * Check if a file is valid
         *
         * @param  File $file
         * @return boolean
         */
        private function validFile($file) {
            if(!isset($file) || !isset($file['name'])) {
                return false;
            }

            $fileNameSplit = explode('.',$file['name']);
            $file_ext = end($fileNameSplit);

            // Check valid extension
            $file_ext = strtolower($file_ext);
            $extensions = array("jpeg","jpg","png");
            if(in_array($file_ext,$extensions) === false){
                return false;
            }

            return true;
        }
        
        /**
         * Check if options has necessary data to show smartbanner
         *
         * @return boolean
         */
        private function validOptions() {
            return !empty($this->options) && 
                !empty($this->options['appTitle']) && 
                !empty($this->options['appDesc']) && 
                !empty($this->options['appIcon']) &&
                (!empty($this->options['appleAppUrl']) || !empty($this->options['androidAppUrl']));
        }
    }
}