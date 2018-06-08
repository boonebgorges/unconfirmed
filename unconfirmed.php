<?php
/**
 * Plugin Name: Unconfirmed
 * Plugin URI: http://github.com/boonebgorges/unconfirmed
 * Description: Allows admins on a WordPress Multisite network to manage unactivated users, by either activating them manually or resending the activation email.
 * Author: Boone B Gorges
 * Author URI: http://boone.gorg.es
 * License: GPLv3
 * Version: 1.3
 */

/**
 * Initialise the plugin.
 *
 * @return BBG_Unconfirmed Main plugin class instance.
 */
function bbg_unconfirmed() {
	global $bbg_unconfirmed;

	if ( empty( $bbg_unconfirmed ) ) {
		require_once __DIR__ . '/class-bbg-unconfirmed.php';

		$bbg_unconfirmed = new BBG_Unconfirmed;
	}

	return $bbg_unconfirmed;
}
add_action( 'plugins_loaded', 'bbg_unconfirmed' );
