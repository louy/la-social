<?php
class GP_Social extends LA_Social {
	function __construct( $file = null ) {
		parent::__construct($file);
		$modules[] = new LA_Social_Comments($this);
	}

	function prefix() {
		return 'gp';
	}
	function api_slug() {
		return 'gplus';
	}
	function name() {
		return __('GeePress', 'la-social');
	}
	function api_name() {
		return __('Google+', 'la-social');
	}

	function app_configs() {
		return  array(
			'GOOGLE_CLIENT_ID'     => 'client_id',
			'GOOGLE_CLIENT_SECRET' => 'client_secret',
			'GOOGLE_API_KEY'       => 'api_key',
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
			'label' => __('Google Client ID', 'la-social'),
			'required' => true,
			'constant' => 'GOOGLE_CLIENT_ID',
		);

		$fields[] = array(
			'name' => 'client_secret',
			'label' => __('Google Client Secret', 'la-social'),
			'required' => true,
			'constant' => 'GOOGLE_CLIENT_SECRET',
		);

		$fields[] = array(
			'name' => 'api_key',
			'label' => __('Google API Key', 'la-social'),
			'required' => true,
			'constant' => 'GOOGLE_API_KEY',
		);

		return parent::app_options_section_fields($fields);
	}

	function app_options_section_callback() {
		if( !$this->required_app_options_are_set() ) {
			?>
<p><?php _e('To connect your site to Google, you will need a Google Application. If you have already created one, please insert your Client ID and Client Secret below.', 'la-social'); ?></p>
<p><strong><?php _e('Can&#39;t find your keys?', 'la-social'); ?></strong></p>
<ol>
<li><?php _e('Get a list of your applications from here: <a target="_blank" href="https://code.google.com/apis/console">Google APIs Console</a>', 'la-social'); ?></li>
<li><?php _e('Select the application (project) you want, then copy and paste the Client ID and Client Secret from the API Access page there.', 'la-social'); ?></li>
</ol>

<p><?php _e('<strong>Haven&#39;t created an application yet?</strong> Don&#39;t worry, it&#39;s easy!', 'la-social'); ?></p>
<ol>
<li><?php _e('Go to this link to create your application: <a target="_blank" href="https://code.google.com/apis/console">Google APIs Console</a>, then create a new project.', 'la-social'); ?></li>
<li><?php _e('Go to "APIs" tab and activate "Google+ API"', 'la-social'); ?></li>
<li><?php _e('Go to "Credentials" tab and get your "Client ID", "Client Secret" and "API Key" from there.', 'la-social'); ?></li>

<li><?php _e('Important Settings:', 'la-social'); ?><ol>
<li><?php _e('Application Type must be set to "Web application".', 'la-social'); ?></li>
<li><?php printf(__('"Redirect URIs" must be set to <code>%s</code>.', 'la-social'), oauth_link($this->api_slug())); ?></li>
</ol>
</li>

<li><?php _e('The other application fields can be set up any way you like.', 'la-social'); ?></li>
</ol>
<?php
		}
	}

	function sanitize_options( $options ) {
		unset($options['client_id'], $options['client_secret'], $options['api_key']);

		$options = apply_filters( $this->prefix() . '_sanitize_options', $options );

		return $options;
	}
	function sanitize_app_options( $app_options ) {
		$app_options['client_id'] = trim( $app_options['client_id'] );
		$app_options['client_secret'] = trim( $app_options['client_secret'] );
		$app_options['api_key'] = trim( $app_options['api_key'] );

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
			$instance->setRedirectUri( oauth_link($this->api_slug()) );
		}
		return $instance;
	}

	function oauth_start() {
		$client = $this->get_api_instance();
		if( $client === false ) {
			wp_die( __('OAuth is misconfigured.', 'la-social') );
		}

		if( !session_id() ) {
			session_start();
		}

		if( isset($_GET['code']) ) {

			$_SESSION['gp-connected'] = true;

			$_SESSION['gp_token'] = $client->getAccessToken();

			if( @$_SESSION['gp_verify'] ) {
				unset( $_SESSION['gp_verify'] );

				if( !( $reply = $client->authenticate($_GET['code']) ) ) {
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
			$this->oauth_error( __('Error: request has not been understood. Please go back and try again.', 'la-social') );
		} else {

			$client->addScope(Google_Service_Plus::PLUS_ME);
			$client->addScope(Google_Service_Plus::USERINFO_EMAIL);

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

		$client->addScope(Google_Service_Plus::PLUS_ME);
		$client->addScope(Google_Service_Plus::USERINFO_EMAIL);

		$plus = new Google_Service_Plus($client);
		$oauth2 = new Google_Service_Oauth2($client);

		$client->setAccessToken($_SESSION['gp_token']);

		$profile = $plus->people->get('me');
		$user = $oauth2->userinfo->get();

		if( !$profile || !$user ) {
			return false;
		}

		return array(
			'id' => $profile['id'],
			'name' => $profile['displayName'],
			'username' => $profile['id'],
			'email' => $user['email'],
			'url' => $profile['url'],
			'image' => $profile['image']['url'],
		);
	}
}
