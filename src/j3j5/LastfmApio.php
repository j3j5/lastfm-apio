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

class LastfmApio {

	private static $debug = FALSE;
	private static $api_url = 'http://ws.audioscrobbler.com/2.0/';
	private static $auth_url = 'http://www.last.fm/api/auth/';

	private $api_settings = array();

	public function _construct($api_settings = array()) {
		include __DIR__ . '/config.php';
		$this->api_settings = array_merge($this->api_settings, $last_fm_api_settings);

		// Don't allow ouput if not running from the CLI
		if(PHP_SAPI != 'cli') {
			self::$debug = FALSE;
		}
	}

	public function set_api_settings($config) {
		// The consumer key and secret must always be included when reconfiguring
		$this->api_settings = array_merge($this->api_settings, $config);
	}

	public function get_api_settings() {
		return $this->api_settings;
	}

}
