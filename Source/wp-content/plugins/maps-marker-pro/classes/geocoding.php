<?php
namespace MMP;

class Geocoding {
	/**
	 * Geocode an address
	 */
	public function getLatLng($address, $provider = null) {
		$address = remove_accents($address);
		$provider = ($provider) ? $provider : Maps_Marker_Pro::$settings['geocodingProvider'];

		switch ($provider) {
			case 'photon':
				return $this->photon($address);
			case 'locationiq':
				return $this->locationiq($address);
			case 'mapquest':
				return $this->mapquest($address);
			case 'google':
				return $this->google($address);
			case 'algolia':
			default:
				return $this->algolia($address);
		}
	}

	/**
	 * Algolia Places
	 */
	public function algolia($address) {
		$params = array(
			'query' => $address,
			'language' => (Maps_Marker_Pro::$settings['geocodingAlgoliaLanguage']) ? Maps_Marker_Pro::$settings['geocodingAlgoliaLanguage'] : substr(get_locale(), 0, 2),
			'countries' => Maps_Marker_Pro::$settings['geocodingAlgoliaCountries'],
			'aroundLatLngViaIP' => Maps_Marker_Pro::$settings['geocodingAlgoliaAroundLatLngViaIp'],
			'aroundLatLng' => Maps_Marker_Pro::$settings['geocodingAlgoliaAroundLatLng'],
			'hitsPerPage' => 1
		);
		$url = 'https://places-dsn.algolia.net/1/places/query?' . http_build_query($params);

		$response = wp_remote_get($url, array(
			'sslverify' => false,
			'timeout' => 10,
			'headers' => array(
				'X-Algolia-Application-Id' => Maps_Marker_Pro::$settings['geocodingAlgoliaAppId'],
				'X-Algolia-API-Key' => Maps_Marker_Pro::$settings['geocodingAlgoliaApiKey']
			)
		));

		if (is_wp_error($response)) {
			return array(
				'success' => false,
				'message' => $response->get_error_message()
			);
		}

		$body = json_decode($response['body'], true);
		if ($response['response']['code'] === 200 && isset($body['nbHits']) && $body['nbHits'] > 0) {
			return array(
				'success' => true,
				'lat' => $body['hits'][0]['_geoloc']['lat'],
				'lon' => $body['hits'][0]['_geoloc']['lng'],
				'address' => $this->format_address('algolia', $body['hits'][0]),
				'rate_limit' => sprintf(esc_html__('Rate Limit: %1$s/day', 'mmp'), 1000)
			);
		} else {
			return array(
				'success' => false,
				'message' => $body['message']
			);
		}
	}

	/**
	 * Photon
	 */
	public function photon($address) {
		$locale = get_locale();

		if (Maps_Marker_Pro::$settings['geocodingPhotonLanguage'] === 'automatic') {
			$locale_for_photon = strtolower(substr($locale, 0, 2));
			$photon_language = (in_array($locale_for_photon, array('de', 'fr', 'it'))) ? $locale_for_photon : 'en';
		} else {
			$photon_language = Maps_Marker_Pro::$settings['geocodingPhotonLanguage'];
		}

		$params = array(
			'q' => $address,
			'limit' => 1,
			'lang' => $photon_language,
			'lat' => Maps_Marker_Pro::$settings['geocodingPhotonBiasLat'],
			'lon' => Maps_Marker_Pro::$settings['geocodingPhotonBiasLon'],
			'osm_tag' => Maps_Marker_Pro::$settings['geocodingPhotonFilter']
		);
		$url = 'https://photon.mapsmarker.com/pro/api?'. http_build_query($params);

		$response = wp_remote_get($url, array(
			'sslverify' => false,
			'timeout' => 10
		));

		if (is_wp_error($response)) {
			return array(
				'success' => false,
				'message' => $response->get_error_message()
			);
		}

		$body = json_decode($response['body'], true);
		if ($response['response']['code'] === 200 && isset($body['features'][0]['geometry']['coordinates'][1])) {
			return array(
				'success' => true,
				'lat' => $body['features'][0]['geometry']['coordinates'][1],
				'lon' => $body['features'][0]['geometry']['coordinates'][0],
				'address' => $this->format_address('photon', $body['features'][0]),
				'rate_limit' => sprintf(esc_html__('Rate Limit: %1$s out of %2$s/day', 'mmp'), $response['headers']['x-ratelimit-remaining-day'], $response['headers']['x-ratelimit-limit-day'])
			);
		} else {
			return array(
				'success' => false,
				'message' => $body['message']
			);
		}
	}

	/**
	 * LocationIQ
	 */
	public function locationiq($address) {
		$params = array(
			'q' => $address,
			'format' => 'json',
			'addressdetails' => 1,
			'normalizecity' => 1,
			'limit' => 1
		);

		if (Maps_Marker_Pro::$settings['geocodingLocationIqBounds']) {
			$params['viewbox'] = Maps_Marker_Pro::$settings['geocodingLocationIqBoundsLon1'] . ',' . Maps_Marker_Pro::$settings['geocodingLocationIqBoundsLat1'] . ',' . Maps_Marker_Pro::$settings['geocodingLocationIqBoundsLon2'] . ',' . Maps_Marker_Pro::$settings['geocodingLocationIqBoundsLat2'];
		}
		if (Maps_Marker_Pro::$settings['geocodingLocationIqLanguage']) {
			$params['accept-language'] = Maps_Marker_Pro::$settings['geocodingLocationIqLanguage'];
		} else {
			$params['accept-language'] = substr(get_locale(), 0, 2);
		}
		if (Maps_Marker_Pro::$settings['geocodingLocationIqApiKey']) {
			$params['key'] = Maps_Marker_Pro::$settings['geocodingLocationIqKey'];
		}
		$url = 'https://us1.locationiq.com/v1/search.php?'. http_build_query($params);

		$response = wp_remote_get($url, array(
			'sslverify' => false,
			'timeout' => 10
		));

		if (is_wp_error($response)) {
			return array(
				'success' => false,
				'message' => $response->get_error_message()
			);
		}

		$body = json_decode($response['body'], true);
		if ($response['response']['code'] === 200 && isset($body[0]['lat'])) {
			return array(
				'success' => true,
				'lat' => $body[0]['lat'],
				'lon' => $body[0]['lon'],
				'address' => $this->format_address('locationiq', $body[0]['address']),
				'rate_limit' => sprintf(esc_html__('Rate Limit: %1$s/day', 'mmp'), 10000)
			);
		} else {
			return array(
				'success' => false,
				'message' => $body['message']
			);
		}
	}

	/**
	 * MapQuest
	 */
	public function mapquest($address) {
		$params = array(
			'location' => $address,
			'maxResults' => 1,
		);

		if (Maps_Marker_Pro::$settings['geocodingMapQuestBounds']) {
			$params['boundingBox'] = Maps_Marker_Pro::$settings['geocodingMapQuestBoundsLat1'] . ',' . Maps_Marker_Pro::$settings['geocodingMapQuestBoundsLon1'] . ',' . Maps_Marker_Pro::$settings['geocodingMapQuestBoundsLat2'] . ',' . Maps_Marker_Pro::$settings['geocodingMapQuestBoundsLon2'];
		}
		if (Maps_Marker_Pro::$settings['geocodingMapQuestApiKey']) {
			$params['key'] = Maps_Marker_Pro::$settings['geocodingMapQuestApiKey'];
		}
		$url = 'https://www.mapquestapi.com/geocoding/v1/address?'. http_build_query($params);

		$response = wp_remote_get($url, array(
			'sslverify' => false,
			'timeout' => 10
		));

		if (is_wp_error($response)) {
			return array(
				'success' => false,
				'message' => $response->get_error_message()
			);
		}

		$body = json_decode($response['body'], true);
		if ($response['response']['code'] === 200 && isset($body['results'])) {
			return array(
				'success' => true,
				'lat' => $body['results'][0]['locations'][0]['latLng']['lat'],
				'lon' => $body['results'][0]['locations'][0]['latLng']['lng'],
				'address' => $this->format_address('mapquest', $body['results'][0]['locations'][0]),
				'rate_limit' => sprintf(esc_html__('Rate Limit: %1$s/month', 'mmp'), 15000)
			);
		} else {
			return array(
				'success' => false,
				'message' => $body['message']
			);
		}
	}

	/**
	 * Google
	 */
	public function google($address) {
		if (Maps_Marker_Pro::$settings['geocodingGoogleAuthMethod'] == 'clientid-signature') {
			$google_api_key = '';
			$gmapsbusiness_client = '&client=' . Maps_Marker_Pro::$settings['geocodingGoogleClient'];
			$gmapsbusiness_signature = '&signature=' . Maps_Marker_Pro::$settings['geocodingGoogleSignature'];
			$gmapsbusiness_channel = '&channel=' . Maps_Marker_Pro::$settings['geocodingGoogleChannel'];
		} else {
			$google_api_key = '&key=' . Maps_Marker_Pro::$settings['geocodingGoogleApiKey'];
			$gmapsbusiness_client = '';
			$gmapsbusiness_signature = '';
			$gmapsbusiness_channel = '';
		}
		$url = 'https://maps.googleapis.com/maps/api/geocode/xml?address=' . $address . $google_api_key . $gmapsbusiness_client . $gmapsbusiness_signature . $gmapsbusiness_channel;

		$response = wp_remote_get($url, array(
			'sslverify' => false,
			'timeout' => 10
		));

		if (is_wp_error($response)) {
			return array(
				'success' => false,
				'message' => $response->get_error_message()
			);
		}

		$xml = simplexml_load_string($response['body']);
		switch ($xml->status) {
			case 'OK':
				return array(
					'success' => true,
					'lat' => $xml->result[0]->geometry->location->lat,
					'lon' => $xml->result[0]->geometry->location->lng,
					'address' => $xml->result[0]->formatted_address,
					'rate_limit' => sprintf(esc_html__('Rate Limit: %1$s/day', 'mmp'), 2500)
				);
			case 'OVER_QUERY_LIMIT':
			case 'REQUEST_DENIED':
			case 'INVALID_REQUEST':
			case 'UNKNOWN_ERROR':
			default:
				return array(
					'success' => false,
					'message' => $xml->status . ' - ' . $xml->error_message
				);
		}
	}

	/**
	 * Format address
	 */
	public function format_address($provider, $data) {
		switch ($provider) {
			case 'algolia':
				$language = (Maps_Marker_Pro::$settings['geocodingAlgoliaLanguage']) ? Maps_Marker_Pro::$settings['geocodingAlgoliaLanguage'] : substr(get_locale(), 0, 2);
				$administrative = $data['administrative'];
				$city = $data['city'];
				$country = $data['country'];
				$hit = $data;
				if (isset($hit['_highlightResult']['locale_names'][0])) {
					$name = $hit['_highlightResult']['locale_names'][0]['value'] . ',';
				} else if (isset($hit['_highlightResult']['locale_names'][$language][0])) {
					$name = $hit['_highlightResult']['locale_names'][$language][0]['value'] . ',';
				} else {
					$name = '';
				}
				$city = ($city) ? $hit['_highlightResult']['city'][0]['value'] : null;
				$administrative = ($administrative && isset($hit['_highlightResult']['administrative'])) ? $hit['_highlightResult']['administrative'][0]['value'] : null;
				$country = ($country)? $hit['_highlightResult']['country']['value'] : null;
				return strip_tags($name) . ' ' . (($administrative) ? $administrative . ',' : '') . ' ' . (($country) ? '' . $country : '');
			case 'photon':
				$country = (isset($data['properties']['country'])) ? $data['properties']['country'] : null;
				$city = (isset($data['properties']['city'])) ? $data['properties']['city'] : null;
				$housenumber = (isset($data['properties']['housenumber'])) ? $data['properties']['housenumber'] : null;
				$street = (isset($data['properties']['street'])) ? $data['properties']['street'] : null;
				$postcode = (isset($data['properties']['postcode'])) ? $data['properties']['postcode'] : null;
				$state = (isset($data['properties']['state'])) ? $data['properties']['state'] : null;
				$name = (isset($data['properties']['name'])) ? $data['properties']['name'] . ',' : null;
				return $name . ' ' . (($street) ? $street . (($housenumber) ? ' ' . $housenumber : '') . ', ' : '') . (($state) ? $state . ', ' : '') . (($country) ? '' . $country : '');
			case 'locationiq':
				$country = (isset($data['country'])) ? $data['country'] : null;
				$city = (isset($data['city'])) ? $data['city'] : null;
				$house_number = (isset($data['house_number'])) ? $data['house_number'] : null;
				$street = (isset($data['street'])) ? $data['street'] : null;
				$postcode = (isset($data['postcode'])) ? $data['postcode'] : null;
				$state = (isset($data['state'])) ? $data['state'] : null;
				$name = (isset($data['name'])) ? $data['name'] . ',' : null;
				return $name . ' ' . (($street) ? $street . (($house_number) ? ' ' . $house_number : '') . ', ' : '') . (($state) ? $state . ', ' : '') . (($country) ? '' . $country : '');
			case 'mapquest':
				$address = '';
				$address .= (isset($data['adminArea5']) && $data['adminArea5']) ? $data['adminArea5'] . ', ' : '';
				$address .= (isset($data['adminArea4']) && $data['adminArea4']) ? $data['adminArea4'] . ', ' : '';
				$address .= (isset($data['adminArea3']) && $data['adminArea3']) ? $data['adminArea3'] . ', ' : '';
				$address .= (isset($data['adminArea2']) && $data['adminArea2']) ? $data['adminArea2'] . ', ' : '';
				$address .= (isset($data['adminArea1']) && $data['adminArea1']) ? $data['adminArea1'] : '';
				return $address;
			default:
				return '';
		}
	}
}
