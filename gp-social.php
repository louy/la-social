<?php
class GP_Social extends LA_Social {
	function __construct( $file = null ) {
		parent::__construct($file);
		$modules[] = new LA_Social_Comments($this);

		add_filter( $this->prefix() . '_comment_avatar', array( $this, 'comment_avatar' ), 10, 6 );
		add_filter( 'comment_post_redirect', array( $this, 'comment_post_redirect' ) );
	}

	function prefix() {
		return 'gp';
	}
	function api_slug() {
		return 'google';
	}
	function name() {
		return __('GeePress');
	}
	function api_name() {
		return __('Google+');
	}

	function app_configs() {
		return  array(
			'GOOGLE_CLIENT_ID'     => 'client_id',
			'GOOGLE_CLIENT_SECRET' => 'client_secret',
		);
	}

	function required_app_options() {
		return  array(
			'client_id',
			'client_secret',
		);
	}

	function app_options_section_fields( $fields = array() ) {
		$fields[] = array(
			'name' => 'client_id',
			'label' => __('Google Client ID', 'gp'),
			'required' => true,
			'constant' => 'GOOGLE_CLIENT_ID',
		);

		$fields[] = array(
			'name' => 'client_secret',
			'label' => __('Google Client Secret', 'gp'),
			'required' => true,
			'constant' => 'GOOGLE_CLIENT_SECRET',
		);

		return parent::app_options_section_fields($fields);
	}

	function app_options_section_callback() {
		if( !$this->required_app_options_are_set() ) {
			?>
<p><?php _e('To connect your site to Google, you will need a Google Application. If you have already created one, please insert your Client ID and Client Secret below.', 'gp'); ?></p>
<p><strong><?php _e('Can&#39;t find your keys?', 'gp'); ?></strong></p>
<ol>
<li><?php _e('Get a list of your applications from here: <a target="_blank" href="https://code.google.com/apis/console">Google APIs Console</a>', 'gp'); ?></li>
<li><?php _e('Select the application (project) you want, then copy and paste the Client ID and Client Secret from the API Access page there.', 'gp'); ?></li>
</ol>

<p><?php _e('<strong>Haven&#39;t created an application yet?</strong> Don&#39;t worry, it&#39;s easy!', 'gp'); ?></p>
<ol>
<li><?php _e('Go to this link to create your application: <a target="_blank" href="https://code.google.com/apis/console">Google APIs Console</a>, then create a new project.', 'gp'); ?></li>
<li><?php _e('Go to API Access tab and click on "Create an OAuth 2.0 client ID..."', 'gp'); ?></li>

<li><?php _e('Important Settings:', 'gp'); ?><ol>
<li><?php _e('Application Type must be set to "Web application".', 'gp'); ?></li>
<li><?php printf(__('Site must be set to <code>%s</code>.', 'gp'), get_bloginfo('url').'/oauth/google/'); ?></li>
</ol>
</li>

<li><?php _e('The other application fields can be set up any way you like.', 'gp'); ?></li>

<li><?php _e('After creating the application, copy and paste the "Client ID" and "Client secret" from the API Access page.', 'gp'); ?></li>
</ol>
<?php
		}
	}

	function sanitize_options( $options ) {
		unset($options['client_id'], $options['client_secret']);

		$options = apply_filters( $this->prefix() . '_sanitize_options', $options );

		return $options;
	}
	function sanitize_app_options( $app_options ) {
		$app_options['client_id'] = preg_replace('/[^a-zA-Z0-9]/', '', $app_options['client_id']);
		$app_options['client_secret'] = preg_replace('/[^a-zA-Z0-9]/', '', $app_options['client_secret']);

		return $app_options;
	}

	function get_api_instance() {
		static $instance;
		if( !$instance ) {

			if( !$this->required_app_options_are_set() ) {
				return false;
			}

			$instance = new Google_Client();
			$instance->setApplicationName(get_bloginfo('name'));
			$instance->setClientId($this->option('client_id'));
			$instance->setClientSecret($this->option('client_secret'));
			$instance->setRedirectUri(get_bloginfo('url').'/oauth/google');
		}
		return $instance;
	}

	function oauth_start() {
		$client = $this->get_api_instance();
		if( $client === false ) {
			wp_die( __('OAuth is misconfigured.') );
		}

		$plus = new Google_PlusService($client);
		$oauth2 = new Google_Oauth2Service($client);
		
		if( isset($_GET['code']) ) {
			
			$_SESSION['gp-connected'] = true;
			
			$_SESSION['gp_token'] = $client->getAccessToken();
			
			if( @$_SESSION['gp_verify'] ) {
				unset( $_SESSION['gp_verify'] );

				if( $reply = $client->authenticate() ) {
					$this->oauth_error( 'Error ', $reply );
				}

				$_SESSION['gp_token'] = $client->getAccessToken();

				$_SESSION['gp_connected'] = true;
			} elseif( !@$_SESSION['gp_connected'] ) {
				wp_die( 'Something wrong happened. Please try again.', 'Unknown Error' );
			}

			$_SESSION['comment_user_service'] = $this->api_slug();

			if( @$_SESSION[ $this->prefix() . '_callback_action' ] ) {
				do_action('gp_action-'.$_SESSION[ $this->prefix() . '_callback_action' ]);
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
			$this->oauth_error( __('Error: request has not been understood. Please go back and try again.') );
		} else {

			$auth_url = $client->createAuthUrl();

			$_SESSION['gp_verify'] = true;
			unset( $_SESSION['gp_connected'] );

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
		if( !@$_SESSION['gp_connected'] ) {
			return false;
		}

		$client = $this->get_api_instance();
		if( $client === false ) {
			return false;
		}

		$plus = new Google_PlusService($client);
		$oauth2 = new Google_Oauth2Service($client);
		
		$client->setAccessToken($_SESSION['gp_token']);
	
		$profile = $plus->people->get('me');
		$user = $oauth2->userinfo->get();

		return array(
			'id' => $profile['id'],
			'name' => $profile['displayName'],
			'username' => $profile['id'],
			'email' => $user['email'],
			'url' => $profile['url'],
			'image' => $profile['image']['url'],
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
