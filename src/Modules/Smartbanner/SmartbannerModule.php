<?php

namespace NAPPS\Modules\Smartbanner;

use NAPPS\Contracts\ModuleImplementation;

class SmartbannerModule implements ModuleImplementation {

    private $banner_script;
    public function __construct()
    {
        if ( is_admin() || napps_doing_ajax() ||  napps_doing_cron())
        {
            return;
        }

        add_action( 'template_redirect', array( $this, 'check_show_smartbanner' ) );

    }

    /**
     * Check if customer is not on payment page before adding actions to show smartbanner
     *
     * @return void
     */
    public function check_show_smartbanner() {

        $this->banner_script = get_option('napps_banner_script');

        $showSmartbanner = true;
        if (isset($_COOKIE['napps_mobile']) && $_COOKIE['napps_mobile'] == 'true') {
            $showSmartbanner = false;
        }
        
        if($showSmartbanner) {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        }

    }

    /**
     * Enqueue custom script
     *
     * @return void
     */
    public function enqueue_scripts()
    {
        if (!$this->banner_script) {
            return;
        }

        if (filter_var($this->banner_script, FILTER_VALIDATE_URL) === FALSE) {
            return ;
        }

        
        //Scripts
        wp_enqueue_script('napps-banner', $this->banner_script, array(), NAPPS_VERSION, false);
    }

}