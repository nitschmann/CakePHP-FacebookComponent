<?php 
	App::import('Core', 'HttpSocket');

	class FacebookComponent extends Object {
		var $name = 'Facebook';
		//Components
		var $components = array('Cookie', 'Session');
		
		/*
		 * The global var for the CakePHP HttpSocket
		 * 
		 * @access private
		 */
		private $HttpSocket;

		//===App -and Auth vars
				
		/*
		 * The APP-ID and App Secret
		 * 
		 * @access private
		 */
		private $app_id, $app_secret;
		/*
		 * The access token of the Facebook user
		 * 
		 * @access private
		 */
		private $access_token;

		 
		//===OAuth setup -and connect

		public function appSetup($app_id, $app_secret, $cookie) {
			$this->app_id = $app_id;
			$this->app_secret = $app_secret;
			//Content for session and cookie
			$content = array(
				'app_id' => $this->app_id,
				'app_secret' => $this->app_secret
			);
			//Check if cookie is allowed
			if($cookie == true) {
				if(!is_null($this->Cookie->read('Facebook.App'))) $this->Cookie->delete('Facebook.App');
				//Write cookie
				$this->Cookie->write('Facebook.App', $content, true, '+365 day');
			}
			//Check local session if isset 
			if(!is_null($this->Session->read('Facebook.App'))) $this->Cookie->delete('Facebook.App');
			//Session in local store
			$this->Session->write('Facebook.App', $content);
		}
		
		/*
		 * Connect to Facebook
		 * 
		 * @access public
		 * @param string $redirect_url The for the redirect after the authetication
		 * @param string $scopes A string with a ',' separated specific permissions of the app
		 * (See: https://developers.facebook.com/docs/reference/api/permissions/ for more information)
		 */
		public function connect($redirect_url, $scopes = null) {
			$id = $this->app_id;
			if($scopes == null || $scopes == '') {	
				$url = 'https://www.facebook.com/dialog/oauth?client_id='.$id.'&redirect_uri='.$redirect_url;			
			}
			else {
				$url = 'https://www.facebook.com/dialog/oauth?client_id='.$id.'&redirect_uri='.$redirect_url.'&scope='.$scopes;			
			}
			//Redirect 
			header('Location: '.$url);
		}
		/*
		 * Authenticate the user for the Facebook Graph-API
		 * 
		 * @access public
		 * @param string $redirect_uri The url where the user should be redirected to
		 * @param string $code The you got after you have called $this->connect 
		 */
		public function authenticateFacebookUser($redirect_uri, $code) {
			//Query 
			$query = "client_id=".$this->app_id."&redirect_uri=".rawurlencode($redirect_uri)."&client_secret=".$this->app_secret."&code=".$code;
			//Request 
			$result = $this->HttpSocket->get('https://graph.facebook.com/oauth/access_token', $query);
			parse_str($result, $result);
			//Login the user 
			$this->loginFacebookUser($result['access_token']);
		}

		//============Facebook user functions

		/*
		 * Set the Facebook users access token and save in a local session
		 * 
		 * @access public
		 * @param string $access_token The access for the Facebook User
		 */ 
		public function loginFacebookUser($access_token) {
			//Session content
			$session_content = array('access_token' => $access_token);
			//Check session content
			if($this->userStatus() == true) $this->logoutFacebookUser();
			//Save access token
			$this->access_token = $access_token;
			//New session for current user
			$this->Session->write('Facebook.User', $session_content);	
		}
		
		/*
		 * Show and return the current access token and Facebook User
		 * 
		 * @access public
		 * @return array()
		 * @param bool $show_full_profile Set true to get the full user-profile
		 */
		public function getFacebookUser($show_full_profile) {
			$user = array();
			//Check access token
			if($this->access_token == '') {
				$session = $this->Session->read('Facebook.User');
				if(!is_null($session)) $user['access_token'] = $session['access_token'];
			}
			else $user['access_token'] = $this->access_token;
			//Show full user profile
			if($show_full_profile == true) {
				$url =	"https://graph.facebook.com/me?access_token=".$this->access_token; 			
				$user['profile'] = json_decode(file_get_contents($url), true);
			}
			return $user;
		}
		/*
		 * Logout the current Facebook user
		 * 
		 * @access public
		 */
		public function logoutFacebookUser() {
			//Set keys to null
			$this->access_token = null;
			//Destroy session
			if(!is_null($this->Session->read('Facebook.User'))) $this->Session->delete('Facebook.User');
		}
		//============
		/*
		 * Initalize function
		 */ 
		 public function initialize(&$controller, $settings = array()) {
		 	//Initialize a new HttpSocket
		 	$this->HttpSocket = new HttpSocket();
			//Connections
			if($this->status() == false) {
				//Check App-ID and App secret
				if($this->appStatus() != true) {
					$cookie = $this->Cookie->read('Facebook.App');
					if(!is_null($cookie)) {
						$this->app_id = $cookie['app_id'];
						$this->app_secret = $cookie['app_secret'];
					}
					else {
						$session = $this->Session->read('Facebook.App');
						if(!is_null($session)) {
							$this->app_id = $session['app_id'];
							$this->app_secret = $session['app_secret'];
						}
					} 
				}
				//Check access token
				if($this->userStatus() != true) {
					$token_session = $this->Session->read('Facebook.User');
					if(!is_null($token_session)) $this->access_token = $token_session['access_token'];
				}
			}
			//---
			$this->controller =& $controller;
		 }
		 //============App-Status Methods
		 
		 /*
		  * Status of the app (Checks if the APP-ID and APP secret are already stored )
		  * 
		  * @access public
		  * @return boolean
		  */
		 public function appStatus() {
		 	if($this->app_id != '' && $this->app_secret != '') return true;
			else return false;
		 }
		 /*
		  * Status of the Facebook user (checks if a access token is available)
		  * 
		  * @access public
		  * @return boolean
		  */
		 public function userStatus() {
		 	if($this->appStatus() == true) {
		 		if($this->access_token != '') return true;
				else return false; 
		 	}
			else return false;
		 }
		 /*
		  * Show current status of the full Facebook connection
		  * 
		  * @access public
		  * @return boolean
		  */
		 public function status() {
		 	if($this->appStatus() == true && $this->userStatus() == true) return true;
			else return false;
		 }
		
		//============Facebook API Methods
	}
?>