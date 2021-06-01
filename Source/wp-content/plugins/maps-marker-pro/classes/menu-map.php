<?php
namespace MMP;

class Menu_Map extends Menu {
	/**
	 * Registers the hooks
	 *
	 * @since 4.0
	 */
	public function init() {
		add_action('admin_enqueue_scripts', array($this, 'load_resources'));
		add_action('wp_ajax_mmp_save_map', array($this, 'save_map'));
		add_action('wp_ajax_mmp_save_map_defaults', array($this, 'save_map_defaults'));
		add_action('wp_ajax_mmp_advanced_settings_state', array($this, 'advanced_settings_state'));
		add_action('wp_ajax_mmp_delete_map_direct', array($this, 'delete_map'));
	}

	/**
	 * Loads the required resources
	 *
	 * @since 4.0
	 *
	 * @param string $hook The current admin page
	 */
	public function load_resources($hook) {
		if (substr($hook, -strlen('mapsmarkerpro_map')) !== 'mapsmarkerpro_map') {
			return;
		}

		$this->load_global_resources($hook);

		wp_enqueue_media();
		wp_enqueue_style('mapsmarkerpro');
		wp_enqueue_style('mmp-leaflet-pm');
		if (is_rtl()) {
			wp_enqueue_style('mapsmarkerpro-rtl');
		}
		if (Maps_Marker_Pro::$settings['googleApiKey']) {
			wp_enqueue_script('mmp-googlemaps');
		}
		wp_enqueue_script('mapsmarkerpro');
		wp_enqueue_script('mmp-leaflet-pm');
		wp_enqueue_script('mmp-admin');
	}

	/**
	 * Saves the map
	 *
	 * @since 4.0
	 */
	public function save_map() {
		global $wpdb, $current_user;
		$db = Maps_Marker_Pro::get_instance('MMP\DB');
		$mmp_settings = Maps_Marker_Pro::get_instance('MMP\Settings');

		$date = date('Y-m-d H:i:s');
		$settings = wp_unslash($_POST['settings']);
		$geojson = json_decode(wp_unslash($_POST['geoJson']));
		if (!isset($geojson->features) || !is_array($geojson->features) || !count($geojson->features)) {
			$geojson = null;
		}
		parse_str($settings, $settings);

		if (!isset($settings['nonce']) || wp_verify_nonce($settings['nonce'], 'mmp-map') === false) {
			wp_send_json(array(
				'success' => false,
				'response' => esc_html__('Security check failed', 'mmp')
			));
		}

		$id = $settings['id'];
		$name = $settings['name'];
		$settings['maxBounds'] = preg_replace('/[^0-9.,-]/', '', $settings['maxBounds']);
		$settings['basemaps'] = (isset($settings['basemaps'])) ? $settings['basemaps'] : array();
		$settings['overlays'] = (isset($settings['overlays'])) ? $settings['overlays'] : array();
		$index = 0;
		$filters = array();
		if (isset($settings['filtersList']) && is_array($settings['filtersList'])) {
			foreach ($settings['filtersList'] as $map_id => $filter) {
				$filters[$map_id] = array(
					'index' => $index++,
					'active' => (isset($filter['active'])) ? true : false,
					'name' => $filter['name'],
					'icon' => $filter['icon']
				);
			}
		}
		$settings = $mmp_settings->validate_map_settings($settings, false, false);
		$settings = json_encode($settings, JSON_FORCE_OBJECT);
		$filters = json_encode($filters, JSON_FORCE_OBJECT);
		$geojson = ($geojson === null) ? '{}' : json_encode($geojson);
		$data = array(
			'name' => $name,
			'settings' => $settings,
			'filters' => $filters,
			'geojson' => $geojson,
			'created_by' => $current_user->user_login,
			'created_on' => $date,
			'updated_by' => $current_user->user_login,
			'updated_on' => $date
		);
		if ($id === 'new') {
			$id = $db->add_map((object) $data);
		} else {
			$db->update_map((object) $data, $id);
		}
		wp_send_json(array(
			'success' => true,
			'response' => array(
				'id' => $id,
				'message' => esc_html__('Map saved successfully', 'mmp')
			)
		));
	}

	/**
	 * Saves the map defaults
	 *
	 * @since 4.0
	 */
	public function save_map_defaults() {
		$mmp_settings = Maps_Marker_Pro::get_instance('MMP\Settings');

		$settings = wp_unslash($_POST['settings']);
		parse_str($settings, $settings);

		if (!isset($settings['nonce']) || wp_verify_nonce($settings['nonce'], 'mmp-map') === false) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('Security check failed', 'mmp')
			));
		}

		$settings = $mmp_settings->validate_map_settings($settings, false, false);
		update_option('mapsmarkerpro_map_defaults', $settings);

		wp_die();
	}

	/**
	 * Saves the current state of the advanced editor toggle
	 *
	 * @since 4.0
	 */
	public function advanced_settings_state() {
		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-map') === false) {
			return;
		}
		if (!isset($_POST['state']) || ($_POST['state'] !== 'basic' && $_POST['state'] !== 'advanced')) {
			return;
		}
		update_option('mapsmarkerpro_editor', $_POST['state']);
		wp_die();
	}

	/**
	 * Deletes the map
	 *
	 * @since 4.0
	 */
	public function delete_map() {
		global $current_user;
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-map') === false) {
			return;
		}

		$id = absint($_POST['id']);
		if (!$id) {
			return;
		}

		$map = $db->get_map($id);
		if (!$map) {
			return;
		}

		if ($map->created_by !== $current_user->user_login && !current_user_can('mmp_delete_other_maps')) {
			return;
		}

		if (!isset($_POST['con']) || !$_POST['con']) {
			$message = sprintf(esc_html__('Are you sure you want to delete the map with ID %1$s?', 'mmp'), $id) . "\n";

			$shortcodes = $db->get_map_shortcodes($id);
			if (count($shortcodes)) {
				$message .= esc_html__('The map is used in the following content:', 'mmp') . "\n";
				foreach ($shortcodes as $shortcode) {
					$message .= $shortcode['title'] . "\n";
				}
			} else {
				$message .= esc_html__('The map is not used in any content.', 'mmp');
			}

			wp_send_json(array(
				'success' => true,
				'response'    => array(
					'id'      => $id,
					'confirm' => false,
					'message' => $message
				)
			));
		}

		$db->delete_map($id);

		wp_send_json(array(
			'success' => true,
			'response'    => array(
				'id'      => $id,
				'confirm' => true
			)
		));
	}

	/**
	 * Shows the map page
	 *
	 * @since 4.0
	 */
	protected function show() {
		global $current_user;
		$db = Maps_Marker_Pro::get_instance('MMP\DB');
		$upload = Maps_Marker_Pro::get_instance('MMP\Upload');
		$l10n = Maps_Marker_Pro::get_instance('MMP\L10n');
		$mmp = Maps_Marker_Pro::get_instance('MMP\Map');
		$mmp_settings = Maps_Marker_Pro::get_instance('MMP\Settings');

		$settings['id'] = (isset($_GET['id'])) ? absint($_GET['id']) : 'new';

		$shortcodes = $db->get_map_shortcodes($settings['id']);

		$maps = $db->get_all_maps(true);
		if ($settings['id'] !== 'new') {
			$map = $db->get_map($settings['id']);
			if (!$map) {
				$this->error(sprintf(esc_html__('A map with ID %1$s does not exist.', 'mmp'), $settings['id']));
				return;
			}
			if ($map->created_by !== $current_user->user_login && !current_user_can('mmp_delete_other_maps')) {
				$this->error(sprintf(esc_html__('You do not have the required capabilities to edit the map with ID %1$s.', 'mmp'), $id));
				return;
			}
			$filters = json_decode($map->filters);
			$settings['name'] = $map->name;
			$settings = array_merge($settings, $mmp_settings->validate_map_settings(json_decode($map->settings, true)));
		} else {
			$filters = json_decode('{}');
			$settings['name'] = '';
			$settings = array_merge($settings, $mmp_settings->get_map_defaults());
		}
		$basemaps = $mmp_settings->get_basemaps();
		$custom_basemaps = $db->get_all_basemaps();
		foreach ($custom_basemaps as $custom_basemap) {
			$basemaps[$custom_basemap->id] = array(
				'type'    => 1,
				'wms'     => absint($custom_basemap->wms),
				'name'    => $custom_basemap->name,
				'url'     => $custom_basemap->url,
				'options' => json_decode($custom_basemap->options)
			);
		}
		$overlays = array();
		$custom_overlays = $db->get_all_overlays();
		foreach ($custom_overlays as $custom_overlay) {
			$overlays[$custom_overlay->id] = array(
				'name'    => $custom_overlay->name,
				'url'     => $custom_overlay->url,
				'options' => json_decode($custom_overlay->options)
			);
		}
		$settings['geocodingMinChars'] = Maps_Marker_Pro::$settings['geocodingMinChars'];
		$settings['geocodingLocationIqApiKey'] = Maps_Marker_Pro::$settings['geocodingLocationIqApiKey'];
		$settings['geocodingMapQuestApiKey'] = Maps_Marker_Pro::$settings['geocodingMapQuestApiKey'];
		$settings['geocodingGoogleApiKey'] = Maps_Marker_Pro::$settings['geocodingGoogleApiKey'];
		$id = $settings['id'];

		wp_add_inline_script('mmp-admin', "var mmpAdmin = new MapsMarkerPro({'uid': 'admin', 'type': 'map', 'id': '{$id}', 'callback': editMapActions});");

		?>
		<div class="wrap mmp-wrap">
			<h1><?= ($settings['id'] !== 'new') ? esc_html__('Edit map', 'mmp') : esc_html__('Add map', 'mmp') ?></h1>
			<div class="mmp-main">
				<form id="mapSettings" method="POST">
					<input type="hidden" id="nonce" name="nonce" value="<?= wp_create_nonce('mmp-map') ?>" />
					<input type="hidden" id="id" name="id" value="<?= $settings['id'] ?>" />
					<div class="mmp-flexwrap">
						<div class="mmp-left">
							<div class="mmp-top-bar">
								<div class="mmp-top-bar-left">
									<button id="save" class="button button-primary" disabled="disabled"><?= esc_html__('Save', 'mmp') ?></button>
								</div>
								<div class="mmp-top-bar-right">
									<label class="switch">
										<input type="checkbox" id="advancedSettings" <?= !(get_option('mapsmarkerpro_editor') === 'advanced') ?: 'checked="checked"' ?> />
										<span class="slider"></span>
									</label>
									<span><?= esc_html__('Show advanced settings', 'mmp') ?></span>
								</div>
							</div>
							<div class="mmp-tabs">
								<button type="button" id="tabMap" class="mmp-tablink"><?= esc_html__('Map', 'mmp') ?></button>
								<button type="button" id="tabLayers" class="mmp-tablink"><?= esc_html__('Layers', 'mmp') ?></button>
								<button type="button" id="tabControl" class="mmp-tablink"><?= esc_html__('Controls', 'mmp') ?></button>
								<button type="button" id="tabMarker" class="mmp-tablink"><?= esc_html__('Markers', 'mmp') ?></button>
								<button type="button" id="tabFilter" class="mmp-tablink"><?= esc_html__('Filters', 'mmp') ?></button>
								<button type="button" id="tabList" class="mmp-tablink"><?= esc_html__('List', 'mmp') ?></button>
								<button type="button" id="tabInteraction" class="mmp-tablink"><?= esc_html__('Interaction', 'mmp') ?></button>
								<button type="button" id="tabGpx" class="mmp-tablink"><?= esc_html__('GPX', 'mmp') ?></button>
								<button type="button" id="tabDraw" class="mmp-tablink"><?= esc_html__('Draw', 'mmp') ?></button>
							</div>
							<div id="mmp-tabMap-settings" class="mmp-tab">
								<button type="button" id="fitMarkers" class="button button-secondary"><?= esc_html__('Fit all markers', 'mmp') ?></button>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="address"><?= esc_html__('Find a location', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="text" id="address" name="address" placeholder="<?= ($settings['geocodingMinChars'] < 2) ? esc_attr__('Start typing for suggestions', 'mmp') : sprintf(esc_attr__('Start typing for suggestions (%1$s characters minimum)', 'mmp'), $settings['geocodingMinChars']) ?>" /><br />
										<select id="geocodingProvider">
											<optgroup label="<?= esc_attr__('Available providers', 'mmp') ?>">
												<option value="algolia" <?= Maps_Marker_Pro::$settings['geocodingProvider'] !== 'algolia' ?: 'selected="selected"' ?>>Algolia Places</option>
												<option value="photon" <?= Maps_Marker_Pro::$settings['geocodingProvider'] !== 'photon' ?: 'selected="selected"' ?>>Photon@MapsMarker</option>
												<?php if ($settings['geocodingLocationIqApiKey']): ?>
													<option value="locationiq" <?= Maps_Marker_Pro::$settings['geocodingProvider'] !== 'locationiq' ?: 'selected="selected"' ?>>LocationIQ</option>
												<?php endif; ?>
												<?php if ($settings['geocodingMapQuestApiKey']): ?>
													<option value="mapquest" <?= Maps_Marker_Pro::$settings['geocodingProvider'] !== 'mapquest' ?: 'selected="selected"' ?>>MapQuest</option>
												<?php endif; ?>
												<?php if ($settings['geocodingGoogleApiKey']): ?>
													<option value="google" <?= Maps_Marker_Pro::$settings['geocodingProvider'] !== 'google' ?: 'selected="selected"' ?>>Google</option>
												<?php endif; ?>
											</optgroup>
											<?php if (!$settings['geocodingLocationIqApiKey'] || !$settings['geocodingMapQuestApiKey'] || !$settings['geocodingGoogleApiKey']): ?>
												<optgroup label="<?= esc_attr__('Inactive (API key required)', 'mmp') ?>">
													<?php if (!$settings['geocodingLocationIqApiKey']): ?>
														<option value="locationiq" disabled="disabled">LocationIQ</option>
													<?php endif; ?>
													<?php if (!$settings['geocodingMapQuestApiKey']): ?>
														<option value="mapquest" disabled="disabled">MapQuest</option>
													<?php endif; ?>
													<?php if (!$settings['geocodingGoogleApiKey']): ?>
														<option value="google" disabled="disabled">Google</option>
													<?php endif; ?>
												</optgroup>
											<?php endif; ?>
										</select>
										<a href="<?= get_admin_url(null, 'admin.php?page=mapsmarkerpro_settings#geocoding_provider') ?>" target="_blank"><?= esc_html__('Geocoding settings', 'mmp') ?></a>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="name"><?= esc_html__('Name', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="text" id="name" name="name" value="<?= esc_attr($settings['name']) ?>" />
										<?php if ($settings['id'] !== 'new'): ?>
											<br />
											<?php if ($l10n->ml === 'wpml'): ?>
												(<a href="<?= get_admin_url(null, 'admin.php?page=wpml-string-translation%2Fmenu%2Fstring-translation.php&context=Maps+Marker+Pro&search=' . urlencode($settings['name'])) ?>"><?= esc_html__('translate', 'mmp') ?></a>)
											<?php elseif ($l10n->ml === 'pll'): ?>
												(<a href="<?= get_admin_url(null, 'admin.php?page=mlang_strings&s=Map+%28ID+' . $settings['id'] . '%29+name&group=Maps+Marker+Pro') ?>"><?= esc_html__('translate', 'mmp') ?></a>)
											<?php else: ?>
												(<a href="https://www.mapsmarker.com/multilingual/" target="_blank"><?= esc_html__('translate', 'mmp') ?></a>)
											<?php endif; ?>
										<?php endif; ?>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="width"><?= esc_html__('Width', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="width" name="width" value="<?= $settings['width'] ?>" min="1" />
										<label><input type="radio" id="widthUnitPct" name="widthUnit" value="%" <?= !($settings['widthUnit'] == '%') ?: 'checked="checked"' ?> />%</label>
										<label><input type="radio" id="widthUnitPx" name="widthUnit" value="px" <?= !($settings['widthUnit'] == 'px') ?: 'checked="checked"' ?> />px</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="height"><?= esc_html__('Height', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="height" name="height" value="<?= $settings['height'] ?>" min="1" />
										<label><input type="radio" id="heightUnitPx" name="heightUnit" value="px" <?= !($settings['heightUnit'] == 'px') ?: 'checked="checked"' ?> />px</label>
									</div>
								</div>
								<div class="mmp-map-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<label for="lat"><?= esc_html__('Latitude', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="text" id="lat" name="lat" value="<?= $settings['lat'] ?>" />
									</div>
								</div>
								<div class="mmp-map-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<label for="lng"><?= esc_html__('Longitude', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="text" id="lng" name="lng" value="<?= $settings['lng'] ?>" />
									</div>
								</div>
								<div class="mmp-map-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<label for="maxBounds"><?= esc_html__('Max bounds', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<textarea id="maxBounds" name="maxBounds"><?= str_replace(',', ",\n", $settings['maxBounds']) ?></textarea><br />
										<button type="button" id="restrictView" class="button button-secondary"><?= esc_html__('Restrict to current view', 'mmp') ?></button>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="zoom"><?= esc_html__('Zoom', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="zoom" name="zoom" value="<?= $settings['zoom'] ?>" min="0" max="23" step="0.1" />
									</div>
								</div>
								<div class="mmp-map-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<label for="minZoom"><?= esc_html__('Min zoom', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="minZoom" name="minZoom" value="<?= $settings['minZoom'] ?>" min="0" max="23" step="0.1" />
									</div>
								</div>
								<div class="mmp-map-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<label for="maxZoom"><?= esc_html__('Max zoom', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="maxZoom" name="maxZoom" value="<?= $settings['maxZoom'] ?>" min="0" max="23" step="0.1" />
									</div>
								</div>
								<div class="mmp-map-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<label for="zoomStep"><?= esc_html__('Zoom step', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="zoomStep" name="zoomStep" value="<?= $settings['zoomStep'] ?>" min="0.1" max="1" step="0.1" />
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<?= esc_html__('Panel', 'mmp') ?>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="panel" name="panel" <?= !$settings['panel'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
											<span><?= esc_html__('Show', 'mmp') ?></span>
										</label>
										<input type="text" id="panelColor" name="panelColor" value="<?= $settings['panelColor'] ?>" /><br />
										<label>
											<div class="switch">
												<input type="checkbox" id="panelFs" name="panelFs" <?= !$settings['panelFs'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
											<span><?= esc_html__('Fullscreen', 'mmp') ?></span>
										</label><br />
										<label>
											<div class="switch">
												<input type="checkbox" id="panelGeoJson" name="panelGeoJson" <?= !$settings['panelGeoJson'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
											<span><?= esc_html__('GeoJSON export', 'mmp') ?></span>
										</label><br />
										<label>
											<div class="switch">
												<input type="checkbox" id="panelKml" name="panelKml" <?= !$settings['panelKml'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
											<span><?= esc_html__('KML export', 'mmp') ?></span>
										</label><br />
										<label>
											<div class="switch">
												<input type="checkbox" id="panelGeoRss" name="panelGeoRss" <?= !$settings['panelGeoRss'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
											<span><?= esc_html__('GeoRSS export', 'mmp') ?></span>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="tabbed"><?= esc_html__('Tabbed', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="tabbed" name="tabbed" <?= !$settings['tabbed'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
											<span><?= esc_html__('This map is embedded in a tab', 'mmp') ?></span>
										</label>
									</div>
								</div>
							</div>
							<div id="mmp-tabLayers-settings" class="mmp-tab">
								<div class="mmp-map-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<label for="basemapDetectRetina"><?= esc_html__('Detect retina', 'mmp') ?>*</label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="basemapDetectRetina" name="basemapDetectRetina" <?= !$settings['basemapDetectRetina'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<label for="basemapEdgeBufferTiles"><?= esc_html__('Edge buffer tiles', 'mmp') ?>*</label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="basemapEdgeBufferTiles" name="basemapEdgeBufferTiles" value="<?= $settings['basemapEdgeBufferTiles'] ?>" min="0" max="10" step="1" />
									</div>
								</div>
								<div class="mmp-map-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<label for="basemapGoogleStyles"><?= esc_html__('Google styles', 'mmp') ?>*</label>
									</div>
									<div class="mmp-map-setting-input">
										<textarea id="basemapGoogleStyles" name="basemapGoogleStyles"><?= $settings['basemapGoogleStyles'] ?></textarea><br />
										<a href="https://www.mapsmarker.com/google-styles/" target="_blank"><?= esc_html__('Tutorial and example styles', 'mmp') ?></a>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<?= esc_html__('Basemaps', 'mmp') ?>
									</div>
									<div class="mmp-map-setting-input">
										<ul id="basemapList">
											<?php foreach ($settings['basemaps'] as $basemap): ?>
												<?php if (isset($basemaps[$basemap])): ?>
													<li class="mmp-basemap" data-id="<?= $basemap ?>">
														<div>
															<input type="hidden" name="basemaps[]" value="<?= $basemap ?>" />
															<img class="mmp-handle mmp-align-middle" src="<?= plugins_url('images/icons/handle.png', __DIR__) ?>" />
															<input type="radio" name="basemapDefault" value="<?= $basemap ?>" <?= !($settings['basemapDefault'] == $basemap) ?: 'checked="checked"' ?> />
															<input type="text" class="mmp-align-middle" value="<?= esc_attr($basemaps[$basemap]['name']) ?>" disabled="disabled" />
															<i class="dashicons dashicons-no mmp-remove-basemap"></i>
														</div>
													</li>
												<?php endif; ?>
											<?php endforeach; ?>
										</ul>
										<select id="basemapsList">
											<?php foreach ($basemaps as $bid => $basemaps): ?>
												<option value="<?= $bid ?>" <?= (array_search($bid, $settings['basemaps']) !== false) ? 'disabled="disabled"' : '' ?>><?= esc_html($basemaps['name']) ?></option>
											<?php endforeach; ?>
										</select><br />
										<button type="button" id="basemapsAdd" class="button button-secondary"><?= esc_html__('Add basemap', 'mmp') ?></button>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<?= esc_html__('Overlays', 'mmp') ?>
									</div>
									<div class="mmp-map-setting-input">
										<ul id="overlayList">
											<?php foreach ($settings['overlays'] as $overlay): ?>
												<?php if (isset($overlays[$overlay])): ?>
													<li class="mmp-overlay" data-id="<?= $overlay ?>">
														<div>
															<input type="hidden" name="overlays[]" value="<?= $overlay ?>" />
															<img class="mmp-handle mmp-align-middle" src="<?= plugins_url('images/icons/handle.png', __DIR__) ?>" />
															<input type="checkbox" name="overlaysActive[]" value="<?= $overlay ?>" <?= !(in_array($overlay, $settings['overlaysActive'])) ?: 'checked="checked"' ?> />
															<input type="text" class="mmp-align-middle" value="<?= esc_attr($overlays[$overlay]['name']) ?>" disabled="disabled" />
															<i class="dashicons dashicons-no mmp-remove-overlay"></i>
														</div>
													</li>
												<?php endif; ?>
											<?php endforeach; ?>
										</ul>
										<select id="overlaysList">
											<?php foreach ($overlays as $oid => $overlays): ?>
												<option value="<?= $oid ?>" <?= (array_search($oid, $settings['overlays']) !== false) ? 'disabled="disabled"' : '' ?>><?= esc_html($overlays['name']) ?></option>
											<?php endforeach; ?>
										</select><br />
										<button type="button" id="overlaysAdd" class="button button-secondary"><?= esc_html__('Add overlay', 'mmp') ?></button>
									</div>
								</div>
							</div>
							<div id="mmp-tabControl-settings" class="mmp-tab">
								<div class="mmp-map-settings-group">
									<span><?= esc_html__('Zoom buttons', 'mmp') ?></span>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<?= esc_html__('Position', 'mmp') ?>
										</div>
										<div class="mmp-map-setting-input">
											<label class="mmp-radio">
												<input type="radio" name="zoomControlPosition" value="hidden" <?= !($settings['zoomControlPosition'] == 'hidden') ?: 'checked="checked"' ?> />
												<i class="dashicons dashicons-no"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="zoomControlPosition" value="topleft" <?= !($settings['zoomControlPosition'] == 'topleft') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-topleft"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="zoomControlPosition" value="topright" <?= !($settings['zoomControlPosition'] == 'topright') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-topright"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="zoomControlPosition" value="bottomleft" <?= !($settings['zoomControlPosition'] == 'bottomleft') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-bottomleft"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="zoomControlPosition" value="bottomright" <?= !($settings['zoomControlPosition'] == 'bottomright') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-bottomright"></i>
											</label>
										</div>
									</div>
								</div>
								<div class="mmp-map-settings-group">
									<span><?= esc_html__('Fullscreen button', 'mmp') ?></span>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<?= esc_html__('Position', 'mmp') ?>
										</div>
										<div class="mmp-map-setting-input">
											<label class="mmp-radio">
												<input type="radio" name="fullscreenPosition" value="hidden" <?= !($settings['fullscreenPosition'] == 'hidden') ?: 'checked="checked"' ?> />
												<i class="dashicons dashicons-no"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="fullscreenPosition" value="topleft" <?= !($settings['fullscreenPosition'] == 'topleft') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-topleft"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="fullscreenPosition" value="topright" <?= !($settings['fullscreenPosition'] == 'topright') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-topright"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="fullscreenPosition" value="bottomleft" <?= !($settings['fullscreenPosition'] == 'bottomleft') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-bottomleft"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="fullscreenPosition" value="bottomright" <?= !($settings['fullscreenPosition'] == 'bottomright') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-bottomright"></i>
											</label>
										</div>
									</div>
								</div>
								<div class="mmp-map-settings-group">
									<span><?= esc_html__('Reset button', 'mmp') ?></span>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<?= esc_html__('Position', 'mmp') ?>
										</div>
										<div class="mmp-map-setting-input">
											<label class="mmp-radio">
												<input type="radio" name="resetPosition" value="hidden" <?= !($settings['resetPosition'] == 'hidden') ?: 'checked="checked"' ?> />
												<i class="dashicons dashicons-no"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="resetPosition" value="topleft" <?= !($settings['resetPosition'] == 'topleft') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-topleft"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="resetPosition" value="topright" <?= !($settings['resetPosition'] == 'topright') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-topright"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="resetPosition" value="bottomleft" <?= !($settings['resetPosition'] == 'bottomleft') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-bottomleft"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="resetPosition" value="bottomright" <?= !($settings['resetPosition'] == 'bottomright') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-bottomright"></i>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="resetOnDemand"><?= esc_html__('On demand', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="resetOnDemand" name="resetOnDemand" <?= !$settings['resetOnDemand'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
								</div>
								<div class="mmp-map-settings-group">
									<span><?= esc_html__('Locate button', 'mmp') ?></span>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<?= esc_html__('Position', 'mmp') ?>
										</div>
										<div class="mmp-map-setting-input">
											<label class="mmp-radio">
												<input type="radio" name="locatePosition" value="hidden" <?= !($settings['locatePosition'] == 'hidden') ?: 'checked="checked"' ?> />
												<i class="dashicons dashicons-no"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="locatePosition" value="topleft" <?= !($settings['locatePosition'] == 'topleft') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-topleft"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="locatePosition" value="topright" <?= !($settings['locatePosition'] == 'topright') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-topright"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="locatePosition" value="bottomleft" <?= !($settings['locatePosition'] == 'bottomleft') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-bottomleft"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="locatePosition" value="bottomright" <?= !($settings['locatePosition'] == 'bottomright') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-bottomright"></i>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="locateDrawCircle"><?= esc_html__('Draw circle', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="locateDrawCircle" name="locateDrawCircle" <?= !$settings['locateDrawCircle'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="locateDrawMarker"><?= esc_html__('Draw marker', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="locateDrawMarker" name="locateDrawMarker" <?= !$settings['locateDrawMarker'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="locateSetView"><?= esc_html__('Set view', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<select id="locateSetView" name="locateSetView">
													<option value="once" <?= !($settings['locateSetView'] == 'once') ?: 'selected="selected"' ?>><?= esc_html__('Once', 'mmp') ?></option>
													<option value="always" <?= !($settings['locateSetView'] == 'always') ?: 'selected="selected"' ?>><?= esc_html__('Always', 'mmp') ?></option>
													<option value="untilPan" <?= !($settings['locateSetView'] == 'untilPan') ?: 'selected="selected"' ?>><?= esc_html__('Until pan', 'mmp') ?></option>
													<option value="untilPanOrZoom" <?= !($settings['locateSetView'] == 'untilPanOrZoom') ?: 'selected="selected"' ?>><?= esc_html__('Until pan or zoom', 'mmp') ?></option>
												</select>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="locateKeepCurrentZoomLevel"><?= esc_html__('Keep current zoom level', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="locateKeepCurrentZoomLevel" name="locateKeepCurrentZoomLevel" <?= !$settings['locateKeepCurrentZoomLevel'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="locateClickBehaviorInView"><?= esc_html__('Click behavior in view', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<select id="locateClickBehaviorInView" name="locateClickBehaviorInView">
													<option value="stop" <?= !($settings['locateClickBehaviorInView'] == 'stop') ?: 'selected="selected"' ?>><?= esc_html__('Stop', 'mmp') ?></option>
													<option value="setView" <?= !($settings['locateClickBehaviorInView'] == 'setView') ?: 'selected="selected"' ?>><?= esc_html__('Set view', 'mmp') ?></option>
												</select>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="locateClickBehaviorOutOfView"><?= esc_html__('Click behavior out of view', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<select id="locateClickBehaviorOutOfView" name="locateClickBehaviorOutOfView">
													<option value="stop" <?= !($settings['locateClickBehaviorOutOfView'] == 'stop') ?: 'selected="selected"' ?>><?= esc_html__('Stop', 'mmp') ?></option>
													<option value="setView" <?= !($settings['locateClickBehaviorOutOfView'] == 'setView') ?: 'selected="selected"' ?>><?= esc_html__('Set view', 'mmp') ?></option>
												</select>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="locateIcon"><?= esc_html__('Icon', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<select id="locateIcon" name="locateIcon">
													<option value="icon-cross-hairs" <?= !($settings['locateIcon'] == 'icon-cross-hairs') ?: 'selected="selected"' ?>><?= esc_html__('Crosshairs', 'mmp') ?></option>
													<option value="icon-pin" <?= !($settings['locateIcon'] == 'icon-pin') ?: 'selected="selected"' ?>><?= esc_html__('Pin', 'mmp') ?></option>
													<option value="icon-arrow" <?= !($settings['locateIcon'] == 'icon-arrow') ?: 'selected="selected"' ?>><?= esc_html__('Arrow', 'mmp') ?></option>
												</select>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="locateMetric"><?= esc_html__('Metric units', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="locateMetric" name="locateMetric" <?= !$settings['locateMetric'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="locateShowPopup"><?= esc_html__('Show popup', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="locateShowPopup" name="locateShowPopup" <?= !$settings['locateShowPopup'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="locateAutostart"><?= esc_html__('Autostart', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="locateAutostart" name="locateAutostart" <?= !$settings['locateAutostart'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
								</div>
								<div class="mmp-map-settings-group">
									<span><?= esc_html__('Scale', 'mmp') ?></span>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<?= esc_html__('Position', 'mmp') ?>
										</div>
										<div class="mmp-map-setting-input">
											<label class="mmp-radio">
												<input type="radio" name="scalePosition" value="hidden" <?= !($settings['scalePosition'] == 'hidden') ?: 'checked="checked"' ?> />
												<i class="dashicons dashicons-no"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="scalePosition" value="topleft" <?= !($settings['scalePosition'] == 'topleft') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-topleft"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="scalePosition" value="topright" <?= !($settings['scalePosition'] == 'topright') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-topright"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="scalePosition" value="bottomleft" <?= !($settings['scalePosition'] == 'bottomleft') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-bottomleft"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="scalePosition" value="bottomright" <?= !($settings['scalePosition'] == 'bottomright') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-bottomright"></i>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="scaleMaxWidth"><?= esc_html__('Max width', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<input type="number" id="scaleMaxWidth" name="scaleMaxWidth" value="<?= $settings['scaleMaxWidth'] ?>" min="0" step="1" />
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="scaleMetric"><?= esc_html__('Show metric', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="scaleMetric" name="scaleMetric" <?= !$settings['scaleMetric'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="scaleImperial"><?= esc_html__('Show imperial', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="scaleImperial" name="scaleImperial" <?= !$settings['scaleImperial'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
								</div>
								<div class="mmp-map-settings-group">
									<span><?= esc_html__('Layers control', 'mmp') ?></span>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<?= esc_html__('Position', 'mmp') ?>
										</div>
										<div class="mmp-map-setting-input">
											<label class="mmp-radio">
												<input type="radio" name="layersPosition" value="hidden" <?= !($settings['layersPosition'] == 'hidden') ?: 'checked="checked"' ?> />
												<i class="dashicons dashicons-no"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="layersPosition" value="topleft" <?= !($settings['layersPosition'] == 'topleft') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-topleft"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="layersPosition" value="topright" <?= !($settings['layersPosition'] == 'topright') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-topright"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="layersPosition" value="bottomleft" <?= !($settings['layersPosition'] == 'bottomleft') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-bottomleft"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="layersPosition" value="bottomright" <?= !($settings['layersPosition'] == 'bottomright') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-bottomright"></i>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="layersCollapsed"><?= esc_html__('Collapsed', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<select id="layersCollapsed" name="layersCollapsed">
												<option value="collapsed" <?= !($settings['layersCollapsed'] == 'collapsed') ?: 'selected="selected"' ?>><?= esc_html__('Collapsed', 'mmp') ?></option>
												<option value="collapsed-mobile" <?= !($settings['layersCollapsed'] == 'collapsed-mobile') ?: 'selected="selected"' ?>><?= esc_html__('Collapsed on mobile', 'mmp') ?></option>
												<option value="expanded" <?= !($settings['layersCollapsed'] == 'expanded') ?: 'selected="selected"' ?>><?= esc_html__('Expanded', 'mmp') ?></option>
											</select>
										</div>
									</div>
								</div>
								<div class="mmp-map-settings-group">
									<span><?= esc_html__('Filters control', 'mmp') ?></span>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<?= esc_html__('Position', 'mmp') ?>
										</div>
										<div class="mmp-map-setting-input">
											<label class="mmp-radio">
												<input type="radio" name="filtersPosition" value="hidden" <?= !($settings['filtersPosition'] == 'hidden') ?: 'checked="checked"' ?> />
												<i class="dashicons dashicons-no"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="filtersPosition" value="topleft" <?= !($settings['filtersPosition'] == 'topleft') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-topleft"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="filtersPosition" value="topright" <?= !($settings['filtersPosition'] == 'topright') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-topright"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="filtersPosition" value="bottomleft" <?= !($settings['filtersPosition'] == 'bottomleft') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-bottomleft"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="filtersPosition" value="bottomright" <?= !($settings['filtersPosition'] == 'bottomright') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-bottomright"></i>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="filtersCollapsed"><?= esc_html__('Collapsed', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<select id="filtersCollapsed" name="filtersCollapsed">
												<option value="collapsed" <?= !($settings['filtersCollapsed'] == 'collapsed') ?: 'selected="selected"' ?>><?= esc_html__('Collapsed', 'mmp') ?></option>
												<option value="collapsed-mobile" <?= !($settings['filtersCollapsed'] == 'collapsed-mobile') ?: 'selected="selected"' ?>><?= esc_html__('Collapsed on mobile', 'mmp') ?></option>
												<option value="expanded" <?= !($settings['filtersCollapsed'] == 'expanded') ?: 'selected="selected"' ?>><?= esc_html__('Expanded', 'mmp') ?></option>
											</select>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="filtersButtons"><?= esc_html__('Buttons', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="filtersButtons" name="filtersButtons" <?= !$settings['filtersButtons'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="filtersIcon"><?= esc_html__('Icon', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="filtersIcon" name="filtersIcon" <?= !$settings['filtersIcon'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="filtersName"><?= esc_html__('Name', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="filtersName" name="filtersName" <?= !$settings['filtersName'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="filtersCount"><?= esc_html__('Count', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="filtersCount" name="filtersCount" <?= !$settings['filtersCount'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="filtersOrderBy"><?= esc_html__('Sorting', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<select id="filtersOrderBy" name="filtersOrderBy">
												<option value="id" <?= !($settings['filtersOrderBy'] == 'id') ?: 'selected="selected"' ?>><?= esc_html__('ID', 'mmp') ?></option>
												<option value="name" <?= !($settings['filtersOrderBy'] == 'name') ?: 'selected="selected"' ?>><?= esc_html__('Name', 'mmp') ?></option>
												<option value="count" <?= !($settings['filtersOrderBy'] == 'count') ?: 'selected="selected"' ?>><?= esc_html__('Count', 'mmp') ?></option>
												<option value="custom" <?= !($settings['filtersOrderBy'] == 'custom') ?: 'selected="selected"' ?>><?= esc_html__('Custom', 'mmp') ?></option>
											</select>
											<select id="filtersSortOrder" name="filtersSortOrder" <?= !($settings['filtersOrderBy'] == 'custom') ? '' : 'disabled="disabled"' ?>>
												<option value="asc" <?= !($settings['filtersSortOrder'] == 'asc') ?: 'selected="selected"' ?>><?= esc_html__('Ascending', 'mmp') ?></option>
												<option value="desc" <?= !($settings['filtersSortOrder'] == 'desc') ?: 'selected="selected"' ?>><?= esc_html__('Descending', 'mmp') ?></option>
											</select>
										</div>
									</div>
								</div>
								<div class="mmp-map-settings-group">
									<span><?= esc_html__('Minimap', 'mmp') ?></span>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<?= esc_html__('Position', 'mmp') ?>
										</div>
										<div class="mmp-map-setting-input">
											<label class="mmp-radio">
												<input type="radio" name="minimapPosition" value="hidden" <?= !($settings['minimapPosition'] == 'hidden') ?: 'checked="checked"' ?> />
												<i class="dashicons dashicons-no"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="minimapPosition" value="topleft" <?= !($settings['minimapPosition'] == 'topleft') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-topleft"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="minimapPosition" value="topright" <?= !($settings['minimapPosition'] == 'topright') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-topright"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="minimapPosition" value="bottomleft" <?= !($settings['minimapPosition'] == 'bottomleft') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-bottomleft"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="minimapPosition" value="bottomright" <?= !($settings['minimapPosition'] == 'bottomright') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-bottomright"></i>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="minimapMinimized"><?= esc_html__('Collapsed', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<select id="minimapMinimized" name="minimapMinimized">
												<option value="collapsed" <?= !($settings['minimapMinimized'] == 'collapsed') ?: 'selected="selected"' ?>><?= esc_html__('Collapsed', 'mmp') ?></option>
												<option value="collapsed-mobile" <?= !($settings['minimapMinimized'] == 'collapsed-mobile') ?: 'selected="selected"' ?>><?= esc_html__('Collapsed on mobile', 'mmp') ?></option>
												<option value="expanded" <?= !($settings['minimapMinimized'] == 'expanded') ?: 'selected="selected"' ?>><?= esc_html__('Expanded', 'mmp') ?></option>
											</select>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="minimapWidth"><?= esc_html__('Width', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<input type="number" id="minimapWidth" name="minimapWidth" value="<?= $settings['minimapWidth'] ?>" min="1" step="1" />
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="minimapHeight"><?= esc_html__('Height', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<input type="number" id="minimapHeight" name="minimapHeight" value="<?= $settings['minimapHeight'] ?>" min="1" step="1" />
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="minimapCollapsedWidth"><?= esc_html__('Collapsed width', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<input type="number" id="minimapCollapsedWidth" name="minimapCollapsedWidth" value="<?= $settings['minimapCollapsedWidth'] ?>" min="1" step="1" />
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="minimapCollapsedHeight"><?= esc_html__('Collapsed height', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<input type="number" id="minimapCollapsedHeight" name="minimapCollapsedHeight" value="<?= $settings['minimapCollapsedHeight'] ?>" min="1" step="1" />
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="minimapZoomLevelOffset"><?= esc_html__('Zoom level offset', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<input type="number" id="minimapZoomLevelOffset" name="minimapZoomLevelOffset" value="<?= $settings['minimapZoomLevelOffset'] ?>" min="-23" max="23" step="0.1" />
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="minimapZoomLevelFixed"><?= esc_html__('Fixed zoom level', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<input type="number" id="minimapZoomLevelFixed" name="minimapZoomLevelFixed" value="<?= $settings['minimapZoomLevelFixed'] ?>" min="0" max="23" step="0.1" />
											</label>
										</div>
									</div>
								</div>
								<div class="mmp-map-settings-group">
									<span><?= esc_html__('Attribution', 'mmp') ?></span>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<?= esc_html__('Positon', 'mmp') ?>
										</div>
										<div class="mmp-map-setting-input">
											<label class="mmp-radio">
												<input type="radio" name="attributionPosition" value="topleft" <?= !($settings['attributionPosition'] == 'topleft') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-topleft"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="attributionPosition" value="topright" <?= !($settings['attributionPosition'] == 'topright') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-topright"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="attributionPosition" value="bottomleft" <?= !($settings['attributionPosition'] == 'bottomleft') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-bottomleft"></i>
											</label>
											<label class="mmp-radio">
												<input type="radio" name="attributionPosition" value="bottomright" <?= !($settings['attributionPosition'] == 'bottomright') ?: 'checked="checked"' ?> />
												<i class="dashicons mmp-dashicons-bottomright"></i>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="attributionCondensed"><?= esc_html__('Condensed', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="attributionCondensed" name="attributionCondensed" <?= !$settings['attributionCondensed'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
								</div>
							</div>
							<div id="mmp-tabMarker-settings" class="mmp-tab">
								<div class="mmp-map-settings-group">
									<span><?= esc_html__('Icon', 'mmp') ?></span>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="markerOpacity"><?= esc_html__('Opacity', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="markerOpacity" name="markerOpacity" value="<?= $settings['markerOpacity'] ?>" min="0" max="1" step="0.01" />
										</div>
									</div>
								</div>
								<div class="mmp-map-settings-group">
									<span><?= esc_html__('Clustering', 'mmp') ?></span>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="clustering"><?= esc_html__('Enable', 'mmp') ?>*</label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="clustering" name="clustering" <?= !$settings['clustering'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="showCoverageOnHover"><?= esc_html__('Show bounds on hover', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="showCoverageOnHover" name="showCoverageOnHover" <?= !$settings['showCoverageOnHover'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="disableClusteringAtZoom"><?= esc_html__('Disable at zoom', 'mmp') ?>*</label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="disableClusteringAtZoom" name="disableClusteringAtZoom" value="<?= $settings['disableClusteringAtZoom'] ?>" min="0" max="23" />
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="maxClusterRadius"><?= esc_html__('Max cluster radius', 'mmp') ?>*</label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="maxClusterRadius" name="maxClusterRadius" value="<?= $settings['maxClusterRadius'] ?>" min="1" />
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="singleMarkerMode"><?= esc_html__('Single marker mode', 'mmp') ?>*</label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="singleMarkerMode" name="singleMarkerMode" <?= !$settings['singleMarkerMode'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="spiderfyDistanceMultiplier"><?= esc_html__('Spiderfy multiplier', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="spiderfyDistanceMultiplier" name="spiderfyDistanceMultiplier" value="<?= $settings['spiderfyDistanceMultiplier'] ?>" min="0" max="10" step="0.1" />
										</div>
									</div>
								</div>
								<div class="mmp-map-settings-group">
									<span><?= esc_html__('Tooltip', 'mmp') ?></span>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="tooltip"><?= esc_html__('Show', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="tooltip" name="tooltip" <?= !$settings['tooltip'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="tooltipDirection"><?= esc_html__('Direction', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<select id="tooltipDirection" name="tooltipDirection">
												<option value="auto" <?= !($settings['tooltipDirection'] == 'auto') ?: 'selected="selected"' ?>><?= esc_html__('Auto', 'mmp') ?></option>
												<option value="right" <?= !($settings['tooltipDirection'] == 'right') ?: 'selected="selected"' ?>><?= esc_html__('Right', 'mmp') ?></option>
												<option value="left" <?= !($settings['tooltipDirection'] == 'left') ?: 'selected="selected"' ?>><?= esc_html__('Left', 'mmp') ?></option>
												<option value="top" <?= !($settings['tooltipDirection'] == 'top') ?: 'selected="selected"' ?>><?= esc_html__('Top', 'mmp') ?></option>
												<option value="bottom" <?= !($settings['tooltipDirection'] == 'bottom') ?: 'selected="selected"' ?>><?= esc_html__('Bottom', 'mmp') ?></option>
												<option value="center" <?= !($settings['tooltipDirection'] == 'center') ?: 'selected="selected"' ?>><?= esc_html__('Center', 'mmp') ?></option>
											</select>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="tooltipPermanent"><?= esc_html__('Permanent', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="tooltipPermanent" name="tooltipPermanent" <?= !$settings['tooltipPermanent'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="tooltipSticky"><?= esc_html__('Sticky', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="tooltipSticky" name="tooltipSticky" <?= !$settings['tooltipSticky'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="tooltipOpacity"><?= esc_html__('Opacity', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="tooltipOpacity" name="tooltipOpacity" value="<?= $settings['tooltipOpacity'] ?>" min="0" max="1" step="0.01" />
										</div>
									</div>
								</div>
								<div class="mmp-map-settings-group">
									<span><?= esc_html__('Popup', 'mmp') ?></span>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="popupOpenOnHover"><?= esc_html__('Open on hover', 'mmp') ?>*</label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="popupOpenOnHover" name="popupOpenOnHover" <?= !$settings['popupOpenOnHover'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="popupCenterOnMap"><?= esc_html__('Center on map', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="popupCenterOnMap" name="popupCenterOnMap" <?= !$settings['popupCenterOnMap'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="popupMarkername"><?= esc_html__('Show marker name', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="popupMarkername" name="popupMarkername" <?= !$settings['popupMarkername'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="popupAddress"><?= esc_html__('Show address', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="popupAddress" name="popupAddress" <?= !$settings['popupAddress'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="popupCoordinates"><?= esc_html__('Show coordinates', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="popupCoordinates" name="popupCoordinates" <?= !$settings['popupCoordinates'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="popupDirections"><?= esc_html__('Show directions link', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="popupDirections" name="popupDirections" <?= !$settings['popupDirections'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="popupMinWidth"><?= esc_html__('Min width', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="popupMinWidth" name="popupMinWidth" value="<?= $settings['popupMinWidth'] ?>" min="0" />
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="popupMaxWidth"><?= esc_html__('Max width', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="popupMaxWidth" name="popupMaxWidth" value="<?= $settings['popupMaxWidth'] ?>" min="0" />
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="popupMaxHeight"><?= esc_html__('Max height', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="popupMaxHeight" name="popupMaxHeight" value="<?= $settings['popupMaxHeight'] ?>" min="0" />
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="popupCloseButton"><?= esc_html__('Add close button', 'mmp') ?>*</label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="popupCloseButton" name="popupCloseButton" <?= !$settings['popupCloseButton'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="popupAutoClose"><?= esc_html__('Auto close', 'mmp') ?>*</label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="popupAutoClose" name="popupAutoClose" <?= !$settings['popupAutoClose'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
								</div>
							</div>
							<div id="mmp-tabFilter-settings" class="mmp-tab">
								<label>
									<div class="switch">
										<input type="checkbox" id="filtersAllMarkers" name="filtersAllMarkers" <?= !$settings['filtersAllMarkers'] ?: 'checked="checked"' ?> />
										<span class="slider"></span>
									</div>
									<?= esc_html__('Show all available markers (disables individual filters)', 'mmp') ?>
								</label>
								<div id="filtersWrap">
									<input type="hidden" id="iconTarget" name="iconTarget" value="" />
									<ul id="filterList">
										<?php foreach ($filters as $id => $filter): ?>
											<li class="mmp-filter" data-id="<?= $id ?>">
												<div>
													<img class="mmp-handle mmp-align-middle" src="<?= plugins_url('images/icons/handle.png', __DIR__) ?>" />
													<label class="mmp-align-middle">
														<div class="switch">
															<input type="checkbox" name="filtersList[<?= $id ?>][active]" <?= !$filter->active ?: 'checked="checked"' ?> />
															<span class="slider"></span>
														</div>
													</label>
													<input type="hidden" name="filtersList[<?= $id ?>][icon]" value="<?= (!$filter->icon) ? plugins_url('images/leaflet/marker.png', __DIR__) : $filter->icon ?>" />
													<img class="mmp-filter-icon mmp-align-middle" src="<?= (!$filter->icon) ? plugins_url('images/leaflet/marker.png', __DIR__) : $filter->icon ?>" />
													<input type="text" class="mmp-align-middle" name="filtersList[<?= $id ?>][name]" value="<?= esc_attr($filter->name) ?>" />
													<i class="dashicons dashicons-no mmp-remove-filter"></i>
												</div>
											</li>
										<?php endforeach; ?>
									</ul>
									<select id="filtersMapList">
										<?php foreach ($maps as $map): ?>
											<option value="<?= $map->id ?>" <?= ($map->id != $settings['id'] && !property_exists($filters, $map->id)) ? '' : 'disabled="disabled"' ?>>[<?= $map->id ?>] <?= esc_html($map->name) ?> (<?= $map->markers ?> markers)</option>
										<?php endforeach; ?>
									</select><br />
									<button type="button" id="filtersAdd" class="button button-secondary"><?= esc_html__('Add filter', 'mmp') ?></button>
									<button type="button" id="updateFilters" class="button button-secondary"><?= esc_html__('Update filters', 'mmp') ?></button>
								</div>
							</div>
							<div id="mmp-tabList-settings" class="mmp-tab">
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="list"><?= esc_html__('Marker list', 'mmp') ?>*</label>
									</div>
									<div class="mmp-map-setting-input">
										<select id="list" name="list">
											<option value="0" <?= !($settings['list'] == '0') ?: 'selected="selected"' ?>><?= esc_html__('None', 'mmp') ?></option>
											<option value="1" <?= !($settings['list'] == '1') ?: 'selected="selected"' ?>><?= esc_html__('Below', 'mmp') ?></option>
											<option value="2" <?= !($settings['list'] == '2') ?: 'selected="selected"' ?>><?= esc_html__('Right', 'mmp') ?></option>
											<option value="3" <?= !($settings['list'] == '3') ?: 'selected="selected"' ?>><?= esc_html__('Left', 'mmp') ?></option>
										</select>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="listWidth"><?= esc_html__('Width', 'mmp') ?>*</label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="listWidth" name="listWidth" value="<?= $settings['listWidth'] ?>" min="0" />
									</div>
								</div>
								<div class="mmp-map-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<label for="listBreakpoint"><?= esc_html__('Breakpoint', 'mmp') ?>*</label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="listBreakpoint" name="listBreakpoint" value="<?= $settings['listBreakpoint'] ?>" min="0" /><br />
										<?= esc_html__('If responsive map is enabled and the total width falls below this value, the list will always be shown below the map', 'mmp') ?>
									</div>
								</div>
								<div class="mmp-map-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<label for="listSearch"><?= esc_html__('Search and sort bar', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="listSearch" name="listSearch" <?= !$settings['listSearch'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="listIcon"><?= esc_html__('Icon', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="listIcon" name="listIcon" <?= !$settings['listIcon'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="listName"><?= esc_html__('Name', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="listName" name="listName" <?= !$settings['listName'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="listPopup"><?= esc_html__('Popup', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="listPopup" name="listPopup" <?= !$settings['listPopup'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="listAddress"><?= esc_html__('Address', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="listAddress" name="listAddress" <?= !$settings['listAddress'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="listDistance"><?= esc_html__('Distance', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="listDistance" name="listDistance" <?= !$settings['listDistance'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="listDistanceUnit"><?= esc_html__('Distance unit', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<select id="listDistanceUnit" name="listDistanceUnit">
											<option value="metric" <?= !($settings['listDistanceUnit'] == 'metric') ?: 'selected="selected"' ?>><?= esc_html__('Metric', 'mmp') ?></option>
											<option value="imperial" <?= !($settings['listDistanceUnit'] == 'imperial') ?: 'selected="selected"' ?>><?= esc_html__('Imperial', 'mmp') ?></option>
											<option value="metric-imperial" <?= !($settings['listDistanceUnit'] == 'metric-imperial') ?: 'selected="selected"' ?>><?= esc_html__('Metric (imperial)', 'mmp') ?></option>
											<option value="imperial-metric" <?= !($settings['listDistanceUnit'] == 'imperial-metric') ?: 'selected="selected"' ?>><?= esc_html__('Imperial (metric)', 'mmp') ?></option>
										</select>
									</div>
								</div>
								<div class="mmp-map-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<label for="listDistancePrecision"><?= esc_html__('Distance precision', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="listDistancePrecision" name="listDistancePrecision" value="<?= $settings['listDistancePrecision'] ?>" min="0" max="6" />
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="listOrderBy"><?= esc_html__('Default sorting', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<select id="listOrderBy" name="listOrderBy">
											<option value="id" <?= !($settings['listOrderBy'] == 'id') ?: 'selected="selected"' ?>><?= esc_html__('ID', 'mmp') ?></option>
											<option value="name" <?= !($settings['listOrderBy'] == 'name') ?: 'selected="selected"' ?>><?= esc_html__('Name', 'mmp') ?></option>
											<option value="address" <?= !($settings['listOrderBy'] == 'address') ?: 'selected="selected"' ?>><?= esc_html__('Address', 'mmp') ?></option>
											<option value="distance" <?= !($settings['listOrderBy'] == 'distance') ?: 'selected="selected"' ?>><?= esc_html__('Distance', 'mmp') ?></option>
											<option value="icon" <?= !($settings['listOrderBy'] == 'icon') ?: 'selected="selected"' ?>><?= esc_html__('Icon', 'mmp') ?></option>
											<option value="created" <?= !($settings['listOrderBy'] == 'created') ?: 'selected="selected"' ?>><?= esc_html__('Created', 'mmp') ?></option>
											<option value="updated" <?= !($settings['listOrderBy'] == 'updated') ?: 'selected="selected"' ?>><?= esc_html__('Updated', 'mmp') ?></option>
										</select>
										<select id="listSortOrder" name="listSortOrder">
											<option value="asc" <?= !($settings['listSortOrder'] == 'asc') ?: 'selected="selected"' ?>><?= esc_html__('Ascending', 'mmp') ?></option>
											<option value="desc" <?= !($settings['listSortOrder'] == 'desc') ?: 'selected="selected"' ?>><?= esc_html__('Descending', 'mmp') ?></option>
										</select><br />
										<?= esc_html__('Distance is only available with location control', 'mmp') ?>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="listOrderById"><?= esc_html__('ID', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="listOrderById" name="listOrderById" <?= !$settings['listOrderById'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="listOrderByName"><?= esc_html__('Name', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="listOrderByName" name="listOrderByName" <?= !$settings['listOrderByName'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="listOrderByAddress"><?= esc_html__('Address', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="listOrderByAddress" name="listOrderByAddress" <?= !$settings['listOrderByAddress'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="listOrderByDistance"><?= esc_html__('Distance', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="listOrderByDistance" name="listOrderByDistance" <?= !$settings['listOrderByDistance'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="listOrderByIcon"><?= esc_html__('Icon', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="listOrderByIcon" name="listOrderByIcon" <?= !$settings['listOrderByIcon'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="listOrderByCreated"><?= esc_html__('Created', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="listOrderByCreated" name="listOrderByCreated" <?= !$settings['listOrderByCreated'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="listOrderByUpdated"><?= esc_html__('Updated', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="listOrderByUpdated" name="listOrderByUpdated" <?= !$settings['listOrderByUpdated'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="listLimit"><?= esc_html__('Markers per page', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="listLimit" name="listLimit" value="<?= $settings['listLimit'] ?>" min="1" />
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="listDir"><?= esc_html__('Show directions link', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="listDir" name="listDir" <?= !$settings['listDir'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="listFs"><?= esc_html__('Show fullscreen link', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="listFs" name="listFs" <?= !$settings['listFs'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<label for="listAction"><?= esc_html__('List action', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<select id="listAction" name="listAction">
											<option value="none" <?= !($settings['listAction'] == 'none') ?: 'selected="selected"' ?>><?= esc_html__('None', 'mmp') ?></option>
											<option value="setview" <?= !($settings['listAction'] == 'setview') ?: 'selected="selected"' ?>><?= esc_html__('Jump to marker', 'mmp') ?></option>
											<option value="popup" <?= !($settings['listAction'] == 'popup') ?: 'selected="selected"' ?>><?= esc_html__('Open popup', 'mmp') ?></option>
										</select>
									</div>
								</div>
							</div>
							<div id="mmp-tabInteraction-settings" class="mmp-tab">
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="gestureHandling"><?= esc_html__('Gesture handling', 'mmp') ?>*</label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="gestureHandling" name="gestureHandling" <?= !$settings['gestureHandling'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="responsive"><?= esc_html__('Responsive map', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="responsive" name="responsive" <?= !$settings['responsive'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="boxZoom"><?= esc_html__('Box zoom', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="boxZoom" name="boxZoom" <?= !$settings['boxZoom'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="doubleClickZoom"><?= esc_html__('Double click zoom', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="doubleClickZoom" name="doubleClickZoom" <?= !$settings['doubleClickZoom'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="dragging"><?= esc_html__('Dragging', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="dragging" name="dragging" <?= !$settings['dragging'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="inertia"><?= esc_html__('Inertia', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="inertia" name="inertia" <?= !$settings['inertia'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<label for="inertiaDeceleration"><?= esc_html__('Inertia deceleration', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="inertiaDeceleration" name="inertiaDeceleration" value="<?= $settings['inertiaDeceleration'] ?>" min="0" />
									</div>
								</div>
								<div class="mmp-map-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<label for="inertiaMaxSpeed"><?= esc_html__('Inertia max speed', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="inertiaMaxSpeed" name="inertiaMaxSpeed" value="<?= $settings['inertiaMaxSpeed'] ?>" min="0" />
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="keyboard"><?= esc_html__('Keyboard navigation', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="keyboard" name="keyboard" <?= !$settings['keyboard'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<label for="keyboardPanDelta"><?= esc_html__('Keyboard pan delta', 'mmp') ?>*</label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="keyboardPanDelta" name="keyboardPanDelta" value="<?= $settings['keyboardPanDelta'] ?>" min="0" />
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="scrollWheelZoom"><?= esc_html__('Scroll wheel zoom', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="scrollWheelZoom" name="scrollWheelZoom" <?= !$settings['scrollWheelZoom'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="touchZoom"><?= esc_html__('Two finger zoom', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="touchZoom" name="touchZoom" <?= !$settings['touchZoom'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<label for="bounceAtZoomLimits"><?= esc_html__('Bounce at zoom limits', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="bounceAtZoomLimits" name="bounceAtZoomLimits" <?= !$settings['bounceAtZoomLimits'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
										</label>
									</div>
								</div>
							</div>
							<div id="mmp-tabGpx-settings" class="mmp-tab">
								<div class="mmp-map-setting">
									<div class="mmp-map-setting-desc">
										<?= esc_html__('GPX URL', 'mmp') ?>
									</div>
									<div class="mmp-map-setting-input">
										<input type="text" id="gpxUrl" name="gpxUrl" value="<?= $settings['gpxUrl'] ?>" /><br />
										<button type="button" id="chooseGpx" class="button button-secondary"><?= esc_html__('Open Media Library', 'mmp') ?></button>
										<button type="button" id="updateGpx" class="button button-secondary"><?= esc_html__('Update GPX', 'mmp') ?></button><br />
										<?= esc_html__('External URLs require an "allow origin" header', 'mmp') ?>
									</div>
								</div>
								<div class="mmp-map-settings-group">
									<span><?= esc_html__('Track', 'mmp') ?></span>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="gpxIcons"><?= esc_html__('Show start/end icons', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="gpxIcons" name="gpxIcons" <?= !$settings['gpxIcons'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxTrackSmoothFactor"><?= esc_html__('Track smooth factor', 'mmp') ?>*</label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="gpxTrackSmoothFactor" name="gpxTrackSmoothFactor" value="<?= $settings['gpxTrackSmoothFactor'] ?>" min="0" step="0.1" />
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="gpxTrackColor"><?= esc_html__('Track color', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="text" id="gpxTrackColor" name="gpxTrackColor" value="<?= $settings['gpxTrackColor'] ?>" />
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="gpxTrackWeight"><?= esc_html__('Track weight', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="gpxTrackWeight" name="gpxTrackWeight" value="<?= $settings['gpxTrackWeight'] ?>" min="0" />
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxTrackOpacity"><?= esc_html__('Track opacity', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="gpxTrackOpacity" name="gpxTrackOpacity" value="<?= $settings['gpxTrackOpacity'] ?>" min="0" max="1" step="0.01" />
										</div>
									</div>
								</div>
								<div class="mmp-map-settings-group">
									<span><?= esc_html__('Metadata', 'mmp') ?></span>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="gpxMeta"><?= esc_html__('Add popup to track', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="gpxMeta" name="gpxMeta" <?= !$settings['gpxMeta'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="gpxMetaUnits"><?= esc_html__('Units', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<select id="gpxMetaUnits" name="gpxMetaUnits">
												<option value="metric" <?= !($settings['gpxMetaUnits'] == 'metric') ?: 'selected="selected"' ?>><?= esc_html__('Metric', 'mmp') ?></option>
												<option value="imperial" <?= !($settings['gpxMetaUnits'] == 'imperial') ?: 'selected="selected"' ?>><?= esc_html__('Imperial', 'mmp') ?></option>
												<option value="metric-imperial" <?= !($settings['gpxMetaUnits'] == 'metric-imperial') ?: 'selected="selected"' ?>><?= esc_html__('Metric (imperial)', 'mmp') ?></option>
												<option value="imperial-metric" <?= !($settings['gpxMetaUnits'] == 'imperial-metric') ?: 'selected="selected"' ?>><?= esc_html__('Imperial (metric)', 'mmp') ?></option>
											</select>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxMetaInterval"><?= esc_html__('Max interval', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="gpxMetaInterval" name="gpxMetaInterval" value="<?= $settings['gpxMetaInterval'] ?>" min="0" />
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxMetaName"><?= esc_html__('Name', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="gpxMetaName" name="gpxMetaName" <?= !$settings['gpxMetaName'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxMetaDesc"><?= esc_html__('Description', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="gpxMetaDesc" name="gpxMetaDesc" <?= !$settings['gpxMetaDesc'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxMetaStart"><?= esc_html__('Start', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="gpxMetaStart" name="gpxMetaStart" <?= !$settings['gpxMetaStart'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxMetaEnd"><?= esc_html__('End', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="gpxMetaEnd" name="gpxMetaEnd" <?= !$settings['gpxMetaEnd'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxMetaTotal"><?= esc_html__('Total', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="gpxMetaTotal" name="gpxMetaTotal" <?= !$settings['gpxMetaTotal'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxMetaMoving"><?= esc_html__('Moving', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="gpxMetaMoving" name="gpxMetaMoving" <?= !$settings['gpxMetaMoving'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxMetaDistance"><?= esc_html__('Distance', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="gpxMetaDistance" name="gpxMetaDistance" <?= !$settings['gpxMetaDistance'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxMetaPace"><?= esc_html__('Pace', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="gpxMetaPace" name="gpxMetaPace" <?= !$settings['gpxMetaPace'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxMetaHeartRate"><?= esc_html__('Heart rate', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="gpxMetaHeartRate" name="gpxMetaHeartRate" <?= !$settings['gpxMetaHeartRate'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxMetaElevation"><?= esc_html__('Elevation', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="gpxMetaElevation" name="gpxMetaElevation" <?= !$settings['gpxMetaElevation'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxMetaDownload"><?= esc_html__('Download', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="gpxMetaDownload" name="gpxMetaDownload" <?= !$settings['gpxMetaDownload'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
								</div>
								<div class="mmp-map-settings-group">
									<span><?= esc_html__('Waypoints', 'mmp') ?></span>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="gpxWaypoints"><?= esc_html__('Show', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="gpxWaypoints" name="gpxWaypoints" <?= !$settings['gpxWaypoints'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="gpxWaypointsRadius"><?= esc_html__('Waypoints radius', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="gpxWaypointsRadius" name="gpxWaypointsRadius" value="<?= $settings['gpxWaypointsRadius'] ?>" min="0" />
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxWaypointsStroke"><?= esc_html__('Stroke', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="gpxWaypointsStroke" name="gpxWaypointsStroke" <?= !$settings['gpxWaypointsStroke'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxWaypointsColor"><?= esc_html__('Stroke color', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="text" id="gpxWaypointsColor" name="gpxWaypointsColor" value="<?= $settings['gpxWaypointsColor'] ?>" />
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxWaypointsWeight"><?= esc_html__('Stroke weight', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="gpxWaypointsWeight" name="gpxWaypointsWeight" value="<?= $settings['gpxWaypointsWeight'] ?>" min="0" />
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxWaypointsFillColor"><?= esc_html__('Fill color', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="text" id="gpxWaypointsFillColor" name="gpxWaypointsFillColor" value="<?= $settings['gpxWaypointsFillColor'] ?>" />
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxWaypointsFillOpacity"><?= esc_html__('Fill opacity', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="gpxWaypointsFillOpacity" name="gpxWaypointsFillOpacity" value="<?= $settings['gpxWaypointsFillOpacity'] ?>" min="0" max="1" step="0.01" />
										</div>
									</div>
								</div>
								<div class="mmp-map-settings-group">
									<span><?= esc_html__('Elevation chart', 'mmp') ?></span>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="gpxChart"><?= esc_html__('Show', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="gpxChart" name="gpxChart" <?= !$settings['gpxChart'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="gpxChartUnits"><?= esc_html__('Units', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<select id="gpxChartUnits" name="gpxChartUnits">
												<option value="metric" <?= !($settings['gpxChartUnits'] == 'metric') ?: 'selected="selected"' ?>><?= esc_html__('Metric', 'mmp') ?></option>
												<option value="imperial" <?= !($settings['gpxChartUnits'] == 'imperial') ?: 'selected="selected"' ?>><?= esc_html__('Imperial', 'mmp') ?></option>
												<option value="metric-imperial" <?= !($settings['gpxChartUnits'] == 'metric-imperial') ?: 'selected="selected"' ?>><?= esc_html__('Metric (imperial)', 'mmp') ?></option>
												<option value="imperial-metric" <?= !($settings['gpxChartUnits'] == 'imperial-metric') ?: 'selected="selected"' ?>><?= esc_html__('Imperial (metric)', 'mmp') ?></option>
											</select>
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="gpxChartHeight"><?= esc_html__('Height', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="gpxChartHeight" name="gpxChartHeight" value="<?= $settings['gpxChartHeight'] ?>" min="0" />
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="gpxChartBgColor"><?= esc_html__('Background color', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="text" id="gpxChartBgColor" name="gpxChartBgColor" value="<?= $settings['gpxChartBgColor'] ?>" />
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="gpxChartGridLinesColor"><?= esc_html__('Grid lines color', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="text" id="gpxChartGridLinesColor" name="gpxChartGridLinesColor" value="<?= $settings['gpxChartGridLinesColor'] ?>" />
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="gpxChartTicksFontColor"><?= esc_html__('Ticks font color', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="text" id="gpxChartTicksFontColor" name="gpxChartTicksFontColor" value="<?= $settings['gpxChartTicksFontColor'] ?>" />
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxChartLineWidth"><?= esc_html__('Line width', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="gpxChartLineWidth" name="gpxChartLineWidth" value="<?= $settings['gpxChartLineWidth'] ?>" min="0" />
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="gpxChartLineColor"><?= esc_html__('Line color', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="text" id="gpxChartLineColor" name="gpxChartLineColor" value="<?= $settings['gpxChartLineColor'] ?>" />
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="gpxChartFill"><?= esc_html__('Fill', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="gpxChartFill" name="gpxChartFill" <?= !$settings['gpxChartFill'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="gpxChartFillColor"><?= esc_html__('Fill color', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="text" id="gpxChartFillColor" name="gpxChartFillColor" value="<?= $settings['gpxChartFillColor'] ?>" />
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="gpxChartTooltipBgColor"><?= esc_html__('Tooltip background color', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="text" id="gpxChartTooltipBgColor" name="gpxChartTooltipBgColor" value="<?= $settings['gpxChartTooltipBgColor'] ?>" />
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="gpxChartTooltipFontColor"><?= esc_html__('Tooltip font color', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="text" id="gpxChartTooltipFontColor" name="gpxChartTooltipFontColor" value="<?= $settings['gpxChartTooltipFontColor'] ?>" />
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="gpxChartLocator"><?= esc_html__('Locator', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="gpxChartLocator" name="gpxChartLocator" <?= !$settings['gpxChartLocator'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxChartLocatorRadius"><?= esc_html__('Locator radius', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="gpxChartLocatorRadius" name="gpxChartLocatorRadius" value="<?= $settings['gpxChartLocatorRadius'] ?>" min="0" />
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxChartLocatorStroke"><?= esc_html__('Locator stroke', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="gpxChartLocatorStroke" name="gpxChartLocatorStroke" <?= !$settings['gpxChartLocatorStroke'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxChartLocatorColor"><?= esc_html__('Locator stroke color', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="text" id="gpxChartLocatorColor" name="gpxChartLocatorColor" value="<?= $settings['gpxChartLocatorColor'] ?>" />
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxChartLocatorWeight"><?= esc_html__('Locator stroke weight', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="gpxChartLocatorWeight" name="gpxChartLocatorWeight" value="<?= $settings['gpxChartLocatorWeight'] ?>" min="0" />
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxChartLocatorFillColor"><?= esc_html__('Locator fill color', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="text" id="gpxChartLocatorFillColor" name="gpxChartLocatorFillColor" value="<?= $settings['gpxChartLocatorFillColor'] ?>" />
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="gpxChartLocatorFillOpacity"><?= esc_html__('Locator fill opacity', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="gpxChartLocatorFillOpacity" name="gpxChartLocatorFillOpacity" value="<?= $settings['gpxChartLocatorFillOpacity'] ?>" min="0" max="1" step="0.01" />
										</div>
									</div>
								</div>
							</div>
							<div id="mmp-tabDraw-settings" class="mmp-tab">
								<div class="mmp-map-settings-group">
									<span><?= esc_html__('New shape settings', 'mmp') ?></span>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="drawStroke"><?= esc_html__('Stroke', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="drawStroke" name="drawStroke" <?= !$settings['drawStroke'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="drawStrokeColor"><?= esc_html__('Stroke color', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="text" id="drawStrokeColor" name="drawStrokeColor" value="<?= $settings['drawStrokeColor'] ?>" />
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="drawStrokeWeight"><?= esc_html__('Stroke weight', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="drawStrokeWeight" name="drawStrokeWeight" value="<?= $settings['drawStrokeWeight'] ?>" min="1" />
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="drawStrokeOpacity"><?= esc_html__('Stroke opacity', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="drawStrokeOpacity" name="drawStrokeOpacity" value="<?= $settings['drawStrokeOpacity'] ?>" min="0" max="1" step="0.01" />
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="drawLineCap"><?= esc_html__('Line cap', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<select id="drawLineCap" name="drawLineCap">
												<option value="butt" <?= !($settings['drawLineCap'] == 'butt') ?: 'selected="selected"' ?>><?= esc_html__('Butt', 'mmp') ?></option>
												<option value="round" <?= !($settings['drawLineCap'] == 'round') ?: 'selected="selected"' ?>><?= esc_html__('Round', 'mmp') ?></option>
												<option value="square" <?= !($settings['drawLineCap'] == 'square') ?: 'selected="selected"' ?>><?= esc_html__('Square', 'mmp') ?></option>
											</select>
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="drawLineJoin"><?= esc_html__('Line join', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<select id="drawLineJoin" name="drawLineJoin">
												<option value="arcs" <?= !($settings['drawLineJoin'] == 'arcs') ?: 'selected="selected"' ?>><?= esc_html__('Arcs', 'mmp') ?></option>
												<option value="bevel" <?= !($settings['drawLineJoin'] == 'bevel') ?: 'selected="selected"' ?>><?= esc_html__('Bevel', 'mmp') ?></option>
												<option value="miter" <?= !($settings['drawLineJoin'] == 'miter') ?: 'selected="selected"' ?>><?= esc_html__('Miter', 'mmp') ?></option>
												<option value="miter-clip" <?= !($settings['drawLineJoin'] == 'miter-clip') ?: 'selected="selected"' ?>><?= esc_html__('Miter-Clip', 'mmp') ?></option>
												<option value="round" <?= !($settings['drawLineJoin'] == 'round') ?: 'selected="selected"' ?>><?= esc_html__('Round', 'mmp') ?></option>
											</select>
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="drawFill"><?= esc_html__('Fill', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<label>
												<div class="switch">
													<input type="checkbox" id="drawFill" name="drawFill" <?= !$settings['drawFill'] ?: 'checked="checked"' ?> />
													<span class="slider"></span>
												</div>
											</label>
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="drawFillColor"><?= esc_html__('Fill color', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="text" id="drawFillColor" name="drawFillColor" value="<?= $settings['drawFillColor'] ?>" />
										</div>
									</div>
									<div class="mmp-map-setting">
										<div class="mmp-map-setting-desc">
											<label for="drawFillOpacity"><?= esc_html__('Fill opacity', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<input type="number" id="drawFillOpacity" name="drawFillOpacity" value="<?= $settings['drawFillOpacity'] ?>" min="0" max="1" step="0.01" />
										</div>
									</div>
									<div class="mmp-map-setting mmp-advanced">
										<div class="mmp-map-setting-desc">
											<label for="drawFillRule"><?= esc_html__('Fill rule', 'mmp') ?></label>
										</div>
										<div class="mmp-map-setting-input">
											<select id="drawFillRule" name="drawFillRule">
												<option value="nonzero" <?= !($settings['drawFillRule'] == 'nonzero') ?: 'selected="selected"' ?>><?= esc_html__('Nonzero', 'mmp') ?></option>
												<option value="evenodd" <?= !($settings['drawFillRule'] == 'evenodd') ?: 'selected="selected"' ?>><?= esc_html__('Evenodd', 'mmp') ?></option>
											</select>
										</div>
									</div>
								</div>
								<div class="mmp-map-settings-group">
									<span><?= esc_html__('Added shapes', 'mmp') ?></span>
									<ul id="geoJson"></ul>
								</div>
							</div>
							<?php if ($settings['id'] !== 'new'): ?>
								<div class="mmp-bottom-bar">
									<div>
										<a href="<?= get_admin_url(null, 'admin.php?page=mapsmarkerpro_marker&basemap=' . $settings['basemapDefault'] . '&lat=' . $settings['lat'] . '&lng=' . $settings['lng'] . '&zoom=' . $settings['zoom'] . '&map=' . $settings['id']) ?>" target="_blank"><?= esc_html__('Add marker', 'mmp') ?></a>
										<?php if ($map->created_by === $current_user->user_login || current_user_can('mmp_delete_other_maps')): ?>
											| <span class="mmp-delete" href=""><?= esc_html__('Delete', 'mmp') ?></span>
										<?php endif; ?>
									</div>
									<div>
										<table>
											<tr>
												<th><?= esc_html__('Shortcode', 'mmp') ?></th>
												<td>
													<input class="mmp-shortcode" type="text" value="[<?= Maps_Marker_Pro::$settings['shortcode'] ?> map=&quot;<?= $settings['id'] ?>&quot;]" readonly="readonly" />
												</td>
											</tr>
											<tr>
												<th><?= esc_html__('Used in content', 'mmp') ?></th>
												<td>
													<?php if ($shortcodes): ?>
														<ul class="mmp-used-in">
															<?php foreach($shortcodes as $shortcode): ?>
																<li>
																	<a href="<?= $shortcode['edit'] ?>" title="<?= esc_attr__('Edit post', 'mmp') ?>" target="_blank"><img src="<?= plugins_url('images/icons/edit-layer.png', __DIR__) ?>" /></a>
																	<a href="<?= $shortcode['link'] ?>" title="<?= esc_attr__('View post', 'mmp') ?>" target="_blank"><?= $shortcode['title'] ?></a>
																</li>
															<?php endforeach; ?>
														</ul>
													<?php else: ?>
														<?= esc_html__('Not used in any content', 'mmp') ?>
													<?php endif; ?>
												</td>
											</tr>
										</table>
									</div>
								</div>
							<?php endif; ?>
							<p>*<?= esc_html__('No preview - save and reload to see changes', 'mmp') ?></p>
							<a id="saveDefaultsLink" href="#"><?= esc_html__('Save current values as defaults for new maps', 'mmp') ?></a>
							<div class="mmp-save-defaults">
								<button type="button" id="saveDefaultsConfirm" class="button button-secondary"><?= esc_html__('OK', 'mmp') ?></button>
								<button type="button" id="saveDefaultsCancel" class="button button-secondary"><?= esc_html__('Cancel', 'mmp') ?></button>
							</div>
						</div>
						<div class="mmp-right">
							<div id="maps-marker-pro-admin" class="maps-marker-pro"></div>
						</div>
					</div>
				</form>
			</div>
			<div id="icons" class="mmp-hidden">
				<div id="iconsList">
					<img class="mmp-icon" src="<?= plugins_url('images/leaflet/marker.png', __DIR__) ?>" />
					<?php foreach ($upload->get_icons() as $icon): ?>
						<img class="mmp-icon" src="<?= Maps_Marker_Pro::$icons_url . $icon ?>" />
					<?php endforeach; ?>
				</div>
			</div>
			<div id="editShape" class="mmp-hidden">
				<input type="hidden" id="shapeId" name="shapeId" value="" />
				<input type="hidden" id="shapeBackup" name="shapeBackup" value="" />
				<div class="mmp-map-setting">
					<div class="mmp-map-setting-desc">
						<label for="editDrawStroke"><?= esc_html__('Stroke', 'mmp') ?></label>
					</div>
					<div class="mmp-map-setting-input">
						<label>
							<div class="switch">
								<input type="checkbox" id="editDrawStroke" name="editDrawStroke" <?= !$settings['drawStroke'] ?: 'checked="checked"' ?> />
								<span class="slider"></span>
							</div>
						</label>
					</div>
				</div>
				<div class="mmp-map-setting">
					<div class="mmp-map-setting-desc">
						<label for="editDrawStrokeColor"><?= esc_html__('Stroke color', 'mmp') ?></label>
					</div>
					<div class="mmp-map-setting-input">
						<input type="text" id="editDrawStrokeColor" name="editDrawStrokeColor" value="<?= $settings['drawStrokeColor'] ?>" />
					</div>
				</div>
				<div class="mmp-map-setting">
					<div class="mmp-map-setting-desc">
						<label for="editDrawStrokeWeight"><?= esc_html__('Stroke weight', 'mmp') ?></label>
					</div>
					<div class="mmp-map-setting-input">
						<input type="number" id="editDrawStrokeWeight" name="editDrawStrokeWeight" value="<?= $settings['drawStrokeWeight'] ?>" min="1" />
					</div>
				</div>
				<div class="mmp-map-setting">
					<div class="mmp-map-setting-desc">
						<label for="editDrawStrokeOpacity"><?= esc_html__('Stroke opacity', 'mmp') ?></label>
					</div>
					<div class="mmp-map-setting-input">
						<input type="number" id="editDrawStrokeOpacity" name="editDrawStrokeOpacity" value="<?= $settings['drawStrokeOpacity'] ?>" min="0" max="1" step="0.01" />
					</div>
				</div>
				<div class="mmp-map-setting mmp-advanced">
					<div class="mmp-map-setting-desc">
						<label for="editDrawLineCap"><?= esc_html__('Line cap', 'mmp') ?></label>
					</div>
					<div class="mmp-map-setting-input">
						<select id="editDrawLineCap" name="editDrawLineCap">
							<option value="butt" <?= !($settings['drawLineCap'] == 'butt') ?: 'selected="selected"' ?>><?= esc_html__('Butt', 'mmp') ?></option>
							<option value="round" <?= !($settings['drawLineCap'] == 'round') ?: 'selected="selected"' ?>><?= esc_html__('Round', 'mmp') ?></option>
							<option value="square" <?= !($settings['drawLineCap'] == 'square') ?: 'selected="selected"' ?>><?= esc_html__('Square', 'mmp') ?></option>
						</select>
					</div>
				</div>
				<div class="mmp-map-setting mmp-advanced">
					<div class="mmp-map-setting-desc">
						<label for="editDrawLineJoin"><?= esc_html__('Line join', 'mmp') ?></label>
					</div>
					<div class="mmp-map-setting-input">
						<select id="editDrawLineJoin" name="editDrawLineJoin">
							<option value="arcs" <?= !($settings['drawLineJoin'] == 'arcs') ?: 'selected="selected"' ?>><?= esc_html__('Arcs', 'mmp') ?></option>
							<option value="bevel" <?= !($settings['drawLineJoin'] == 'bevel') ?: 'selected="selected"' ?>><?= esc_html__('Bevel', 'mmp') ?></option>
							<option value="miter" <?= !($settings['drawLineJoin'] == 'miter') ?: 'selected="selected"' ?>><?= esc_html__('Miter', 'mmp') ?></option>
							<option value="miter-clip" <?= !($settings['drawLineJoin'] == 'miter-clip') ?: 'selected="selected"' ?>><?= esc_html__('Miter-Clip', 'mmp') ?></option>
							<option value="round" <?= !($settings['drawLineJoin'] == 'round') ?: 'selected="selected"' ?>><?= esc_html__('Round', 'mmp') ?></option>
						</select>
					</div>
				</div>
				<div class="mmp-map-setting">
					<div class="mmp-map-setting-desc">
						<label for="editDrawFill"><?= esc_html__('Fill', 'mmp') ?></label>
					</div>
					<div class="mmp-map-setting-input">
						<label>
							<div class="switch">
								<input type="checkbox" id="editDrawFill" name="editDrawFill" <?= !$settings['drawFill'] ?: 'checked="checked"' ?> />
								<span class="slider"></span>
							</div>
						</label>
					</div>
				</div>
				<div class="mmp-map-setting">
					<div class="mmp-map-setting-desc">
						<label for="editDrawFillColor"><?= esc_html__('Fill color', 'mmp') ?></label>
					</div>
					<div class="mmp-map-setting-input">
						<input type="text" id="editDrawFillColor" name="editDrawFillColor" value="<?= $settings['drawFillColor'] ?>" />
					</div>
				</div>
				<div class="mmp-map-setting">
					<div class="mmp-map-setting-desc">
						<label for="editDrawFillOpacity"><?= esc_html__('Fill opacity', 'mmp') ?></label>
					</div>
					<div class="mmp-map-setting-input">
						<input type="number" id="editDrawFillOpacity" name="editDrawFillOpacity" value="<?= $settings['drawFillOpacity'] ?>" min="0" max="1" step="0.01" />
					</div>
				</div>
				<div class="mmp-map-setting mmp-advanced">
					<div class="mmp-map-setting-desc">
						<label for="editDrawFillRule"><?= esc_html__('Fill rule', 'mmp') ?></label>
					</div>
					<div class="mmp-map-setting-input">
						<select id="editDrawFillRule" name="editDrawFillRule">
							<option value="nonzero" <?= !($settings['drawFillRule'] == 'nonzero') ?: 'selected="selected"' ?>><?= esc_html__('Nonzero', 'mmp') ?></option>
							<option value="evenodd" <?= !($settings['drawFillRule'] == 'evenodd') ?: 'selected="selected"' ?>><?= esc_html__('Evenodd', 'mmp') ?></option>
						</select>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
