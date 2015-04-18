<?php
use j3j5\LastfmApio;

class LastfmApioTest extends PHPUnit_Framework_TestCase {

	public function testCanGetSettings() {
		// Arrange
		$api = new LastfmApio();

		$api_settings = $api->get_api_settings();

		// Assert
		$this->assertEquals(TRUE, is_array($api_settings));
	}

	public function testCanGetMaxConcurrent() {
		// Arrange
		$api = new LastfmApio();

		$max_reqs = $api->get_max_concurrent_reqs();

		// Assert
		$this->assertEquals(TRUE, is_numeric($max_reqs));
	}

	public function testCanSetSettings() {
		// Arrange
		$api = new LastfmApio();

		$lastfm_settings = array('api_key' => 'fa6c4dc3a0ffc7fbec685dc3491eb080', 'api_secret' => ''); // api key from last.fm example docs
		// Act
		$api->set_api_settings($lastfm_settings);
		$api_settings = $api->get_api_settings();

		LastfmApio::disable_logging();

		// Assert
		$this->assertEquals($lastfm_settings['api_key'], $api_settings['api_key']);

		return $api;
	}

	/**
	 * @depends testCanSetSettings
	 */
	public function testSetMaxConcurrent(LastfmApio $api) {
		$default_max_reqs = $api->get_max_concurrent_reqs();
		$new_max_req = 200;

		$api->set_max_concurrent_reqs($new_max_req);
		$max_reqs = $api->get_max_concurrent_reqs();

		$this->assertNotEquals($default_max_reqs, $new_max_req);
		$this->assertEquals($new_max_req, $max_reqs);
	}

	/**
	 * @depends testCanSetSettings
	 */
	public function testSingleRequest(LastfmApio $api) {
		$username = 'rj';

		$response = $api->user_getweeklychartlist(array('user' => $username));

		$this->assertEquals(TRUE, is_object($response));
	}

	/**
	 * @depends testCanSetSettings
	 */
	public function testMultiRequest(LastfmApio $api) {
		$username = 'rj';

		$api->user_getweeklyartistchart(array('user' => $username), FALSE, TRUE);
		$api->user_getweeklyartistchart(array('user' => $username, 'from' => 1210507200, 'to' => 1211112000), FALSE, TRUE);
		$api->user_getweeklyartistchart(array('user' => $username, 'from' => 1217764800, 'to' => 1218369600), FALSE, TRUE);
		$api->user_getweeklyartistchart(array('user' => $username, 'from' => 1232280000, 'to' => 1232884800), FALSE, TRUE);

		// The response to all concurrent requests is return as an array using as key a string of the parameters joined by '.'
		$responses = $api->run_multi_requests();

		foreach($responses AS $response) {
			$this->assertEquals(TRUE, is_object($response));
		}
	}

	/**
	 * @depends testCanSetSettings
	 */
	public function testSingleRequestParametersError(LastfmApio $api) {
		$username = 'rjadsf';

		$response = $api->user_getweeklychartlist(array('user' => $username));

		$this->assertEquals(FALSE, $response);
	}

	/**
	 * @depends testCanSetSettings
	 */
	public function testSingleRequestWrongMethod(LastfmApio $api) {
		$response = $api->user_getweeklychartlista();

		$this->assertEquals(FALSE, $response);
	}

	/**
	 * @depends testCanSetSettings
	 */
	public function testSingleRequestWrongJson(LastfmApio $api) {
		$username = 'rj';
		$api->set_api_settings(array('format' => NULL));
		$response = $api->user_getweeklychartlist(array('user' => $username));
		$this->assertEquals(FALSE, $response);
	}

	/**
	 * @depends testCanSetSettings
	 */
	public function testSingleRequestWrongResponseCode(LastfmApio $api) {
		$username = 'rj';
		$response = $api->user_getweeklychartlist(array('user' => $username, 'format' => 'xml'));
		$this->assertEquals(FALSE, $response);
	}

	public function testLog() {
		LastfmApio::create_log_instance();
		$this->assertEquals(TRUE, is_object(LastfmApio::$log));
	}
}
