<?php

class LA_Social_Comments extends LA_Social_Module {
	function module_options_defaults() {
		return array(
			'allow_comment_login' => false,
			// 'comment_share_text' => '',
		);
	}

	function hooks() {
		parent::hooks();
		add_action('admin_notices', array( $this, 'admin_notices' ) );
		add_action( $this->prefix() . '_register_options', array( $this, 'register_options' ), 12, 2 );

		if( $this->parent->option('allow_comment_login') ) {
			add_action( 'wp_ajax_nopriv_' . $this->ajax_hook(), array( $this, 'ajax_get_display' ) );
			add_filter( 'pre_comment_on_post', array( $this, 'pre_comment_on_post' ) );

			add_action( 'wp_footer', array( $this, 'wp_footer' ) );
			add_action( 'init', array( $this, 'maybe_logout' ) );
			add_action( 'comment_post', array( $this, 'add_comment_meta' ) );
			add_action( 'alt_comment_login', array( $this, 'alt_comment_login_button' ) );
		}

		add_filter( 'get_avatar', array( $this, 'filter_avatar' ), 10, 5 );
	}

	function maybe_logout() {
		if( isset( $_GET[ $this->prefix() . '-logout'] ) ) {
			session_unset();
			wp_redirect( remove_query_arg( $this->prefix() . '-logout', $this->parent->get_current_url() ) );
			exit;
		}
	}

	function ajax_hook() {
		return 'get_commenter_display';
	}

	function admin_notices() {
		if ( get_option( 'comment_registration' ) && fp_options('allow_comments') ) {
			echo "<div class='error'><p>" . esc_html( sprintf( __("%s Comment function doesn't work with sites that require registration to comment.", 'la-social'), $this->parent->name() ) ) . '</p></div>';
		}
	}

	function register_options( $page, $options_group ) {
		$section = $this->prefix() . '_options_comments';
		add_settings_section( $section, __('Comments', 'la-social'), array( $this, 'section_callback' ), $page );

		foreach( array(
			array(
				'name' => 'allow_comment_login',
				'label' => sprintf( __('Allow %s users to comment', 'la-social'), $this->api_name() ),
				'type' => 'checkbox',
			),
			// array(
			// 	'name' => 'comment_share_text',
			// 	'label' => __('Comment share text'),
			// ),
		) as $field ) {
			$field['options_group'] = $options_group;
			$field['id'] = $this->prefix() . '-' . $field['name'];

			add_settings_field( $field['id'], $field['label'], array( $this->parent, 'settings_field' ), $page, $section, $field );
		}
	}

	function section_callback() {
		echo '<p>' . sprintf( __('Allow %s users to comment with their accounts.', 'la-social'), $this->api_name() ) . '</p>';
	}

	function sanitize_options( $options ) {
		$options['allow_comment_login'] = isset( $options['allow_comment_login'] );
		return $options;
	}

	function ajax_get_display() {
		header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");

		if( !session_id() ) {
			session_start();
		}

		if( @$_SESSION['comment_user_service'] !== $this->api_slug() ) {
			return;
		}

		$social_user = $this->parent->get_social_user();

		$image_size = apply_filters('alt_login_image_size', 50);

		if( $social_user ) {
			echo '<div class="social-user social-user-', $this->prefix(), '">',
					'<img src="', $social_user['image'], '" width="' . $image_size . '" height="' . $image_size . '" class="social-avatar avatar" />',
					'<h3>', esc_html( sprintf(__('Hi %s!', 'la-social'), $social_user['name'] ) ), '</h3>',
					'<p>', sprintf( __('You are connected with your %s account.', 'la-social'), $this->parent->api_name() ), ' ',
						apply_filters( $this->prefix() . '_user_logout','<a href="?' . $this->prefix() . '-logout" class="social-logout">' . __('Logout', 'la-social') . '</a>' ),
					'</p>',
				'</div>';
			exit;
		}
	}

	function wp_footer() {
		if( !is_singular() || is_user_logged_in() || !comments_open() ) {
			return;
		}

		if( defined('LA_SOCIAL_COMMENTS_SCRIPT_SHOW') ) {
			return;
		}
		define('LA_SOCIAL_COMMENTS_SCRIPT_SHOW', true);

		$ajax_url = preg_replace( '/^https?\:/', '', admin_url("admin-ajax.php") );
		?>
		<script>
		/* @nominify */var ajax_url = '<?php echo $ajax_url; ?>';
		</script>
		<script>
			jQuery(function($) {
				if( !$('#alt-comment-login').size() ) {
					return;
				}
				var data = { action: '<?php echo $this->ajax_hook(); ?>' };
				$.post(ajax_url, data, function(response) {
					if (response != '0') {
						$('#alt-comment-login, #respond .comment-notes').hide();
						$('#comment-user-details').hide().after(response);
					}
				});
			});
		</script>
		<?php
	}

	function pre_comment_on_post( $comment_post_ID ) {
		if (is_user_logged_in()) return; // do nothing to WP users

		if( @$_SESSION['comment_user_service'] !== $this->api_slug() ) {
			return;
		}

		$social_user = $this->parent->get_social_user();

		if( $social_user ) {
			$_POST['author'] = $social_user['name'];
			$_POST['url'] = $social_user['url'];
			$_POST['email'] = $social_user['email'];
		}
	}

	function add_comment_meta( $comment_id ) {
		$social_user = $this->parent->get_social_user();

		if( $social_user ) {
			update_comment_meta($comment_id, $this->prefix() . '_uid', $social_user['id']);
		}
	}

	function alt_comment_login_button() {
		echo '<p id="' . $this->prefix() . '-connect">' . $this->parent->get_connect_button( 'comment', 'permissions=email' ) . '</p>';
	}

	function filter_avatar( $avatar, $id_or_email, $size = '96', $default = '', $alt = false ) {
		// check to be sure this is for a comment
		if ( !is_object($id_or_email) || !isset($id_or_email->comment_ID) || $id_or_email->user_id)
			 return $avatar;

		$userid = get_comment_meta( $id_or_email->comment_ID, $this->prefix() . '_uid', true );

		if( $userid ) {
			$avatar = $this->parent->get_avatar( $userid, $size, $default, $alt );

			return apply_filters( $this->prefix() . '_comment_avatar', $avatar, $userid, $id_or_email, $size, $default, $alt );
		}

		return $avatar;
	}
}


if( !function_exists('alt_comment_login') ) {
	function alt_comment_login() {
		echo '<div id="alt-comment-login">';
		do_action('alt_comment_login');
		echo '</div>';
	}
	function comment_user_details_begin() { echo '<div id="comment-user-details">'; }
	function comment_user_details_end() { echo '</div>'; }

    add_action( 'comment_form_before_fields', 'comment_user_details_begin', 2  );
    add_action( 'comment_form_after_fields' , 'comment_user_details_end'  , 99 );
    add_action( 'comment_form_before_fields', 'alt_comment_login'         , 1  );
}
