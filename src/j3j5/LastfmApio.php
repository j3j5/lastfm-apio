<?php

/**
 * LastfmApio
 *
 * A wrapper to make easier to use Twitter's API with tmhOAuth library.
 *
 * @author Julio Foulquié
 * @version 0.1.0
 *
 * 06 Mar 2015
 */

namespace j3j5;

use marcushat\RollingCurlX;
use \Monolog\Logger;
use \Monolog\Handler\StreamHandler;

class LastfmApio {

	private static $api_url = 'http://ws.audioscrobbler.com/2.0/';
	private static $auth_url = 'http://www.last.fm/api/auth/';

	private $api_settings = array();
	private $curl_settings = array();

	private $curl;
	private $rolling_curl;
	private $max_concurrent_requests = 20;

	private $responses = array();

	public function __construct($api_settings = array()) {
		include __DIR__ . '/config.php';
		$this->api_settings = array_merge($this->api_settings, $last_fm_api_settings);
		$this->api_settings = array_merge($this->api_settings, $api_settings);

		$this->curl_settings = array(
			CURLOPT_SSL_VERIFYPEER	=> FALSE,
			CURLOPT_SSL_VERIFYHOST	=> FALSE,
			CURLOPT_USERAGENT		=> 'RollingCurlX test script',
		);

		$this->log = new Logger('lastfm-api');
		if(PHP_SAPI == 'cli') {
			$this->log->pushHandler(new StreamHandler("php://stdout", Logger::WARNING));
		} else {
// 			$this->log->pushHandler(new StreamHandler(dirname(__DIR__) . '/data/logs/last-stream.log', Logger::WARNING));
		}
	}

	public function set_api_settings($config) {
		// The consumer key and secret must always be included when reconfiguring
		$this->api_settings = array_merge($this->api_settings, $config);
	}

	public function get_api_settings() {
		return $this->api_settings;
	}

	private function _get_curl() {
		if(empty($this->curl)) {
			$this->curl = new Curl();
			foreach($this->curl_settings AS $setting => $value) {
				$this->curl->setopt($setting, $value);
			}
		}
		return $this->curl;
	}

	private function _get_rolling_curl() {
		if(empty($this->rolling_curl)) {
			$this->rolling_curl = new RollingCurlX($this->max_concurrent_requests);
			$this->rolling_curl->setOptions($this->curl_settings);
		}
		return $this->rolling_curl;
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
					'format' => 'json',
					'api_key' => $this->api_settings['api_key']
				),
				$parameters
		);

		// Do we need to authenticate the request ?
		if( $do_request_auth ) {
			// We add the session key if it's been given
			if( !empty($this->api_settings['session_key']) )
				$parameters['sk'] = $this->api_settings['session_key'];

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
			$parameters['api_sig'] = md5($signature . $this->api_settings['api_secret']);
		}

		// We have everything we need, let's query the API
		$rolling_curl = $this->_get_rolling_curl();
		$rolling_curl->setHeaders(array('Content-type: application/x-www-form-urlencoded'));
		$rolling_curl->addRequest(self::$api_url, $parameters, array($this, 'process_response'), $user_parameters);
		if(!$do_multi_request) {
			$responses = $this->run_multi_requests();
			return $responses[implode('.', $user_parameters)];
		}
	}

	public function run_multi_requests() {
		$this->_get_rolling_curl()->execute();
		return $this->get_responses();
	}

	/**
	 * Process the response from an API call
	 *
	 * @throws Exception if something goes wrong.
	 *
	 */
	public function process_response($response, $url, $request_info, $parameters, $time) {
		if ($request_info['http_code'] !== 200) {
			throw new \Exception("Connection error " . $request_info['http_code']);
		}

		$json = json_decode( $response );

		// The JSON couldn't be decoded …
		if( $json === false )
			throw new \Exception("JSON response seems incorrect.");

		// An error has occurred …
		if( !empty($json->error) )
			throw new \Exception("[{$json->error}|{$json->message}] "
			.implode(', ', $json->links)."\n".http_build_query( $parameters ));
		$this->responses[implode('.', $parameters)] = $json;
	}

	private function get_responses() {
		return $this->responses;
	}

}
