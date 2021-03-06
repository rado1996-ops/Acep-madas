<?php
namespace MMP;

class Menu_Maps extends Menu {
	/**
	 * Registers the hooks
	 *
	 * @since 4.0
	 */
	public function init() {
		add_action('admin_enqueue_scripts', array($this, 'load_resources'));
		add_action('wp_ajax_mmp_map_list', array($this, 'map_list'));
		add_action('wp_ajax_mmp_delete_map', array($this, 'delete_map'));
		add_action('wp_ajax_mmp_bulk_action_maps', array($this, 'bulk_action_maps'));
	}

	/**
	 * Loads the required resources
	 *
	 * @since 4.0
	 *
	 * @param string $hook The current admin page
	 */
	public function load_resources($hook) {
		if (substr($hook, -strlen('mapsmarkerpro_maps')) !== 'mapsmarkerpro_maps') {
			return;
		}

		$this->load_global_resources($hook);

		wp_enqueue_script('mmp-admin');
		wp_add_inline_script('mmp-admin', 'listMapsActions();');
	}

	/**
	 * Renders the HTML for the current range of the map list
	 *
	 * @since 4.0
	 */
	public function map_list() {
		global $current_user;
		$db = Maps_Marker_Pro::get_instance('MMP\DB');
		$l10n = Maps_Marker_Pro::get_instance('MMP\L10n');
		$api = Maps_Marker_Pro::get_instance('MMP\API');

		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-map-list') === false) {
			wp_send_json(array(
				'html' => '<tr><td class="mmp-no-results" colspan="7">' . esc_html__('Security check failed') . '</td></tr>'
			));
		}

		$page = isset($_POST['page']) && absint($_POST['page']) ? absint($_POST['page']) : 1;
		$limit = isset($_POST['limit']) && absint($_POST['limit']) ? absint($_POST['limit']) : 25;
		$search = isset($_POST['search']) ? $_POST['search'] : '';
		$sort = isset($_POST['sort']) ? $_POST['sort'] : '';
		$order = isset($_POST['order']) && $_POST['order'] === 'desc' ? 'desc' : 'asc';

		$total = $db->count_maps(array(
			'name' => $search
		));

		$page = ($page > ceil($total / $limit)) ? ceil($total / $limit) : $page;

		$maps = $db->get_all_maps(true, array(
			'name' => $search,
			'sortorder' => $order,
			'orderby' => $sort,
			'limit' => $limit,
			'offset' => ($page - 1) * $limit
		));

		$shortcodes = array();
		foreach ($maps as $key => $map) {
			$shortcodes[$map->id] = $db->get_map_shortcodes($map->id);
			$maps[$key]->settings = json_decode($map->settings);
		}

		ob_start();
		?>
		<tr>
			<th><input type="checkbox" id="selectAll" name="selectAll" /></th>
			<th><a href="" class="mmp-sortable" data-orderby="id" title="<?= esc_html__('click to sort', 'mmp') ?>"><?= esc_html__('ID', 'mmp') ?></a></th>
			<th><a href="" class="mmp-sortable" data-orderby="name" title="<?= esc_html__('click to sort', 'mmp') ?>"><?= esc_html__('Name', 'mmp') ?></a></th>
			<th><?= esc_html__('Markers', 'mmp') ?></th>
			<th><a href="" class="mmp-sortable" data-orderby="createdby" title="<?= esc_html__('click to sort', 'mmp') ?>"><?= esc_html__('Created by', 'mmp') ?></a></th>
			<th><?= esc_html__('Used in content', 'mmp') ?></th>
			<th><?= esc_html__('Shortcode', 'mmp') ?></th>
		</tr>
		<?php if (!count($maps)): ?>
			<tr><td class="mmp-no-results" colspan="7"><?= esc_html__('No results') ?></td></tr>
		<?php else: ?>
			<?php foreach ($maps as $map): ?>
				<tr>
					<td><input type="checkbox" name="selected[]" value="<?= $map->id ?>" /></td>
					<td><?= $map->id ?></td>
					<td>
						<?php if ($map->created_by === $current_user->user_login || current_user_can('mmp_edit_other_maps')): ?>
							<a href="<?= get_admin_url(null, 'admin.php?page=mapsmarkerpro_map&id=' . $map->id) ?>" title="<?= esc_html__('Edit map', 'mmp') ?>"><?= ($map->name) ? esc_html($map->name) : esc_html__('(no name)', 'mmp') ?></a>
						<?php else: ?>
							<?= ($map->name) ? esc_html($map->name) : esc_html__('(no name)', 'mmp') ?>
						<?php endif; ?>
						<div class="mmp-action">
							<ul>
								<?php if ($map->created_by === $current_user->user_login || current_user_can('mmp_edit_other_maps')): ?>
									<li><a href="<?= get_admin_url(null, 'admin.php?page=mapsmarkerpro_map&id=' . $map->id) ?>" title="<?= esc_html__('Edit map', 'mmp') ?>"><?= esc_html__('Edit', 'mmp') ?></a></li>
								<?php endif; ?>
								<?php if ($map->created_by === $current_user->user_login || current_user_can('mmp_delete_other_maps')): ?>
									<li><span class="mmp-delete" href="" data-id="<?= $map->id ?>" title="<?= esc_html__('Delete map', 'mmp') ?>"><?= esc_html__('Delete', 'mmp') ?></span></li>
								<?php endif; ?>
								<li>
									<?php if ($l10n->ml === 'wpml'): ?>
										<a href="<?= get_admin_url(null, 'admin.php?page=wpml-string-translation%2Fmenu%2Fstring-translation.php&context=Maps+Marker+Pro&search=' . urlencode($map->name)) ?>" target="_blank"><?= esc_html__('Translate', 'mmp') ?></a>
									<?php elseif ($l10n->ml === 'pll'): ?>
										<a href="<?= get_admin_url(null, 'admin.php?page=mlang_strings&s=Map+%28ID+' . $map->id . '%29&group=Maps+Marker+Pro') ?>" target="_blank"><?= esc_html__('Translate', 'mmp') ?></a>
									<?php else: ?>
										<a href="https://www.mapsmarker.com/multilingual/" target="_blank"><?= esc_html__('Translate', 'mmp') ?></a>
									<?php endif; ?>
								</li>
								<li><a href="<?= get_admin_url(null, 'admin.php?page=mapsmarkerpro_marker&basemap=' . $map->settings->basemapDefault . '&lat=' . $map->settings->lat . '&lng=' . $map->settings->lng . '&zoom=' . $map->settings->zoom . '&map=' . $map->id) ?>" target="_blank"><?= esc_html__('Add marker', 'mmp') ?></a></li>
								<li><a href="<?= $api->link("/fullscreen/{$map->id}/") ?>" target="_blank" title="<?= esc_attr__('Open standalone map in fullscreen mode', 'mmp') ?>"><img src="<?= plugins_url('images/icons/fullscreen.png', __DIR__) ?>" /></a></li>
								<li><a class="mmp-qrcode-link" href="" data-id="<?= $map->id ?>" data-url="<?= $api->link("/fullscreen/{$map->id}/") ?>" title="<?= esc_attr__('Show QR code for fullscreen map', 'mmp') ?>"><img src="<?= plugins_url('images/icons/qr-code.png', __DIR__) ?>" /></a></li>
								<li><a href="<?= $api->link("/export/geojson/{$map->id}/") ?>" target="_blank" title="<?= esc_attr__('Export as GeoJSON', 'mmp') ?>"><img src="<?= plugins_url('images/icons/geojson.png', __DIR__) ?>" /></a></li>
								<li><a href="<?= $api->link("/export/kml/{$map->id}/") ?>" target="_blank" title="<?= esc_attr__('Export as KML', 'mmp') ?>"><img src="<?= plugins_url('images/icons/kml.png', __DIR__) ?>" /></a></li>
								<li><a href="<?= $api->link("/export/georss/{$map->id}/") ?>" target="_blank" title="<?= esc_attr__('Export as GeoRSS', 'mmp') ?>"><img src="<?= plugins_url('images/icons/georss.png', __DIR__) ?>" /></a></li>
							</ul>
							<div id="qrcode_<?= $map->id ?>" class="mmp-qrcode">
								<a href="" download="qr-code-map-<?= $map->id ?>.png"><img src="" title="<?= esc_html__('Download QR code', 'mmp') ?>"/></a>
							</div>
						</div>
					</td>
					<td><?= $map->markers ?></td>
					<td><?= esc_html($map->created_by) ?></td>
					<td>
						<?php if ($shortcodes[$map->id]): ?>
							<ul class="mmp-used-in">
								<?php foreach($shortcodes[$map->id] as $shortcode): ?>
									<li>
										<a href="<?= $shortcode['edit'] ?>" title="<?= esc_attr__('Edit post', 'mmp') ?>"><img src="<?= plugins_url('images/icons/edit-layer.png', __DIR__) ?>" /></a>
										<a href="<?= $shortcode['link'] ?>" title="<?= esc_attr__('View post', 'mmp') ?>"><?= $shortcode['title'] ?></a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else: ?>
							<?= esc_html__('Not used in any content', 'mmp') ?>
						<?php endif; ?>
					</td>
					<td><input class="mmp-shortcode" type="text" value="[<?= Maps_Marker_Pro::$settings['shortcode'] ?> map=&quot;<?= $map->id ?>&quot;]" readonly="readonly" /></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		<?php
		$rows = ob_get_clean();

		wp_send_json(array(
			'html' => $rows,
			'total' => $total,
			'page' => $page,
			'limit' => $limit,
			'search' => $search
		));
	}

	/**
	 * Deletes the map
	 *
	 * @since 4.0
	 */
	public function delete_map() {
		global $current_user;
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-map-list') === false) {
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
				'data'    => array(
					'id'      => $id,
					'confirm' => false,
					'message' => $message
				)
			));
		}

		$db->delete_map($id);

		wp_send_json(array(
			'success' => true,
			'data'    => array(
				'id'      => $id,
				'confirm' => true
			)
		));
	}

	/**
	 * Executes the map bulk actions
	 *
	 * @since 4.0
	 */
	public function bulk_action_maps() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-bulk-action-map') === false) {
			wp_die();
		}

		parse_str($_POST['data'], $data);
		if ($data['bulkAction'] === 'duplicate') {
			$maps = $db->get_maps($data['selected']);
			foreach ($maps as $map) {
				$db->add_map($map);
			}
		} else if ($data['bulkAction'] === 'duplicate-assign') {
			$maps = $db->get_maps($data['selected']);
			foreach ($maps as $map) {
				$id = $db->add_map($map);
				$markers = $db->get_map_markers($map->id);
				foreach ($markers as $marker) {
					$db->assign_marker($id, $marker->id);
				}
			}
		} else if ($data['bulkAction'] === 'delete') {
			$maps = $db->get_maps($data['selected']);
			foreach ($maps as $map) {
				$db->delete_map($map->id);
			}
		} else if ($data['bulkAction'] === 'delete-assign') {
			$target = absint($data['assignTarget']);
			$maps = $db->get_maps($data['selected']);
			foreach ($maps as $map) {
				$markers = $db->get_map_markers($map->id);
				foreach ($markers as $marker) {
					$db->assign_marker($target, $marker->id);
				}
				$db->delete_map($map->id);
			}
		}
		wp_die();
	}

	/**
	 * Shows the maps page
	 *
	 * @since 4.0
	 */
	protected function show() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		$maps = $db->get_all_maps();

		?>
		<div class="wrap mmp-wrap">
			<h1><?= esc_html__('List all maps', 'mmp') ?></h1>
			<form id="mapList" method="POST">
				<input type="hidden" id="mapListNonce" name="mapListNonce" value="<?= wp_create_nonce('mmp-map-list') ?>" />
				<div id="pagination_top" class="mmp-pagination mmp-pagination-maps">
					<div>
						<?= esc_html__('Total maps:', 'mmp') ?> <span id="mapcount_top">0</span>
					</div>
					<div>
						<button type="button" id="first_top" value="1"><span>&laquo;</span></button>
						<button type="button" id="previous_top" value="1"><span>&lsaquo;</span></button>
						<button type="button" id="next_top" value="1"><span>&rsaquo;</span></button>
						<button type="button" id="last_top" value="1"><span>&raquo;</span></i></button>
					</div>
					<div>
						<?= esc_html__('Page', 'mmp') ?> <input type="text" id="page_top" value="1" /> <?= esc_html__('of', 'mmp') ?> <span id="pagecount_top">1</span>
					</div>
					<div>
						<?= esc_html__('Maps per page', 'mmp') ?> <input type="text" id="perpage_top" value="25" />
					</div>
					<div>
						<input type="text" id="search_top" class="mmp-search" placeholder="<?= esc_html__('Search maps', 'mmp') ?>" />
					</div>
					<input type="hidden" id="sortorder" value="asc" />
					<input type="hidden" id="orderby" value="id" />
				</div>
				<table id="map_list" class="mmp-table mmp-maps"></table>
				<div id="pagination_bottom" class="mmp-pagination mmp-pagination-maps">
					<div>
						<?= esc_html__('Total maps:', 'mmp') ?> <span id="mapcount_bottom">0</span>
					</div>
					<div>
						<button type="button" id="first_bottom" value="1"><span>&laquo;</span></button>
						<button type="button" id="previous_bottom" value="1"><span>&lsaquo;</span></button>
						<button type="button" id="next_bottom" value="1"><span>&rsaquo;</span></button>
						<button type="button" id="last_bottom" value="1"><span>&raquo;</span></button>
					</div>
					<div>
						<?= esc_html__('Page', 'mmp') ?> <input type="text" id="page_bottom" value="1" /> <?= esc_html__('of', 'mmp') ?> <span id="pagecount_bottom">1</span>
					</div>
					<div>
						<?= esc_html__('Maps per page', 'mmp') ?> <input type="text" id="perpage_bottom" value="25" />
					</div>
					<div>
						<input type="text" id="search_bottom" class="mmp-search" placeholder="<?= esc_html__('Search maps', 'mmp') ?>" />
					</div>
				</div>
				<div class="mmp-bulk">
					<input type="hidden" id="bulkNonce" name="bulkNonce" value="<?= wp_create_nonce('mmp-bulk-action-map') ?>" />
					<ul>
						<li>
							<input type="radio" id="duplicate" name="bulkAction" value="duplicate" />
							<label for="duplicate"><?= esc_html__('Duplicate maps', 'mmp') ?></label>
						</li>
						<li>
							<input type="radio" id="duplicateAssign" name="bulkAction" value="duplicate-assign" />
							<label for="duplicateAssign"><?= esc_html__('Duplicate maps and assign the same markers', 'mmp') ?></label>
						</li>
						<li>
							<input type="radio" id="delete" name="bulkAction" value="delete" />
							<label for="delete"><?= esc_html__('Delete maps and unassign markers', 'mmp') ?></label>
						</li>
						<li>
							<input type="radio" id="deleteAssign" name="bulkAction" value="delete-assign" />
							<label for="deleteAssign"><?= esc_html__('Delete maps and assign markers to this map', 'mmp') ?></label>
							<select id="assignTarget" name="assignTarget">
								<?php foreach ($maps as $map): ?>
									<option value="<?= $map->id ?>"><?= "[{$map->id}] " . esc_html($map->name) ?></option>
								<?php endforeach; ?>
							</select>
						</li>
					</ul>
					<button id="bulkActionSubmit" class="button button-primary" disabled="disabled"><?= esc_html__('Submit', 'mmp') ?></button>
				</div>
			</form>
		</div>
		<?php
	}
}
