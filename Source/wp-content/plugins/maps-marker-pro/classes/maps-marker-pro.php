<?php
namespace MMP;

/**
 * Main plugin class
 *
 * @since 4.0
 */
class Maps_Marker_Pro {
	/**
	 * Absolute path to the main plugin file
	 *
	 * @since 4.3
	 * @var string
	 */
	public static $path;

	/**
	 * Absolute path to the root plugin directory
	 * Includes the trailing slash
	 *
	 * @since 4.3
	 * @var string
	 */
	public static $dir;

	/**
	 * Path to the main plugin file
	 * Relative to the WordPress plugins directory
	 * Does not include the leading and trailing slashes
	 *
	 * @since 4.3
	 * @var string
	 */
	public static $file;

	/**
	 * Plugin name
	 *
	 * @since 4.3
	 * @var string
	 */
	public static $name;

	/**
	 * Plugin slug
	 *
	 * @since 4.3
	 * @var string
	 */
	public static $slug;

	/**
	 * Plugin version
	 *
	 * @since 4.0
	 * @var string
	 */
	public static $version;

	/**
	 * Plugin capabilities
	 *
	 * @since 4.0
	 * @var array
	 */
	public static $capabilities;

	/**
	 * Plugin settings
	 *
	 * @since 4.0
	 * @var array
	 */
	public static $settings;

	/**
	 * Absolute path to the plugin cache directory
	 *
	 * @since 4.0
	 * @var string
	 */
	public static $cache_dir;

	/**
	 * Absolute path to the plugin temp directory
	 *
	 * @since 4.0
	 * @var string
	 */
	public static $temp_dir;

	/**
	 * Absolute path to the plugin icons directory
	 *
	 * @since 4.0
	 * @var string
	 */
	public static $icons_dir;

	/**
	 * Absolute URL to the plugin icons directory
	 *
	 * @since 4.0
	 * @var string
	 */
	public static $icons_url;

	/**
	 * List of instantiated classes
	 *
	 * @since 4.0
	 * @see get_instance()
	 * @var array
	 */
	public static $instances;

	/**
	 * Returns a class object, instantiating it if necessary
	 *
	 * @since 4.0
	 * @param string $class Namespace and name of the class
	 * @return object Class object
	 */
	public static function get_instance($class) {
		if (!isset(self::$instances[$class])) {
			self::$instances[$class] = new $class();
		}

		return self::$instances[$class];
	}

	/**
	 * Sets up the class
	 *
	 * @since 4.0
	 * @param string $path Absolute path to the main plugin file
	 */
	public function __construct($path) {
		self::$path = $path;
		self::$dir  = plugin_dir_path($path);
		self::$file = plugin_basename($path);

		self::$name    = 'Maps Marker Pro';
		self::$slug    = 'maps-marker-pro';
		self::$version = '4.5';

		self::$capabilities = array(
			'mmp_view_maps',
			'mmp_add_maps',
			'mmp_edit_other_maps',
			'mmp_delete_other_maps',
			'mmp_view_markers',
			'mmp_add_markers',
			'mmp_edit_other_markers',
			'mmp_delete_other_markers',
			'mmp_use_tools',
			'mmp_change_settings'
		);

		$settings = self::get_instance('MMP\Settings');
		self::$settings = $settings->get_settings();

		$upload_dir = wp_get_upload_dir();
		self::$cache_dir = $upload_dir['basedir'] . '/' . self::$slug . '/cache/';
		self::$temp_dir  = $upload_dir['basedir'] . '/' . self::$slug . '/temp/';
		self::$icons_dir = $upload_dir['basedir'] . '/' . self::$slug . '/icons/';
		self::$icons_url = $upload_dir['baseurl'] . '/' . self::$slug . '/icons/';
	}

	/**
	 * Initializes the class
	 *
	 * @since 4.0
	 */
	public function init() {
		add_filter('widget_text', 'do_shortcode'); // Parse shortcode in widgets
		add_filter('term_description', 'do_shortcode'); // Parse shortcodes in term descriptions
		add_filter('upload_mimes', array($this, 'filter_upload_mimes'));
		add_filter('post_mime_types', array($this, 'filter_post_mime_types'));
		add_filter('wp_check_filetype_and_ext', array($this, 'filter_check_filetype_and_ext'), 99, 5);
		add_filter('plugin_action_links_' . self::$file, array($this,'filter_plugin_action_links'));
		add_filter('network_admin_plugin_action_links_' . self::$file, array($this,'filter_plugin_action_links'));

		self::get_instance('MMP\SPBAS')->init();
		self::get_instance('MMP\Upload')->init();
		self::get_instance('MMP\Resources')->init();
		self::get_instance('MMP\API')->init();
		self::get_instance('MMP\Update')->init();
		self::get_instance('MMP\L10n')->init();
		self::get_instance('MMP\Map')->init();
		self::get_instance('MMP\Shortcodes')->init();
		self::get_instance('MMP\Menus')->init();
		self::get_instance('MMP\Menu_License')->init();
		self::get_instance('MMP\Menu_Maps')->init();
		self::get_instance('MMP\Menu_Map')->init();
		self::get_instance('MMP\Menu_Markers')->init();
		self::get_instance('MMP\Menu_Marker')->init();
		self::get_instance('MMP\Menu_Tools')->init();
		self::get_instance('MMP\Menu_Settings')->init();
		self::get_instance('MMP\Menu_Support')->init();
		self::get_instance('MMP\Migration')->init();
		self::get_instance('MMP\Geo_Sitemap')->init();
		self::get_instance('MMP\Setup')->init();
		self::get_instance('MMP\TinyMCE')->init();
		self::get_instance('MMP\Notice')->init();
		self::get_instance('MMP\Compatibility')->init();
		self::get_instance('MMP\Dashboard')->init();
		self::get_instance('MMP\Widget_Shortcode')->init();

		self::init_puc();
	}

	/**
	 * Initializes the update checker
	 *
	 * @since 4.3
	 */
	public function init_puc() {
		require_once self::$dir . 'dist/plugin-update-checker/plugin-update-checker.php';

		$endpoint = 'https://www.mapsmarker.com/updates_pro/?action=get_metadata&slug=' . self::$slug;
		if (self::$settings['betaTesting']) {
			$endpoint .= '-beta';
		}

		\Puc_v4_Factory::buildUpdateChecker(
			$endpoint,
			self::$path,
			self::$slug,
			'24',
			'mapsmarkerpro_update'
		);
	}

	/**
	 * Modifies the allowed mime types for uploads
	 *
	 * @since 4.3
	 * @param array $mimes Current allowed mime types
	 * @return array Modified allowed mime types
	 */
	public function filter_upload_mimes($mimes) {
		$mimes['gpx'] = 'application/gpx+xml';

		return $mimes;
	}

	/**
	 * Modifies the mime type filters for the media library
	 *
	 * @since 4.3
	 * @param array $mimes Current post mime types
	 * @return array Modified post mime types
	 */
	public function filter_post_mime_types($mimes) {
		$mimes['application/gpx+xml'] = array(
			__('GPX tracks', 'mmp'),
			__('Manage GPX tracks', 'mmp'),
			_n_noop('GPX track <span class="count">(%s)</span>', 'GPX tracks <span class="count">(%s)</span>', 'mmp')
		);

		return $mimes;
	}

	/**
	 * Modifies the filetype and extension check
	 *
	 * @since 4.3
	 * @param array $check List containing the current file data
	 * @param string $file Absolute path to the file
	 * @param string $filename Name of the file
	 * @param array $mimes List of extensions and mime types to check against
	 * @param string|false $real_mime Actual mime type or false if it cannot be determined
	 * @return array List containing the modified file data
	 */
	public function filter_check_filetype_and_ext($check, $file, $filename, $mimes, $real_mime = false) {
		global $wp_version;

		if (!version_compare($wp_version, '5.0.1', '>=')) {
			return $check;
		}

		$gpx_mimes = array(
			'application/gpx+xml',
			'text/xml'
		);

		$info = pathinfo($filename);
		$ext = strtolower($info['extension']);
		if ($ext === 'gpx' && ($real_mime === false || in_array($real_mime, $gpx_mimes))) {
			$check['ext'] = 'gpx';
			$check['type'] = 'application/gpx+xml';
		}

		return $check;
	}

	/**
	 * Modifies the action links in the plugins list
	 *
	 * @since 4.3
	 * @param array $links Current plugin action links
	 * @return array Modified plugin action links
	 */
	public function filter_plugin_action_links($links) {
		array_unshift(
			$links,
			'<a href="' . get_admin_url(null, 'admin.php?page=mapsmarkerpro_settings') . '">' . esc_html__('Settings', 'mmp') . '</a>',
			'<a href="' . get_admin_url(null, 'admin.php?page=mapsmarkerpro_license') . '">' . esc_html__('License', 'mmp') . '</a>'
		);

		return $links;
	}
}
