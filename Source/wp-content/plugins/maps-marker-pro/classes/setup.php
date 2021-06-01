<?php
namespace MMP;

class Setup {
	/**
	 * Registers the hooks
	 *
	 * @since 4.0
	 */
	public function init() {
		register_activation_hook(Maps_Marker_Pro::$path, array($this, 'activate'));
		register_deactivation_hook(Maps_Marker_Pro::$path, array($this, 'deactivate'));

		add_action('wpmu_new_blog', array($this, 'add_blog'));
		add_action('delete_blog', array($this, 'delete_blog'));
		add_action('mmp_temp_cleanup', array($this, 'temp_cleanup'));
	}

	/**
	 * Executes when the plugin is activated
	 *
	 * @since 4.0
	 *
	 * @param bool $networkwide Whether the plugin was set to network active on a multisite installation
	 */
	public function activate($networkwide) {
		global $wpdb, $wp_version;
		$php_version = PHP_VERSION;

		$wp_min = 4.5;
		$php_min = 5.4;

		if (!version_compare($wp_version, $wp_min, '>=')) {
			die("[Maps Marker Pro - activation failed!]: WordPress Version $wp_min or higher is needed for this plugin to run properly (you are using version $wp_version) - please upgrade your WordPress installation!");
		}
		if (!version_compare($php_version, $php_min, '>=')) {
			die("[Maps Marker Pro - activation failed]: PHP $php_min or higher is needed for this plugin to run properly (you are using PHP $php_version) - please contact your hoster to upgrade your PHP installation!");
		}

		if (is_plugin_active('leaflet-maps-marker/leaflet-maps-marker.php') || class_exists('Leafletmapsmarker')) {
			$version = get_option('leafletmapsmarker_version');
			$version = ($version) ? $version : '';
			die("[Maps Marker Pro - activation failed]: Please deactivate Leaflet Maps Marker $version first.");
		}
		if (is_plugin_active('leaflet-maps-marker-pro/leaflet-maps-marker.php') || class_exists('LeafletmapsmarkerPro')) {
			$version = get_option('leafletmapsmarker_version_pro');
			$version = ($version) ? $version : '';
			die("[Maps Marker Pro - activation failed]: Please deactivate Maps Marker Pro $version first.");
		}

		if (is_multisite() && $networkwide) {
			$blogs = $wpdb->get_col($wpdb->prepare(
				"SELECT blog_id
				FROM {$wpdb->blogs}
				WHERE site_id = %d",
				$wpdb->siteid
			));
			foreach ($blogs as $blog_id) {
				switch_to_blog($blog_id);
				$this->setup();
				restore_current_blog();
			}
		} else {
			$this->setup();
		}
	}

	/**
	 * Executes when the plugin is deactivated
	 *
	 * @since 4.0
	 */
	public function deactivate() {
		wp_clear_scheduled_hook('mmp_temp_cleanup', array(604800));
	}

	/**
	 * Executes when a new blog is created on a multisite installation
	 *
	 * @since 4.0
	 *
	 * @param $blog_id The ID of the newly created blog
	 */
	public function add_blog($blog_id) {
		if (is_plugin_active_for_network(Maps_Marker_Pro::$file)) {
			switch_to_blog($blog_id);
			$this->setup();
			restore_current_blog();
		}
	}

	/**
	 * Executes when a blog is deleted on a multisite installation
	 *
	 * @since 4.0
	 *
	 * @param $blog_id The ID of the deleted blog
	 */
	public function delete_blog($blog_id) {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		switch_to_blog($blog_id);
		$db->delete_tables();
		restore_current_blog();
	}

	/**
	 * Cleans up the files in the temp directory
	 *
	 * @since 4.0
	 *
	 * @param int $threshold The amount of seconds the file has to have existed before getting deleted
	 */
	public function temp_cleanup($threshold) {
		$handle = @opendir(Maps_Marker_Pro::$temp_dir);
		if ($handle === false) {
			return;
		}
		while (($file = readdir($handle)) !== false) {
			if ($file === '.' || $file === '..') {
				continue;
			}

			if (time() - filemtime(Maps_Marker_Pro::$temp_dir . $file) >= $threshold) {
				unlink(Maps_Marker_Pro::$temp_dir . $file);
			}
		}
		closedir($handle);
	}

	/**
	 * Initializes the plugin
	 *
	 * @since 4.0
	 */
	public function setup() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');
		$mmp_settings = Maps_Marker_Pro::get_instance('MMP\Settings');
		$notice = Maps_Marker_Pro::get_instance('MMP\Notice');
		$api = Maps_Marker_Pro::get_instance('MMP\API');

		$db->create_tables();

		// Give administrators all capabilities to prevent lockouts
		$admin = get_role('administrator');
		foreach (Maps_Marker_Pro::$capabilities as $cap) {
			$admin->add_cap($cap, true);
		}

		add_option('mapsmarkerpro_version', Maps_Marker_Pro::$version);
		add_option('mapsmarkerpro_update', null);
		add_option('mapsmarkerpro_changelog', null);
		add_option('mapsmarkerpro_settings', $mmp_settings->get_default_settings());
		add_option('mapsmarkerpro_map_defaults', $mmp_settings->get_default_map_settings());
		add_option('mapsmarkerpro_marker_defaults', $mmp_settings->get_default_marker_settings());
		add_option('mapsmarkerpro_editor', 'basic');
		add_option('mapsmarkerpro_notices', array());
		add_option('mapsmarkerpro_key', null);
		add_option('mapsmarkerpro_key_trial', null);
		add_option('mapsmarkerpro_key_local', null);

		// Copy keys if a version prior to 4.0 exists
		$key = get_option('leafletmapsmarkerpro_license_key');
		if ($key) {
			update_option('mapsmarkerpro_key', $key);
		}
		$key_trial = get_option('leafletmapsmarkerpro_license_key_trial');
		if ($key_trial) {
			update_option('mapsmarkerpro_key_trial', $key_trial);
		}
		$key_local = get_option('leafletmapsmarkerpro_license_local_key');
		if ($key_local) {
			update_option('mapsmarkerpro_key_local', $key_local);
		}

		// Offer data migration if a version prior to 4.0 exists
		$maps = $db->count_maps();
		$markers = $db->count_markers();
		if (!$maps && !$markers) {
			$old = get_option('leafletmapsmarker_version_pro');
			if ($old === '3.1.1') {
				$notice->add_admin_notice('migration_ok');
				$notice->remove_admin_notice('migration_update');
			} else if ($old !== false) {
				$notice->add_admin_notice('migration_update');
				$notice->remove_admin_notice('migration_ok');
			}
		}

		$api->add_rewrite_rules();
		flush_rewrite_rules(true);

		// WP_Filesystem is only available in the admin area and after the wp_loaded hook
		if (function_exists('WP_Filesystem')) {
			WP_Filesystem();
			if (!is_dir(Maps_Marker_Pro::$cache_dir)) {
				wp_mkdir_p(Maps_Marker_Pro::$cache_dir);
			}
			if (!is_dir(Maps_Marker_Pro::$temp_dir)) {
				wp_mkdir_p(Maps_Marker_Pro::$temp_dir);
			}
			if (!is_dir(Maps_Marker_Pro::$icons_dir)) {
				wp_mkdir_p(Maps_Marker_Pro::$icons_dir);
				unzip_file(Maps_Marker_Pro::$dir . 'images/mapicons/mapicons.zip', Maps_Marker_Pro::$icons_dir);
			}
		}

		if (!wp_next_scheduled('mmp_temp_cleanup', array(604800))) {
			wp_schedule_event(time(), 'daily', 'mmp_temp_cleanup', array(604800));
		}
	}
}
