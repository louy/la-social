<?php
/**
 * OAuth script for WordPress
 * @author Louy Alakkad <louy08@gmail.com>
 * @website http://l0uy.com/
 */
if( !defined( 'WP_OAUTH' ) ) :

define('WP_OAUTH', true);

/**
 * Don't forget to call oauth_activate() when you activate your plugin.
 *  just to make sure rewrite rules will be flushed.
 */

add_action('init', 'oauth_init');
function oauth_init() {
	global $wp, $oauth_activate;

	add_rewrite_rule('oauth/(.+)/?$', 'index.php?oauth=$matches[1]',1);
	add_rewrite_rule('oauth/?', 'index.php?oauth=',1);

	$wp->add_query_var('oauth');
}

add_action('template_redirect', 'oauth_template_redirect');
function oauth_template_redirect() {
	if( get_query_var('oauth') ) {
		$site = explode('/',get_query_var('oauth'));
		$site = $site[0];
		do_action('oauth_start_'.$site);

		// Nothing happened!?
		do_action('wp_oauth_unknown_site');
		wp_die( __('OAuth site not recognized!') );
	}
}

function oauth_link($site,$args=array()){
	global $wp_rewrite;

	$link = get_bloginfo('url');
	if( $wp_rewrite->using_permalinks() ) {
		$link .= '/oauth/' . $site;
	} else {
		$link = add_query_arg(array(
			'oauth' => $site
		), $link . '/');
	}
	$link = add_query_arg($args, $link);

	return $link;
}

function oauth_activate() {
	delete_option('rewrite_rules');
}

endif;
