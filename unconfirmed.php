<?php

/*
Plugin Name: Unconfirmed
Plugin URI: http://github.com/boonebgorges/unconfirmed
Description: Allows admins on a WordPress Multisite network to manage unactivated users, by either activating them manually or resending the activation email.
Author: Boone B Gorges
Author URI: https://boone.gorg.es
License: GPLv3
Version: 1.3.5
Text Domain: unconfirmed
Domain Path: /languages/
*/

define( 'UNCONFIRMED_PLUGIN_DIR', dirname( __FILE__ ) );

/**
 * Plugin loader.
 */
function BBG_Unconfirmed() {
	global $bbg_unconfirmed;

	require_once UNCONFIRMED_PLUGIN_DIR . '/includes/class-bbg-unconfirmed.php';

	if ( empty( $bbg_unconfirmed ) ) {
		$bbg_unconfirmed = new BBG_Unconfirmed();
	}

	return $bbg_unconfirmed;
}
add_action( 'plugins_loaded', 'BBG_Unconfirmed' );
