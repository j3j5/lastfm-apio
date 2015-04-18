<?php

/**
 * LastfmApio
 *
 * A wrapper to make easier to use Last.fm API service.
 *
 * @author Julio Foulquié
 * @version 0.1.0
 *
 * 10 Apr 2015
 */

namespace j3j5;

use \marcushat\RollingCurlX;
use \Monolog\Logger;
use \Monolog\Handler\StreamHandler;

class LastfmApio {

	private static $api_url = 'http://ws.audioscrobbler.com/2.0/';
	private static $auth_url = 'http://www.last.fm/api/auth/';
	private static $total_requests = 0;
	private static $total_errors = array();
	private static $responses = array();
	public static $log;

	private static $api_settings = array();
	private $curl_settings = array();

	private $curl;
	private $rolling_curl;
	private $max_concurrent_requests = 50;


	public function __construct($api_settings = array()) {
		include __DIR__ . '/config.php';
		self::$api_settings = array_merge(self::$api_settings, $last_fm_api_settings);
		self::$api_settings = array_merge(self::$api_settings, $api_settings);

		$this->curl_settings = array(
			CURLOPT_SSL_VERIFYPEER	=> FALSE,
			CURLOPT_SSL_VERIFYHOST	=> FALSE,
			CURLOPT_USERAGENT		=> 'RollingCurlX test script',
		);

		self::create_log_instance();
	}

	public function __destruct() {
		if(count(self::$total_errors) > 0) {
			self::$log->addWarning("Error summary:");
			foreach(self::$total_errors AS $error_code => $number_of_errors) {
				self::$log->addWarning("$number_of_errors requests failed (out of " . self::$total_requests . ") with error $error_code.");
			}
		}
	}

	public function set_api_settings($config) {
		// The consumer key and secret must always be included when reconfiguring
		self::$api_settings = array_merge(self::$api_settings, $config);
	}

	public function get_api_settings() {
		return self::$api_settings;
	}

	public function set_max_concurrent_reqs($max_concurrent_requests) {
		$this->max_concurrent_requests = intval($max_concurrent_requests);
		$this->rolling_curl = NULL;
		$this->_get_rolling_curl();
	}

	public function get_max_concurrent_reqs() {
		return $this->max_concurrent_requests;
	}

	/**
	 * The magic happens here : as the Last.fm API is very well thought-of,
	 * this method can infer what method you're trying to get and transmit
	 * the parameters to {@link _make_request()}.
	 *
	 * This method is not meant to be called directly, but PHP will use
	 * it if you're trying to call a method which is not defined here.
	 *
	 * @param string method name (replace «.» by «_»).
	 * @param array parameters of the method.
	 * @return the result of {@link self::_make_request()}
	 */
	public function __call( $method, $parameters = array() ) {
		$method = str_replace('_','.',$method);
		$params = isset($parameters[0]) ? $parameters[0] : array();
		$do_request_auth = isset($parameters[1]) ? $parameters[1] : false;
		$do_multi_request = isset($parameters[2]) ? $parameters[2] : false;
		return $this->_add_multi_request( $method, $params, $do_request_auth, $do_multi_request );
	}

	/**
	 * Performs a request to the the API in JSON
	 *
	 * @param string Last.fm method name
	 * @param array parameters that will be added (optional)
	 * @param boolean [Optional] does the request needs to be authenticated ? (default FALSE)
	 *
	 * @return object depends on what you asked for.
	 *
	 * @note:	If multi_requests is enabled you're responsible to call run_multi_requests() after
	 * 			adding as many requests as wished
	 */
	private function _add_multi_request($method, $parameters = array(), $do_request_auth = FALSE, $do_multi_request = FALSE) {
		$user_parameters = $parameters;
		$user_parameters['method'] = $method;

		// Always append 'method', 'format' and 'api_key'
		$parameters = array_merge(
				array(
					'method' => $method,
					'api_key' => self::$api_settings['api_key']
				),
				$parameters
		);

		if(!empty(self::$api_settings['format']) && !isset($parameters['format'])) {
			$parameters['format'] = self::$api_settings['format'];
		}

		// Do we need to authenticate the request ?
		if( $do_request_auth ) {
			// We add the session key if it's been given
			if( !empty(self::$api_settings['session_key']) )
				$parameters['sk'] = self::$api_settings['session_key'];

			// Known bug : you have to get rid of format parameter to compute the api_sig parameter.
			// http://www.lastfm.fr/group/Last.fm+Web+Services/forum/21604/_/428269/1#f18907544
			$parameters_without_format = $parameters;
			unset($parameters_without_format['format']);

			// What follows is well-documented here: http://www.lastfm.fr/api/webauth#6
			ksort($parameters_without_format);
			$signature = '';
			foreach( $parameters_without_format as $k => $v ) {
				$signature .= "$k$v";
			}
			$parameters['api_sig'] = md5($signature . self::$api_settings['api_secret']);
		}

		// We have everything we need, let's query the API
		$rolling_curl = $this->_get_rolling_curl();
		$rolling_curl->setHeaders(array('Content-type: application/x-www-form-urlencoded'));
		$rolling_curl->addRequest(self::$api_url, $parameters, __NAMESPACE__ .'\LastfmApio::process_response', $user_parameters);
		self::$total_requests++;
		self::$log->addDebug("Request added: " . self::$api_url . '?' . http_build_query($parameters));
		if(!$do_multi_request) {
			self::$log->addDebug("Single request, running...");
			$responses = $this->run_multi_requests();
			return $responses[implode('.', $user_parameters)];
		}
	}

	public function run_multi_requests() {
		self::$log->addDebug("Executing parallel calls");
		$this->_get_rolling_curl()->execute();
		return $this->get_responses();
	}

	private function get_responses() {
		return self::$responses;
	}

	private function _get_rolling_curl() {
		if(empty($this->rolling_curl)) {
			$this->rolling_curl = new RollingCurlX($this->max_concurrent_requests);
			self::$log->addDebug("New RollingCurlX created with {$this->max_concurrent_requests} max concurrent reqs.");
			$this->rolling_curl->setOptions($this->curl_settings);
		}
		return $this->rolling_curl;
	}

	public static function create_log_instance() {
		if(empty(self::$log)) {
			///WARNING: Setting this to a level lower level than WARNING can slow things down.
			self::$log = new Logger('lastfm-apio');
			if(PHP_SAPI == 'cli') {
				self::$log->pushHandler(new StreamHandler("php://stdout", Logger::WARNING));
			} else {
				// 			self::$log->pushHandler(new StreamHandler(dirname(__DIR__) . '/data/logs/last-stream.log', Logger::WARNING));
			}
		}
	}

	public static function disable_logging(){
		if(!empty(self::$log)) {
			self::$log = NULL;
			self::$log = new Logger('lastfm-apio');
			self::$log->pushHandler(new StreamHandler("/dev/null", Logger::ERROR));
		}
	}

	/**
	 * Process the response from an API call
	 *
	 * @throws Exception if something goes wrong.
	 *
	 */
	public static function process_response($response, $url, $request_info, $parameters, $time) {
		self::create_log_instance();
		self::$log->addDebug("Processing response for: " . $url . " with parameters " . print_r($parameters, TRUE) );

		// In case for whatever reason the API hosts returns a 500 or something like it
		if ($request_info['http_code'] !== 200) {
			self::$log->addError("Connection error " . $request_info['http_code']);
			self::$responses[implode('.', $parameters)] = FALSE;
			return;
		}
		$json = json_decode( $response );

		// The JSON couldn't be decoded …
		if( is_null($json) ) {
			self::$log->addError("JSON response seems incorrect:");
			self::$log->addError($response);
			self::$responses[implode('.', $parameters)] = FALSE;
			return;
		}

		// An error has occurred …
		if( !empty($json->error) ) {
			self::handle_api_errors($json, $parameters);
			self::$responses[implode('.', $parameters)] = FALSE;
			return;
		}
		self::$responses[implode('.', $parameters)] = $json;
	}

	private static function handle_api_errors($json, $parameters) {
		self::create_log_instance();
		$parameters = array_merge(
			array(
				'format' => 'json',
				'api_key' => self::$api_settings['api_key']
			),
			$parameters
		);
		if(!isset(self::$total_errors[$json->error])) {
			self::$total_errors[$json->error] = 0;
		}
		switch($json->error) {
		// 8 : Operation failed - Something else went wrong
			case 8:
			// 16 : There was a temporary error processing your request. Please try again
			case 16:
				// Esto peeeetaaaa (error that the API throws on some legit calls, just ignore it)
				self::$total_errors[$json->error]++;
				self::$log->addWarning("API call failed with error({$json->error}): http://ws.audioscrobbler.com/2.0/?" . http_build_query( $parameters ));
				self::$log->addWarning($json->message);
				return;
			// 29 : Rate limit exceeded - Your IP has made too many requests in a short period
			case 29:
				self::$total_errors[$json->error]++;
				self::$log->addError("Rate limit hit on: http://ws.audioscrobbler.com/2.0/?" . http_build_query( $parameters ));
				self::$log->addError($json->message);
				self::$log->addError("Exiting.");
				exit;
			// 10 : Invalid API key - You must be granted a valid key by last.fm
			case 10:
				self::$total_errors[$json->error]++;
				self::$log->addError("Invalid API key, go to http://www.last.fm/api/accounts and get a valid one.");
				return;
			// 6 : Invalid parameters - Your request is missing a required parameter
			case 6:
				self::$total_errors[$json->error]++;
				self::$log->addWarning("Wrong parameters:");
				self::$log->addWarning($json->message);
				return;
			// 3 : Invalid Method - No method with that name in this package
			case 3:
				self::$total_errors[$json->error]++;
				self::$log->addWarning("Wrong method:");
				self::$log->addWarning($json->message);
				return;
			// 2 : Invalid service - This service does not exist
			case 2:
			// 4 : Authentication Failed - You do not have permissions to access the service
			case 4:
			// 5 : Invalid format - This service doesn't exist in that format
			case 5:
			// 7 : Invalid resource specified
			case 7:
			// 9 : Invalid session key - Please re-authenticate
			case 9:
			// 11 : Service Offline - This service is temporarily offline. Try again later.
			case 11:
			// 13 : Invalid method signature supplied
			case 13:
			// 26 : Suspended API key - Access for your account has been suspended, please contact Last.fm
			case 26:
			default:
				self::$total_errors[$json->error]++;
				self::$log->addError('UNHANDLED ERROR: ' );
				self::$log->addError("API call failed: http://ws.audioscrobbler.com/2.0/?" . http_build_query( $parameters ));
				self::$log->addError(print_r($json, TRUE));
				exit;
				break;
		}
		self::$log->addError('UNKNOWN ERROR: ' . $json->message );
		self::$log->addError("API call failed: http://ws.audioscrobbler.com/2.0/?" . http_build_query( $parameters ));
		exit;
	}

}
