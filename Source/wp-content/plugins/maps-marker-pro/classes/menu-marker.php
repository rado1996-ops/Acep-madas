<?php
namespace MMP;

class Menu_Marker extends Menu {
	/**
	 * Registers the hooks
	 *
	 * @since 4.0
	 */
	public function init() {
		add_action('admin_enqueue_scripts', array($this, 'load_resources'));
		add_action('wp_ajax_mmp_save_marker', array($this, 'save_marker'));
		add_action('wp_ajax_mmp_save_marker_defaults', array($this, 'save_marker_defaults'));
		add_action('wp_ajax_mmp_delete_marker_direct', array($this, 'delete_marker'));
	}

	/**
	 * Loads the required resources
	 *
	 * @since 4.0
	 *
	 * @param string $hook The current admin page
	 */
	public function load_resources($hook) {
		if (substr($hook, -strlen('mapsmarkerpro_marker')) !== 'mapsmarkerpro_marker') {
			return;
		}

		wp_enqueue_style('mapsmarkerpro');
		if (is_rtl()) {
			wp_enqueue_style('mapsmarkerpro-rtl');
		}
		if (Maps_Marker_Pro::$settings['googleApiKey']) {
			wp_enqueue_script('mmp-googlemaps');
		}
		wp_enqueue_script('mapsmarkerpro');

		$this->load_global_resources($hook);

		wp_enqueue_script('mmp-admin');
		wp_add_inline_script('mmp-admin', 'var editMarker = new editMarkerActions();');
	}

	/**
	 * Saves the marker
	 *
	 * @since 4.0
	 */
	public function save_marker() {
		global $wpdb, $current_user;
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		$date = date('Y-m-d H:i:s');
		$settings = wp_unslash($_POST['settings']);
		parse_str($settings, $settings);

		if (!isset($settings['nonce']) || wp_verify_nonce($settings['nonce'], 'mmp-marker') === false) {
			wp_send_json(array(
				'success' => false,
				'response' => esc_html__('Security check failed', 'mmp')
			));
		}
		$id = $settings['id'];
		$data = array(
			'name' => $settings['name'],
			'address' => $settings['address'],
			'lat' => $settings['lat'],
			'lng' => $settings['lng'],
			'zoom' => $settings['zoom'],
			'popup' => $settings['popup'],
			'link' => $settings['link'],
			'blank' => $settings['blank'],
			'icon' => $settings['iconTarget'],
			'created_by' => $current_user->user_login,
			'created_on' => $date,
			'updated_by' => $current_user->user_login,
			'updated_on' => $date
		);
		if ($id === 'new') {
			$id = $db->add_marker((object) $data);
		} else {
			$db->update_marker((object) $data, $id);
		}
		$marker = $db->get_marker($id);
		if ($marker->maps) {
			$db->unassign_maps_marker($marker->maps, $id);
		}
		if (isset($settings['assignedMaps']) && is_array($settings['assignedMaps'])) {
			foreach ($settings['assignedMaps'] as $map) {
				$db->assign_marker($map, $id);
			}
		}
		wp_send_json(array(
			'success' => true,
			'response' => array(
				'id' => $id,
				'message' => esc_html__('Marker saved successfully', 'mmp')
			)
		));
	}

	/**
	 * Saves the marker defaults
	 *
	 * @since 4.0
	 */
	public function save_marker_defaults() {
		$mmp_settings = Maps_Marker_Pro::get_instance('MMP\Settings');

		$settings = wp_unslash($_POST['settings']);
		parse_str($settings, $settings);

		if (!isset($settings['nonce']) || wp_verify_nonce($settings['nonce'], 'mmp-marker') === false) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('Security check failed', 'mmp')
			));
		}

		$settings['icon'] = $settings['iconTarget']; // Workaround, needs to be improved

		$settings = $mmp_settings->validate_marker_settings($settings, false, false);
		update_option('mapsmarkerpro_marker_defaults', $settings);

		wp_die();
	}

	/**
	 * Deletes the marker
	 *
	 * @since 4.0
	 */
	public function delete_marker() {
		global $current_user;
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-marker') === false) {
			return;
		}

		$id = absint($_POST['id']);
		if (!$id) {
			return;
		}

		$marker = $db->get_marker($id);
		if (!$marker) {
			return;
		}

		if ($marker->created_by !== $current_user->user_login && !current_user_can('mmp_delete_other_markers')) {
			return;
		}

		$db->delete_marker($id);
	}

	/**
	 * Shows the marker page
	 *
	 * @since 4.0
	 */
	protected function show() {
		global $current_user;
		$db = Maps_Marker_Pro::get_instance('MMP\DB');
		$l10n = Maps_Marker_Pro::get_instance('MMP\L10n');
		$upload = Maps_Marker_Pro::get_instance('MMP\Upload');
		$mmp_settings = Maps_Marker_Pro::get_instance('MMP\Settings');

		$maps = $db->get_all_maps();

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

		$id = (isset($_GET['id'])) ? absint($_GET['id']) : 'new';
		if ($id !== 'new') {
			$marker = $db->get_marker($id);
			if (!$marker) {
				$this->error(sprintf(esc_html__('A marker with ID %1$s does not exist.', 'mmp'), $id));
				return;
			}
			if ($marker->created_by !== $current_user->user_login && !current_user_can('mmp_delete_other_markers')) {
				$this->error(sprintf(esc_html__('You do not have the required capabilities to edit the marker with ID %1$s.', 'mmp'), $id));
				return;
			}
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
			$settings['basemap'] = isset($_GET['basemap']) ? preg_replace('/[^0-9A-Za-z]/', '', $_GET['basemap']) : $settings['basemap'];
			$settings['name'] = '';
			$settings['address'] = '';
			$settings['lat'] = isset($_GET['lat']) ? floatval($_GET['lat']) : $settings['lat'];
			$settings['lng'] = isset($_GET['lng']) ? floatval($_GET['lng']) : $settings['lng'];
			$settings['zoom'] = isset($_GET['zoom']) ? abs(floatval($_GET['zoom'])) : $settings['zoom'];
			$settings['popup'] = '';
			$settings['link'] = '';
			$settings['blank'] = '1';
			$settings['maps'] = isset($_GET['map']) ? array(absint($_GET['map'])) : array();
		}

		$globals = array(
			'googleApiKey' => Maps_Marker_Pro::$settings['googleApiKey'],
			'bingApiKey' => Maps_Marker_Pro::$settings['bingApiKey'],
			'bingCulture' => (Maps_Marker_Pro::$settings['bingCulture'] === 'automatic') ? str_replace('_', '-', get_locale()) : Maps_Marker_Pro::$settings['bingCulture'],
			'hereAppId' => Maps_Marker_Pro::$settings['hereAppId'],
			'hereAppCode' => Maps_Marker_Pro::$settings['hereAppCode'],
			'geocodingMinChars' => Maps_Marker_Pro::$settings['geocodingMinChars'],
			'geocodingLocationIqApiKey' => Maps_Marker_Pro::$settings['geocodingLocationIqApiKey'],
			'geocodingMapQuestApiKey' => Maps_Marker_Pro::$settings['geocodingMapQuestApiKey'],
			'geocodingGoogleApiKey' => Maps_Marker_Pro::$settings['geocodingGoogleApiKey']
		);

		$settings = array_merge($globals, $settings);

		?>
		<div class="wrap mmp-wrap">
			<h1><?= ($id !== 'new') ? esc_html__('Edit marker', 'mmp') : esc_html__('Add marker', 'mmp') ?></h1>
			<div class="mmp-main">
				<form id="markerSettings" method="POST">
					<input type="hidden" id="nonce" name="nonce" value="<?= wp_create_nonce('mmp-marker') ?>" />
					<input type="hidden" id="id" name="id" value="<?= $id ?>" />
					<div class="mmp-flexwrap">
						<div class="mmp-left">
							<div class="mmp-top-bar">
								<div class="mmp-top-bar-left">
									<button id="save" class="button button-primary" disabled="disabled"><?= esc_html__('Save', 'mmp') ?></button>
								</div>
								<?php if ($id !== 'new' && ($marker->created_by === $current_user->user_login || current_user_can('mmp_delete_other_markers'))): ?>
									<div class="mmp-top-bar-right">
										<span class="mmp-delete" href=""><?= esc_html__('Delete', 'mmp') ?></span>
									</div>
								<?php endif; ?>
							</div>
							<div class="mmp-marker-settings">
								<div class="mmp-marker-setting">
									<div class="mmp-marker-setting-desc">
										<label for="name"><?= esc_html__('Preview basemap', 'mmp') ?></label>
									</div>
									<div class="mmp-marker-setting-input">
										<select id="basemap" name="basemap">
											<?php foreach ($basemaps as $bid => $basemap): ?>
												<option value="<?= $bid ?>" <?= !($settings['basemap'] == $bid) ?: 'selected="selected"' ?>><?= esc_html($basemap['name']) ?></option>
											<?php endforeach; ?>
										</select>
									</div>
								</div>
								<div class="mmp-marker-setting">
									<div class="mmp-marker-setting-desc">
										<label for="name"><?= esc_html__('Name', 'mmp') ?></label>
									</div>
									<div class="mmp-marker-setting-input">
										<input type="text" id="name" name="name" value="<?= $settings['name'] ?>" />
										<?php if ($id !== 'new'): ?>
											<br />
											<?php if ($l10n->ml === 'wpml'): ?>
												(<a href="<?= get_admin_url(null, 'admin.php?page=wpml-string-translation%2Fmenu%2Fstring-translation.php&context=Maps+Marker+Pro') ?>"><?= esc_html__('translate', 'mmp') ?></a>)
											<?php elseif ($l10n->ml === 'pll'): ?>
												(<a href="<?= get_admin_url(null, 'admin.php?page=mlang_strings&s=Marker+%28ID+' . $id . '%29+name&group=Maps+Marker+Pro') ?>"><?= esc_html__('translate', 'mmp') ?></a>)
											<?php else: ?>
												(<a href="https://www.mapsmarker.com/multilingual/" target="_blank"><?= esc_html__('translate', 'mmp') ?></a>)
											<?php endif; ?>
										<?php endif; ?>
									</div>
								</div>
								<div class="mmp-marker-setting">
									<div class="mmp-marker-setting-desc">
										<label for="address"><?= esc_html__('Address', 'mmp') ?></label>
									</div>
									<div class="mmp-marker-setting-input">
										<input type="text" id="address" name="address" placeholder="<?= ($settings['geocodingMinChars'] < 2) ? esc_attr__('Start typing for suggestions', 'mmp') : sprintf(esc_attr__('Start typing for suggestions (%1$s characters minimum)', 'mmp'), $settings['geocodingMinChars']) ?>" value="<?= $settings['address'] ?>" /><br />
										<?php if ($id !== 'new'): ?>
											<?php if ($l10n->ml === 'wpml'): ?>
												(<a href="<?= get_admin_url(null, 'admin.php?page=wpml-string-translation%2Fmenu%2Fstring-translation.php&context=Maps+Marker+Pro') ?>"><?= esc_html__('translate', 'mmp') ?></a>)<br />
											<?php elseif ($l10n->ml === 'pll'): ?>
												(<a href="<?= get_admin_url(null, 'admin.php?page=mlang_strings&s=Marker+%28ID+' . $id . '%29+address&group=Maps+Marker+Pro') ?>"><?= esc_html__('translate', 'mmp') ?></a>)<br />
											<?php else: ?>
												(<a href="https://www.mapsmarker.com/multilingual/" target="_blank"><?= esc_html__('translate', 'mmp') ?></a>)<br />
											<?php endif; ?>
										<?php endif; ?>
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
								<div class="mmp-marker-setting">
									<div class="mmp-marker-setting-desc">
										<label for="lat"><?= esc_html__('Latitude', 'mmp') ?></label>
									</div>
									<div class="mmp-marker-setting-input">
										<input type="text" id="lat" name="lat" value="<?= $settings['lat'] ?>" />
									</div>
								</div>
								<div class="mmp-marker-setting">
									<div class="mmp-marker-setting-desc">
										<label for="lng"><?= esc_html__('Longitude', 'mmp') ?></label>
									</div>
									<div class="mmp-marker-setting-input">
										<input type="text" id="lng" name="lng" value="<?= $settings['lng'] ?>" />
									</div>
								</div>
								<div class="mmp-marker-setting">
									<div class="mmp-marker-setting-desc">
										<label for="zoom"><?= esc_html__('Zoom', 'mmp') ?></label>
									</div>
									<div class="mmp-marker-setting-input">
										<input type="number" id="zoom" name="zoom" min="0" max="23" step="0.5" value="<?= $settings['zoom'] ?>" />
									</div>
								</div>
								<div class="mmp-marker-setting">
									<div class="mmp-marker-setting-desc">
										<label for="changeIcon"><?= esc_html__('Icon', 'mmp') ?></label>
									</div>
									<div class="mmp-marker-setting-input">
										<input type="hidden" id="iconTarget" name="iconTarget" value="<?= $settings['icon'] ?>" />
										<button type="button" id="changeIcon"><?= esc_html__('Change', 'mmp') ?></button>
									</div>
								</div>
								<div class="mmp-marker-setting">
									<div class="mmp-marker-setting-desc">
										<label for="assignedMaps"><?= esc_html__('Maps', 'mmp') ?></label>
									</div>
									<div class="mmp-marker-setting-input">
										<select id="assignedMaps" name="assignedMaps[]" multiple="multiple">
											<?php foreach ($maps as $map): ?>
												<option value="<?= $map->id ?>" <?= (!in_array($map->id, $settings['maps'])) ?: 'selected="selected"' ?>>[<?= $map->id ?>] <?= esc_html($map->name) ?></option>
											<?php endforeach; ?>
										</select>
									</div>
								</div>
								<div class="mmp-marker-setting">
									<div class="mmp-marker-setting-desc">
										<label for="action"><?= esc_html__('Action', 'mmp') ?></label>
									</div>
									<div class="mmp-marker-setting-input">
										<label><input type="radio" name="action" value="popup" <?= ($settings['link']) ?: 'checked="checked"' ?> /> <?= esc_html__('Show popup', 'mmp') ?></label><br />
										<label><input type="radio" name="action" value="link" <?= (!$settings['link']) ?: 'checked="checked"' ?> /> <?= esc_html__('Open link', 'mmp') ?></label>
									</div>
								</div>
								<div id="link_settings">
									<div class="mmp-marker-setting">
										<div class="mmp-marker-setting-desc">
											<label for="link"><?= esc_html__('URL', 'mmp') ?></label>
										</div>
										<div class="mmp-marker-setting-input">
											<input type="text" id="link" name="link" value="<?= $settings['link'] ?>" />
										</div>
									</div>
									<div class="mmp-marker-setting">
										<div class="mmp-marker-setting-desc">
											<label for="blank"><?= esc_html__('Target', 'mmp') ?></label>
										</div>
										<div class="mmp-marker-setting-input">
											<label><input type="radio" name="blank" value="0" <?= !($settings['blank'] == '0') ?: 'checked="checked"' ?> /> <?= esc_html__('Same tab', 'mmp') ?></label>
											<label><input type="radio" name="blank" value="1" <?= !($settings['blank'] == '1') ?: 'checked="checked"' ?> /> <?= esc_html__('New tab', 'mmp') ?></label>
										</div>
									</div>
								</div>
							</div>
							<a id="saveDefaultsLink" href="#"><?= esc_html__('Save current values as defaults for new markers', 'mmp') ?></a>
							<div class="mmp-save-defaults">
								<button type="button" id="saveDefaultsConfirm" class="button button-secondary"><?= esc_html__('OK', 'mmp') ?></button>
								<button type="button" id="saveDefaultsCancel" class="button button-secondary"><?= esc_html__('Cancel', 'mmp') ?></button>
							</div>
						</div>
						<div class="mmp-right">
							<div id="maps-marker-pro-marker" class="maps-marker-pro"></div>
						</div>
					</div>
					<div class="mmp-below">
						<div id="editor" class="mmp-editor">
							<?php wp_editor($settings['popup'], 'popup') ?>
							<?php if ($id !== 'new'): ?>
								<?php if ($l10n->ml === 'wpml'): ?>
									(<a href="<?= get_admin_url(null, 'admin.php?page=wpml-string-translation%2Fmenu%2Fstring-translation.php&context=Maps+Marker+Pro') ?>"><?= esc_html__('translate', 'mmp') ?></a>)
								<?php elseif ($l10n->ml === 'pll'): ?>
									(<a href="<?= get_admin_url(null, 'admin.php?page=mlang_strings&s=Marker+%28ID+' . $id . '%29+popup&group=Maps+Marker+Pro') ?>"><?= esc_html__('translate', 'mmp') ?></a>)
								<?php else: ?>
									(<a href="https://www.mapsmarker.com/multilingual/" target="_blank"><?= esc_html__('translate', 'mmp') ?></a>)
								<?php endif; ?>
							<?php endif; ?>
						</div>
					</div>
					<div id="icons" class="mmp-hidden">
						<div>
							<label>
								<span><?= esc_html__('Search', 'mmp') ?></span>
								<input type="text" id="iconSearch" value="" />
							</label>
							<button type="button" id="toggleUpload"><?= esc_html__('Upload new icon', 'mmp') ?></button>
							<div style="float:right;">
								<a href="https://mapicons.mapsmarker.com/" target="_blank" title="<?= esc_attr__('click here for 1000+ free icons', 'mmp') ?>"><img src="<?= plugins_url('images/logo-mapicons.png', __DIR__) ?>" /></a>
							</div>
						</div>
						<div id="iconUpload">
							<input type="hidden" id="upload_nonce" value="<?= wp_create_nonce('mmp-icon-upload') ?>" />
							<?= esc_html__('Allowed file types', 'mmp') ?>: png, gif, jpg<br />
							<?= esc_html__('New icons will be uploaded to the following directory', 'mmp') ?>:<br />
							<?= Maps_Marker_Pro::$icons_url ?><br />
							<input type="file" id="uploadFile" name="uploadFile" />
							<button type="button" id="upload" name="upload" class="button button-primary"><?= esc_html__('Upload', 'mmp') ?></button>
						</div>
						<div id="iconList">
							<label class="mmp-radio">
								<input type="radio" name="icon" value="" <?= ($settings['icon']) ?: 'checked="checked"' ?> />
								<img class="mmp-icon" src="<?= plugins_url('images/leaflet/marker.png', __DIR__) ?>" />
							</label>
							<?php foreach ($upload->get_icons() as $icon): ?>
								<label class="mmp-radio">
									<input type="radio" name="icon" value="<?= $icon ?>" <?= !($settings['icon'] == $icon) ?: 'checked="checked"' ?> />
									<img class="mmp-icon" src="<?= Maps_Marker_Pro::$icons_url . $icon ?>" title="<?= $icon ?>" />
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</form>
			</div>
		</div>
		<?php
	}
}
