<?php
use j3j5\LastfmApio;

class LastfmApioTest extends PHPUnit_Framework_TestCase {

	public function testCanGetSettings()
	{
		// Arrange
		$api = new LastfmApio();

		$api_settings = $api->get_api_settings();

		// Assert
		$this->assertEquals(TRUE, is_array($api_settings));
	}

	public function testCanReconfigure()
	{
		// Arrange
		$api = new LastfmApio();

		$lastfm_settings = array('api_key' => 'fa6c4dc3a0ffc7fbec685dc3491eb080', 'api_secret' => ''); // api key from last.fm example docs
		// Act
		$api->set_api_settings($lastfm_settings);
		$api_settings = $api->get_api_settings();

		// Assert
		$this->assertEquals($lastfm_settings['api_key'], $api_settings['api_key']);
	}
}
