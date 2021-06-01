<?php
namespace MMP;

class Map {
	/**
	 * Registers the hooks
	 *
	 * @since 4.0
	 */
	public function init() {
		add_filter('mmp_popup', array($this, 'popup'));

		add_action('wp_ajax_mmp_map_settings', array($this, 'map_settings'));
		add_action('wp_ajax_nopriv_mmp_map_settings', array($this, 'map_settings'));
		add_action('wp_ajax_mmp_map_markers', array($this, 'map_markers'));
		add_action('wp_ajax_nopriv_mmp_map_markers', array($this, 'map_markers'));
		add_action('wp_ajax_mmp_map_geojson', array($this, 'map_geojson'));
		add_action('wp_ajax_nopriv_mmp_map_geojson', array($this, 'map_geojson'));
		add_action('wp_ajax_mmp_marker_settings', array($this, 'marker_settings'));
		add_action('wp_ajax_nopriv_mmp_marker_settings', array($this, 'marker_settings'));
	}

	/**
	 * Modifies the popup content before outputting it
	 *
	 * @since 4.0
	 *
	 * @param string $popup The contents of the popup
	 */
	public function popup($popup) {
		global $allowedposttags;

		$popup = do_shortcode($popup);
		if (Maps_Marker_Pro::$settings['popupKses']) {
			add_filter('safe_style_css', function($styles) {
				$styles[] = 'display';

				return $styles;
			});
			$additionaltags = array(
				'iframe' => array(
					'id' => true,
					'name' => true,
					'src' => true,
					'class' => true,
					'style' => true,
					'frameborder' => true,
					'scrolling' => true,
					'align' => true,
					'width' => true,
					'height' => true,
					'marginwidth' => true,
					'marginheight' => true,
					'allowfullscreen' => true
				),
				'style' => array(
					'media' => true,
					'scoped' => true,
					'type' => true
				),
				'form' => array(
					'action' => true,
					'accept' => true,
					'accept-charset' => true,
					'enctype' => true,
					'method' => true,
					'name' => true,
					'target' => true
				),
				'input' => array(
					'accept' => true,
					'align' => true,
					'alt' => true,
					'autocomplete' => true,
					'autofocus' => true,
					'checked' => true,
					'dirname' => true,
					'disabled' => true,
					'form' => true,
					'formaction' => true,
					'formenctype' => true,
					'formmethod' => true,
					'formnovalidate' => true,
					'formtarget' => true,
					'height' => true,
					'id' => true,
					'list' => true,
					'max' => true,
					'maxlength' => true,
					'min' => true,
					'multiple' => true,
					'name' => true,
					'pattern' => true,
					'placeholder' => true,
					'readonly' => true,
					'required' => true,
					'size' => true,
					'src' => true,
					'step' => true,
					'type' => true,
					'value' => true,
					'width' => true
				),
				'source' => array(
					'type' => true,
					'src' => true
				)
			);
			$popup = wp_kses($popup, array_merge($allowedposttags, $additionaltags));
		}

		return wpautop($popup);
	}

	public function map_settings() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');
		$l10n = Maps_Marker_Pro::get_instance('MMP\L10n');
		$mmp_settings = Maps_Marker_Pro::get_instance('MMP\Settings');

		$type = (isset($_POST['type'])) ? $_POST['type'] : null;
		$id = (isset($_POST['id'])) ? absint($_POST['id']) : null;

		if ($type === 'map' || $type === 'custom') {
			if ($id) {
				$map = $db->get_map($id);
				$settings = $mmp_settings->validate_map_settings(json_decode($map->settings, true));
				$settings['name'] = esc_html($l10n->__($map->name, "Map (ID {$id}) name"));
				if ($type === 'map') {
					$settings['filtersDetails'] = json_decode($map->filters, true);
				} else {
					$settings['filtersDetails'] = array();
				}
			} else {
				$settings = $mmp_settings->get_map_defaults();
				$settings['name'] = '';
				$settings['filtersDetails'] = array();
			}
		} else if ($type === 'marker') {
			if ($id) {
				$marker = $db->get_marker($id);
				$settings = $mmp_settings->get_map_defaults();
				$settings['lat'] = $marker->lat;
				$settings['lng'] = $marker->lng;
				$settings['zoom'] = $marker->zoom;
				$settings['name'] = esc_html($l10n->__($marker->name, "Marker (ID {$id}) name"));
				$settings['filtersDetails'] = array();
			}
		}

		$settings['availableBasemaps'] = $mmp_settings->get_basemaps();
		$custom_basemaps = $db->get_all_basemaps();
		foreach ($custom_basemaps as $custom_basemap) {
			$settings['availableBasemaps'][$custom_basemap->id] = array(
				'type'    => 1,
				'wms'     => absint($custom_basemap->wms),
				'name'    => $custom_basemap->name,
				'url'     => $custom_basemap->url,
				'options' => json_decode($custom_basemap->options)
			);
		}

		$custom_overlays = $db->get_all_overlays();
		foreach ($custom_overlays as $custom_overlay) {
			$settings['availableOverlays'][$custom_overlay->id] = array(
				'wms'     => absint($custom_overlay->wms),
				'name'    => $custom_overlay->name,
				'url'     => $custom_overlay->url,
				'options' => json_decode($custom_overlay->options)
			);
		}

		$settings['errorTileUrl'] = plugins_url('images/error-tile-image.png', __DIR__);
		$settings['basemapBingCulture'] = (Maps_Marker_Pro::$settings['bingCulture'] === 'automatic') ? str_replace('_', '-', get_locale()) : Maps_Marker_Pro::$settings['bingCulture'];
		$settings['basemapGoogleStyles'] = json_decode($settings['basemapGoogleStyles']);

		if (Maps_Marker_Pro::$settings['backlinks']) {
			if (Maps_Marker_Pro::$settings['affiliateId'] === '') {
				$suffix = 'welcome';
			} else {
				$suffix = Maps_Marker_Pro::$settings['affiliateId'] . '.html';
			}
			$prefix = '<a href="https://www.mapsmarker.com/' . $suffix . '" target="_blank" title="' . esc_attr__('Maps Marker Pro - #1 mapping plugin for WordPress', 'mmp') . '">MapsMarker.com</a> (<a href="http://www.leafletjs.com" target="_blank" title="' . sprintf(esc_attr__('%1$s is based on Leaflet.js maintained by Vladimir Agafonkin', 'mmp'), 'Maps Marker Pro') . '">Leaflet</a>/<a href="https://mapicons.mapsmarker.com" target="_blank" title="' . sprintf(esc_attr__('%1$s uses icons from the Maps Icons Collection maintained by Nicolas Mollet', 'mmp'), 'Maps Marker Pro') . '">Icons</a>)';
		} else {
			$prefix = '';
		}

		if (Maps_Marker_Pro::$settings['googleLanguage'] === 'browser_setting') {
			$google_language = '';
		} else if (Maps_Marker_Pro::$settings['googleLanguage'] === 'wordpress_setting') {
			$google_language = substr(get_locale(), 0, 2);
		} else {
			$google_language = Maps_Marker_Pro::$settings['googleLanguage'];
		}

		$globals = array(
			'language' => ($l10n->ml) ? ICL_LANGUAGE_CODE : false,
			'panelEdit' => current_user_can('mmp_edit_other_maps'),
			'listEdit' => current_user_can('mmp_edit_other_markers'),
			'attributionPrefix' => $prefix,
			'directionsProvider' => Maps_Marker_Pro::$settings['directionsProvider'],
			'directionsGoogleType' => Maps_Marker_Pro::$settings['directionsGoogleType'],
			'directionsGoogleTraffic' => Maps_Marker_Pro::$settings['directionsGoogleTraffic'],
			'directionsGoogleUnits' => Maps_Marker_Pro::$settings['directionsGoogleUnits'],
			'directionsGoogleAvoidHighways' => Maps_Marker_Pro::$settings['directionsGoogleAvoidHighways'],
			'directionsGoogleAvoidTolls' => Maps_Marker_Pro::$settings['directionsGoogleAvoidTolls'],
			'directionsGooglePublicTransport' => Maps_Marker_Pro::$settings['directionsGooglePublicTransport'],
			'directionsGoogleWalking' => Maps_Marker_Pro::$settings['directionsGoogleWalking'],
			'directionsGoogleLanguage' => $google_language,
			'directionsGoogleOverview' => Maps_Marker_Pro::$settings['directionsGoogleOverview'],
			'directionsYoursType' => Maps_Marker_Pro::$settings['directionsYoursType'],
			'directionsYoursRoute' => Maps_Marker_Pro::$settings['directionsYoursRoute'],
			'directionsYoursLayer' => Maps_Marker_Pro::$settings['directionsYoursLayer'],
			'directionsOrsRoute' => Maps_Marker_Pro::$settings['directionsOrsRoute'],
			'directionsOrsType' => Maps_Marker_Pro::$settings['directionsOrsType'],
			'googleApiKey' => Maps_Marker_Pro::$settings['googleApiKey'],
			'bingApiKey' => Maps_Marker_Pro::$settings['bingApiKey'],
			'hereAppId' => Maps_Marker_Pro::$settings['hereAppId'],
			'hereAppCode' => Maps_Marker_Pro::$settings['hereAppCode'],
			'iconSize' => array(Maps_Marker_Pro::$settings['iconSizeX'], Maps_Marker_Pro::$settings['iconSizeY']),
			'iconAnchor' => array(Maps_Marker_Pro::$settings['iconAnchorX'], Maps_Marker_Pro::$settings['iconAnchorY']),
			'popupAnchor' => array(Maps_Marker_Pro::$settings['iconPopupAnchorX'], Maps_Marker_Pro::$settings['iconPopupAnchorY']),
			'shadowUrl' => (Maps_Marker_Pro::$settings['iconCustomShadow'] === 'custom') ? Maps_Marker_Pro::$settings['iconCustomShadowUrl'] : plugins_url('images/leaflet/marker-shadow.png', __DIR__),
			'shadowSize' => array(Maps_Marker_Pro::$settings['iconShadowSizeX'], Maps_Marker_Pro::$settings['iconShadowSizeY']),
			'shadowAnchor' => array(Maps_Marker_Pro::$settings['iconShadowAnchorX'], Maps_Marker_Pro::$settings['iconShadowAnchorY'])
		);

		wp_send_json(array_merge($globals, $settings));
	}

	public function map_markers() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');
		$l10n = Maps_Marker_Pro::get_instance('MMP\L10n');

		$type = (isset($_POST['type'])) ? $_POST['type'] : null;
		$id = (isset($_POST['id'])) ? $_POST['id'] : null;
		$custom = (isset($_POST['custom'])) ? $_POST['custom'] : null;
		$all = (isset($_POST['all']) && $_POST['all'] == 'true') ? true : false;

		if (!$id || $id === 'new') {
			wp_send_json(array());
		}

		if ($type === 'map') {
			if ($all) {
				$filters = array();
			} else {
				$filters = array('include_maps' => $id);
			}
		} else if ($type === 'marker') {
			$filters = array('include' => $id);
		} else if ($type === 'custom') {
			$filters = array('include' => $custom);
		}

		$data = array();
		$total = $db->count_markers($filters);
		$batches = ceil($total / 1000);
		for ($i = 1; $i <= $batches; $i++) {
			$filters = array_merge($filters, array(
				'offset' => ($i - 1) * 1000,
				'limit' => 1000
			));
			$markers = $db->get_all_markers($filters);
			foreach ($markers as $marker) {
				$data[$marker->id] = array(
					'lat' => $marker->lat,
					'lng' => $marker->lng,
					'name' => $l10n->__($marker->name, "Marker (ID {$marker->id}) name"),
					'address' => $l10n->__($marker->address, "Marker (ID {$marker->id}) address"),
					'popup' => apply_filters('mmp_popup', $l10n->__($marker->popup, "Marker (ID {$marker->id}) popup")),
					'link' => $marker->link,
					'blank' => $marker->blank,
					'icon' => $marker->icon,
					'created' => strtotime($marker->created_on),
					'updated' => strtotime($marker->updated_on),
					'maps' => explode(',', $marker->maps)
				);
			}
		}

		wp_send_json($data);
	}

	public function map_geojson() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		$type = (isset($_POST['type'])) ? $_POST['type'] : null;
		$id = (isset($_POST['id'])) ? absint($_POST['id']) : null;

		if ($id && ($type === 'map' || $type === 'custom')) {
			$map = $db->get_map($id);
			$geojson = json_decode($map->geojson);
		} else {
			$geojson = json_decode('{}');
		}

		wp_send_json($geojson);
	}

	public function marker_settings() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');
		$mmp_settings = Maps_Marker_Pro::get_instance('MMP\Settings');

		$id = (isset($_POST['id'])) ? absint($_POST['id']) : null;
		$basemap = isset($_POST['basemap']) ? preg_replace('/[^0-9A-Za-z]/', '', $_POST['basemap']) : null;
		$lat = (isset($_POST['lat'])) ? floatval($_POST['lat']) : null;
		$lng = (isset($_POST['lng'])) ? floatval($_POST['lng']) : null;
		$zoom = (isset($_POST['zoom'])) ? abs(floatval($_POST['zoom'])) : null;

		if ($id) {
			$marker = $db->get_marker($id);
			$settings = $mmp_settings->get_marker_defaults();
			$settings['name'] = $marker->name;
			$settings['address'] = $marker->address;
			$settings['lat'] = $marker->lat;
			$settings['lng'] = $marker->lng;
			$settings['zoom'] = $marker->zoom;
			$settings['popup'] = $marker->popup;
			$settings['link'] = $marker->link;
			$settings['blank'] = $marker->blank;
			$settings['icon'] = $marker->icon;
			$settings['maps'] = $db->sanitize_ids($marker->maps);
		} else {
			$settings = $mmp_settings->get_marker_defaults();
			$settings['basemap'] = ($basemap) ? $basemap : $settings['basemap'];
			$settings['name'] = '';
			$settings['address'] = '';
			$settings['lat'] = ($lat) ? $lat : $settings['lat'];
			$settings['lng'] = ($lng) ? $lng : $settings['lng'];
			$settings['zoom'] = ($zoom) ? $zoom : $settings['zoom'];
			$settings['popup'] = '';
			$settings['link'] = '';
			$settings['blank'] = '1';
			$settings['maps'] = array();
		}

		$settings['availableBasemaps'] = $mmp_settings->get_basemaps();
		$custom_basemaps = $db->get_all_basemaps();
		foreach ($custom_basemaps as $custom_basemap) {
			$settings['availableBasemaps'][$custom_basemap->id] = array(
				'type'    => 1,
				'wms'     => absint($custom_basemap->wms),
				'name'    => $custom_basemap->name,
				'url'     => $custom_basemap->url,
				'options' => json_decode($custom_basemap->options)
			);
		}

		$globals = array(
			'googleApiKey' => Maps_Marker_Pro::$settings['googleApiKey'],
			'bingApiKey' => Maps_Marker_Pro::$settings['bingApiKey'],
			'bingCulture' => (Maps_Marker_Pro::$settings['bingCulture'] === 'automatic') ? str_replace('_', '-', get_locale()) : Maps_Marker_Pro::$settings['bingCulture'],
			'hereAppId' => Maps_Marker_Pro::$settings['hereAppId'],
			'hereAppCode' => Maps_Marker_Pro::$settings['hereAppCode']
		);

		wp_send_json(array(
			'settings' => array_merge($globals, $settings)
		));
	}
}
