[![Build Status](https://travis-ci.org/j3j5/lastfm-apio.svg?branch=master)](https://travis-ci.org/j3j5/lastfm-apio)


LastfmApio
============

LastfmApio is a simple PHP wrapper for the Last.fm API that allows you to make parallel requests so it works faster (JS style).

Internally, it uses [marcushat/RollingCurlX](https://github.com/marcushat/RollingCurlX), a wrapper of cURL Multi.


## Installation

Add `j3j5/lastfm-apio` to `composer.json`.
```
"j3j5/lastfm-apio": "dev-master"
```

Run `composer update` to pull down the latest version of LastfmApio.

or alternatively, run
```
$ composer require j3j5/lastfm-apio dev-master
```

## Configuration

Open up the `config.php` included with the package and set there all your API key and secret (optional).

Alternatively, you can set your own config array and use it to overwrite the config file when you create the first instance of LastfmApio.
The array config must be as follows:

```php
$lastfm_settings = array(
	'api_key'		=> 'YOUR_API_KEY',
	'api_secret'	=> 'YOUR_API_SECRET',
);

$api = new LastfmApio($lastfm_settings);
```

## Use

Once you have created your own instance of the library, you can use any of the public methods to request from Twitter's API.

If you decide to set your tokens from your own app instead of from the config file:
```php
use j3j5\LastfmApio;

$lastfm_settings = array(
	'api_key'		=> 'YOUR_API_KEY',
	'api_secret'	=> 'YOUR_API_SECRET',
);

$api = new LastfmApio($lastfm_settings);

// Now you can do all type of requests

$user_info = $api->user_getinfo(
	array('user' => $username)
);
$artist = $api->artist_getInfo(
	array('artist' => 'Rosendo')
);
```

Or the more interesting ones...the ones with concurrent requests!!
```php
use j3j5\LastfmApio;

$lastfm_settings = array(
	'api_key'		=> 'YOUR_API_KEY',
	'api_secret'	=> 'YOUR_API_SECRET',
);

$api = new LastfmApio($lastfm_settings);

$username = 'lapegatina';
$api->user_getweeklyartistchart(array('user' => $username), FALSE, TRUE);
$api->user_getweeklyartistchart(array('user' => $username, 'from' => 1210507200, 'to' => 1211112000), FALSE, TRUE);
$api->user_getweeklyartistchart(array('user' => $username, 'from' => 1217764800, 'to' => 1218369600), FALSE, TRUE);
$api->user_getweeklyartistchart(array('user' => $username, 'from' => 1232280000, 'to' => 1232884800), FALSE, TRUE);

// The response to all concurrent requests is return as an array using as key a string of the parameters joined by '.'
$responses = $api->run_multi_requests();
foreach($responses AS $response) {
	$allresponseresults = isset($response->weeklyartistchart->artist) ? $response->weeklyartistchart->artist : array();
	print count($allresponseresults) . " artists found." . PHP_EOL;
}
```

You can also change the maximum amount of concurrent requests to be launched against the API doing
```php
$api->set_max_concurrent_reqs(50); // High values might get you in trouble with Last.fm, please be considerate with them!
```

This library is deeply inspired on [dandelionmood/php-lastfm](https://github.com/dandelionmood/php-lastfm), which was useful but
did not support multirequests, which made me code this one.
