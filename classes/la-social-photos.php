<?php
class LA_Social_Photos extends LA_Social_Module {
	protected $table;
	protected $db_version_key;
	function module_options_defaults() {
		return array(
			'photo_age' => false,
		);
	}

	function db_setup() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table} (
			uid varchar(255) NOT NULL,
			url varchar(55) DEFAULT '' NOT NULL,
			time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			UNIQUE KEY uid (uid)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		update_option( $this->db_version_key, LA_SOCIAL_VERSION );
	}

	function update_photo_for_user($uid, $url) {
		global $wpdb;

		$wpdb->replace(
			$this->table,
			array(
				'uid' => $uid,
				'url' => $url,
				'time' => current_time( 'mysql' ),
			)
		);
	}

	function grab_photo($uid, $fallback = false) {
		$photo = $this->parent->get_photo_for_user( $uid );

		if( !$photo ) {
			$photo = $fallback;
		} else {
			$this->update_photo_for_user( $uid, $photo );
		}

		return $photo;
	}

	function get_photo_url($uid, $size) {
		global $wpdb;

		$url = $wpdb->get_var( $wpdb->prepare(
			"SELECT url FROM {$this->table} WHERE uid = %s", $uid
		) );

		if( !$url ) {
			$url = $this->grab_photo($uid);
		}

		$url = apply_filters( $this->prefix() . '_get_photo_url', $url, $uid, $size );

		return $url;
	}

	function hooks() {
		global $wpdb;

		$this->table = $wpdb->prefix . $this->api_slug() . "_photos";
		$this->db_version_key = $wpdb->prefix . $this->prefix() . "_db_version";

		parent::hooks();

		if( get_option( $this->db_version_key ) != LA_SOCIAL_VERSION ) {
			$this->db_setup();
		}

		add_action( $this->prefix() . '_register_options', array( $this, 'register_options' ), 12, 2 );

		add_filter( 'get_avatar', array( $this, 'filter_avatar' ), 10, 5 );
	}

	function register_options( $page, $options_group ) {
		$section = $this->prefix() . '_options_comments';

		foreach( array(
			array(
				'name' => 'photo_age',
				'label' => __('Photo Maximum Age (in seconds)'),
				'type' => 'number',
			),
		) as $field ) {
			$field['options_group'] = $options_group;
			$field['id'] = $this->prefix() . '-' . $field['name'];

			add_settings_field( $field['id'], $field['label'], array( $this->parent, 'settings_field' ), $page, $section, $field );
		}
	}

	function sanitize_options( $options ) {
		$options['photo_age'] = $options['photo_age'] > 0 ? intval($options['photo_age']) : 0;
		return $options;
	}

	function filter_avatar( $avatar, $id_or_email, $size = '96', $default = '', $alt = false ) {
		// check to be sure this is for a comment
		if ( !is_object($id_or_email) || !isset($id_or_email->comment_ID) || $id_or_email->user_id)
			return $avatar;

		$uid = get_comment_meta( $id_or_email->comment_ID, $this->prefix() . '_uid', true );

		if( $uid ) {
			$avatar = $this->get_photo_url( $uid, $size );

			return apply_filters( $this->prefix() . '_comment_avatar', $avatar, $uid, $id_or_email, $size, $default, $alt );
		}

		return $avatar;
	}
}
