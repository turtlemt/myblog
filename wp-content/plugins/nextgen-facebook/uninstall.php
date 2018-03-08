<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

$plugin_dir = trailingslashit( dirname( __FILE__ ) );
$plugin_filepath = $plugin_dir.'nextgen-facebook.php';

require_once $plugin_dir.'lib/config.php';

NgfbConfig::set_constants( $plugin_filepath );
NgfbConfig::require_libs( $plugin_filepath );	// includes the register.php class library
NgfbRegister::network_uninstall();

