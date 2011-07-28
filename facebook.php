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
			//Save access token
			$this->access_token = $access_token;
			//Check session content
			if($this->userStatus() == true) $this->logoutFacebookUser();
			//Users profile
			$user_profile = json_decode($this->HttpSocket->get('https://graph.facebook.com/me', 'access_token='.$access_token.''), true);
			//Session content
			$session_content = array(
				'access_token' => $access_token,
				'id' => $user_profile['id'],
				'name' => $user_profile['name']
			); 
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
		public function showFacebookUser($show_full_profile = false) {
			if($this->userStatus() == true) {
				$user = $this->Session->read('Facebook.User');
				if($show_full_profile == true) $user['profile'] = $this->myProfile();
				return $user;
			}
			else return null;
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
			if($this->userStatus() == true) $this->Session->delete('Facebook.User');
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
		 
		 //===My-Profile API Mehthods
		 
		 /*
		  * Make custom API-Requests on the authenticated user profile
		  *(See: https://developers.facebook.com/docs/reference/api/ for more information)
		  * 
		  * @access private
		  * @return JSON
		  * @param string $action The specific action for the 'thing' of the profile wich 
		  * should be called
		  */
		 private function myProfileApiRequest($action) {
		 	//Action
		 	if(substr($action, 0, 1) == '/') $action = substr($action, 1, strlen($action));
		 	//Query 
		 	$query = array('access_token' => $this->access_token);
			//Request array
			$request = array(
				'method' => 'GET',
				'uri' => array(
					'scheme' => 'https',
					'host' => 'graph.facebook.com/me',
					'path' => '/'.$action,
					'query' => $query
				) 
			);
			//Request and return 
			return $this->HttpSocket->request($request);
		 }
		 
		 /*
		  * Show the full profile of the authenticated user
		  * 
		  * @access public
		  * @return array
		  */
		 public function myProfile() {
		 	//Query
		 	$query = 'access_token='.$this->access_token;
			//Request and return 
		  	return json_decode($this->HttpSocket->get('https://graph.facebook.com/me', $query), true);
		 }
		 
		 //Friends
		 public function myFriends() {
		 	return json_decode($this->myProfileApiRequest('/friends'), true);
		 }
		 
		 //Newsfeed (Home)	 
		 public function myNewsFeed() {
		 	return json_decode($this->myProfileApiRequest('/home'), true);
		 }
		 
		 //Wall 
		 public function myProfileFeed() {
		 	return json_decode($this->myProfileApiRequest('/feed'), true);
		 }
		 
		 //Likes
		 public function myLikes() {
		 	return json_decode($this->myProfileApiRequest('/likes'), true);
		 }
		 
		 //Movies
		 public function myMovies() {
		 	return json_decode($this->myProfileApiRequest('/movies'), true);
		 }
		 
		 //Music
		 public function myMusic() {
		 	return json_decode($this->myProfileApiRequest('/music'), true);
		 }
		 
		 //Books
		 public function myBooks() {
		 	return json_decode($this->myProfileApiRequest('/books'), true);
		 }
		 
		 //Notes
		 public function myNotes() {
		 	return json_decode($this->myProfileApiRequest('/notes'), true);
		 }
		 
		 //Permissions
		 public function myPermissions() {
		 	return json_decode($this->myProfileApiRequest('/permissions'), true);
		 }
		 
		 //Photos
		 public function myPhotoTags() {
		 	return json_decode($this->myProfileApiRequest('/photos'), true);
		 }
		 
		 //Photo Albums
		 public function myAlbums() {
		 	return json_decode($this->myProfileApiRequest('/albums'), true);
		 }
		 
		 //Video Tags
		 public function myVideoTags() {
		 	return json_decode($this->myProfileApiRequest('/videos'), true);
		 }
		 
		 //Uploaded videos
		 public function myUploadedVideos() {
		 	return json_decode($this->myProfileApiRequest('/videos/uploaded'), true);
		 }
		 
		 //Events
		 public function myEvents() {
		 	return json_decode($this->myProfileApiRequest('/events'), true);
		 }
		 
		 //Groups
		 public function myGroups() {
		 	return json_decode($this->myProfileApiRequest('/groups'), true);
		 }
		 
		 //Checkins
		 public function myCheckins() {
		 	return json_decode($this->myProfileApiRequest('/checkins'), true);
		 }		 
		 
		 
	//---End 		
	}
?>