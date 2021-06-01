<?php
namespace MMP;

class Menu_Markers extends Menu {
	/**
	 * Registers the hooks
	 *
	 * @since 4.0
	 */
	public function init() {
		add_action('admin_enqueue_scripts', array($this, 'load_resources'));
		add_action('wp_ajax_mmp_marker_list', array($this, 'marker_list'));
		add_action('wp_ajax_mmp_delete_marker', array($this, 'delete_marker'));
		add_action('wp_ajax_mmp_bulk_action_markers', array($this, 'bulk_action_markers'));
	}

	/**
	 * Loads the required resources
	 *
	 * @since 4.0
	 *
	 * @param string $hook The current admin page
	 */
	public function load_resources($hook) {
		if (substr($hook, -strlen('mapsmarkerpro_markers')) !== 'mapsmarkerpro_markers') {
			return;
		}

		$this->load_global_resources($hook);

		wp_enqueue_script('mmp-admin');
		wp_add_inline_script('mmp-admin', 'listMarkersActions();');
	}

	/**
	 * Renders the HTML for the current range of the marker list
	 *
	 * @since 4.0
	 */
	public function marker_list() {
		global $current_user;
		$db = Maps_Marker_Pro::get_instance('MMP\DB');
		$l10n = Maps_Marker_Pro::get_instance('MMP\L10n');

		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-marker-list') === false) {
			wp_send_json(array(
				'html' => '<tr><td class="mmp-no-results" colspan="7">' . esc_html__('Security check failed') . '</td></tr>'
			));
		}

		$page = isset($_POST['page']) && absint($_POST['page']) ? absint($_POST['page']) : 1;
		$limit = isset($_POST['limit']) && absint($_POST['limit']) ? absint($_POST['limit']) : 25;
		$search = isset($_POST['search']) ? $_POST['search'] : '';
		$sort = isset($_POST['sort']) ? $_POST['sort'] : '';
		$map_filter = isset($_POST['map']) ? absint($_POST['map']) : 0;
		$order = isset($_POST['order']) && $_POST['order'] === 'desc' ? 'desc' : 'asc';

		$total = $db->count_markers(array(
			'include_maps' => $map_filter,
			'contains' => $search
		));

		$page = ($page > ceil($total / $limit)) ? ceil($total / $limit) : $page;

		$markers = $db->get_all_markers(array(
			'include_maps' => $map_filter,
			'contains' => $search,
			'limit' => $limit,
			'sortorder' => $order,
			'orderby' => $sort,
			'offset' => ($page - 1) * $limit
		));

		ob_start();
		?>
		<tr>
			<th><input type="checkbox" id="selectAll" name="selectAll" /></th>
			<th><a href="" class="mmp-sortable" data-orderby="id" title="<?= esc_html__('click to sort', 'mmp') ?>"><?= esc_html__('ID', 'mmp') ?></a></th>
			<th><a href="" class="mmp-sortable" data-orderby="icon" title="<?= esc_html__('click to sort', 'mmp') ?>"><?= esc_html__('Icon', 'mmp') ?></a></th>
			<th><a href="" class="mmp-sortable" data-orderby="name" title="<?= esc_html__('click to sort', 'mmp') ?>"><?= esc_html__('Name', 'mmp') ?></a></th>
			<th><a href="" class="mmp-sortable" data-orderby="address" title="<?= esc_html__('click to sort', 'mmp') ?>"><?= esc_html__('Location', 'mmp') ?></a></th>
			<th><a href="" class="mmp-sortable" data-orderby="popup" title="<?= esc_html__('click to sort', 'mmp') ?>"><?= esc_html__('Popup', 'mmp') ?></a></th>
			<th><?= esc_html__('Assigned to map', 'mmp') ?></th>
		</tr>
		<?php if (!count($markers)): ?>
			<tr><td class="mmp-no-results" colspan="7"><?= esc_html__('No results') ?></td></tr>
		<?php else: ?>
			<?php foreach ($markers as $marker): ?>
				<tr>
					<td><input type="checkbox" name="selected[]" value="<?= $marker->id ?>" /></td>
					<td><?= $marker->id ?></td>
					<td><img src="<?= ($marker->icon) ? Maps_Marker_Pro::$icons_url . '/' . $marker->icon : plugins_url('images/leaflet/marker.png', __DIR__) ?>" /></td>
					<td>
						<?php if ($marker->created_by === $current_user->user_login || current_user_can('mmp_edit_other_markers')): ?>
							<a href="<?= get_admin_url(null, 'admin.php?page=mapsmarkerpro_marker&id=' . $marker->id) ?>" title="<?= esc_html__('Edit marker', 'mmp') ?>"><?= ($marker->name) ? esc_html($marker->name) : esc_html__('(no name)', 'mmp') ?></a>
						<?php else: ?>
							<?= ($marker->name) ? esc_html($marker->name) : esc_html__('(no name)', 'mmp') ?>
						<?php endif; ?>
						<div class="mmp-action">
							<ul>
								<?php if ($marker->created_by === $current_user->user_login || current_user_can('mmp_edit_other_markers')): ?>
									<li><a href="<?= get_admin_url(null, 'admin.php?page=mapsmarkerpro_marker&id=' . $marker->id) ?>" title="<?= esc_html__('Edit marker', 'mmp') ?>"><?= esc_html__('Edit', 'mmp') ?></a></li>
								<?php endif; ?>
								<?php if ($marker->created_by === $current_user->user_login || current_user_can('mmp_delete_other_markers')): ?>
									<li><span class="mmp-delete" href="" data-id="<?= $marker->id ?>" title="<?= esc_html__('Delete marker', 'mmp') ?>"><?= esc_html__('Delete', 'mmp') ?></span></li>
								<?php endif; ?>
								<li>
									<?php if ($l10n->ml === 'wpml'): ?>
										<a href="<?= get_admin_url(null, 'admin.php?page=wpml-string-translation%2Fmenu%2Fstring-translation.php&context=Maps+Marker+Pro') ?>" target="_blank"><?= esc_html__('Translate', 'mmp') ?></a>
									<?php elseif ($l10n->ml === 'pll'): ?>
										<a href="<?= get_admin_url(null, 'admin.php?page=mlang_strings&s=Marker+%28ID+' . $marker->id . '%29&group=Maps+Marker+Pro') ?>" target="_blank"><?= esc_html__('Translate', 'mmp') ?></a>
									<?php else: ?>
										<a href="https://www.mapsmarker.com/multilingual/" target="_blank"><?= esc_html__('Translate', 'mmp') ?></a>
									<?php endif; ?>
								</li>
							</ul>
						</div>
					</td>
					<td><?= esc_html($marker->address) ?></td>
					<td><?= wp_strip_all_tags($marker->popup) ?></td>
					<td>
						<?php if ($marker->maps): ?>
							<ul class="mmp-used-in">
								<?php foreach(explode(',', $marker->maps) as $map): ?>
								<?php $map_details = $db->get_map($map) ?>
									<li>
										<a href="<?= get_admin_url(null, 'admin.php?page=mapsmarkerpro_map&id=' . $map) ?>" title="<?= esc_attr__('Edit map', 'mmp') ?>"><?= esc_html($map_details->name) ?> (<?= esc_html__('ID', 'mmp') ?> <?= $map ?>)</a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else: ?>
							<?= esc_html__('Not assigned to any map', 'mmp') ?>
						<?php endif; ?>
					</td>
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
			'search' => $search,
			'map' => $map_filter
		));
	}

	/**
	 * Deletes the marker
	 *
	 * @since 4.0
	 */
	public function delete_marker() {
		global $current_user;
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-marker-list') === false) {
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

	public function bulk_action_markers() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-bulk-action-marker') === false) {
			wp_die();
		}

		parse_str($_POST['data'], $data);
		if ($data['bulkAction'] === 'duplicate') {
			$markers = $db->get_markers($data['selected']);
			foreach ($markers as $marker) {
				$db->add_marker($marker);
			}
		} else if ($data['bulkAction'] === 'delete') {
			$markers = $db->get_markers($data['selected']);
			foreach ($markers as $marker) {
				$db->delete_marker($marker->id);
			}
		} else if ($data['bulkAction'] === 'assign') {
			foreach ($data['selected'] as $marker) {
				$db->assign_marker($data['assignTarget'], $marker);
			}
		}
		wp_die();
	}

	/**
	 * Shows the markers page
	 *
	 * @since 4.0
	 */
	protected function show() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		$maps = $db->get_all_maps();

		?>
		<div class="wrap mmp-wrap">
			<h1><?= esc_html__('List all markers', 'mmp') ?></h1>
			<form id="markerList" method="POST">
				<input type="hidden" id="markerListNonce" name="markerListNonce" value="<?= wp_create_nonce('mmp-marker-list') ?>" />
				<div id="pagination_top" class="mmp-pagination mmp-pagination-markers">
					<div>
						<?= esc_html__('Total markers:', 'mmp') ?> <span id="markercount_top">0</span>
					</div>
					<div>
						<button type="button" id="first_top" value="1"><span>&laquo;</span></button>
						<button type="button" id="previous_top" value="1"><span>&lsaquo;</span></button>
						<button type="button" id="next_top" value="1"><span>&rsaquo;</span></button>
						<button type="button" id="last_top" value="1"><span>&raquo;</span></button>
					</div>
					<div>
						<?= esc_html__('Page', 'mmp') ?> <input type="text" id="page_top" value="1" /> <?= esc_html__('of', 'mmp') ?> <span id="pagecount_top">1</span>
					</div>
					<div>
						<?= esc_html__('Markers per page', 'mmp') ?> <input type="text" id="perpage_top" value="25" />
					</div>
					<div>
						<input type="text" id="search_top" class="mmp-search" placeholder="<?= esc_html__('Search markers', 'mmp') ?>" />
					</div>
					<div>
						<select id="map_top" name="map_top">
							<option value="0"><?= esc_html__('All maps', 'mmp') ?></option>
							<?php foreach ($maps as $map): ?>
								<option value="<?= $map->id ?>">[<?= $map->id ?>] <?= esc_html($map->name) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<input type="hidden" id="sortorder" value="asc" />
					<input type="hidden" id="orderby" value="id" />
				</div>
				<table id="marker_list" class="mmp-table mmp-markers"></table>
				<div id="pagination_bottom" class="mmp-pagination mmp-pagination-markers">
					<div>
						<?= esc_html__('Total markers:', 'mmp') ?> <span id="markercount_bottom">0</span>
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
						<?= esc_html__('Markers per page', 'mmp') ?> <input type="text" id="perpage_bottom" value="25" />
					</div>
					<div>
						<input type="text" id="search_bottom" class="mmp-search" placeholder="<?= esc_html__('Search markers', 'mmp') ?>" />
					</div>
					<div>
						<select id="map_bottom" name="map_bottom">
							<option value="0"><?= esc_html__('All maps', 'mmp') ?></option>
							<?php foreach ($maps as $map): ?>
								<option value="<?= $map->id ?>">[<?= $map->id ?>] <?= esc_html($map->name) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<div class="mmp-bulk">
					<input type="hidden" id="bulkNonce" name="bulkNonce" value="<?= wp_create_nonce('mmp-bulk-action-marker') ?>" />
					<ul>
						<li>
							<input type="radio" id="duplicate" name="bulkAction" value="duplicate" />
							<label for="duplicate"><?= esc_html__('Duplicate markers', 'mmp') ?></label>
						</li>
						<li>
							<input type="radio" id="delete" name="bulkAction" value="delete" />
							<label for="delete"><?= esc_html__('Delete markers', 'mmp') ?></label>
						</li>
						<li>
							<input type="radio" id="assign" name="bulkAction" value="assign" />
							<label for="assign"><?= esc_html__('Assign markers to this map', 'mmp') ?></label>
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
