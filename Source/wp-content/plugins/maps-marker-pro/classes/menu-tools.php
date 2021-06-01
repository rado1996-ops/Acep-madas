<?php
namespace MMP;

class Menu_Tools extends Menu {
	/**
	 * Registers the hooks
	 *
	 * @since 4.0
	 */
	public function init() {
		add_action('admin_enqueue_scripts', array($this, 'load_resources'));
		add_action('wp_ajax_mmp_batch_settings', array($this, 'batch_settings'));
		add_action('wp_ajax_mmp_batch_layers', array($this, 'batch_layers'));
		add_action('wp_ajax_mmp_replace_icon', array($this, 'replace_icon'));
		add_action('wp_ajax_mmp_backup', array($this, 'backup'));
		add_action('wp_ajax_mmp_restore', array($this, 'restore'));
		add_action('wp_ajax_mmp_update_settings', array($this, 'update_settings'));
		add_action('wp_ajax_mmp_move_markers', array($this, 'move_markers'));
		add_action('wp_ajax_mmp_remove_markers', array($this, 'remove_markers'));
		add_action('wp_ajax_mmp_register_strings', array($this, 'register_strings'));
		add_action('wp_ajax_mmp_import', array($this, 'import'));
		add_action('wp_ajax_mmp_export', array($this, 'export'));
		add_action('wp_ajax_mmp_reset_database', array($this, 'reset_database'));
		add_action('wp_ajax_mmp_reset_settings', array($this, 'reset_settings'));
	}

	/**
	 * Loads the required resources
	 *
	 * @since 4.0
	 *
	 * @param string $hook The current admin page
	 */
	public function load_resources($hook) {
		if (substr($hook, -strlen('mapsmarkerpro_tools')) !== 'mapsmarkerpro_tools') {
			return;
		}

		$this->load_global_resources($hook);

		wp_enqueue_script('mmp-admin');
		wp_add_inline_script('mmp-admin', 'toolsActions();');
	}

	/**
	 * Changes settings for multiple maps
	 *
	 * @since 4.1
	 */
	 public function batch_settings() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');
		$mmp_settings = Maps_Marker_Pro::get_instance('MMP\Settings');

		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-tools-batch-settings') === false) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('Security check failed', 'mmp')
			));
		}

		$settings = wp_unslash($_POST['settings']);
		parse_str($settings, $settings);

		$batch_settings_mode = (isset($settings['batch_settings_mode']) && $settings['batch_settings_mode'] === 'all') ? 'all' : 'include';

		if ($batch_settings_mode === 'include' && !isset($settings['batch_settings_maps'])) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('No maps selected', 'mmp')
			));
		}

		$batch_settings = array();
		$keys = array_keys($mmp_settings->get_map_defaults());
		foreach ($keys as $key) {
			if (isset($settings["{$key}Check"])) {
				$batch_settings[$key] = (isset($settings[$key])) ? $settings[$key] : false;
			}
		}

		if ($batch_settings_mode === 'all') {
			$maps = $db->get_all_maps();
		} else {
			$maps = $db->get_maps($settings['batch_settings_maps']);
		}
		foreach ($maps as $map) {
			$new_settings = array_merge(json_decode($map->settings, true), $batch_settings);
			$new_settings = $mmp_settings->validate_map_settings($new_settings, false, false);
			$map->settings = json_encode($new_settings, JSON_FORCE_OBJECT);
			$db->update_map($map, $map->id);
		}

		wp_send_json(array(
			'success' => true,
			'message' => esc_html__('Settings updated successfully', 'mmp')
		));
	 }

	 /**
	 * Changes layers for multiple maps
	 *
	 * @since 4.3
	 */
	public function batch_layers() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');
		$mmp_settings = Maps_Marker_Pro::get_instance('MMP\Settings');

		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-tools-batch-layers') === false) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('Security check failed', 'mmp')
			));
		}

		$settings = wp_unslash($_POST['settings']);
		parse_str($settings, $settings);

		$batch_layers_mode = (isset($settings['batch_layers_mode']) && $settings['batch_layers_mode'] === 'all') ? 'all' : 'include';

		if ($batch_layers_mode === 'include' && !isset($settings['batch_layers_maps'])) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('No maps selected', 'mmp')
			));
		}

		$batch_layers = array(
			'basemaps'       => (isset($settings['basemaps'])) ? $settings['basemaps'] : array(),
			'basemapDefault' => (isset($settings['basemapDefault'])) ? $settings['basemapDefault'] : null,
			'overlays'       => (isset($settings['overlays'])) ? $settings['overlays'] : array()
		);

		if ($batch_layers_mode === 'all') {
			$maps = $db->get_all_maps();
		} else {
			$maps = $db->get_maps($settings['batch_layers_maps']);
		}
		foreach ($maps as $map) {
			$new_settings = array_merge(json_decode($map->settings, true), $batch_layers);
			$new_settings = $mmp_settings->validate_map_settings($new_settings, false, false);
			$map->settings = json_encode($new_settings, JSON_FORCE_OBJECT);
			$db->update_map($map, $map->id);
		}

		wp_send_json(array(
			'success' => true,
			'message' => esc_html__('Settings updated successfully', 'mmp')
		));
	 }

	 /**
	 * Replaces a marker icon
	 *
	 * @since 4.1
	 */
	public function replace_icon() {
		global $wpdb;
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-tools-replace-icon') === false) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('Security check failed', 'mmp')
			));
		}
		if (!isset($_POST['source']) || !isset($_POST['target'])) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('Source or target missing', 'mmp')
			));
		}
		$source = ($_POST['source'] === plugins_url('images/leaflet/marker.png', __DIR__)) ? '' : basename($_POST['source']);
		$target = ($_POST['target'] === plugins_url('images/leaflet/marker.png', __DIR__)) ? '' : basename($_POST['target']);

		$wpdb->update(
			"{$wpdb->prefix}mmp_markers",
			array('icon' => $target),
			array('icon' => $source),
			array('%s'),
			array('%s')
		);

		wp_send_json(array(
			'success' => true,
			'message' => esc_html__('Icon replaced successfully', 'mmp')
		));
	}

	/**
	 * Backs up the database
	 *
	 * @since 4.0
	 */
	public function backup() {
		global $wpdb;
		$api = Maps_Marker_Pro::get_instance('MMP\API');
		$l10n = Maps_Marker_Pro::get_instance('MMP\L10n');

		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-tools-backup') === false) {
			return;
		}

		$table = (isset($_POST['table'])) ? absint($_POST['table']) : 0;
		$offset = (isset($_POST['offset'])) ? absint($_POST['offset']) : 0;
		$total = (isset($_POST['offset'])) ? json_decode($_POST['total'], true) : array();
		$file = (isset($_POST['filename']) && $_POST['filename']) ? Maps_Marker_Pro::$temp_dir . $_POST['filename'] : Maps_Marker_Pro::$temp_dir . 'backup-' . date('Y-m-d-his') . '.mmp';

		if (!count($total)) {
			$index = 0;
			while (($cur_table = $this->get_table($index)) !== false) {
				$rows = $wpdb->get_var("SELECT COUNT(1) FROM $cur_table");
				$total[] = intval($rows); // MySQL always returns a string
				$index++;
			}
			fclose(fopen($file, 'w'));
		}

		$handle = fopen($file, 'a');
		$batch = $wpdb->get_results("SELECT * FROM " . $this->get_table($table) . " LIMIT $offset, 1000");
		if (!count($batch)) {
			$log[] = '[OK] Table ' . $this->get_table($table) . ' skipped (empty)';
		} else {
			foreach ($batch as $line) {
				$data = "$table:" . json_encode($line) . "\n";
				fwrite($handle, $data);
			}
			$log[] = '[OK] Processed table ' . $this->get_table($table) . ' (' . ($offset / 1000 + 1) . ' of ' . ceil($total[$table] / 1000) . ')';
		}
		fclose($handle);

		$filename = basename($file);
		$response = array(
			'table' => $table,
			'offset' => $offset,
			'total' => $total,
			'log' => $log,
			'filename' => basename($filename)
		);
		if (($table + 1) > 3) {
			$url = "{$api->base_url}index.php?mapsmarkerpro=download_file&filename={$filename}";
			$response['message'] = esc_html__('Backup completed successfully', 'mmp') . '<br />' . sprintf($l10n->kses__('If the download does not start automatically, please <a href="%1$s">click here</a>', 'mmp'), $url);
			$response['url'] = $url;
		}
		wp_send_json($response);
	}

	/**
	 * Restores a database backup
	 *
	 * @since 4.0
	 */
	public function restore() {
		global $wpdb;
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-tools-restore-backup') === false) {
			return;
		}

		$table = (isset($_POST['table'])) ? absint($_POST['table']) : 0;
		$offset = (isset($_POST['offset'])) ? absint($_POST['offset']) : 0;
		$total = (isset($_POST['offset'])) ? json_decode($_POST['total'], true) : array();

		$file = sys_get_temp_dir() . '/restore.mmp';
		if (isset($_FILES['upload'])) {
			move_uploaded_file($_FILES['upload']['tmp_name'], $file);
		}
		$handle = fopen($file, 'r');

		if (!count($total)) {
			$db->create_tables();
			$index = 0;
			while (($cur_table = $this->get_table($index)) !== false) {
				$total[] = 0;
				$index++;
			}
			while (($buffer = fgets($handle)) !== false) {
				$cur_table = substr($buffer, 0, 1);
				$total[$cur_table]++;
			}
			rewind($handle);
		}

		if ($offset === 0) {
			$wpdb->query('TRUNCATE TABLE ' . $this->get_table($table));
		}

		$batch = array();
		$count = 0;
		while (($buffer = fgets($handle)) !== false) {
			if (substr($buffer, 0, 1) < $table) {
				continue;
			}
			if ($count >= $offset && $count < $offset + 1000) {
				if (substr($buffer, 0, 1) > $table) {
					break;
				}
				$batch[] = substr($buffer, 2);
			}
			$count++;
		}
		fclose($handle);

		if (!count($batch)) {
			$log[] = '[OK] Table ' . $this->get_table($table) . ' skipped (empty)';
		} else {
			// Chaining the rows and only calling the query once is significantly faster
			if ($table === 0) {
				$prepare = '(' . implode(',', array_values($db->prepare_layers())) . '),';
			} else if ($table === 1) {
				$prepare = '(' . implode(',', array_values($db->prepare_maps())) . '),';
			} else if ($table === 2) {
				$prepare = '(' . implode(',', array_values($db->prepare_markers())) . '),';
			} else {
				$prepare = '(' . implode(',', array_values($db->prepare_rels())) . '),';
			}
			$cols = implode(',', array_keys($batch[0]));
			$sql = 'INSERT INTO ' . $this->get_table($table) . " ($cols) VALUES ";
			foreach ($batch as $line) {
				$data = json_decode($line, true);
				$sql .= $wpdb->prepare($prepare, array_values($data));
			}
			$sql = substr($sql, 0, -1); // Remove trailing comma from loop-generated query
			$wpdb->query($sql);
			$log[] = '[OK] Processed table ' . $this->get_table($table) . ' (' . ($offset / 1000 + 1) . ' of ' . ceil($total[$table] / 1000) . ')';
		}

		$response = array(
			'table' => $table,
			'offset' => $offset,
			'total' => $total,
			'log' => $log
		);
		if (($table + 1) > 3) {
			$response['message'] = esc_html__('Restore completed successfully', 'mmp');
			$response['maps'] = $this->get_map_list();
		}
		wp_send_json($response);
	}

	/**
	 * Updates the settings
	 *
	 * @since 4.0
	 */
	public function update_settings() {
		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-tools-update-settings') === false) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('Security check failed', 'mmp')
			));
		}
		if (!isset($_POST['settings'])) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('Settings missing', 'mmp')
			));
		}
		$settings = json_decode(stripslashes($_POST['settings']), true);
		if ($settings === null) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('Could not parse settings', 'mmp')
			));
		}
		update_option('mapsmarkerpro_settings', $settings);
		wp_send_json(array(
			'success' => true,
			'message' => esc_html__('Settings updated successfully', 'mmp')
		));
	}

	/**
	 * Moves markers from a map to a different map
	 *
	 * @since 4.0
	 */
	public function move_markers() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-tools-move-markers') === false) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('Security check failed', 'mmp')
			));
		}
		if (!isset($_POST['source']) || !isset($_POST['target'])) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('Source or target missing', 'mmp')
			));
		}
		$source = $db->get_map($_POST['source']);
		$target = $db->get_map($_POST['target']);
		if (!$source || !$target) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('Source or target not found', 'mmp')
			));
		}
		$ids = array();
		foreach ($db->get_map_markers($source->id) as $marker) {
			$ids[] = $marker->id;
		}
		$db->unassign_all_markers($source->id);
		$db->assign_markers($target->id, $ids);
		wp_send_json(array(
			'success' => true,
			'message' => esc_html__("Markers from map {$source->id} successfully moved to map {$target->id}", 'mmp'),
			'maps' => $this->get_map_list()
		));
	}

	/**
	 * Removes markers from a map
	 *
	 * @since 4.0
	 */
	public function remove_markers() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-tools-remove-markers') === false) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('Security check failed', 'mmp')
			));
		}
		if (!isset($_POST['map'])) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('Map missing', 'mmp')
			));
		}
		$map = $db->get_map($_POST['map']);
		if (!$map) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('Map not found', 'mmp')
			));
		}
		$db->unassign_all_markers($map->id);
		wp_send_json(array(
			'success' => true,
			'message' => esc_html__("Markers successfully removed from map {$map->id}", 'mmp'),
			'maps' => $this->get_map_list()
		));
	}

	/**
	 * Initializes all existing maps and markers for multilingual support
	 *
	 * @since 4.0
	 */
	public function register_strings() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');
		$l10n = Maps_Marker_Pro::get_instance('MMP\L10n');

		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-tools-register-strings') === false) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('Security check failed', 'mmp')
			));
		}
		if (!$l10n->ml) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('No supported multilingual plugin found', 'mmp')
			));
		}
		$maps = $db->get_all_maps();
		foreach ($maps as $map) {
			$l10n->register("Map (ID {$map->id}) name", $map->name);
		}
		$markers = $db->get_all_markers();
		foreach ($markers as $marker) {
			$l10n->register("Marker (ID {$marker->id}) name", $marker->name);
			$l10n->register("Marker (ID {$marker->id}) address", $marker->address);
			$l10n->register("Marker (ID {$marker->id}) popup", $marker->popup);
		}
		wp_send_json(array(
			'success' => true,
			'message' => esc_html__('Strings for all maps and markers successfully registered for translation', 'mmp')
		));
	}

	/**
	 * Imports markers to the database
	 *
	 * @since 4.0
	 */
	public function import() {
		global $current_user;
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		$test_mode = (isset($_POST['test_mode']) && $_POST['test_mode'] === 'off') ? false : true;
		$marker_mode = (isset($_POST['marker_mode']) && in_array($_POST['marker_mode'], array('add', 'update', 'both'))) ? $_POST['marker_mode'] : 'add';
		$geocoding = (isset($_POST['geocoding']) && in_array($_POST['geocoding'], array('on', 'missing', 'off'))) ? $_POST['geocoding'] : 'off';

		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-tools-import') === false) {
			wp_send_json(array(
				'success' => false,
				'response' => esc_html__('Security check failed', 'mmp')
			));
		}
		if (!isset($_FILES['file'])) {
			wp_send_json(array(
				'success' => false,
				'response' => esc_html__('File missing', 'mmp')
			));
		}
		$file = file_get_contents($_FILES['file']['tmp_name']);
		if ($file === false) {
			wp_send_json(array(
				'success' => false,
				'response' => esc_html__('File could not be read', 'mmp')
			));
		}
		$json = json_decode($file, true);
		if ($json === null) {
			wp_send_json(array(
				'success' => false,
				'response' => esc_html__('File could not be parsed', 'mmp')
			));
		}
		if (!isset($json['features']) || !is_array($json['features'])) {
			wp_send_json(array(
				'success' => false,
				'response' => esc_html__('Invalid GeoJSON', 'mmp')
			));
		}
		if (!$json['features']) {
			wp_send_json(array(
				'success' => false,
				'response' => esc_html__('No geographical data found', 'mmp')
			));
		}
		$results = array();
		foreach ($json['features'] as $feature) {
			$time = date('Y-m-d H:i:s');
			if (!isset($feature['geometry']['type'])) {
				$results[] = array(
					'status' => 3,
					'message' => esc_html__('Missing geometry type', 'mmp')
				);
				continue;
			}
			if ($feature['geometry']['type'] === 'Point') {
				$geocoding_flag = false;
				if (!isset($feature['geometry']['coordinates'][0]) || !isset($feature['geometry']['coordinates'][1]) || !$feature['geometry']['coordinates'][0] || !$feature['geometry']['coordinates'][1]) {
					if ($geocoding === 'off') {
						$results[] = array(
							'status' => 3,
							'message' => esc_html__('Missing or incomplete coordinates', 'mmp')
						);
						continue;
					}
					if (!isset($feature['properties']['address'])) {
						$results[] = array(
							'status' => 3,
							'message' => esc_html__('Missing address for geocoding', 'mmp')
						);
						continue;
					}
					$geocoding_flag = true;
				}
				if ($geocoding === 'on') {
					if (!isset($feature['properties']['address'])) {
						$results[] = array(
							'status' => 3,
							'message' => esc_html__('Missing address for geocoding', 'mmp')
						);
						continue;
					}
					$geocoding_flag = true;
				}
				$marker = array(
					'id' => (isset($feature['properties']['id'])) ? $feature['properties']['id'] : 0,
					'name' => (isset($feature['properties']['name'])) ? $feature['properties']['name'] : '',
					'address' => (isset($feature['properties']['address'])) ? $feature['properties']['address'] : '',
					'lat' => $feature['geometry']['coordinates'][1],
					'lng' => $feature['geometry']['coordinates'][0],
					'zoom' => (isset($feature['properties']['zoom'])) ? $feature['properties']['zoom'] : 11,
					'icon' => (isset($feature['properties']['icon'])) ? $feature['properties']['icon'] : '',
					'popup' => (isset($feature['properties']['popup'])) ? $feature['properties']['popup'] : '',
					'link' => (isset($feature['properties']['link'])) ? $feature['properties']['link'] : '',
					'blank' => (isset($feature['properties']['blank'])) ? $feature['properties']['blank'] : '1',
					'created_by' => $current_user->user_login,
					'created_on' => $time,
					'updated_by' => $current_user->user_login,
					'updated_on' => $time
				);
				if ($marker_mode === 'add') {
					if ($test_mode) {
						$results[] = array(
							'status' => 1,
							'message' => esc_html__('New marker will be added', 'mmp')
						);
					} else {
						$result = $db->add_marker((object) $marker, $geocoding_flag);
						if ($result) {
							if (isset($feature['properties']['maps'])) {
								$maps = $db->sanitize_ids($feature['properties']['maps']);
								foreach ($maps as $map) {
									$db->assign_marker($map, $result);
								}
							}
							$results[] = array(
								'status' => 1,
								'message' => sprintf(esc_html__('Marker successfully added with ID %1$s', 'mmp'), $result)
							);
						} else {
							$results[] = array(
								'status' => 3,
								'message' => esc_html__('Marker could not be added', 'mmp')
							);
						}
					}
				} else {
					$old_marker = $db->get_marker($marker['id']);
					if ($marker_mode === 'update') {
						if ($test_mode) {
							if ($old_marker) {
								$results[] = array(
									'status' => 2,
									'message' => sprintf(esc_html__('Marker with ID %1$s will be updated', 'mmp'), $marker['id'])
								);
							} else {
								$results[] = array(
									'status' => 3,
									'message' => sprintf(esc_html__('Marker with ID %1$s not found', 'mmp'), $marker['id'])
								);
							}
						} else {
							if ($old_marker) {
								$result = $db->update_marker((object) $marker, $marker['id'], $geocoding_flag);
								if ($result !== false) {
									if (isset($feature['properties']['maps'])) {
										$maps = $db->sanitize_ids($feature['properties']['maps']);
										foreach ($maps as $map) {
											$db->assign_marker($map, $marker['id']);
										}
									}
									$results[] = array(
										'status' => 2,
										'message' => sprintf(esc_html__('Marker with ID %1$s successfully updated', 'mmp'), $marker['id'])
									);
								} else {
									$results[] = array(
										'status' => 3,
										'message' => sprintf(esc_html__('Marker with ID %1$s could not be updated', 'mmp'), $marker['id'])
									);
								}
							} else {
								$results[] = array(
									'status' => 3,
									'message' => sprintf(esc_html__('Marker with ID %1$s not found', 'mmp'), $marker['id'])
								);
							}
						}
					} else {
						if ($test_mode) {
							if ($old_marker) {
								$results[] = array(
									'status' => 2,
									'message' => sprintf(esc_html__('Marker with ID %1$s will be updated', 'mmp'), $marker['id'])
								);
							} else {
								$results[] = array(
									'status' => 1,
									'message' => esc_html__('New marker will be added', 'mmp')
								);
							}
						} else {
							if ($old_marker) {
								$result = $db->update_marker((object) $marker, $marker['id'], $geocoding_flag);
								if ($result !== false) {
									if (isset($feature['properties']['maps'])) {
										$maps = $db->sanitize_ids($feature['properties']['maps']);
										foreach ($maps as $map) {
											$db->assign_marker($map, $marker['id']);
										}
									}
									$results[] = array(
										'status' => 2,
										'message' => sprintf(esc_html__('Marker with ID %1$s successfully updated', 'mmp'), $marker['id'])
									);
								} else {
									$results[] = array(
										'status' => 3,
										'message' => sprintf(esc_html__('Marker with ID %1$s could not be updated', 'mmp'), $marker['id'])
									);
								}
							} else {
								$result = $db->add_marker((object) $marker, $geocoding_flag);
								if ($result) {
									if (isset($feature['properties']['maps'])) {
										$maps = $db->sanitize_ids($feature['properties']['maps']);
										foreach ($maps as $map) {
											$db->assign_marker($map, $result);
										}
									}
									$results[] = array(
										'status' => 1,
										'message' => sprintf(esc_html__('Marker successfully added with ID %1$s', 'mmp'), $result)
									);
								} else {
									$results[] = array(
										'status' => 3,
										'message' => esc_html__('Marker could not be added', 'mmp')
									);
								}
							}
						}
					}
				}
			} else {
				$results[] = array(
					'status' => 3,
					'message' => esc_html__('Invalid geometry type', 'mmp')
				);
			}
		}

		$added = $updated = $skipped = 0;
		foreach ($results as $result) {
			switch ($result['status']) {
				case 1:
					$added++;
					break;
				case 2:
					$updated++;
					break;
				case 3:
					$skipped++;
					break;
			}
		}

		if ($test_mode) {
			$summary = sprintf(esc_html__('%1$s markers will be added, %2$s markers will be updated, %3$s markers will be skipped', 'mmp'), $added, $updated, $skipped);
		} else {
			$summary = sprintf(esc_html__('%1$s markers added, %2$s markers updated, %3$s markers skipped', 'mmp'), $added, $updated, $skipped);
		}

		wp_send_json(array(
			'success' => true,
			'response' => array(
				'result' => $summary,
				'details' => $results
			)
		));
	}

	/**
	 * Exports markers from the database
	 *
	 * @since 4.0
	 */
	public function export() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');
		$api = Maps_Marker_Pro::get_instance('MMP\API');
		$l10n = Maps_Marker_Pro::get_instance('MMP\L10n');

		$filter_mode = (isset($_POST['filter_mode']) && in_array($_POST['filter_mode'], array('all', 'include', 'exclude'))) ? $_POST['filter_mode'] : 'all';
		$filter_include = (isset($_POST['filter_include'])) ? $_POST['filter_include'] : array();
		$filter_exclude = (isset($_POST['filter_exclude'])) ? $_POST['filter_exclude'] : array();

		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-tools-export') === false) {
			wp_send_json(array(
				'success' => false,
				'response' => esc_html__('Security check failed', 'mmp')
			));
		}

		if ($filter_mode === 'include') {
			$filters = array(
				'include_maps' => $filter_include
			);
		} else if ($filter_mode === 'exclude') {
			$filters = array(
				'exclude_maps' => $filter_exclude
			);
		} else {
			$filters = array();
		}

		$json = array(
			'type' => 'FeatureCollection',
			'features' => array()
		);
		$total = $db->count_markers($filters);
		$batches = ceil($total / 1000);
		for ($i = 1; $i <= $batches; $i++) {
			$filters = array_merge($filters, array(
				'offset' => ($i - 1) * 1000,
				'limit' => 1000
			));
			$markers = $db->get_all_markers($filters);
			foreach ($markers as $marker) {
				$json['features'][] = array(
					'type' => 'Feature',
					'geometry' => array(
						'type' => 'Point',
						'coordinates' => array($marker->lng, $marker->lat)
					),
					'properties' => array(
						'id' => $marker->id,
						'name' => $marker->name,
						'address' => $marker->address,
						'zoom' => $marker->zoom,
						'icon' => $marker->icon,
						'popup' => $marker->popup,
						'link' => $marker->link,
						'blank' => $marker->blank,
						'maps' => $marker->maps
					)
				);
			}
		}
		$json = json_encode($json, JSON_PRETTY_PRINT);

		$file = Maps_Marker_Pro::$temp_dir . 'export-' . date('Y-m-d-his') . '.geojson';
		$handle = file_put_contents($file, $json);
		if ($handle === false) {
			wp_send_json(array(
				'success' => false,
				'response' => esc_html__('File could not be written', 'mmp')
			));
		}

		$url = "{$api->base_url}index.php?mapsmarkerpro=download_file&filename=" . basename($file);
		wp_send_json(array(
			'success' => true,
			'response' => array(
				'message' => esc_html__('Export completed successfully', 'mmp') . '<br />' . sprintf($l10n->kses__('If the download does not start automatically, please <a href="%1$s">click here</a>', 'mmp'), $url),
				'url' => $url
			)
		));
	}

	/**
	 * Resets the database
	 *
	 * @since 4.0
	 */
	public function reset_database() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-tools-reset-db') === false) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('Security check failed', 'mmp')
			));
		}
		if (!isset($_POST['confirm']) || !$_POST['confirm']) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('You need to confirm this action', 'mmp')
			));
		}
		$db->reset_tables();
		wp_send_json(array(
			'success' => true,
			'message' => esc_html__('Database reset successfully', 'mmp')
		));
	}

	/**
	 * Resets the settings
	 *
	 * @since 4.0
	 */
	public function reset_settings() {
		$mmp_settings = Maps_Marker_Pro::get_instance('MMP\Settings');

		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-tools-reset-settings') === false) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('Security check failed', 'mmp')
			));
		}
		if (!isset($_POST['confirm']) || !$_POST['confirm']) {
			wp_send_json(array(
				'success' => false,
				'message' => esc_html__('You need to confirm this action', 'mmp')
			));
		}
		update_option('mapsmarkerpro_settings', $mmp_settings->get_default_settings());
		wp_send_json(array(
			'success' => true,
			'message' => esc_html__('Settings reset successfully', 'mmp')
		));
	}

	/**
	 * Returns the table name for a given table index
	 *
	 * @since 4.0
	 */
	private function get_table($index) {
		global $wpdb;
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		$tables = array(
			"{$wpdb->prefix}mmp_layers",
			"{$wpdb->prefix}mmp_maps",
			"{$wpdb->prefix}mmp_markers",
			"{$wpdb->prefix}mmp_relationships"
		);
		if (isset($tables[$index])) {
			return $tables[$index];
		} else {
			return false;
		}
	}

	/**
	 * Returns a list of all maps
	 *
	 * @since 4.0
	 */
	public function get_map_list() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		$maps = $db->get_all_maps(true);
		$map_list = array();
		foreach ($maps as $map) {
			// No escaping, since wp_send_json() escapes automatically
			$map_list[$map->id] = "[{$map->id}] " . $map->name . " ({$map->markers} " . __('markers', 'mmp') . ')';
		}

		return $map_list;
	}

	/**
	 * Shows the tools page
	 *
	 * @since 4.0
	 */
	protected function show() {
		global $wpdb;
		$db = Maps_Marker_Pro::get_instance('MMP\DB');
		$upload = Maps_Marker_Pro::get_instance('MMP\Upload');
		$l10n = Maps_Marker_Pro::get_instance('MMP\L10n');
		$mmp_settings = Maps_Marker_Pro::get_instance('MMP\Settings');

		$maps = $db->get_all_maps(true);
		$settings = $mmp_settings->get_map_defaults();
		$old_version = get_option('leafletmapsmarker_version_pro');

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

		?>
		<div class="wrap mmp-wrap">
			<h1><?= esc_html__('Tools', 'mmp') ?></h1>
			<div class="mmp-tools-tabs">
				<button id="maps_markers_tab" class="mmp-tablink" type="button"><?= esc_html__('Maps and markers', 'mmp') ?></button>
				<button id="import_tab" class="mmp-tablink" type="button"><?= esc_html__('Import markers', 'mmp') ?></button>
				<button id="export_tab" class="mmp-tablink" type="button"><?= esc_html__('Export markers', 'mmp') ?></button>
				<button id="backup_restore_tab" class="mmp-tablink" type="button"><?= esc_html__('Backup and restore', 'mmp') ?></button>
				<?php if ($old_version !== false): ?>
					<button id="migration_tab" class="mmp-tablink" type="button"><?= esc_html__('Data migration', 'mmp') ?></button>
				<?php endif; ?>
				<button id="reset_tab" class="mmp-tablink" type="button"><?= esc_html__('Reset', 'mmp') ?></button>
			</div>
			<div id="maps_markers_tab_content" class="mmp-tools-tab">
				<div id="batch_settings_section" class="mmp-tools-section">
					<h2><?= esc_html__('Batch update map settings', 'mmp') ?></h2>
					<div class="mmp-batch-settings-tabs">
						<button type="button" class="mmp-batch-settings-tablink" data-target="mapDimensions"><?= esc_html__('Map dimensions', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="initialView"><?= esc_html__('Initial view', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="panel"><?= esc_html__('Panel', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="layers"><?= esc_html__('Layers', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="zoomButtons"><?= esc_html__('Zoom buttons', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="fullscreenButton"><?= esc_html__('Fullscreen button', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="resetButton"><?= esc_html__('Reset button', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="locateButton"><?= esc_html__('Locate button', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="scale"><?= esc_html__('Scale', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="layersControl"><?= esc_html__('Layers control', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="filtersControl"><?= esc_html__('Filters control', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="minimap"><?= esc_html__('Minimap', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="attribution"><?= esc_html__('Attribution', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="icon"><?= esc_html__('Icon', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="clustering"><?= esc_html__('Clustering', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="tooltip"><?= esc_html__('Tooltip', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="popup"><?= esc_html__('Popup', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="list"><?= esc_html__('List', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="interaction"><?= esc_html__('Interaction', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="track"><?= esc_html__('Track', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="metadata"><?= esc_html__('Metadata', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="waypoints"><?= esc_html__('Waypoints', 'mmp') ?></button>
						<button type="button" class="mmp-batch-settings-tablink" data-target="elevationChart"><?= esc_html__('Elevation chart', 'mmp') ?></button>
					</div>
					<div class="mmp-batch-settings">
						<form id="mapSettings" method="POST">
							<div id="mapDimensionsContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="widthCheck" class="batch-settings-check" name="widthCheck" />
										<label for="width"><?= esc_html__('Width', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="width" name="width" value="<?= $settings['width'] ?>" min="1" />
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="widthUnitCheck" class="batch-settings-check" name="widthUnitCheck" />
										<label><?= esc_html__('Width unit', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label><input type="radio" id="widthUnitPct" name="widthUnit" value="%" <?= !($settings['widthUnit'] == '%') ?: 'checked="checked"' ?> />%</label>
										<label><input type="radio" id="widthUnitPx" name="widthUnit" value="px" <?= !($settings['widthUnit'] == 'px') ?: 'checked="checked"' ?> />px</label>
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="heightCheck" class="batch-settings-check" name="heightCheck" />
										<label for="height"><?= esc_html__('Height', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="height" name="height" value="<?= $settings['height'] ?>" min="1" />
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="tabbedCheck" class="batch-settings-check" name="tabbedCheck" />
										<label for="tabbed"><?= esc_html__('Tabbed', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="tabbed" name="tabbed" <?= !$settings['tabbed'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
											<span></span>
										</label>
									</div>
								</div>
							</div>
							<div id="initialViewContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="latCheck" class="batch-settings-check" name="latCheck" />
										<label for="lat"><?= esc_html__('Latitude', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="text" id="lat" name="lat" value="<?= $settings['lat'] ?>" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="lngCheck" class="batch-settings-check" name="lngCheck" />
										<label for="lng"><?= esc_html__('Longitude', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="text" id="lng" name="lng" value="<?= $settings['lng'] ?>" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="maxBoundsCheck" class="batch-settings-check" name="maxBoundsCheck" />
										<label for="maxBounds"><?= esc_html__('Max bounds', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<textarea id="maxBounds" name="maxBounds"><?= str_replace(',', ",\n", $settings['maxBounds']) ?></textarea>
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="zoomCheck" class="batch-settings-check" name="zoomCheck" />
										<label for="zoom"><?= esc_html__('Zoom', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="zoom" name="zoom" value="<?= $settings['zoom'] ?>" min="0" max="23" step="0.1" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="minZoomCheck" class="batch-settings-check" name="minZoomCheck" />
										<label for="minZoom"><?= esc_html__('Min zoom', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="minZoom" name="minZoom" value="<?= $settings['minZoom'] ?>" min="0" max="23" step="0.1" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="maxZoomCheck" class="batch-settings-check" name="maxZoomCheck" />
										<label for="maxZoom"><?= esc_html__('Max zoom', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="maxZoom" name="maxZoom" value="<?= $settings['maxZoom'] ?>" min="0" max="23" step="0.1" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="zoomStepCheck" class="batch-settings-check" name="zoomStepCheck" />
										<label for="zoomStep"><?= esc_html__('Zoom step', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="zoomStep" name="zoomStep" value="<?= $settings['zoomStep'] ?>" min="0.1" max="1" step="0.1" />
									</div>
								</div>
							</div>
							<div id="panelContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="panelCheck" class="batch-settings-check" name="panelCheck" />
										<label for="panel"><?= esc_html__('Show', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="panel" name="panel" <?= !$settings['panel'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
											<span></span>
										</label>
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="panelColorCheck" class="batch-settings-check" name="panelColorCheck" />
										<label for="panelColor"><?= esc_html__('Color', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="text" id="panelColor" name="panelColor" value="<?= $settings['panelColor'] ?>" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="panelFsCheck" class="batch-settings-check" name="panelFsCheck" />
										<label for="panelFs"><?= esc_html__('Fullscreen button', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="panelFs" name="panelFs" <?= !$settings['panelFs'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
											<span></span>
										</label>
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="panelGeoJsonCheck" class="batch-settings-check" name="panelGeoJsonCheck" />
										<label for="panelGeoJson"><?= esc_html__('GeoJSON button', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="panelGeoJson" name="panelGeoJson" <?= !$settings['panelGeoJson'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
											<span></span>
										</label>
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="panelKmlCheck" class="batch-settings-check" name="panelKmlCheck" />
										<label for="panelKml"><?= esc_html__('KML button', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="panelKml" name="panelKml" <?= !$settings['panelKml'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
											<span></span>
										</label>
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="panelGeoRssCheck" class="batch-settings-check" name="panelGeoRssCheck" />
										<label for="panelGeoRss"><?= esc_html__('GeoRss button', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<div class="switch">
												<input type="checkbox" id="panelGeoRss" name="panelGeoRss" <?= !$settings['panelGeoRss'] ?: 'checked="checked"' ?> />
												<span class="slider"></span>
											</div>
											<span></span>
										</label>
									</div>
								</div>
							</div>
							<div id="layersContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="basemapDetectRetinaCheck" class="batch-settings-check" name="basemapDetectRetinaCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="basemapEdgeBufferTilesCheck" class="batch-settings-check" name="basemapEdgeBufferTilesCheck" />
										<label for="basemapEdgeBufferTiles"><?= esc_html__('Edge buffer tiles', 'mmp') ?>*</label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="basemapEdgeBufferTiles" name="basemapEdgeBufferTiles" value="<?= $settings['basemapEdgeBufferTiles'] ?>" min="0" max="10" step="1" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="basemapGoogleStylesCheck" class="batch-settings-check" name="basemapGoogleStylesCheck" />
										<label for="basemapGoogleStyles"><?= esc_html__('Google styles', 'mmp') ?>*</label>
									</div>
									<div class="mmp-map-setting-input">
										<textarea id="basemapGoogleStyles" name="basemapGoogleStyles"><?= $settings['basemapGoogleStyles'] ?></textarea><br />
									</div>
								</div>
							</div>
							<div id="zoomButtonsContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="zoomControlPositionCheck" class="batch-settings-check" name="zoomControlPositionCheck" />
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
							<div id="fullscreenButtonContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="fullscreenPositionCheck" class="batch-settings-check" name="fullscreenPositionCheck" />
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
							<div id="resetButtonContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="resetPositionCheck" class="batch-settings-check" name="resetPositionCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="resetOnDemandCheck" class="batch-settings-check" name="resetOnDemandCheck" />
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
							<div id="locateButtonContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="locatePositionCheck" class="batch-settings-check" name="locatePositionCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="locateDrawCircleCheck" class="batch-settings-check" name="locateDrawCircleCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="locateDrawMarkerCheck" class="batch-settings-check" name="locateDrawMarkerCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="locateSetViewCheck" class="batch-settings-check" name="locateSetViewCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="locateKeepCurrentZoomLevelCheck" class="batch-settings-check" name="locateKeepCurrentZoomLevelCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="locateClickBehaviorInViewCheck" class="batch-settings-check" name="locateClickBehaviorInViewCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="locateClickBehaviorOutOfViewCheck" class="batch-settings-check" name="locateClickBehaviorOutOfViewCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="locateIconCheck" class="batch-settings-check" name="locateIconCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="locateMetricCheck" class="batch-settings-check" name="locateMetricCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="locateShowPopupCheck" class="batch-settings-check" name="locateShowPopupCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="locateAutostartCheck" class="batch-settings-check" name="locateAutostartCheck" />
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
							<div id="scaleContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="scalePositionCheck" class="batch-settings-check" name="scalePositionCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="scaleMaxWidthCheck" class="batch-settings-check" name="scaleMaxWidthCheck" />
										<label for="scaleMaxWidth"><?= esc_html__('Max width', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<input type="number" id="scaleMaxWidth" name="scaleMaxWidth" value="<?= $settings['scaleMaxWidth'] ?>" min="0" step="1" />
										</label>
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="scaleMetricCheck" class="batch-settings-check" name="scaleMetricCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="scaleImperialCheck" class="batch-settings-check" name="scaleImperialCheck" />
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
							<div id="layersControlContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="layersPositionCheck" class="batch-settings-check" name="layersPositionCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="layersCollapsedCheck" class="batch-settings-check" name="layersCollapsedCheck" />
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
							<div id="filtersControlContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="filtersPositionCheck" class="batch-settings-check" name="filtersPositionCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="filtersCollapsedCheck" class="batch-settings-check" name="filtersCollapsedCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="filtersButtonsCheck" class="batch-settings-check" name="filtersButtonsCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="filtersIconCheck" class="batch-settings-check" name="filtersIconCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="filtersNameCheck" class="batch-settings-check" name="filtersNameCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="filtersCountCheck" class="batch-settings-check" name="filtersCountCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="filtersOrderByCheck" class="batch-settings-check" name="filtersOrderByCheck" />
										<label for="filtersOrderBy"><?= esc_html__('Order by', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<select id="filtersOrderBy" name="filtersOrderBy">
											<option value="id" <?= !($settings['filtersOrderBy'] == 'id') ?: 'selected="selected"' ?>><?= esc_html__('ID', 'mmp') ?></option>
											<option value="name" <?= !($settings['filtersOrderBy'] == 'name') ?: 'selected="selected"' ?>><?= esc_html__('Name', 'mmp') ?></option>
											<option value="count" <?= !($settings['filtersOrderBy'] == 'count') ?: 'selected="selected"' ?>><?= esc_html__('Count', 'mmp') ?></option>
											<option value="custom" <?= !($settings['filtersOrderBy'] == 'custom') ?: 'selected="selected"' ?>><?= esc_html__('Custom', 'mmp') ?></option>
										</select>
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="filtersSortOrderCheck" class="batch-settings-check" name="filtersSortOrderCheck" />
										<label for="filtersSortOrder"><?= esc_html__('Sort order', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<select id="filtersSortOrder" name="filtersSortOrder" <?= !($settings['filtersOrderBy'] == 'custom') ? '' : 'disabled="disabled"' ?>>
											<option value="asc" <?= !($settings['filtersSortOrder'] == 'asc') ?: 'selected="selected"' ?>><?= esc_html__('Ascending', 'mmp') ?></option>
											<option value="desc" <?= !($settings['filtersSortOrder'] == 'desc') ?: 'selected="selected"' ?>><?= esc_html__('Descending', 'mmp') ?></option>
										</select>
									</div>
								</div>
							</div>
							<div id="minimapContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="minimapPositionCheck" class="batch-settings-check" name="minimapPositionCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="minimapMinimizedCheck" class="batch-settings-check" name="minimapMinimizedCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="minimapWidthCheck" class="batch-settings-check" name="minimapWidthCheck" />
										<label for="minimapWidth"><?= esc_html__('Width', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<input type="number" id="minimapWidth" name="minimapWidth" value="<?= $settings['minimapWidth'] ?>" min="1" step="1" />
										</label>
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="minimapHeightCheck" class="batch-settings-check" name="minimapHeightCheck" />
										<label for="minimapHeight"><?= esc_html__('Height', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<input type="number" id="minimapHeight" name="minimapHeight" value="<?= $settings['minimapHeight'] ?>" min="1" step="1" />
										</label>
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="minimapCollapsedWidthCheck" class="batch-settings-check" name="minimapCollapsedWidthCheck" />
										<label for="minimapCollapsedWidth"><?= esc_html__('Collapsed width', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<input type="number" id="minimapCollapsedWidth" name="minimapCollapsedWidth" value="<?= $settings['minimapCollapsedWidth'] ?>" min="1" step="1" />
										</label>
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="minimapCollapsedHeightCheck" class="batch-settings-check" name="minimapCollapsedHeightCheck" />
										<label for="minimapCollapsedHeight"><?= esc_html__('Collapsed height', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<input type="number" id="minimapCollapsedHeight" name="minimapCollapsedHeight" value="<?= $settings['minimapCollapsedHeight'] ?>" min="1" step="1" />
										</label>
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="minimapZoomLevelOffsetCheck" class="batch-settings-check" name="minimapZoomLevelOffsetCheck" />
										<label for="minimapZoomLevelOffset"><?= esc_html__('Zoom level offset', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<input type="number" id="minimapZoomLevelOffset" name="minimapZoomLevelOffset" value="<?= $settings['minimapZoomLevelOffset'] ?>" min="-23" max="23" step="0.1" />
										</label>
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="minimapZoomLevelFixedCheck" class="batch-settings-check" name="minimapZoomLevelFixedCheck" />
										<label for="minimapZoomLevelFixed"><?= esc_html__('Fixed zoom level', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<label>
											<input type="number" id="minimapZoomLevelFixed" name="minimapZoomLevelFixed" value="<?= $settings['minimapZoomLevelFixed'] ?>" min="0" max="23" step="0.1" />
										</label>
									</div>
								</div>
							</div>
							<div id="attributionContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="attributionPositionCheck" class="batch-settings-check" name="attributionPositionCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="attributionCondensedCheck" class="batch-settings-check" name="attributionCondensedCheck" />
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
							<div id="iconContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="markerOpacityCheck" class="batch-settings-check" name="markerOpacityCheck" />
										<label for="markerOpacity"><?= esc_html__('Opacity', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="markerOpacity" name="markerOpacity" value="<?= $settings['markerOpacity'] ?>" min="0" max="1" step="0.01" />
									</div>
								</div>
							</div>
							<div id="clusteringContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="clusteringCheck" class="batch-settings-check" name="clusteringCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="showCoverageOnHoverCheck" class="batch-settings-check" name="showCoverageOnHoverCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="disableClusteringAtZoomCheck" class="batch-settings-check" name="disableClusteringAtZoomCheck" />
										<label for="disableClusteringAtZoom"><?= esc_html__('Disable at zoom', 'mmp') ?>*</label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="disableClusteringAtZoom" name="disableClusteringAtZoom" value="<?= $settings['disableClusteringAtZoom'] ?>" min="0" max="23" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="maxClusterRadiusCheck" class="batch-settings-check" name="maxClusterRadiusCheck" />
										<label for="maxClusterRadius"><?= esc_html__('Max cluster radius', 'mmp') ?>*</label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="maxClusterRadius" name="maxClusterRadius" value="<?= $settings['maxClusterRadius'] ?>" min="1" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="singleMarkerModeCheck" class="batch-settings-check" name="singleMarkerModeCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="spiderfyDistanceMultiplierCheck" class="batch-settings-check" name="spiderfyDistanceMultiplierCheck" />
										<label for="spiderfyDistanceMultiplier"><?= esc_html__('Spiderfy multiplier', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="spiderfyDistanceMultiplier" name="spiderfyDistanceMultiplier" value="<?= $settings['spiderfyDistanceMultiplier'] ?>" min="0" max="10" step="0.1" />
									</div>
								</div>
							</div>
							<div id="tooltipContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="tooltipCheck" class="batch-settings-check" name="tooltipCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="tooltipDirectionCheck" class="batch-settings-check" name="tooltipDirectionCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="tooltipPermanentCheck" class="batch-settings-check" name="tooltipPermanentCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="tooltipStickyCheck" class="batch-settings-check" name="tooltipStickyCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="tooltipOpacityCheck" class="batch-settings-check" name="tooltipOpacityCheck" />
										<label for="tooltipOpacity"><?= esc_html__('Opacity', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="tooltipOpacity" name="tooltipOpacity" value="<?= $settings['tooltipOpacity'] ?>" min="0" max="1" step="0.01" />
									</div>
								</div>
							</div>
							<div id="popupContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="popupOpenOnHoverCheck" class="batch-settings-check" name="popupOpenOnHoverCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="popupCenterOnMapCheck" class="batch-settings-check" name="popupCenterOnMapCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="popupMarkernameCheck" class="batch-settings-check" name="popupMarkernameCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="popupAddressCheck" class="batch-settings-check" name="popupAddressCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="popupCoordinatesCheck" class="batch-settings-check" name="popupCoordinatesCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="popupDirectionsCheck" class="batch-settings-check" name="popupDirectionsCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="popupMinWidthCheck" class="batch-settings-check" name="popupMinWidthCheck" />
										<label for="popupMinWidth"><?= esc_html__('Min width', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="popupMinWidth" name="popupMinWidth" value="<?= $settings['popupMinWidth'] ?>" min="0" />
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="popupMaxWidthCheck" class="batch-settings-check" name="popupMaxWidthCheck" />
										<label for="popupMaxWidth"><?= esc_html__('Max width', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="popupMaxWidth" name="popupMaxWidth" value="<?= $settings['popupMaxWidth'] ?>" min="0" />
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="popupMaxHeightCheck" class="batch-settings-check" name="popupMaxHeightCheck" />
										<label for="popupMaxHeight"><?= esc_html__('Max height', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="popupMaxHeight" name="popupMaxHeight" value="<?= $settings['popupMaxHeight'] ?>" min="0" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="popupCloseButtonCheck" class="batch-settings-check" name="popupCloseButtonCheck" />
										<label for="popupCloseButton"><?= esc_html__('Add close button', 'mmp') ?></label>
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="popupAutoCloseCheck" class="batch-settings-check" name="popupAutoCloseCheck" />
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
							<div id="listContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listCheck" class="batch-settings-check" name="listCheck" />
										<label for="list"><?= esc_html__('Show', 'mmp') ?>*</label>
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listWidthCheck" class="batch-settings-check" name="listWidthCheck" />
										<label for="listWidth"><?= esc_html__('Width', 'mmp') ?>*</label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="listWidth" name="listWidth" value="<?= $settings['listWidth'] ?>" min="0" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listBreakpointCheck" class="batch-settings-check" name="listBreakpointCheck" />
										<label for="listBreakpoint"><?= esc_html__('Breakpoint', 'mmp') ?>*</label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="listBreakpoint" name="listBreakpoint" value="<?= $settings['listBreakpoint'] ?>" min="0" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listSearchCheck" class="batch-settings-check" name="listSearchCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listIconCheck" class="batch-settings-check" name="listIconCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listNameCheck" class="batch-settings-check" name="listNameCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listPopupCheck" class="batch-settings-check" name="listPopupCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listAddressCheck" class="batch-settings-check" name="listAddressCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listDistanceCheck" class="batch-settings-check" name="listDistanceCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listDistanceUnitCheck" class="batch-settings-check" name="listDistanceUnitCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listDistancePrecisionCheck" class="batch-settings-check" name="listDistancePrecisionCheck" />
										<label for="listDistancePrecision"><?= esc_html__('Distance precision', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="listDistancePrecision" name="listDistancePrecision" value="<?= $settings['listDistancePrecision'] ?>" min="0" max="6" />
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listOrderByCheck" class="batch-settings-check" name="listOrderByCheck" />
										<label for="listOrderBy"><?= esc_html__('Default order by', 'mmp') ?></label>
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
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listSortOrderCheck" class="batch-settings-check" name="listSortOrderCheck" />
										<label for="listSortOrder"><?= esc_html__('Default sort order', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<select id="listSortOrder" name="listSortOrder">
											<option value="asc" <?= !($settings['listSortOrder'] == 'asc') ?: 'selected="selected"' ?>><?= esc_html__('Ascending', 'mmp') ?></option>
											<option value="desc" <?= !($settings['listSortOrder'] == 'desc') ?: 'selected="selected"' ?>><?= esc_html__('Descending', 'mmp') ?></option>
										</select>
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listOrderByIdCheck" class="batch-settings-check" name="listOrderByIdCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listOrderByNameCheck" class="batch-settings-check" name="listOrderByNameCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listOrderByAddressCheck" class="batch-settings-check" name="listOrderByAddressCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listOrderByDistanceCheck" class="batch-settings-check" name="listOrderByDistanceCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listOrderByIconCheck" class="batch-settings-check" name="listOrderByIconCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listOrderByCreatedCheck" class="batch-settings-check" name="listOrderByCreatedCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listOrderByUpdatedCheck" class="batch-settings-check" name="listOrderByUpdatedCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listLimitCheck" class="batch-settings-check" name="listLimitCheck" />
										<label for="listLimit"><?= esc_html__('Markers per page', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="listLimit" name="listLimit" value="<?= $settings['listLimit'] ?>" min="1" />
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listDirCheck" class="batch-settings-check" name="listDirCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listFsCheck" class="batch-settings-check" name="listFsCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="listActionCheck" class="batch-settings-check" name="listActionCheck" />
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
							<div id="interactionContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gestureHandlingCheck" class="batch-settings-check" name="gestureHandlingCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="responsiveCheck" class="batch-settings-check" name="responsiveCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="boxZoomCheck" class="batch-settings-check" name="boxZoomCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="doubleClickZoomCheck" class="batch-settings-check" name="doubleClickZoomCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="draggingCheck" class="batch-settings-check" name="draggingCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="inertiaCheck" class="batch-settings-check" name="inertiaCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="inertiaDecelerationCheck" class="batch-settings-check" name="inertiaDecelerationCheck" />
										<label for="inertiaDeceleration"><?= esc_html__('Inertia deceleration', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="inertiaDeceleration" name="inertiaDeceleration" value="<?= $settings['inertiaDeceleration'] ?>" min="0" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="inertiaMaxSpeedCheck" class="batch-settings-check" name="inertiaMaxSpeedCheck" />
										<label for="inertiaMaxSpeed"><?= esc_html__('Inertia max speed', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="inertiaMaxSpeed" name="inertiaMaxSpeed" value="<?= $settings['inertiaMaxSpeed'] ?>" min="0" />
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="keyboardCheck" class="batch-settings-check" name="keyboardCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="keyboardPanDeltaCheck" class="batch-settings-check" name="keyboardPanDeltaCheck" />
										<label for="keyboardPanDelta"><?= esc_html__('Keyboard pan delta', 'mmp') ?>*</label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="keyboardPanDelta" name="keyboardPanDelta" value="<?= $settings['keyboardPanDelta'] ?>" min="0" />
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="scrollWheelZoomCheck" class="batch-settings-check" name="scrollWheelZoomCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="touchZoomCheck" class="batch-settings-check" name="touchZoomCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="bounceAtZoomLimitsCheck" class="batch-settings-check" name="bounceAtZoomLimitsCheck" />
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
							<div id="trackContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxIconsCheck" class="batch-settings-check" name="gpxIconsCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxTrackSmoothFactorCheck" class="batch-settings-check" name="gpxTrackSmoothFactorCheck" />
										<label for="gpxTrackSmoothFactor"><?= esc_html__('Track smooth factor', 'mmp') ?>*</label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="gpxTrackSmoothFactor" name="gpxTrackSmoothFactor" value="<?= $settings['gpxTrackSmoothFactor'] ?>" min="0" step="0.1" />
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxTrackColorCheck" class="batch-settings-check" name="gpxTrackColorCheck" />
										<label for="gpxTrackColor"><?= esc_html__('Track color', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="text" id="gpxTrackColor" name="gpxTrackColor" value="<?= $settings['gpxTrackColor'] ?>" />
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxTrackWeightCheck" class="batch-settings-check" name="gpxTrackWeightCheck" />
										<label for="gpxTrackWeight"><?= esc_html__('Track weight', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="gpxTrackWeight" name="gpxTrackWeight" value="<?= $settings['gpxTrackWeight'] ?>" min="0" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxTrackOpacityCheck" class="batch-settings-check" name="gpxTrackOpacityCheck" />
										<label for="gpxTrackOpacity"><?= esc_html__('Track opacity', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="gpxTrackOpacity" name="gpxTrackOpacity" value="<?= $settings['gpxTrackOpacity'] ?>" min="0" max="1" step="0.01" />
									</div>
								</div>
							</div>
							<div id="metadataContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxMetaCheck" class="batch-settings-check" name="gpxMetaCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxMetaUnitsCheck" class="batch-settings-check" name="gpxMetaUnitsCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxMetaIntervalCheck" class="batch-settings-check" name="gpxMetaIntervalCheck" />
										<label for="gpxMetaInterval"><?= esc_html__('Max interval', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="gpxMetaInterval" name="gpxMetaInterval" value="<?= $settings['gpxMetaInterval'] ?>" min="0" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxMetaNameCheck" class="batch-settings-check" name="gpxMetaNameCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxMetaDescCheck" class="batch-settings-check" name="gpxMetaDescCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxMetaStartCheck" class="batch-settings-check" name="gpxMetaStartCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxMetaEndCheck" class="batch-settings-check" name="gpxMetaEndCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxMetaTotalCheck" class="batch-settings-check" name="gpxMetaTotalCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxMetaMovingCheck" class="batch-settings-check" name="gpxMetaMovingCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxMetaDistanceCheck" class="batch-settings-check" name="gpxMetaDistanceCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxMetaPaceCheck" class="batch-settings-check" name="gpxMetaPaceCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxMetaHeartRateCheck" class="batch-settings-check" name="gpxMetaHeartRateCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxMetaElevationCheck" class="batch-settings-check" name="gpxMetaElevationCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxMetaDownloadCheck" class="batch-settings-check" name="gpxMetaDownloadCheck" />
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
							<div id="waypointsContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxWaypointsCheck" class="batch-settings-check" name="gpxWaypointsCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxWaypointsRadiusCheck" class="batch-settings-check" name="gpxWaypointsRadiusCheck" />
										<label for="gpxWaypointsRadius"><?= esc_html__('Waypoints radius', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="gpxWaypointsRadius" name="gpxWaypointsRadius" value="<?= $settings['gpxWaypointsRadius'] ?>" min="0" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxWaypointsStrokeCheck" class="batch-settings-check" name="gpxWaypointsStrokeCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxWaypointsColorCheck" class="batch-settings-check" name="gpxWaypointsColorCheck" />
										<label for="gpxWaypointsColor"><?= esc_html__('Stroke color', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="text" id="gpxWaypointsColor" name="gpxWaypointsColor" value="<?= $settings['gpxWaypointsColor'] ?>" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxWaypointsWeightCheck" class="batch-settings-check" name="gpxWaypointsWeightCheck" />
										<label for="gpxWaypointsWeight"><?= esc_html__('Stroke weight', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="gpxWaypointsWeight" name="gpxWaypointsWeight" value="<?= $settings['gpxWaypointsWeight'] ?>" min="0" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxWaypointsFillColorCheck" class="batch-settings-check" name="gpxWaypointsFillColorCheck" />
										<label for="gpxWaypointsFillColor"><?= esc_html__('Fill color', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="text" id="gpxWaypointsFillColor" name="gpxWaypointsFillColor" value="<?= $settings['gpxWaypointsFillColor'] ?>" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxWaypointsFillOpacityCheck" class="batch-settings-check" name="gpxWaypointsFillOpacityCheck" />
										<label for="gpxWaypointsFillOpacity"><?= esc_html__('Fill opacity', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="gpxWaypointsFillOpacity" name="gpxWaypointsFillOpacity" value="<?= $settings['gpxWaypointsFillOpacity'] ?>" min="0" max="1" step="0.01" />
									</div>
								</div>
							</div>
							<div id="elevationChartContent" class="mmp-map-batch-settings-group">
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxChartCheck" class="batch-settings-check" name="gpxChartCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxChartUnitsCheck" class="batch-settings-check" name="gpxChartUnitsCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxChartHeightCheck" class="batch-settings-check" name="gpxChartHeightCheck" />
										<label for="gpxChartHeight"><?= esc_html__('Height', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="gpxChartHeight" name="gpxChartHeight" value="<?= $settings['gpxChartHeight'] ?>" min="0" />
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxChartBgColorCheck" class="batch-settings-check" name="gpxChartBgColorCheck" />
										<label for="gpxChartBgColor"><?= esc_html__('Background color', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="text" id="gpxChartBgColor" name="gpxChartBgColor" value="<?= $settings['gpxChartBgColor'] ?>" />
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxChartGridLinesColorCheck" class="batch-settings-check" name="gpxChartGridLinesColorCheck" />
										<label for="gpxChartGridLinesColor"><?= esc_html__('Grid lines color', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="text" id="gpxChartGridLinesColor" name="gpxChartGridLinesColor" value="<?= $settings['gpxChartGridLinesColor'] ?>" />
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxChartTicksFontColorCheck" class="batch-settings-check" name="gpxChartTicksFontColorCheck" />
										<label for="gpxChartTicksFontColor"><?= esc_html__('Ticks font color', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="text" id="gpxChartTicksFontColor" name="gpxChartTicksFontColor" value="<?= $settings['gpxChartTicksFontColor'] ?>" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxChartLineWidthCheck" class="batch-settings-check" name="gpxChartLineWidthCheck" />
										<label for="gpxChartLineWidth"><?= esc_html__('Line width', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="gpxChartLineWidth" name="gpxChartLineWidth" value="<?= $settings['gpxChartLineWidth'] ?>" min="0" />
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxChartLineColorCheck" class="batch-settings-check" name="gpxChartLineColorCheck" />
										<label for="gpxChartLineColor"><?= esc_html__('Line color', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="text" id="gpxChartLineColor" name="gpxChartLineColor" value="<?= $settings['gpxChartLineColor'] ?>" />
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxChartFillCheck" class="batch-settings-check" name="gpxChartFillCheck" />
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
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxChartFillColorCheck" class="batch-settings-check" name="gpxChartFillColorCheck" />
										<label for="gpxChartFillColor"><?= esc_html__('Fill color', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="text" id="gpxChartFillColor" name="gpxChartFillColor" value="<?= $settings['gpxChartFillColor'] ?>" />
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxChartTooltipBgColorCheck" class="batch-settings-check" name="gpxChartTooltipBgColorCheck" />
										<label for="gpxChartTooltipBgColor"><?= esc_html__('Tooltip background color', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="text" id="gpxChartTooltipBgColor" name="gpxChartTooltipBgColor" value="<?= $settings['gpxChartTooltipBgColor'] ?>" />
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxChartTooltipFontColorCheck" class="batch-settings-check" name="gpxChartTooltipFontColorCheck" />
										<label for="gpxChartTooltipFontColor"><?= esc_html__('Tooltip font color', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="text" id="gpxChartTooltipFontColor" name="gpxChartTooltipFontColor" value="<?= $settings['gpxChartTooltipFontColor'] ?>" />
									</div>
								</div>
								<div class="mmp-map-batch-setting">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxChartLocatorCheck" class="batch-settings-check" name="gpxChartLocatorCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxChartLocatorRadiusCheck" class="batch-settings-check" name="gpxChartLocatorRadiusCheck" />
										<label for="gpxChartLocatorRadius"><?= esc_html__('Locator radius', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="gpxChartLocatorRadius" name="gpxChartLocatorRadius" value="<?= $settings['gpxChartLocatorRadius'] ?>" min="0" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxChartLocatorStrokeCheck" class="batch-settings-check" name="gpxChartLocatorStrokeCheck" />
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
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxChartLocatorColorCheck" class="batch-settings-check" name="gpxChartLocatorColorCheck" />
										<label for="gpxChartLocatorColor"><?= esc_html__('Locator stroke color', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="text" id="gpxChartLocatorColor" name="gpxChartLocatorColor" value="<?= $settings['gpxChartLocatorColor'] ?>" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxChartLocatorWeightCheck" class="batch-settings-check" name="gpxChartLocatorWeightCheck" />
										<label for="gpxChartLocatorWeight"><?= esc_html__('Locator stroke weight', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="gpxChartLocatorWeight" name="gpxChartLocatorWeight" value="<?= $settings['gpxChartLocatorWeight'] ?>" min="0" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxChartLocatorFillColorCheck" class="batch-settings-check" name="gpxChartLocatorFillColorCheck" />
										<label for="gpxChartLocatorFillColor"><?= esc_html__('Locator fill color', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="text" id="gpxChartLocatorFillColor" name="gpxChartLocatorFillColor" value="<?= $settings['gpxChartLocatorFillColor'] ?>" />
									</div>
								</div>
								<div class="mmp-map-batch-setting mmp-advanced">
									<div class="mmp-map-setting-desc">
										<input type="checkbox" id="gpxChartLocatorFillOpacityCheck" class="batch-settings-check" name="gpxChartLocatorFillOpacityCheck" />
										<label for="gpxChartLocatorFillOpacity"><?= esc_html__('Locator fill opacity', 'mmp') ?></label>
									</div>
									<div class="mmp-map-setting-input">
										<input type="number" id="gpxChartLocatorFillOpacity" name="gpxChartLocatorFillOpacity" value="<?= $settings['gpxChartLocatorFillOpacity'] ?>" min="0" max="1" step="0.01" />
									</div>
								</div>
							</div>
							<label><input name="batch_settings_mode" type="radio" value="all" checked="checked" /> <?= esc_html__('Apply settings to all maps', 'mmp') ?></label><br />
							<label><input name="batch_settings_mode" type="radio" value="include" /> <?= esc_html__('Apply settings to these maps', 'mmp') ?></label><br />
							<select id="batch_settings_maps" name="batch_settings_maps[]" multiple="multiple">
								<?php foreach ($maps as $map): ?>
									<option value="<?= $map->id ?>">[<?= $map->id ?>] <?= esc_html($map->name) ?> (<?= $map->markers ?> <?= esc_html__('markers', 'mmp') ?>)</option>
								<?php endforeach; ?>
							</select><br />
							<button type="button" id="save_batch_settings" class="button button-primary" data-nonce="<?= wp_create_nonce('mmp-tools-batch-settings') ?>"><?= esc_html__('Save', 'mmp') ?></button>
						</form>
					</div>
				</div>
				<div id="batch_layers_section" class="mmp-tools-section">
					<h2><?= esc_html__('Batch update layers', 'mmp') ?></h2>
					<div>
						<form id="mapLayers" method="POST">
							<ul id="basemapList"></ul>
							<select id="basemapsList">
								<?php foreach ($basemaps as $bid => $basemaps): ?>
									<option value="<?= $bid ?>"><?= esc_html($basemaps['name']) ?></option>
								<?php endforeach; ?>
							</select><br />
							<button type="button" id="basemapsAdd" class="button button-secondary"><?= esc_html__('Add basemap', 'mmp') ?></button><br />
							<ul id="overlayList"></ul>
							<select id="overlaysList">
								<?php foreach ($overlays as $oid => $overlays): ?>
									<option value="<?= $oid ?>"><?= esc_html($overlays['name']) ?></option>
								<?php endforeach; ?>
							</select><br />
							<button type="button" id="overlaysAdd" class="button button-secondary"><?= esc_html__('Add overlay', 'mmp') ?></button><br />
							<label><input name="batch_layers_mode" type="radio" value="all" checked="checked" /> <?= esc_html__('Apply settings to all maps', 'mmp') ?></label><br />
							<label><input name="batch_layers_mode" type="radio" value="include" /> <?= esc_html__('Apply settings to these maps', 'mmp') ?></label><br />
							<select id="batch_layers_maps" name="batch_layers_maps[]" multiple="multiple">
								<?php foreach ($maps as $map): ?>
									<option value="<?= $map->id ?>">[<?= $map->id ?>] <?= esc_html($map->name) ?> (<?= $map->markers ?> <?= esc_html__('markers', 'mmp') ?>)</option>
								<?php endforeach; ?>
							</select><br />
							<button type="button" id="save_batch_layers" class="button button-primary" data-nonce="<?= wp_create_nonce('mmp-tools-batch-layers') ?>"><?= esc_html__('Save', 'mmp') ?></button>
						</form>
					</div>
				</div>
				<div id="replace_icon_section" class="mmp-tools-section">
					<h2><?= esc_html__('Replace marker icons', 'mmp') ?></h2>
					<div>
						<input type="hidden" id="replaceIcon" value="0" />
						<?= esc_html__('Icon to replace', 'mmp') ?>: <img id="sourceIcon" class="mmp-align-middle" src="<?= plugins_url('images/leaflet/marker.png', __DIR__) ?>" /><br />
						<?= esc_html__('Replacement icon', 'mmp') ?>: <img id="targetIcon" class="mmp-align-middle" src="<?= plugins_url('images/leaflet/marker.png', __DIR__) ?>" /><br />
						<button type="button" id="replace_icon" class="button button-primary" data-nonce="<?= wp_create_nonce('mmp-tools-replace-icon') ?>"><?= esc_html__('Replace', 'mmp') ?></button>
					</div>
				</div>
				<div id="move_markers_section" class="mmp-tools-section">
					<h2><?= esc_html__('Move markers to a map', 'mmp') ?></h2>
					<div>
						<label>
							<?= esc_html__('Source', 'mmp') ?>
							<select id="move_markers_source" name="move_markers_source">
								<?php foreach ($maps as $map): ?>
									<option value="<?= $map->id ?>">[<?= $map->id ?>] <?= esc_html($map->name) ?> (<?= $map->markers ?> <?= esc_html__('markers', 'mmp') ?>)</option>
								<?php endforeach; ?>
							</select>
						</label><br />
						<label>
							<?= esc_html__('Target', 'mmp') ?>
							<select id="move_markers_target" name="move_markers_target">
								<?php foreach ($maps as $map): ?>
									<option value="<?= $map->id ?>">[<?= $map->id ?>] <?= esc_html($map->name) ?> (<?= $map->markers ?> <?= esc_html__('markers', 'mmp') ?>)</option>
								<?php endforeach; ?>
							</select>
						</label><br />
						<button type="button" id="move_markers" class="button button-primary" data-nonce="<?= wp_create_nonce('mmp-tools-move-markers') ?>"><?= esc_html__('Move markers', 'mmp') ?></button>
					</div>
				</div>
				<div id="remove_markers_section" class="mmp-tools-section">
					<h2><?= esc_html__('Remove all markers from a map', 'mmp') ?></h2>
					<div>
						<p><?= esc_html__('Note: markers are only unassigned, but not deleted.', 'mmp') ?></p>
						<label>
							<?= esc_html__('Source', 'mmp') ?>
							<select id="remove_markers_map" name="remove_markers_map">
								<?php if ($maps !== null): ?>
									<?php foreach ($maps as $map): ?>
										<option value="<?= $map->id ?>">[<?= $map->id ?>] <?= esc_html($map->name) ?> (<?= $map->markers ?> <?= esc_html__('markers', 'mmp') ?>)</option>
									<?php endforeach; ?>
								<?php endif; ?>
							</select>
						</label><br />
						<button type="button" id="remove_markers" class="button button-primary" data-nonce="<?= wp_create_nonce('mmp-tools-remove-markers') ?>"><?= esc_html__('Remove markers', 'mmp') ?></button>
					</div>
				</div>
				<div id="register_strings_section" class="mmp-tools-section">
					<h2><?= esc_html__('Register strings for translation', 'mmp') ?></h2>
					<div>
						<?php if (!$l10n->ml): ?>
							<p><a href="https://www.mapsmarker.com/multilingual/" target="_blank"><?= esc_html__('No supported multilingual plugin installed.', 'mmp') ?></a></p>
						<?php else: ?>
							<button type="button" id="register_strings" class="button button-primary" data-nonce="<?= wp_create_nonce('mmp-tools-register-strings') ?>"><?= esc_html__('Register strings', 'mmp') ?></button>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<div id="import_tab_content" class="mmp-tools-tab">
				<div id="import" class="mmp-tools-section">
					<h2><?= esc_html__('Import markers', 'mmp') ?></h2>
					<div>
						<p>
							<?= sprintf(esc_html__('Import file needs to be in GeoJSON format. For more details about the import feature and a tutorial on how to convert other formats into GeoJSON, please visit %1$s.', 'mmp'), '<a href="https://www.mapsmarker.com/import-export/" target="_blank">https://www.mapsmarker.com/import-export/</a>') ?>
						</p>
						<form id="import_form" method="POST">
							<input name="action" type="hidden" value="mmp_import" />
							<input name="nonce" type="hidden" value="<?= wp_create_nonce('mmp-tools-import') ?>" />
							<div id="import_log" class="mmp-log"></div>
							<?= esc_html__('Test mode', 'mmp') ?>:<br />
							<input name="test_mode" type="radio" value="on" checked="checked" /> <?= esc_html__('On', 'mmp') ?><br />
							<input name="test_mode" type="radio" value="off" /> <?= esc_html__('Off', 'mmp') ?><br />
							<?= esc_html__('Marker mode', 'mmp') ?>:<br />
							<input name="marker_mode" type="radio" value="add" checked="checked" /> <?= esc_html__('Add markers', 'mmp') ?><br />
							<input name="marker_mode" type="radio" value="update" /> <?= esc_html__('Update markers', 'mmp') ?><br />
							<input name="marker_mode" type="radio" value="both" /> <?= esc_html__('Update existing, add remaining', 'mmp') ?><br />
							<?= esc_html__('Geocoding', 'mmp') ?>:<br />
							<input name="geocoding" type="radio" value="off" checked="checked" /> <?= esc_html__('Off (markers with missing coordinates will be skipped)', 'mmp') ?><br />
							<input name="geocoding" type="radio" value="missing" /> <?= esc_html__('Missing (markers with missing coordinates will be geocoded)', 'mmp') ?><br />
							<input name="geocoding" type="radio" value="on" /> <?= esc_html__('On (all markers will be geocoded)', 'mmp') ?><br />
							<button id="import_start" class="button button-primary" disabled="disabled"><?= esc_html__('Start import', 'mmp') ?></button>
							<input id="import_file" name="file" type="file" />
							<input id="import_max_size" type="hidden" value="<?= $upload->get_max_upload_size(); ?>" />
							<span id="import_filesize_error">(<?= esc_html__('Maximum upload size exceeded', 'mmp') ?>)</span>
						</form>
					</div>
				</div>
			</div>
			<div id="export_tab_content" class="mmp-tools-tab">
				<div id="export" class="mmp-tools-section">
					<h2><?= esc_html__('Export markers', 'mmp') ?></h2>
					<div>
						<p>
							<?= sprintf(esc_html__('Markers will be exported to GeoJSON format. For more details about the export feature and a tutorial on how to convert the output file into other formats, please visit %1$s.', 'mmp'), '<a href="https://www.mapsmarker.com/import-export/" target="_blank">https://www.mapsmarker.com/import-export/</a>') ?>
						</p>
						<form id="export_form" method="POST">
							<input name="action" type="hidden" value="mmp_export" />
							<input name="nonce" type="hidden" value="<?= wp_create_nonce('mmp-tools-export') ?>" />
							<div id="export_log" class="mmp-log"></div>
							<?= esc_html__('Filter mode', 'mmp') ?>:<br />
							<input name="filter_mode" type="radio" value="all" checked="checked" /> <?= esc_html__('All markers', 'mmp') ?><br />
							<input name="filter_mode" type="radio" value="include" /> <?= esc_html__('Only markers from these maps', 'mmp') ?><br />
							<select id="export_include" name="filter_include[]" multiple="multiple">
								<?php foreach ($maps as $map): ?>
									<option value="<?= $map->id ?>">[<?= $map->id ?>] <?= esc_html($map->name) ?> (<?= $map->markers ?> <?= esc_html__('markers', 'mmp') ?>)</option>
								<?php endforeach; ?>
							</select><br />
							<input name="filter_mode" type="radio" value="exclude" /> <?= esc_html__('All markers except from these maps', 'mmp') ?><br />
							<select id="export_exclude" name="filter_exclude[]" multiple="multiple">
								<?php foreach ($maps as $map): ?>
									<option value="<?= $map->id ?>">[<?= $map->id ?>] <?= esc_html($map->name) ?> (<?= $map->markers ?> <?= esc_html__('markers', 'mmp') ?>)</option>
								<?php endforeach; ?>
							</select><br />
							<button id="export_start" class="button button-primary"><?= esc_html__('Start export', 'mmp') ?></button>
						</form>
					</div>
				</div>
			</div>
			<div id="backup_restore_tab_content" class="mmp-tools-tab">
				<div id="backup_restore_section" class="mmp-tools-section">
					<h2><?= esc_html__('Backup or restore database', 'mmp') ?></h2>
					<div>
						<p>
							<?= esc_html__('This includes custom layers, maps, markers and relationships. Settings need to be backed up separately.', 'mmp') ?>
						</p>
						<div id="backup_log" class="mmp-log"></div>
						<div class="mmp-progress-bar">
							<div id="backup_progress" class="mmp-progress-bar-fill"></div>
						</div>
						<button id="backup_start" class="button button-primary" data-nonce="<?= wp_create_nonce('mmp-tools-backup') ?>"><?= esc_html__('Start backup', 'mmp') ?></button>
						<div class="mmp-restore">
							<p class="mmp-warning"><?= esc_html__('WARNING: If you restore a backup, the entire Maps Marker Pro database will be wiped and replaced with the data from the file. This cannot be undone.', 'mmp') ?></p>
							<button id="restore_start" class="button button-primary" data-nonce="<?= wp_create_nonce('mmp-tools-restore-backup') ?>" disabled="disabled"><?= esc_html__('Restore backup', 'mmp') ?></button>
							<input id="backup_max_size" type="hidden" name="MAX_FILE_SIZE" value="<?= $upload->get_max_upload_size(); ?>" />
							<input id="backup_file" name="file" type="file" />
						</div>
					</div>
				</div>
				<div id="update_settings_section" class="mmp-tools-section">
					<h2><?= esc_html__('Backup or restore settings', 'mmp') ?></h2>
					<div>
						<p>
							<?= esc_html__('Below are your current settings, encoded in JSON. Use copy and paste to create or restore a backup.', 'mmp') ?><br/>
							<?= sprintf(esc_html__('Please be aware that restoring settings from a version other than %1$s will result in settings that have been added, changed or removed in this version to revert to their default values.', 'mmp'), Maps_Marker_Pro::$version) ?><br />
							<?= sprintf($l10n->kses__('In case of any issues, you can always <a href="%1$s">reset the plugin settings</a>.', 'mmp'), get_admin_url(null, 'admin.php?page=mapsmarkerpro_tools#reset')) ?>
						</p>
						<textarea id="settings" class="mmp-tools-settings" name="settings"><?= json_encode(Maps_Marker_Pro::$settings) ?></textarea><br />
						<button type="button" id="update_settings" class="button button-primary" data-nonce="<?= wp_create_nonce('mmp-tools-update-settings') ?>"><?= esc_html__('Update settings', 'mmp') ?></button>
						<?php if (is_multisite() && is_super_admin()): ?>
							<label><input type="checkbox" id="update_settings_multisite" name="update_settings_multisite" /><?= esc_html__('Multisite-only: also update settings on all subsites', 'mmp') ?></label>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php if ($old_version !== false): ?>
				<div id="migration_tab_content" class="mmp-tools-tab">
					<div id="data_migration" class="mmp-tools-section">
						<h2><?= esc_html__('Maps Marker Pro 3.1.1 data migration', 'mmp') ?></h2>
						<div>
							<?php if ($old_version === '3.1.1'): ?>
								<p><?= esc_html__('Maps Marker Pro 4.0 was completely rewritten from scratch and received a new database structure. As a result, existing data needs to be migrated. To make this as risk-free as possible, the plugin folder was renamed (which means it is considered a different plugin by WordPress) and a new database was created. This allows you to easily go back to the old plugin, should you encounter any issues, by simply deactivating this version and reactivating Maps Marker 3.1.1.', 'mmp') ?></p>
								<p class="mmp-warning"><?= esc_html__('Warning: If you migrate your data, any maps or markers created with Maps Marker Pro 4.0 will be deleted and replaced with the Maps Marker Pro 3.1.1 data (which will remain unchanged). This cannot be undone.', 'mmp') ?></p>
								<p><?= sprintf(esc_html__('Please do not delete Maps Marker Pro 3.1.1 until you have verified that all data has been migrated correctly. We also recommend to make a backup of the %1$s and %2$s database tables, to be able to run the migration again at a later point, should it become necessary.', 'mmp'), "<code>{$wpdb->prefix}leafletmapsmarker_layers</code>", "<code>{$wpdb->prefix}leafletmapsmarker_markers</code>") ?></p>
								<p><?= esc_html__('Starting with Maps Marker Pro 4.0, marker maps have been deprecated, but can still be used for backwards compatibility. However, additional shortcode attributes are needed in order to make them look the same. Due to the high risk of doing this programmatically, we require you to replace these shortcodes manually. Start the migration check to get a list of used shortcodes and how they need to be updated. Only shortcodes in posts and pages can be detected, so please also check if you are using any shortcodes in widgets or other places. This only affects shortcodes for marker maps. Shortcodes for layer maps do not need to be updated.', 'mmp') ?></p>
								<div id="migration_log"></div>
								<button id="data_migration_check" class="button button-primary" data-nonce="<?= wp_create_nonce('mmp-tools-check-migration') ?>"><?= esc_html__('Check migration', 'mmp') ?></button>
								<button id="data_migration_start" class="button button-primary" data-nonce="<?= wp_create_nonce('mmp-tools-migration') ?>" disabled="disabled"><?= esc_html__('Start migration', 'mmp') ?></button>
							<?php else: ?>
								<?= sprintf($l10n->kses__('If you want to copy your existing maps to this version, you need to update the old Maps Marker Pro installation to version %1$s first.', 'mmp'), '3.1.1') ?>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endif; ?>
			<div id="reset_tab_content" class="mmp-tools-tab">
				<div id="reset_database_section" class="mmp-tools-section">
					<h2><?= esc_html__('Reset database', 'mmp') ?></h2>
					<div>
						<p><?= esc_html__('This will reset the Maps Marker Pro database. All custom layers, maps, markers and relationships will be deleted. Settings are not affected.', 'mmp') ?></p>
						<p class="mmp-warning"><?= esc_html__('WARNING: this cannot be undone.', 'mmp') ?></p>
						<button type="button" id="reset_database" class="button button-primary" data-nonce="<?= wp_create_nonce('mmp-tools-reset-db') ?>" disabled="disabled"><?= esc_html__('Reset database', 'mmp') ?></button>
						<label><input type="checkbox" id="reset_database_confirm" name="reset_database_confirm" /><?= esc_html__('Are you sure?', 'mmp') ?></label>
					</div>
				</div>

				<div id="reset_settings_section" class="mmp-tools-section">
					<h2><?= esc_html__('Reset settings', 'mmp') ?></h2>
					<div>
						<p><?= esc_html__('This will reset the Maps Marker Pro settings to their default values.', 'mmp') ?></p>
						<p class="mmp-warning"><?= esc_html__('WARNING: this cannot be undone.', 'mmp') ?></p>
						<button type="button" id="reset_settings" class="button button-primary" data-nonce="<?= wp_create_nonce('mmp-tools-reset-settings') ?>" disabled="disabled"><?= esc_html__('Reset settings', 'mmp') ?></button>
						<label><input type="checkbox" id="reset_settings_confirm" name="reset_settings_confirm" /><?= esc_html__('Are you sure?', 'mmp') ?></label>
					</div>
				</div>
			</div>
			<div id="icons" class="mmp-hidden">
				<div id="iconsList">
					<img class="mmp-icon" src="<?= plugins_url('images/leaflet/marker.png', __DIR__) ?>" />
					<?php foreach ($upload->get_icons() as $icon): ?>
						<img class="mmp-icon" src="<?= Maps_Marker_Pro::$icons_url . $icon ?>" />
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
