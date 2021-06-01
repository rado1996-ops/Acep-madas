<?php
namespace MMP;

class Google_Places {
	private $autocomplete_endpoint = 'https://maps.googleapis.com/maps/api/place/autocomplete/json?input={query}';
	private $details_endpoint = 'https://maps.googleapis.com/maps/api/place/details/json?placeid={place_id}';
	private $address;
	private $place_id;

	public function request() {
		if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'google-places')) {
			$this->response('Security check failed!');
		}
		if (Maps_Marker_Pro::$settings['geocodingGoogleAuthMethod'] === 'api-key' && !Maps_Marker_Pro::$settings['geocodingGoogleApiKey']) {
			$this->response('Google geocoding authentication failed - please provide an API key!');
		}
		if (Maps_Marker_Pro::$settings['geocodingGoogleAuthMethod'] === 'clientid-signature') {
			if (!Maps_Marker_Pro::$settings['geocodingGoogleClient']) {
				$this->response('Google geocoding authentication failed - please provide a client ID!');
			}
			if (!Maps_Marker_Pro::$settings['geocodingGoogleSignature']) {
				$this->send_response('Google geocoding authentication failed - please provide a signature!');
			}
		}
		if (isset($_GET['address']) && $_GET['address']) {
			$this->autocomplete();
		} else if (isset($_GET['place_id']) && $_GET['place_id']) {
			$this->details();
		}
	}

	private function autocomplete() {
		$this->address = $_GET['address'];
		$url = $this->prepare_api_url('autocomplete');
		$autocomplete = wp_remote_get($url, array('sslverify' => false, 'timeout' => 10));
		$autocomplete = json_decode($autocomplete['body'], true);
		$response = array();
		if ($autocomplete['status'] == 'OK') {
			foreach ($autocomplete['predictions'] as $prediction) {
				$response[] = array(
					'formatted_address' => $prediction['description'],
					'place_id' => $prediction['place_id'],
					'types' => $prediction['types']
				);
			}
			$this->response('OK', $response);
		} else if ($autocomplete['status'] == 'ZERO_RESULTS') {
			$response[] = array(
				'formatted_address' => '',
				'place_id' => '',
				'types' => ''
			);
			$this->response('ZERO_RESULTS', $response);
		} else { // Custom error handling for geocoding.js
			$response = array(
				'status' => $autocomplete['status'],
				'error_message' => $autocomplete['error_message']
			);
			$this->response('GOOGLE-ERROR', $response);
		}
	}

	private function details() {
		$this->place_id = $_GET['place_id'];
		$url = $this->prepare_api_url('details');
		$details = wp_remote_get($url, array('sslverify' => false, 'timeout' => 10));
		$details = json_decode($details['body'], true);
		if ($details['status'] == 'OK') {
			$response = array(
				'formatted_address' => $details['result']['formatted_address'],
				'types' => $details['result']['types'],
				'geometry' => array(
					'location' => array(
						'lat' => $details['result']['geometry']['location']['lat'],
						'lng' => $details['result']['geometry']['location']['lng']
					)
				)
			);
			$this->response('OK', $response);
		} else { // Custom error handling for geocoding.js
			$response = array(
				'status' => $details['status'],
				'error_message' => $details['error_message']
			);
			$this->send_response('GOOGLE-ERROR', $response);
		}
	}

	protected function prepare_api_url($type) {
		if ($type === 'autocomplete') {
			$url = str_replace('{query}', urlencode($this->address), $this->autocomplete_endpoint);
		} else if ($type === 'details') {
			$url = str_replace('{place_id}', urlencode($this->place_id), $this->details_endpoint);
		}

		if (Maps_Marker_Pro::$settings['geocodingGoogleAuthMethod'] === 'api-key') {
			$url = $url . '&key=' . Maps_Marker_Pro::$settings['geocodingGoogleApiKey'];
		} else if (Maps_Marker_Pro::$settings['geocodingGoogleAuthMethod'] == 'clientid-signature') {
			$client = '&client=' . urlencode(Maps_Marker_Pro::$settings['geocodingGoogleClient']);
			$signature = '&signature=' . urlencode(Maps_Marker_Pro::$settings['geocodingGoogleSignature']);
			$channel = '&channel=' . urlencode(Maps_Marker_Pro::$settings['geocodingGoogleChannel']);
			$url = $url . $client . $signature . $channel;
		}

		if (Maps_Marker_Pro::$settings['geocodingGoogleLocation']) {
			$url .= '&location=' . Maps_Marker_Pro::$settings['geocodingGoogleLocation'];
		}
		if (Maps_Marker_Pro::$settings['geocodingGoogleRadius']) {
			$url .= '&radius=' . Maps_Marker_Pro::$settings['geocodingGoogleRadius'];
		}

		if (Maps_Marker_Pro::$settings['geocodingGoogleLanguage']) {
			$google_language = Maps_Marker_Pro::$settings['geocodingGoogleLanguage'];
		} else if (Maps_Marker_Pro::$settings['googleLanguage'] !== 'browser_setting' && Maps_Marker_Pro::$settings['googleLanguage'] !== 'wordpress_setting') {
			$google_language = Maps_Marker_Pro::$settings['googleLanguage'];
		} else {
			$google_language = substr(get_locale(), 0, 2);
		}
		$url .= '&language=' . $google_language;

		if (Maps_Marker_Pro::$settings['geocodingMinChars']) {
			$url .= '&offset=' . trim(Maps_Marker_Pro::$settings['geocodingMinChars']);
		}
		if (Maps_Marker_Pro::$settings['geocodingGoogleRegion']) {
			$url .= '&region=' . trim(Maps_Marker_Pro::$settings['geocodingGoogleRegion']);
		}
		if (Maps_Marker_Pro::$settings['geocodingGoogleComponents']) {
			$url .= '&components=' . trim(Maps_Marker_Pro::$settings['geocodingGoogleComponents']);
		}

		return $url;
	}

	private function response($msg, $data = '') {
		$response['status'] = $msg;
		if ($data) {
			$response['results'] = $data;
		}
		header('Content-type: application/json; charset=utf-8');
		echo json_encode($response);
		die();
	}
}
