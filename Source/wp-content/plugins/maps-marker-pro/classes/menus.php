<?php
namespace MMP;

class Menus {
	/**
	 * Registers the hooks
	 *
	 * @since 4.0
	 */
	public function init() {
		add_action('admin_menu', array($this, 'add_menu'));
		if (Maps_Marker_Pro::$settings['adminBar']) {
			add_action('admin_bar_menu', array($this, 'add_admin_bar_menu'), 149);
			add_action('wp_head', array($this, 'admin_bar_css'));
			add_action('admin_head', array($this, 'admin_bar_css'));
		}
	}

	/**
	 * Adds the menu to the backend
	 *
	 * @since 4.0
	 */
	public function add_menu() {
		if (Maps_Marker_Pro::$settings['whitelabelBackend']) {
			$menu_name = esc_attr__('Maps', 'mmp');
		} else {
			$menu_name = 'Maps Marker Pro';
		}

		add_menu_page(
			$menu_name,
			$menu_name,
			'mmp_view_maps',
			'mapsmarkerpro_maps',
			array(Maps_Marker_Pro::get_instance('MMP\Menu_Maps'), 'display'),
			plugins_url('images/icons/admin-menu.png', __DIR__),
			'25.01'
		);
		add_submenu_page(
			'mapsmarkerpro_maps',
			$menu_name . ' - ' . esc_html__('List all maps', 'mmp'),
			'<i class="dashicons dashicons-editor-table mmp-menu-icon"></i>' . esc_html__('List all maps', 'mmp'),
			'mmp_view_maps',
			'mapsmarkerpro_maps',
			array(Maps_Marker_Pro::get_instance('MMP\Menu_Maps'), 'display')
		);
		add_submenu_page(
			'mapsmarkerpro_maps',
			$menu_name . ' - ' . esc_html__('Add or edit map', 'mmp'),
			'<i class="dashicons dashicons-plus mmp-menu-icon"></i>' . esc_html__('Add new map', 'mmp'),
			'mmp_add_maps',
			'mapsmarkerpro_map',
			array(Maps_Marker_Pro::get_instance('MMP\Menu_Map'), 'display')
		);
		add_submenu_page(
			'mapsmarkerpro_maps',
			$menu_name . ' - ' . esc_html__('List all markers', 'mmp'),
			'<i class="dashicons dashicons-editor-table mmp-menu-icon"></i>' . esc_html__('List all markers', 'mmp'),
			'mmp_view_markers',
			'mapsmarkerpro_markers',
			array(Maps_Marker_Pro::get_instance('MMP\Menu_Markers'), 'display')
		);
		add_submenu_page(
			'mapsmarkerpro_maps',
			$menu_name . ' - ' . esc_html__('Add or edit marker', 'mmp'),
			'<i class="dashicons dashicons-plus mmp-menu-icon"></i>' . esc_html__('Add new marker', 'mmp'),
			'mmp_add_markers',
			'mapsmarkerpro_marker',
			array(Maps_Marker_Pro::get_instance('MMP\Menu_Marker'), 'display')
		);
		add_submenu_page(
			'mapsmarkerpro_maps',
			$menu_name . ' - ' . esc_html__('Tools', 'mmp'),
			'<i class="dashicons dashicons-admin-tools mmp-menu-icon"></i>' . esc_html__('Tools', 'mmp'),
			'mmp_use_tools',
			'mapsmarkerpro_tools',
			array(Maps_Marker_Pro::get_instance('MMP\Menu_Tools'), 'display')
		);
		add_submenu_page(
			'mapsmarkerpro_maps',
			$menu_name . ' - ' . esc_html__('Settings', 'mmp'),
			'<i class="dashicons dashicons-admin-settings mmp-menu-icon"></i>' . esc_html__('Settings', 'mmp'),
			'mmp_change_settings',
			'mapsmarkerpro_settings',
			array(Maps_Marker_Pro::get_instance('MMP\Menu_Settings'), 'display')
		);
		add_submenu_page(
			'mapsmarkerpro_maps',
			$menu_name . ' - ' . esc_html__('License settings', 'mmp'),
			'<i class="dashicons dashicons-admin-network mmp-menu-icon"></i>' . esc_html__('License', 'mmp'),
			'activate_plugins',
			'mapsmarkerpro_license',
			array(Maps_Marker_Pro::get_instance('MMP\Menu_License'), 'display')
		);
		add_submenu_page(
			'mapsmarkerpro_maps',
			$menu_name . ' - ' . esc_html__('Support', 'mmp'),
			'<i class="dashicons dashicons-sos mmp-menu-icon"></i>' . esc_html__('Support', 'mmp'),
			'activate_plugins',
			'mapsmarkerpro_support',
			array(Maps_Marker_Pro::get_instance('MMP\Menu_Support'), 'display')
		);
	}

	/**
	 * Adds the menu to the admin bar
	 *
	 * @since 4.0
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The instance of the admin bar object
	 */
	public function add_admin_bar_menu($wp_admin_bar) {
		if (Maps_Marker_Pro::$settings['whitelabelBackend']) {
			$menu_name = esc_html__('Maps', 'mmp');
		} else {
			$menu_name = 'Maps Marker Pro';
		}

		$menus = array(
			array(
				'capability' => 'mmp_view_maps',
				'node' => array(
					'id'    => 'mmp',
					'title' => '<span class="ab-icon"></span><span class="ab-label">' . $menu_name . '</span>'
				)
			),
			array(
				'capability' => 'mmp_view_maps',
				'node' => array(
					'id'     => 'mmp-maps',
					'parent' => 'mmp',
					'title'  => '<i class="mmp-ab-icon dashicons-editor-table"></i>' . esc_html__('List all maps', 'mmp'),
					'href'   => get_admin_url(null, 'admin.php?page=mapsmarkerpro_maps')
				)
			),
			array(
				'capability' => 'mmp_add_maps',
				'node' => array(
					'id'     => 'mmp-add-layer',
					'parent' => 'mmp',
					'title'  => '<i class="mmp-ab-icon dashicons-plus"></i>' . esc_html__('Add new map', 'mmp'),
					'href'   => get_admin_url(null, 'admin.php?page=mapsmarkerpro_map')
				)
			),
			array(
				'capability' => 'mmp_view_markers',
				'node' => array(
					'id'     => 'mmp-markers',
					'parent' => 'mmp',
					'title'  => '<i class="mmp-ab-icon dashicons-editor-table"></i>' . esc_html__('List all markers', 'mmp'),
					'href'   => get_admin_url(null, 'admin.php?page=mapsmarkerpro_markers')
				)
			),
			array(
				'capability' => 'mmp_add_markers',
				'node' => array(
					'id'     => 'mmp-add-marker',
					'parent' => 'mmp',
					'title'  => '<i class="mmp-ab-icon dashicons-plus"></i>' . esc_html__('Add new marker', 'mmp'),
					'href'   => get_admin_url(null, 'admin.php?page=mapsmarkerpro_marker')
				)
			),
			array(
				'capability' => 'mmp_use_tools',
				'node' => array(
					'id'     => 'mmp-tools',
					'parent' => 'mmp',
					'title'  => '<i class="mmp-ab-icon dashicons-admin-tools"></i>' . esc_html__('Tools', 'mmp'),
					'href'   => get_admin_url(null, 'admin.php?page=mapsmarkerpro_tools')
				)
			),
			array(
				'capability' => 'mmp_change_settings',
				'node' => array(
					'id'     => 'mmp-settings',
					'parent' => 'mmp',
					'title'  => '<i class="mmp-ab-icon dashicons-admin-settings"></i>' . esc_html__('Settings', 'mmp'),
					'href'   => get_admin_url(null, 'admin.php?page=mapsmarkerpro_settings')
				)
			),
			array(
				'capability' => 'activate_plugins',
				'node' => array(
					'id'     => 'mmp-license',
					'parent' => 'mmp',
					'title'  => '<i class="mmp-ab-icon dashicons-admin-network"></i>' . esc_html__('License', 'mmp'),
					'href'   => get_admin_url(null, 'admin.php?page=mapsmarkerpro_license')
				)
			),
			array(
				'capability' => 'activate_plugins',
				'node' => array(
					'id'     => 'mmp-support',
					'parent' => 'mmp',
					'title'  => '<i class="mmp-ab-icon dashicons-sos"></i>' . esc_html__('Support', 'mmp'),
					'href'   => get_admin_url(null, 'admin.php?page=mapsmarkerpro_support')
				)
			)
		);

		foreach ($menus as $menu) {
			if (current_user_can($menu['capability'])) {
				$wp_admin_bar->add_node($menu['node']);
			}
		}
	}

	/**
	 * Loads the CSS for the admin bar menu
	 *
	 * @since 4.0
	 */
	public function admin_bar_css() {
		if (!is_admin_bar_showing()) {
			return;
		}

		?>
		<style>
			#wp-admin-bar-mmp .ab-item {
				cursor: pointer;
			}
			#wp-admin-bar-mmp .ab-icon:before {
				content: url(<?= plugins_url('images/icons/adminbar.png', __DIR__) ?>);
			}
			@media (max-width: 782px) {
				#wp-admin-bar-mmp.menupop {
					display: block;
				}
				#wp-admin-bar-mmp .ab-icon:before {
					content: url(<?= plugins_url('images/icons/adminbar-2x.png', __DIR__) ?>);
					margin: 0 5px;
				}
			}
			#wp-admin-bar-mmp .ab-item .mmp-ab-icon {
				display: inline-block;
				padding: 2px 4px 4px 0;
				width: 12px;
				height: 12px;
				line-height: 1;
				font-family: dashicons;
				font-size: 12px;
				font-weight: 400;
				font-style: normal;
				text-align: center;
				text-decoration: inherit;
				text-transform: none;
				text-rendering: auto;
				vertical-align: middle;
				speak: none;
			}
		</style>
		<?php
	}
}
