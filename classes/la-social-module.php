<?php
abstract class LA_Social_Module {
	protected $parent;
	function __construct( LA_Social $parent ) {
		$this->parent = $parent;
		$this->hooks();
	}

	function prefix() {
		return $this->parent->prefix();
	}
	function api_slug() {
		return $this->parent->api_slug();
	}
	function name() {
		return $this->parent->name();
	}
	function api_name() {
		return $this->parent->api_name();
	}

	function module_options_defaults() {
		// @implement
		return array(
		);
	}
	function hooks() {
		// @implement
		add_filter( $this->prefix() . '_sanitize_options', array( $this, 'sanitize_options' ) );
		add_filter( $this->prefix() . '_get_options', array( $this, 'get_options' ) );
	}

	function sanitize_options( $options ) {
		// @implement
		return $options;
	}

	function get_options( $options ) {
		return wp_parse_args( $options, $this->module_options_defaults() );
	}
}
