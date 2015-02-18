<?php
/*
Plugin Name: FacePress
Description: All the tools you need to integrate your wordpress and facebook.
Author: Louy
Version: 2.0
Author URI: http://l0uy.com/
Text Domain: fp
Domain Path: /po
*/
/*
if you want to force the plugin to use app id and secret and/or fanpage,
 add your info and uncomment the following three lines:
*/

//define('FACEBOOK_APP_ID', 'EnterYourAppIDHere');
//define('FACEBOOK_APP_SECRET', 'EtnterYourSecretHere');
//define('FACEBOOK_FANPAGE', 'EnterYourPageIDHere');
//define('FACEBOOK_DISABLE_LOGIN', true);

// Load translations
load_plugin_textdomain( 'fp', false, dirname( plugin_basename( __FILE__ ) ) . '/po/' );

define( 'FP_VERSION', '2.0' );
define( 'FP_PHP_VERSION_REQUIRED', '5.4.0' );

function fp_activate(){
	// require PHP 5
	if( version_compare(PHP_VERSION, FP_PHP_VERSION_REQUIRED, '<')) {
		deactivate_plugins(basename(__FILE__)); // Deactivate ourself
		wp_die( sprintf( __("Sorry, FacePress requires PHP %1$s or higher. Ask your host how to enable PHP %1$s as the default on your servers.", 'tp'), FP_PHP_VERSION_REQUIRED ) );
	}
}
register_activation_hook(__FILE__, 'fp_activate');

if( version_compare(PHP_VERSION, FP_PHP_VERSION_REQUIRED, '>=') ) {
	require_once dirname(__FILE__) . '/fp-social.php';
	global $fp;
	$fp = FP_Social::get_instance(__FILE__);
}
