<?php

namespace NAPPS;

use NAPPS\Utils\SingletonTrait;

class I18n {

    use SingletonTrait;

    public function init() {

        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

    }

    public function load_textdomain() {

        load_plugin_textdomain( 'napps', false, dirname( NAPPS_PLUGIN_BASENAME ) . '/lang' );

    }

}