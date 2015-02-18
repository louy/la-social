<?php
defined('FACEBOOK_SDK_V4_SRC_DIR') ||
	define( 'FACEBOOK_SDK_V4_SRC_DIR', __DIR__ . '/lib/Facebook/' );
require_once __DIR__ . '/la-social/la-social.php';
require_once __DIR__ . '/lib/autoload.php';

use Facebook\FacebookSession;
use Facebook\FacebookRequest;
use Facebook\FacebookRequestException;
use Facebook\FacebookRedirectLoginHelper;
use Facebook\GraphUser;

class FP_Social extends LA_Social {

	function __construct( $file = null ) {
		parent::__construct($file);
		$modules[] = new LA_Social_Comments($this);
	}

	function prefix() {
		return 'fp';
	}
	function api_slug() {
		return 'facebook';
	}
	function name() {
		return __('FacePress');
	}
	function api_name() {
		return __('Facebook');
	}

	function app_configs() {
		return  array(
			'FACEBOOK_APP_ID'        => 'appId',
			'FACEBOOK_APP_SECRET'    => 'secret',
			'FACEBOOK_FANPAGE'       => 'fanpage',
			'FACEBOOK_DISABLE_LOGIN' => 'disable_login',
		);
	}

	function required_app_options() {
		return  array(
			'appId',
			'secret',
		);
	}

	function options_defaults() {
		return array(
        );
	}

	function app_options_section_fields( $fields = array() ) {
		$fanpage_help = '<p>' .
			__('Some sites use Fan Pages on Facebook to connect with their users. The Application wall acts as a  Fan Page in all respects, however some sites have been using Fan Pages previously, and already have communities and content built around them. Facebook offers no way to migrate these, so the option to use an existing Fan Page is offered for people with this situation. Note that this doesn&#39;t <em>replace</em> the application, as that is not optional. However, you can use a Fan Page for specific parts of the FacePress plugin, such as the Fan Box, the Publisher, and the Chicklet.', 'fp') .
		'</p>' .
		'<p>' .
			__('If you have a <a href="http://www.facebook.com/pages/manage/">Fan Page</a> that you want to use for your site, enter the ID of the page here. Most users should leave this blank.', 'fp') .
		'</p>';

		$fields[] = array(
			'name' => 'appId',
			'label' => __('Facebook App ID', 'fp'),
			'required' => true,
			'constant' => 'FACEBOOK_APP_ID',
		);

		$fields[] = array(
			'name' => 'secret',
			'label' => __('Facebook App Secret', 'fp'),
			'required' => true,
			'constant' => 'FACEBOOK_APP_SECRET',
		);
		$fields[] = array(
			'name' => 'fanpage',
			'label' => __('Facebook Fan Page ID', 'fp'),
			'constant' => 'FACEBOOK_FANPAGE',
			'help_text' => $fanpage_help,
		);
		$fields[] = array(
			'name' => 'disable_login',
			'label' => __('Disable Facebook login', 'fp'),
			'required' => true,
			'constant' => 'FACEBOOK_DISABLE_LOGIN',
			'type' => 'checkbox',
		);

		return parent::app_options_section_fields($fields);
	}

	function app_options_section_callback() {
		if( !$this->required_app_options_are_set() ) {
			echo '<p>',
				__('To connect your site to Facebook, you will need a Facebook Application. If you have already created one, please insert your Application ID and Application Secret below.', 'fp'),
			'</p>';
			echo '<p><strong>',
				esc_html( __("Can't find your ID?", 'fp') ),
			'</strong></p>';
			echo '<ol>',
				'<li>',
					sprintf( __('Get a list of your applications from here: <a target="_blank" href="%s">Facebook Application List</a>', 'fp'), 'https://developers.facebook.com/apps/' ),
				'</li>',
				'<li>',
					__('Select the application you want, then copy and paste the Application ID and the Application Secret from there.', 'fp' ),
				'</li>',
			'</ol>';
			echo '<p><strong>',
				esc_html( __("Haven't created an application yet?", 'fp') ),
				'</strong> ',
				esc_html( __("Don't worry, it's easy!", 'fp') ),
			'</p>';
			echo '<ol>',
				'<li>',
					sprintf( __('Go to this link to create your application: <a target="_blank" href="%s">Facebook Connect Setup</a>', 'fp'), 'https://developers.facebook.com/quickstarts/?platform=web' ),
				'</li>',
				'<li>',
					__('When it tells you to "Upload a file" on step 2, just hit the "Upload Later" button. This plugin takes care of that part for you!', 'fp' ),
				'</li>',
				'<li>',
					__('On the final screen, there will be an application info box. Copy and paste that information into here.', 'fp' ),
				'</li>',
				'<li>',
					sprintf( __('You can get the rest of the information from the application on the <a target="_blank" href="%s">Facebook Application List</a> page.', 'fp'), 'https://developers.facebook.com/apps/' ),
				'</li>',
				'<li>',
					__('Select the application you want, then copy and paste the information from there.', 'fp' ),
				'</li>',
			'</ol>';
		}
	}

	function sanitize_options( $options ) {
		unset($options['appId'], $options['secret'], $options['fanpage'], $options['disable_login']);

		$options = apply_filters( $this->prefix() . '_sanitize_options', $options );

		return $options;
	}
	function sanitize_app_options( $app_options ) {
		$app_options['appId'] = preg_replace('/[^a-zA-Z0-9]/', '', $app_options['appId']);
		$app_options['secret'] = preg_replace('/[^a-zA-Z0-9]/', '', $app_options['secret']);
		$app_options['disable_login'] = isset( $app_options['disable_login'] );
		$app_options['fanpage'] = preg_replace('/[^0-9]/', '', $app_options['fanpage']);

		return $app_options;
	}

	function setup_session() {
		FacebookSession::setDefaultApplication( $this->option('appId'), $this->option('secret') );
	}

	function oauth_start() {
		if( !$this->required_app_options_are_set() ) {
			wp_die( __('OAuth is misconfigured.') );
		}

		$this->setup_session();
		$helper = new FacebookRedirectLoginHelper( oauth_link('facebook') );

		if( isset( $_GET['code'] ) ) {

			try {
				$session = $helper->getSessionFromRedirect();
			} catch(FacebookRequestException $ex) {
				// When Facebook returns an error
				$this->oauth_error( '<b>' . __('Facebook Error.') . "</b>\n" . $ex->getResponse()['error']['message'], $ex );
			} catch(\Exception $ex) {
				// When validation fails or other local issues
				$this->oauth_error( __('Unknown Error.'), $ex );
			}

			if( !$session ) {
				$this->oauth_error( __('Unknown Session Error.') );
			}

			$_SESSION['fb-connected'] = true;
			$_SESSION['fp_access_token'] = $session->getToken();

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

			// echo '<p>To complete the OAuth flow follow this URL: <a href="'. $return_url . '">' . $return_url . '</a></p>';
			wp_redirect( utf8_encode( $return_url ) );
			exit;

		} elseif( !isset( $_GET['location'] ) && !isset( $_GET['action'] ) ) {
			$this->oauth_error( __('Error: request has not been understood. Please go back and try again.') );
		}

		if( isset( $_GET['return'] ) ) {
			$_SESSION[ $this->prefix() . '_callback' ] = $_GET['return'];
		}
		if( isset( $_GET['action'] ) ) {
			$_SESSION[ $this->prefix() . '_callback_action' ] = $_GET['action'];
		}

		$scope = array();
		if( isset( $_GET['permissions'] ) && !empty($_GET['permissions']) ) {
			$scope = explode(',', $_GET['permissions']);
		}
		$scope[] = 'email';
		$scope = array_unique($scope);

		$login_url = $helper->getLoginUrl($scope);

		wp_redirect( $login_url );
		exit;
	}

	function oauth_error( $message, $object = null ) {
		wp_die(
			( !empty( $message ) ? $message : 'Unknown Facebook API Error.' ) . "\n" .
			( WP_DEBUG ? '<pre style="overflow:scroll; direction: ltr; background: #efefef; padding: 10px;">' . esc_html( print_r( $object, true ) ) . '</pre>'
				 : '' )
			, 'Facebook OAuth Error' );
	}

	function get_social_user() {
		if( !@$_SESSION['fp_access_token'] ) {
			return false;
		}

		$this->setup_session();

		try {
			$session = new FacebookSession( $_SESSION['fp_access_token'] );
			$me = (new FacebookRequest( $session, 'GET', '/me' ))
				->execute()->getGraphObject(GraphUser::className());

			return array(
				'id' => $me->getId(),
				'name' => $me->getName(),
				'email' => $me->getEmail(),
				'url' => $me->getLink(),
				'image' => $this->get_avatar( $me->getId(), 100, true ),
			);
		} catch( \Exception $ex ) {
			if( WP_DEBUG ) {
				print_r($ex);
			}
		}
		return false;
	}

}
