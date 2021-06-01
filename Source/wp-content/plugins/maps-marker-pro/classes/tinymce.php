<?php
namespace MMP;

class TinyMCE {
	/**
	 * Registers the hooks
	 *
	 * @since 4.0
	 */
	public function init() {
		if (Maps_Marker_Pro::$settings['tinyMce']) {
			if (isset($_GET['page']) && $_GET['page'] === 'mapsmarkerpro_marker') {
				return;
			}

			add_action('wp_enqueue_media', array($this, 'load_resources'));
			add_action('media_buttons', array($this, 'add_button'));
			add_action('wp_ajax_mmp_get_shortcode_list', array($this, 'get_shortcode_list'));
		}
	}

	/**
	 * Loads the required resources
	 *
	 * @since 4.0
	 */
	public function load_resources() {
		wp_enqueue_style('mmp-shortcode');
		wp_enqueue_script('mmp-shortcode');
	}

	/**
	 * Adds the shortcode button above the editor
	 *
	 * @since 4.0
	 *
	 * @param string $editor_id The HTML ID of the editor that triggered the hook
	 */
	public function add_button($editor_id) {
		?><button type="button" id="mmp-shortcode-button" class="button button-secondary"><?= esc_html__('Add Map', 'mmp') ?></button><?php
	}

	/**
	 * Displays the list of shortcodes
	 *
	 * @since 4.0
	 */
	public function get_shortcode_list() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		$maps = $db->get_all_maps(false, array(
			'orderby' => 'id',
			'sortorder' => 'desc'
		));

		?>
		<input type="hidden" id="mmp-shortcode-selected" value="0" />
		<input type="text" id="mmp-shortcode-search" placeholder="<?= esc_attr__('Search', 'mmp') ?>" />
		<?php if (current_user_can('mmp_add_maps')): ?>
			<a class="button button-secondary mmp-shortcode-add-map" href="<?= get_admin_url(null, 'admin.php?page=mapsmarkerpro_map') ?>" target="_blank"><?= esc_html__('Add new map', 'mmp') ?></a>
		<?php endif; ?>
		<?php if (!count($maps)): ?>
			<p><?= esc_html__('No map has been created yet.', 'mmp') ?></p>
		<?php else: ?>
			<p><?= esc_html__('Please select the map you would like to add.', 'mmp') ?>:</p>
			<div id="mmp-shortcode-list-container">
				<ul id="mmp-shortcode-list">
					<?php foreach ($maps as $map): ?>
						<li data-id="<?= $map->id ?>"><?= "[ID {$map->id}]" ?> <?= ($map->name) ? esc_html($map->name) : esc_html__('(no name)', 'mmp') ?></li>
					<?php endforeach; ?>
				</ul>
				<p id="mmp-shortcode-no-results"><?= esc_html__('No results', 'mmp') ?></p>
			</div>
		<?php endif; ?>
		<?php

		wp_die();
	}
}
