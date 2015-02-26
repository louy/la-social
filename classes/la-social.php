<?php
require_once dirname(__FILE__) . '/wp-oauth.php';
require_once dirname(__FILE__) . '/la-social-module.php';
require_once dirname(__FILE__) . '/la-social-comments.php';

if( !session_id() ) {
	session_start();
}
abstract class LA_Social {
	// supply your own in child class
	function prefix() {
		// @implement
		return 'my_app';
	}

	// api slig
	function api_slug() {
		// @implement
		return 'myapi';
	}

	// plugin name
	function name() {
		// @implement
		return 'My App';
	}

	// api name
	function api_name() {
		// @implement
		return 'My API';
	}

	protected $modules = array();

	// Those are the config keys that can be added in wp-config.php or in the app config page.
	// If all of these are defined in wp-config. The App Settings page will be hidden.
	function app_configs() {
		return  array(
			// @implement
			// 'MY_APP_KEY'    => 'key',
			// 'MY_APP_SECRET' => 'secret',
		);
	}

	// Those are the app option keys that the plugin wont work without
	function required_app_options() {
		return  array(
			// @implement
			// 'key',
			// 'secret',
		);
	}

	protected $is_active_for_network = false;

	function __construct( $file = null ) {
		if( $file ) {
			register_activation_hook( $file, array( $this, 'activate' ) );
			add_filter( 'plugin_action_links_' . plugin_basename($file), array( $this, 'plugin_action_links' ) );
			$this->is_active_for_network = is_multisite() && is_plugin_active_for_network( plugin_basename( $file ) );
		}
		add_action( 'oauth_start_'. $this->api_slug(), array( $this, 'oauth_start' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		add_action('admin_menu', array( $this, 'admin_menu' ) );
		add_action('network_admin_menu', array( $this, 'network_admin_menu' ) );
	}

	/* Options */
	function app_options_defined() {
		foreach( array_keys( $this->app_configs() ) as $app_option ) {
			if( !defined($app_option) ) {
				return false;
			}
		}
		return true;
	}

	function app_options_defaults() {
		return array_map( '__return_false', array_flip(
			$this->app_configs()
		) );
	}

	function options_defaults() {
		// @implement
		return array();
	}

	function required_app_options_are_set() {
		$options = $this->get_app_options();
		foreach( $this->required_app_options() as $option ) {
			if( empty($options[$option]) ) {
				return false;
			}
		}
		return true;
	}

	function all_app_options_are_defined() {
		foreach( $this->app_configs() as $config => $option ) {
			if( !defined($config) ) {
				return false;
			}
		}
		return true;
	}

	function override_app_options($options) {
		foreach( $this->app_configs() as $config => $option ) {
			if( defined($config) ) {
				$options[$option] = constant($config);
			}
		}
		return $options;
	}

	function get_app_options() {
		$options = get_site_option( $this->prefix() . '_app_options');

		if( !is_array($options) ) {
			add_site_option( $this->prefix() . '_app_options', $options = $this->app_options_defaults() );
		}

		$options = wp_parse_args( $options, $this->app_options_defaults() );
		$options = $this->override_app_options($options);

		return $options;
	}

	function get_options() {
		$options = get_option( $this->prefix() . '_options');

		if( !is_array($options) ) {
			add_option( $this->prefix() . '_options', $options = $this->options_defaults() );
		}

		$options = wp_parse_args( $options, $this->options_defaults() );

		$options = apply_filters( $this->prefix() . '_get_options', $options );

		$options = array_merge( $options, $this->get_app_options() );

		return $options;
	}

	function option($key) {
		$options = $this->get_options();
		return isset( $options[$key] ) ? $options[$key] : null;
	}

	function user_can_edit_options() {
		return current_user_can('manage_options');
	}

	function user_can_edit_app_options() {
		return !$this->all_app_options_are_defined() &&
			( $this->is_active_for_network ? is_super_admin() : current_user_can('manage_options') );
	}

	function options_page_link() {
		return admin_url('options-general.php?page=' . $this->prefix() );
	}

	function app_options_page_link() {
		return $this->is_active_for_network ?
			admin_url('network/settings.php?page=' . $this->prefix() . 'app' ) :
			admin_url('options-general.php?page=' . $this->prefix() . 'app' );
	}

	function admin_menu() {
		add_options_page($this->name(), $this->name(), 'manage_options', $this->prefix(), array( $this, 'options_page' ) );
		if( (! $this->is_active_for_network ) && $this->user_can_edit_app_options() ) {
			add_submenu_page( 'options-general.php', sprintf( __('%s App'), $this->name() ), sprintf( __('%s App'), $this->name() ), 'manage_options', $this->prefix() . 'app', array( $this, 'app_options_page' ) );
		}
	}
	function network_admin_menu() {
		if( $this->is_active_for_network && $this->user_can_edit_app_options() ) {
			add_submenu_page('settings.php', sprintf( __('%s App'), $this->name() ), sprintf( __('%s App'), $this->name() ), 'manage_options', $this->prefix() . 'app', array( $this, 'app_options_page' ) );
		}
	}

	function admin_init() {
		register_setting( $this->prefix() . '_options', $this->prefix() . '_options', array( $this, 'sanitize_options' ) );
		$this->register_settings( sprintf( __('%s Settings'), $this->name() ), 'options' );

		if( $this->app_options_defined() ) {
			return;
		}

		register_setting( $this->prefix() . '_app_options', $this->prefix() . '_app_options', array( $this, 'sanitize_app_options' ) );
		$this->register_settings( sprintf( __('%s App Settings'), $this->name() ), 'app_options' );

        add_filter('pre_update_option_' . $this->prefix() . '_app_options', array( $this, 'pre_update_app_options' ), 10, 2 );
	}

	function register_settings( $title, $options_page = 'options' ) {
		$page = $this->prefix() . '_' . $options_page;
		$section = $this->prefix() . '_' . $options_page . '_section';
		$options_group = $this->prefix() . '_' . $options_page;

		add_settings_section( $section, $title, array( $this, $options_page . '_section_callback' ), $page );

		foreach( call_user_func( array( $this, $options_page . '_section_fields' ) ) as $field ) {
			$field['options_group'] = $options_group;
			$field['id'] = $this->prefix() . '-' . $field['name'];

			add_settings_field( $field['id'], $field['label'], array( $this, 'settings_field' ), $page, $section, $field );
		}

		apply_filters( $this->prefix() . '_register_' . $options_page, $page, $options_group );
	}

	function options_section_fields( $fields = array() ) {
		return apply_filters( $this->prefix() . '_options_fields', $fields );
	}
	function app_options_section_fields( $fields = array() ) {
		return apply_filters( $this->prefix() . '_app_options_fields', $fields );
	}

	function options_section_callback() {
	}
	function app_options_section_callback() {
	}

	abstract function sanitize_options( $options );
	abstract function sanitize_app_options( $app_options );

	function options_page() {
		?>
		<!-- Create a header in the default WordPress 'wrap' container -->
		 <div class="wrap">

			<h2><?php printf( '%s', $this->name() ); ?></h2>

			<form method="post" action="options.php"><?php

				settings_fields( $this->prefix() . '_options' );
				do_settings_sections( $this->prefix() . '_options' );

				submit_button();

			?></form>

		</div><!-- /.wrap -->
		<?php
	}
	function app_options_page() {
		?>
		<!-- Create a header in the default WordPress 'wrap' container -->
		<div class="wrap">

			<h2><?php printf( __('%s App'), $this->name() ); ?></h2>

			<form method="post" action="options.php"><?php

				settings_fields( $this->prefix() . '_app_options' );
				do_settings_sections( $this->prefix() . '_app_options' );

				submit_button();

			?></form>

		</div><!-- /.wrap -->
		<?php
	}

	function pre_update_app_options( $options ) {
		if( $this->is_active_for_network ) {
            update_site_option( $this->prefix() . '_app_options', $options );
            return null;
		}
		return $options;
	}

	function settings_field( $args ) {
		$args = wp_parse_args( $args, array(
			'name' => null,
			'type' => 'text',
			'required' => false,
			'constant' => false,
			'help_text' => false,
			'options_group' => '',
		) );

		$option = $this->option($args['name']);

		$value = ( $args['type'] == 'checkbox' ) ? checked( $option, true, false ) : ' value="' . esc_attr( $option ) . '"';

		$option_name = $args['name'];
		if( $args['options_group'] ) {
			$option_name = $args['options_group'] . '[' . $option_name . ']';
		}

		$required = $args['required'] ? __('(required)', 'fp') : '';

		if( $args['constant'] && defined($args['constant']) ) {
			$args['type'] = 'hidden';
			$required = '';

			_e( 'Already defined in <code>wp-config.php</code>.' );
		}

		$class = $args['type'] === 'text' ? ' class="regular-text code"' : '';

		if( !$option ) {
			echo $args['help_text'];
		}

		echo '<input type="' . $args['type'] . '" name="' . $option_name . '"' . $value . $class . ' />';
	}
	/* /Options */

	/* Hooks */
	function activate() {
		oauth_activate();
	}

	function init() {
	}

	function plugin_action_links($links) {
		$links[] = sprintf( '<a href="%1$s">%2$s</a>',
			admin_url('options-general.php?page=' . $this->prefix() ),
			esc_html( __('Settings') ) );

		if( !$this->app_options_defined() ) {
			$links[] = sprintf( '<a href="%1$s">%2$s</a>',
				admin_url('options-general.php?page=' . $this->prefix() . 'app' ),
				esc_html( __('App Settings') ) );
		}

		return $links;
	}

	function oauth_start() {
		// @implement
		wp_die( 'OAuth Not Implemented.' );
	}

	function admin_notices() {
		if( !$this->required_app_options_are_set() && $this->user_can_edit_app_options() ) {
			printf( '<div class="error"><p>%s</p></div>',
				sprintf(
					__('%s needs to be configured on its <a href="%s">app settings</a> page.'),
					$this->name(),
					admin_url('options-general.php?page=' . $this->prefix() . 'app' )
				)
			);
		}
	}
	/* /Hooks */

	function get_connect_button( $action = '', $args = '' ) {
		$args = wp_parse_args( array(
			'action' => $action,
			'return' => $this->get_current_url(),
		), $args );

		$args = apply_filters( $this->prefix() . '_connect_button_args', $args );

		$template = apply_filters( $this->prefix() . '_connect_button_template',
			'<a href="%1$s" title="%2$s">%2$s</a>', $args );

		return apply_filters( $this->prefix() . '_connect_button',
			sprintf( $template, esc_attr( oauth_link( $this->api_slug(), $args ) ), esc_attr( sprintf( __('Sign in with %s'), $this->api_name() ) ) ),
			$action, $args );
	}

	function get_current_url() {
		$requested_url  = is_ssl() ? 'https://' : 'http://';
		$requested_url .= $_SERVER['HTTP_HOST'];
		$requested_url .= $_SERVER['REQUEST_URI'];
		return $requested_url;
	}

	function get_api_instance() {
		static $instance;
		if( !$instance ) {
			// @implement

			// if( !class_exists('My_API') ) {
			// 	require_once dirname(dirname(__FILE__)) . '/my_api.php';
			// }

			// $instance = new My_API();

			// $instance->set_options();
		}
		return $instance;
	}

	function get_social_user() {
		return false;
		/*
		return array(
			'id' => '0123',
			'name' => 'John Doe',
			'email' => 'john@example.com',
			'url' => 'http://example.com/',
			'image' => 'http://example.com/image.jpg',
		);
		*/
	}

	function get_avatar( $userid, $size = '96', $default = '', $alt = false, $url_only = false ) {
		$size = intval( $size );

		switch( true ) { // size
			case $size <= 50:
				$imgsize = 'small';
				break;
			case $size <= 100:
				$imgsize = 'medium';
				break;
			default:
				$imgsize = 'large';
				break;
		}

		$api = $this->api_slug();

		$src = "http://avatars.io/{$api}/{$userid}?size={$imgsize}";
		$src = apply_filters( $this->prefix() . '_get_avatar_src', $src, $userid, $size, $default );

		if( $url_only ) {
			return $src;
		}

		$avatar = "<img width='{$size}' class='avatar avatar-{$size} {$api}-avatar' src='{$src}' alt='{$alt}' />";

		return apply_filters( $this->prefix() . '_get_avatar', $avatar, $userid, $size, $default, $alt );
	}

	static function get_instance( $file = null ) {
		static $instance;
		if( !$instance ) {
			$class = static::class_name();
			$instance = new $class( $file );
		}
		return $instance;
	}

	public static function class_name() {
		return get_called_class();
	}
}
