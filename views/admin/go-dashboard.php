<?php
/**
 * Admin View: Napps - Getting started
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="flex flex-col bg-white items-center napps-font min-h-screen">

    <img class="w-full" src="<?php echo esc_url(NAPPS_PLUGIN_DIR_URL) ?>/public/images/napps-home-logo.jpg" />

    <div class="flex flex-col items-center started-outter">
        <h1><?php _e('Go Dashboard Title', 'napps') ?></h1>
        <p class="text-center"><?php _e('Go Dashboard Description', 'napps') ?></p>

        <a href="https://napps.io" target="_blank" class="napps-button go-dashboard-button font-medium"><?php _e('Go Dashboard Button', 'napps') ?></a>
    </div>

</div>