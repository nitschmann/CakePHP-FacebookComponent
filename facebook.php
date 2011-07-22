<?php 
	class FacebookComponent extends Object {
		//Name
		var $name = 'Facebook';
		//Components
		var $components = array('Cookie', 'Session');
		
		//============OAuthSetup anc connect
		
		/*
		 * The APP-ID and App Secret
		 * 
		 * @access private
		 */
		private $app_id, $app_secret;
		/*
		 * setup
		 */ 
		public function setup($app_id, $app_secret, $cookie) {
			$this->app_id = $app_id;
			$this->app_secret = $app_secret;
			//Content for session and cookie
			$content = array(
				'app_id' => $this->app_id,
				'app_secret' => $this->app_secret
			);
			//Check if cookie is allowed
			if($cookie == true) {
				//Facebook OAuth Cookie
				$fb_oauth_cookie = $this->Cookie->read('Facebook.App');
				//Check for content
				if(is_null($fb_oauth_cookie)) $this->Cookie->write('Facebook.App', $content, true, '+365 day');
				else {
					$this->Cookie->delete('Facebook.App');
					$this->Cookie->write('Facebook.App', $content, true, '+365 day');
				}	
			}
			//Session in local store
			$this->Session->write('Facebook.App', $content);
		}
		
		/*
		 * Connect to Facebook
		 * 
		 * @access public
		 * @param string $redirect_url The for the redirect after the authetication 
		 */
		public function connect($redirect_url) {
			$id = $this->app_id;	
			$url = 'https://www.facebook.com/dialog/oauth?client_id='.$id.'&redirect_uri='.$redirect_url;			
			
			echo("<script>top.location.href='".$url."'</script>");
		}
		/*
		 * Authenticate the user for the Facebook Graph-API
		 * 
		 * @access public
		 * @param string $redirect_url The url where the user should be redirected to
		 * @param string $code The you got after you have called $this->connect 
		 */
		public function connect_user($redirect_url, $code) {
			$id = $this->app_id;
			$secret = $this->app_secret;
			
			$url = "https://graph.facebook.com/oauth/access_token?"
			. "client_id=" . $this->app_id . "&redirect_uri=" . urlencode($redirect_url)
			. "&client_secret=" . $this->app_secret . "&code=" . $code;
			//Request the access token
			$response = file_get_contents($url);
			$params = null;
			parse_str($response, $params);
			//print_r($params);
			//Save session and user
			$this->setFacebookUser($params['access_token']);
		}
		/*
		 * The access token of the Facebook user
		 * 
		 * @access private
		 */
		private $access_token;
		/*
		 * Set the Facebook users access token and save in a local session
		 * 
		 * @access public
		 * @param string $access_token The access for the Facebook User
		 */ 
		public function setFacebookUser($access_token) {
			//Current session
			$current_session = $this->Session->read('Facebook.User');
			//Content for session
			$session_content = array('access_token' => $access_token);
			//Check session content
			if(!is_null($current_session)) $this->Session->delete('Facebook.User');
			//Save access token in $this->access_token
			$this->access_token = $access_token;
			//Save a new Session with user content
			$this->Session->write('Facebook.User', $session_content);	
		}
		//============
		//============Facebook user functions
		
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
			//Check App-ID and App secret
			if($this->app_id == '' || $this->app_secret == '') {
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
			if($this->access_token == '') {
				$token_session = $this->Session->read('Facebook.User');
				if(!is_null($token_session)) $this->access_token = $token_session['access_token'];
			}
			//
			$this->controller =& $controller;
		 }
		 //============Status Methods
		 
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
		 	if($this->app_id != '' && $this->app_secret != '' && $this->access_token != '') return true;
			else return false;
		 }
	}
?>