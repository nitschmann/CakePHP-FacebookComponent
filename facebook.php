<?php 
	App::import('Vendor', 'HttpSocketOauth');
	
	class FacebookComponent extends Object {
		//Name
		var $name = 'Facebook';
		//Components
		var $components = array('Cookie', 'Session');
		/*
		 * The APP-ID and App Secret
		 */
		private $app_id, $app_secret;
		//============OAuthSetup anc connect
		
		/*
		 * OAuth 
		 */
		private $oAuth;
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
		 * Initalize function
		 */ 
		 public function initialize(&$controller, $settings = array()) {
		 	//Create a new oauth socket
		 	$this->oAuth = new HttpSocketOauth();
			//Check for App-ID and App secret
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
			$this->controller =& $controller;
		 }
	}
?>