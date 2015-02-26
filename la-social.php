<?php
/*
Plugin Name: LA Social
Description: Integrates your WordPress blog with social networks like Facebook, Twitter and Google+.
Author: Louy Alakkad
Version: 1.0
Author URI: http://l0uy.com/
Text Domain: la-social
Domain Path: /languages
*/

/*
if you want to force the plugin to use some settings, you can add these to your wp-config.php
*/

//define('TWITTER_CONSUMER_KEY', 'EnterYourKeyHere');
//define('TWITTER_CONSUMER_SECRET', 'EnterYourSecretHere');

//define('FACEBOOK_APP_ID', 'EnterYourAppIDHere');
//define('FACEBOOK_APP_SECRET', 'EtnterYourSecretHere');
//define('FACEBOOK_FANPAGE', 'EnterYourPageIDHere');

//define('GOOGLE_CLIENT_ID', 'EnterYourIDHere');
//define('GOOGLE_CLIENT_SECRET', 'EnterYourSecretHere');

// Load translations
load_plugin_textdomain( 'la-social', false, dirname( plugin_basename( __FILE__ ) ) . '/po/' );

define( 'LA_SOCIAL_VERSION', '1.0' );
define( 'LA_SOCIAL_PHP_VERSION_REQUIRED', '5.4.0' );

function la_social_activate(){
	// require PHP 5
	if( version_compare(PHP_VERSION, LA_SOCIAL_PHP_VERSION_REQUIRED, '<')) {
		deactivate_plugins(basename(__FILE__)); // Deactivate ourself
		wp_die( sprintf( __("Sorry, LA Social requires PHP %1$s or higher. Ask your host how to enable PHP %1$s as the default on your servers.", 'tp', 'la-social'), LA_SOCIAL_PHP_VERSION_REQUIRED ) );
	}
}
register_activation_hook(__FILE__, 'la_social_activate');

if( version_compare(PHP_VERSION, LA_SOCIAL_PHP_VERSION_REQUIRED, '>=') ) {
	require_once dirname(__FILE__) . '/vendor/autoload.php';
	require_once dirname(__FILE__) . '/classes/la-social.php';
	require_once dirname(__FILE__) . '/fp-social.php';
	require_once dirname(__FILE__) . '/tp-social.php';
	require_once dirname(__FILE__) . '/gp-social.php';
	global $fp, $tp, $gp;
	$fp = FP_Social::get_instance(__FILE__);
	$tp = TP_Social::get_instance(__FILE__);
	$gp = GP_Social::get_instance(__FILE__);
}
