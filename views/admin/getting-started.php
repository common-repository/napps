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
        <h1><?php _e('Getting started title', 'napps') ?></h1>
        <p class="text-center"><?php _e('Getting started description', 'napps') ?></p>

        <a 
            target="_blank"
            href="<?php echo esc_url($gettingStartedButton); ?>" 
            class="napps-button get-started-button font-medium"
        >
            <?php _e('Getting started button', 'napps') ?>
        </a>
    </div>

</div>