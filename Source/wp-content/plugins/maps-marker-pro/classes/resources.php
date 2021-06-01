<?php
namespace MMP;

class Resources {
	/**
	 * Registers the hooks
	 *
	 * @since 4.0
	 */
	public function init() {
		add_action('wp_enqueue_scripts', array($this, 'register_frontend_resources'));
		add_action('admin_enqueue_scripts', array($this, 'register_backend_resources'));
		add_action('wp_enqueue_media', array($this, 'register_media_resources'));
		add_action('enqueue_block_editor_assets', array($this, 'register_block_resources'));
	}

	/**
	 * Registers the resources used on the front end
	 *
	 * @since 4.0
	 */
	public function register_frontend_resources() {
		$l10n = Maps_Marker_Pro::get_instance('MMP\L10n');

		wp_register_style('mapsmarkerpro', plugins_url('css/mapsmarkerpro.css', __DIR__), array(), Maps_Marker_Pro::$version);
		wp_register_style('mapsmarkerpro-rtl', plugins_url('css/mapsmarkerpro-rtl.css', __DIR__), array('mapsmarkerpro'), Maps_Marker_Pro::$version);

		wp_register_script('mmp-googlemaps', $this->get_google_maps_url(), array(), null, true);
		wp_register_script('mapsmarkerpro', plugins_url('js/mapsmarkerpro.js', __DIR__), array(), Maps_Marker_Pro::$version, true);
		wp_localize_script('mapsmarkerpro', 'ajaxurl', get_admin_url(null, 'admin-ajax.php'));
		wp_localize_script('mapsmarkerpro', 'mmpVars', $this->get_plugin_vars());
		wp_localize_script('mapsmarkerpro', 'mmpL10n', $l10n->map_strings());
	}

	/**
	 * Registers the resources used on the back end
	 *
	 * @since 4.0
	 */
	public function register_backend_resources() {
		$l10n = Maps_Marker_Pro::get_instance('MMP\L10n');

		wp_register_style('mapsmarkerpro', plugins_url('css/mapsmarkerpro.css', __DIR__), array(), Maps_Marker_Pro::$version);
		wp_register_style('mapsmarkerpro-rtl', plugins_url('css/mapsmarkerpro-rtl.css', __DIR__), array(), Maps_Marker_Pro::$version);
		wp_register_style('mmp-admin', plugins_url('css/admin.css', __DIR__), array(), Maps_Marker_Pro::$version);
		wp_register_style('mmp-admin-rtl', plugins_url('css/admin-rtl.css', __DIR__), array(), Maps_Marker_Pro::$version);
		wp_register_style('mmp-leaflet-pm', plugins_url('css/leaflet-pm.css', __DIR__), array(), Maps_Marker_Pro::$version);
		wp_register_style('mmp-dashboard', plugins_url('css/dashboard.css', __DIR__), array(), Maps_Marker_Pro::$version);

		wp_register_script('mmp-googlemaps', $this->get_google_maps_url(), array(), null, true);
		wp_register_script('mapsmarkerpro', plugins_url('js/mapsmarkerpro.js', __DIR__), array(), Maps_Marker_Pro::$version, true);
		wp_localize_script('mapsmarkerpro', 'mmpVars', $this->get_plugin_vars());
		wp_localize_script('mapsmarkerpro', 'mmpL10n', $l10n->map_strings());
		wp_register_script('mmp-admin', plugins_url('js/admin.js', __DIR__), array('jquery', 'jquery-ui-dialog', 'jquery-ui-sortable'), Maps_Marker_Pro::$version, true);
		wp_localize_script('mmp-admin', 'mmpAdminVars', $this->get_plugin_vars());
		wp_localize_script('mmp-admin', 'mmpGeocoding', $this->geocoding_settings());
		wp_localize_script('mmp-admin', 'mmpAdminL10n', $l10n->admin_strings());
		wp_register_script('mmp-leaflet-pm', plugins_url('js/leaflet-pm.js', __DIR__), array('mapsmarkerpro'), Maps_Marker_Pro::$version, true);
		wp_register_script('mmp-dashboard', plugins_url('js/dashboard.js', __DIR__), array('jquery'), Maps_Marker_Pro::$version, true);
	}

	/**
	 * Registers the resources used for the TinyMCE editor
	 *
	 * @since 4.0
	 */
	public function register_media_resources() {
		wp_register_style('mmp-shortcode', plugins_url('css/shortcode.css', __DIR__), array(), Maps_Marker_Pro::$version);

		wp_register_script('mmp-shortcode', plugins_url('js/shortcode.js', __DIR__), array('jquery', 'jquery-ui-dialog'), Maps_Marker_Pro::$version, true);
		wp_localize_script('mmp-shortcode', 'mmpShortcodeStrings', array(
			'shortcode' => (Maps_Marker_Pro::$settings['shortcode']) ? Maps_Marker_Pro::$settings['shortcode'] : 'mapsmarkerpro',
			'insertMap' => esc_html__('Insert map', 'mmp'),
			'cancel'    => esc_html__('Cancel', 'mmp'),
			'loading'   => esc_html__('Loading ...', 'mmp')
		));
	}

	/**
	 * Registers the resources used for the Gutenberg block editor
	 *
	 * @since 4.3
	 */
	public function register_block_resources() {
		$l10n = Maps_Marker_Pro::get_instance('MMP\L10n');

		wp_register_style('mmp-gb-block', plugins_url('css/block.css', __DIR__), array('wp-edit-blocks'), Maps_Marker_Pro::$version);

		wp_register_script('mmp-gb-block', plugins_url('js/block.js', __DIR__), array('wp-blocks', 'wp-element'), Maps_Marker_Pro::$version, true);
		wp_localize_script('mmp-gb-block', 'mmpGbVars', $this->gb_vars());
		wp_localize_script('mmp-gb-block', 'mmpGbL10n', $l10n->gb_strings());

		register_block_type('mmp/map', array(
			'editor_style'  => 'mmp-gb-block',
			'editor_script' => 'mmp-gb-block'
		));
	}

	/**
	 * Returns the URL for the Google Maps API
	 *
	 * @since 4.0
	 */
	private function get_google_maps_url() {
		$google_maps_url = 'https://maps.googleapis.com/maps/api/js?key=' . Maps_Marker_Pro::$settings['googleApiKey'];
		if (Maps_Marker_Pro::$settings['googleLanguage'] !== 'browser_setting') {
			$google_maps_url .= '&language=';
			if (Maps_Marker_Pro::$settings['googleLanguage'] === 'wordpress_setting') {
				$google_maps_url .= substr(get_locale(), 0, 2);
			} else {
				$google_maps_url .= Maps_Marker_Pro::$settings['googleLanguage'];
			}
		}

		return $google_maps_url;
	}

	/**
	 * Returns the plugin vars needed for JavaScript
	 *
	 * @since 4.0
	 */
	private function get_plugin_vars() {
		$api = Maps_Marker_Pro::get_instance('MMP\API');

		return array(
			'baseUrl'   => $api->base_url,
			'slug'      => $api->slug,
			'apiUrl'    => $api->base_url . $api->slug . '/',
			'adminUrl'  => get_admin_url(),
			'pluginUrl' => plugins_url('/', __DIR__),
			'iconsUrl'  => Maps_Marker_Pro::$icons_url,
			'shortcode' => (Maps_Marker_Pro::$settings['shortcode']) ? Maps_Marker_Pro::$settings['shortcode'] : 'mapsmarkerpro'
		);
	}

	/**
	 * Returns geocoding settings
	 *
	 * @since 4.0
	 */
	private function geocoding_settings() {
		$locale = get_locale();

		if (Maps_Marker_Pro::$settings['geocodingPhotonLanguage'] == 'automatic') {
			$photon_language = strtolower(substr($locale, 0,2));
			if (!in_array($photon_language, array('de', 'fr', 'it'))) {
				$photon_language = 'en';
			}
		} else {
			$photon_language = Maps_Marker_Pro::$settings['geocodingPhotonLanguage'];
		}

		$footer_tips = '<a href="https://mapsmarker.com/geocoding-optimization/" target="_blank" title="' . esc_attr__('Show tutorial at mapsmarker.com', 'mmp') . '">' . esc_html__('Tip: adjust geocoding settings for more targeted search results', 'mmp') . '</a><br />';

		$settings = array(
			'algolia' => array(
				'appId' => Maps_Marker_Pro::$settings['geocodingAlgoliaAppId'],
				'apiKey' => Maps_Marker_Pro::$settings['geocodingAlgoliaApiKey'],
				'language' => (Maps_Marker_Pro::$settings['geocodingAlgoliaLanguage']) ? Maps_Marker_Pro::$settings['geocodingAlgoliaLanguage'] : substr($locale, 0, 2),
				'countries' => Maps_Marker_Pro::$settings['geocodingAlgoliaCountries'],
				'aroundLatLngViaIP' => Maps_Marker_Pro::$settings['geocodingAlgoliaAroundLatLngViaIp'],
				'aroundLatLng' => Maps_Marker_Pro::$settings['geocodingAlgoliaAroundLatLng'],
				'footer' => '<div class="ap-footer">' . $footer_tips . 'Built by <a href="https://www.mapsmarker.com/algolia-places/" target="_blank" title="Search by Algolia" class="ap-footer-algolia"></a> using <a href="https://community.algolia.com/places/documentation.html#license" class="ap-footer-osm" target="_blank" title="Algolia Places data &copy; OpenStreetMap contributors"> data</a></div>'
			),
			'photon' => array(
				'language' => $photon_language,
				'locationbiaslat' => (Maps_Marker_Pro::$settings['geocodingPhotonBiasLat']) ? Maps_Marker_Pro::$settings['geocodingPhotonBiasLat'] : 'none',
				'locationbiaslon' => (Maps_Marker_Pro::$settings['geocodingPhotonBiasLon']) ? Maps_Marker_Pro::$settings['geocodingPhotonBiasLon'] : 'none',
				'filter' => (Maps_Marker_Pro::$settings['geocodingPhotonFilter']) ? Maps_Marker_Pro::$settings['geocodingPhotonFilter'] : 'none',
				'footer' => '<div class="ap-footer">' . $footer_tips . '<div style="float:right;"><a href="https://www.mapsmarker.com/photon/" target="_blank"><img src="' . plugins_url('images/geocoding/photon-mapsmarker-small.png', __DIR__) . '" width="144" height="23"/></a></div><div style="float:right;margin:4px 5px 0 0;"><a href="https://www.mapsmarker.com/photon/" target="_blank">Powered by </a></div></div>'
			),
			'locationiq' => array(
				'apiKey' => Maps_Marker_Pro::$settings['geocodingLocationIqApiKey'],
				'bounds' => Maps_Marker_Pro::$settings['geocodingLocationIqBounds'],
				'lat1' => Maps_Marker_Pro::$settings['geocodingLocationIqBoundsLat1'],
				'lon1' => Maps_Marker_Pro::$settings['geocodingLocationIqBoundsLon1'],
				'lat2' => Maps_Marker_Pro::$settings['geocodingLocationIqBoundsLat2'],
				'lon2' => Maps_Marker_Pro::$settings['geocodingLocationIqBoundsLon2'],
				'language' => (Maps_Marker_Pro::$settings['geocodingLocationIqLanguage']) ? Maps_Marker_Pro::$settings['geocodingLocationIqLanguage'] : substr($locale, 0, 2),
				'footer' => '<div class="ap-footer">' . $footer_tips . '<a href="https://www.mapsmarker.com/locationiq-geocoding/" target="_blank">Powered by LocationIQ</a></div>'
			),
			'mapquest' => array(
				'api_key' => Maps_Marker_Pro::$settings['geocodingMapQuestApiKey'],
				'boundingBox' => Maps_Marker_Pro::$settings['geocodingMapQuestBounds'],
				'lat1' => Maps_Marker_Pro::$settings['geocodingMapQuestBoundsLat1'],
				'lon1' => Maps_Marker_Pro::$settings['geocodingMapQuestBoundsLon1'],
				'lat2' => Maps_Marker_Pro::$settings['geocodingMapQuestBoundsLat2'],
				'lon2' => Maps_Marker_Pro::$settings['geocodingMapQuestBoundsLon2'],
				'footer' => '<div class="ap-footer">' . $footer_tips . '<div style="float:right;"><a href="https://www.mapsmarker.com/mapquest-geocoding/" target="_blank"><img src="' . plugins_url('images/geocoding/mapquest-logo-small.png', __DIR__) . '" width="144" height="26"/></a></div><div style="float:right;margin:6px 5px 0 0;"><a href="https://www.mapsmarker.com/mapquest-geocoding" target="_blank">Powered by </a></div></div>'
			),
			'google' => array(
				'nonce' => wp_create_nonce('google-places'),
				'footer' => '<div class="ap-footer">' . $footer_tips . '<a href="https://www.mapsmarker.com/google-geocoding/" target="_blank"><img src="' . plugins_url('images/geocoding/powered-by-google.png', __DIR__) . '" width="144" height="18" /></a></div>'
			),
			'fallback' => str_replace("-", "_", Maps_Marker_Pro::$settings['geocodingProviderFallback']),
			'header' => esc_html__('To select a location, please click on a result or press', 'mmp')
		);

		return $settings;
	}

	/**
	 * Returns the Gutenberg vars needed for JavaScript
	 *
	 * @since 4.3
	 */
	private function gb_vars() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		$maps = $db->get_all_maps(false, array(
			'orderby' => 'id',
			'sortorder' => 'desc'
		));
		$data = array();
		foreach ($maps as $map) {
			$data[] = array(
				'id'   => $map->id,
				'name' => "[ID {$map->id}] " . (($map->name) ? esc_html($map->name) : esc_html__('(no name)', 'mmp'))
			);
		}

		return array(
			'iconUrl'    => plugins_url('images/logo-mapsmarker-pro.svg', __DIR__),
			'shortcode'  => Maps_Marker_Pro::$settings['shortcode'],
			'maps'       => $data
		);
	}
}
