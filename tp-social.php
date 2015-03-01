<?php
use Codebird\Codebird;

class TP_Social extends LA_Social {
	function __construct( $file = null ) {
		parent::__construct($file);
		$modules[] = new LA_Social_Comments($this);

		add_filter( $this->prefix() . '_comment_avatar', array( $this, 'comment_avatar' ), 10, 6 );
		add_filter( 'comment_post_redirect', array( $this, 'comment_post_redirect' ) );
	}

	function prefix() {
		return 'tp';
	}
	function api_slug() {
		return 'twitter';
	}
	function name() {
		return __('TweetPress', 'la-social');
	}
	function api_name() {
		return __('Twitter', 'la-social');
	}

	function app_configs() {
		return  array(
			'TWITTER_CONSUMER_KEY'    => 'consumer_key',
			'TWITTER_CONSUMER_SECRET' => 'consumer_secret',
			// 'TWITTER_DISABLE_LOGIN'   => 'disable_login',
		);
	}

	function required_app_options() {
		return  array(
			'consumer_key',
			'consumer_secret',
		);
	}

	function app_options_section_fields( $fields = array() ) {
		$fields[] = array(
			'name' => 'consumer_key',
			'label' => __('Twitter Consumer Key', 'la-social'),
			'required' => true,
			'constant' => 'TWITTER_CONSUMER_KEY',
		);

		$fields[] = array(
			'name' => 'consumer_secret',
			'label' => __('Twitter Consumer Secret', 'la-social'),
			'required' => true,
			'constant' => 'TWITTER_CONSUMER_SECRET',
		);

		// $fields[] = array(
		// 	'name' => 'disable_login',
		// 	'label' => __('Disable Twitter login', 'tp'),
		// 	'required' => true,
		// 	'constant' => 'TWITTER_DISABLE_LOGIN',
		// 	'type' => 'checkbox',
		// );

		return parent::app_options_section_fields($fields);
	}

	function app_options_section_callback() {
		if( !$this->required_app_options_are_set() ) {
			echo '<p>',
				__('To connect your site to Twitter, you will need a Twitter Application. If you have already created one, please insert your Consumer Key and Consumer Secret below.', 'la-social'),
			'</p>';
			echo '<p><strong>',
				esc_html( __("Can't find your key?", 'la-social') ),
			'</strong></p>';
			echo '<ol>',
				'<li>',
					sprintf( __('Get a list of your applications from here: <a target="_blank" href="%s">Twitter Applications List</a>', 'la-social'), 'https://dev.twitter.com/apps/' ),
				'</li>',
				'<li>',
					__('Select the application you want, then copy and paste the Consumer Key and Consumer Secret from there.', 'la-social' ),
				'</li>',
			'</ol>';
			echo '<p><strong>',
				esc_html( __("Haven't created an application yet?", 'la-social') ),
				'</strong> ',
				esc_html( __("Don't worry, it's easy!", 'la-social') ),
			'</p>';
			echo '<ol>',
				'<li>',
					sprintf( __('Go to this link to create your application: <a target="_blank" href="%s">Twitter: Register an Application</a>', 'la-social'), 'https://dev.twitter.com/apps/new' ),
				'</li>',
				'<li><strong>',
					__('Important Settings:', 'la-social' ),
				'</strong></li>',
				'<li>',
					__('Default Access type must be set to "Read and Write".', 'la-social' ),
				'</li>',
				'<li>',
					__('The other application fields can be set up any way you like.', 'la-social' ),
				'</li>',
				'<li>',
					__('After creating the application, copy and paste the Consumer Key and Consumer Secret from the Application Details page.', 'la-social' ),
				'</li>',
			'</ol>';
		}
	}

	function sanitize_options( $options ) {
		unset($options['consumer_key'], $options['consumer_secret']/*, $options['disable_login']*/);

		$options = apply_filters( $this->prefix() . '_sanitize_options', $options );

		return $options;
	}
	function sanitize_app_options( $app_options ) {
		$app_options['consumer_key'] = preg_replace('/[^a-zA-Z0-9]/', '', $app_options['consumer_key']);
		$app_options['consumer_secret'] = preg_replace('/[^a-zA-Z0-9]/', '', $app_options['consumer_secret']);
		// $app_options['disable_login'] = isset( $app_options['disable_login'] );

		return $app_options;
	}

	function get_api_instance() {
		static $instance;
		if( !$instance ) {

			if( !$this->required_app_options_are_set() ) {
				return false;
			}

			$instance = \Codebird\Codebird::getInstance();
			$instance->setConsumerKey( $this->option('consumer_key'), $this->option('consumer_secret') );

		}
		return $instance;
	}

	function oauth_start() {
		$cb = $this->get_api_instance();
		if( $cb === false ) {
			wp_die( __('OAuth is misconfigured.', 'la-social') );
		}

		if( isset($_GET['oauth_token']) ) {

			if( @$_SESSION['tp_oauth_verify'] ) {
				$cb->setToken($_SESSION['tp_oauth_token'], $_SESSION['tp_oauth_token_secret']);
				unset( $_SESSION['tp_oauth_verify'] );

				// get the access token
				$reply = $cb->oauth_accessToken(array(
					'oauth_verifier' => $_GET['oauth_verifier']
				));

				if( $reply->httpstatus != 200 ) {
					$this->oauth_error( 'Error ' . $reply->httpstatus, $reply );
				}

				// store the token (which is different from the request token!)
				$_SESSION['tp_oauth_token'] = $reply->oauth_token;
				$_SESSION['tp_oauth_token_secret'] = $reply->oauth_token_secret;

				$_SESSION['tp_connected'] = true;
			} elseif( !@$_SESSION['tp_connected'] ) {
				wp_die( 'Something wrong happened. Please try again.', 'Unknown Error' );
			}

			$_SESSION['comment_user_service'] = $this->api_slug();

			if( @$_SESSION[ $this->prefix() . '_callback_action' ] ) {
				do_action('fp_action-'.$_SESSION[ $this->prefix() . '_callback_action' ]);
				unset( $_SESSION[ $this->prefix() . '_callback_action' ] ); // clear the action
			}

			if( @$_SESSION[ $this->prefix() . '_callback' ] ) {
				$return_url = remove_query_arg('reauth', $_SESSION[ $this->prefix() . '_callback' ]);
				// unset( $_SESSION[ $this->prefix() . '_callback' ] );
			} else {
				$return_url = get_bloginfo('url');
			}

			// Escape Unicode. Don't ask.
			$return_url = explode('?', $return_url);
			$return_url[0] = explode(':', $return_url[0]);
				$return_url[0][1] = implode('/', array_map( 'rawurlencode', explode('/', $return_url[0][1]) ) );
			$return_url[0] = implode(':', $return_url[0]);
			$return_url = implode('?', $return_url);

			wp_redirect( utf8_encode( $return_url ) );
			exit;

		} elseif( !isset( $_GET['location'] ) && !isset( $_GET['action'] ) ) {
			$this->oauth_error( __('Error: request has not been understood. Please go back and try again.', 'la-social') );
		} else {

			$auth_options = array(
				'is_write' => isset( $_GET['is_write'] ),
				'authorize' => isset( $_GET['authorize'] ),
				'force' => isset( $_GET['force'] ),
			);

			$reply = $cb->oauth_requestToken(array(
			    'oauth_callback' => oauth_link( $this->api_slug() ),
				'x_auth_access_type' => $auth_options['is_write'] ? 'write' : 'read'
			));

			if( $reply->httpstatus != 200 ) {
				$this->oauth_error( 'Error ' . $reply->httpstatus, $reply );
			}

			$cb->setToken($reply->oauth_token, $reply->oauth_token_secret);

			$_SESSION['tp_oauth_token'] = $reply->oauth_token;
			$_SESSION['tp_oauth_token_secret'] = $reply->oauth_token_secret;
			$_SESSION['tp_oauth_verify'] = true;
			unset( $_SESSION['tp_connected'] );

			$auth_url = $auth_options['authorize'] ? $cb->oauth_authorize( $auth_options['force'] ) : $cb->oauth_authenticate( $auth_options['force'] );

			if( isset( $_GET['return'] ) ) {
				$_SESSION[ $this->prefix() . '_callback' ] = $_GET['return'];
			}
			if( isset( $_GET['action'] ) ) {
				$_SESSION[ $this->prefix() . '_callback_action' ] = $_GET['action'];
			}

			wp_redirect($auth_url);
			exit;
		}
	}

	function oauth_error( $message, $object = null ) {
		wp_die(
			( !empty( $message ) ? print_r($message,true) : 'Unknown Twitter API Error.' ) . "\n" .
			( WP_DEBUG ? '<pre style="overflow:scroll; direction: ltr; background: #efefef; padding: 10px;">' . esc_html( print_r( $object, true ) ) . '</pre>'
				 : '' )
			, 'Twitter OAuth Error' );
	}

	function get_social_user() {
		if( !@$_SESSION['tp_connected'] ) {
			return false;
		}

		$cb = $this->get_api_instance();
		if( $cb === false ) {
			return false;
		}

		$cb->setToken($_SESSION['tp_oauth_token'], $_SESSION['tp_oauth_token_secret']);

		$credentials = $cb->account_verifyCredentials();

		return array(
			'id' => $credentials->id,
			'name' => $credentials->name,
			'username' => $credentials->screen_name,
			'email' => $credentials->screen_name . '@fake.twitter.com',
			'url' => 'http://twitter.com/' . $credentials->screen_name,
			'image' => $credentials->profile_image_url,
		);
	}

	function comment_avatar( $avatar, $userid, $id_or_email, $size, $default, $alt ) {
		$screen_name = explode( '@', $id_or_email->comment_author_email );
		return $this->get_avatar( $screen_name[0], $size, $default, $alt );
	}

	/* unset comment email cookie */
	function comment_post_redirect( $location ) {
		if( @$_SESSION['comment_user_service'] === $this->api_slug() ) {
			setcookie('comment_author_email_' . COOKIEHASH, '', 0, COOKIEPATH, COOKIE_DOMAIN);
		}
		return $location;
	}
}
