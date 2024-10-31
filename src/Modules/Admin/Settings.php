<?php

namespace NAPPS\Modules\Admin;

use NAPPS\ActivationService;

class Settings
{
    public function __construct()
    {
        add_filter( 'plugin_action_links_' . NAPPS_PLUGIN_BASENAME, array($this, 'add_settings_link') );
        add_action('admin_menu', array($this, 'setup_menu'));
    }

    public function add_settings_link($actions) 
    {
        $action = array(
            '<a href="' . admin_url( 'admin.php?page=napps-home' ) . '">' . __( 'Settings', 'napps' ) . '</a>'
        );
        return array_merge($action, $actions);
    }

    protected function get_svg() 
    {
        $svg = file_get_contents( NAPPS_PLUGIN_DIR . 'public/images/nLogo.svg' );
        $svg = "data:image/svg+xml;base64," . base64_encode( $svg );
        return $svg;
    }

    public function setup_menu() {
        add_menu_page( __('NAPPS', 'napps'), __('NAPPS', 'napps'), 'manage_options', sanitize_key('napps-home'), array($this, 'get_home'), $this->get_svg(), 59 );
    }

    public function get_home() {

        wp_enqueue_style('napps-css', NAPPS_PLUGIN_DIR_URL . 'public/css/napps.css', array(), NAPPS_VERSION, 'all');

        $isShopActive = count(ActivationService::instance()->get_active_webhooks()) > 0;
        if($isShopActive) {
            include_once NAPPS_PLUGIN_DIR . '/views/admin/go-dashboard.php';
            return;
        }


        $websiteUrl = get_site_url();
        $email = get_option('admin_email');
        $currentUser = wp_get_current_user();
        $firstName = $email;
        if($currentUser->id != 0) {
            $firstName = $currentUser->user_firstname;
        }

        $gettingStartedButton = "https://master.napps-solutions.com/auth/register?email=%s&shopOwner=%s&websiteUrl=%s";
        $gettingStartedButton = sprintf($gettingStartedButton, $email, $firstName, $websiteUrl);
        include_once NAPPS_PLUGIN_DIR . '/views/admin/getting-started.php';
    }
}