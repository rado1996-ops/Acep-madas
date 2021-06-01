<?php
namespace MMP;

class L10n {
	public $ml;

	/**
	 * Registers the hooks
	 *
	 * @since 4.0
	 */
	public function init() {
		add_filter('plugin_locale', array($this,'set_plugin_locale'), 10, 2);

		add_action('init', array($this, 'load_translations'));
		add_action('plugins_loaded', array($this, 'check_ml'));
	}

	/**
	 * Sets the plugin locale
	 *
	 * @since 4.0
	 *
	 * @param string $lang The plugin's current locale
	 */
	public function set_plugin_locale($locale, $domain) {
		if ($domain !== 'mmp') {
			return $locale;
		}

		if (is_admin()) {
			if (Maps_Marker_Pro::$settings['pluginLanguageAdmin'] === 'automatic') {
				return $locale;
			}

			return Maps_Marker_Pro::$settings['pluginLanguageAdmin'];
		} else {
			if (Maps_Marker_Pro::$settings['pluginLanguageFrontend'] === 'automatic') {
				return $locale;
			}

			return Maps_Marker_Pro::$settings['pluginLanguageFrontend'];
		}
	}

	/**
	 * Loads the plugin translations
	 *
	 * @since 4.0
	 */
	public function load_translations() {
		load_plugin_textdomain('mmp', false, basename(Maps_Marker_Pro::$dir) . '/languages');
	}

	/**
	 * Translates a string and strips all HTML tags that don't belong to a link
	 *
	 * @since 4.0
	 *
	 * @param string $string The string to be translated
	 * @param string $domain The translation text domain
	 */
	public function kses__($string, $domain) {
		$allowed = array(
			'a' => array(
				'href' => array(),
				'target' => array(),
				'title' => array()
			)
		);

		return wp_kses(__($string, $domain), $allowed);
	}

	/**
	 * Checks if multilingual support is enabled and either WPML or Polylang is installed
	 *
	 * @since 4.0
	 */
	public function check_ml() {
		if (defined("ICL_SITEPRESS_VERSION") && defined('WPML_ST_VERSION')) {
			$this->ml = 'wpml';
		} else if (defined('POLYLANG_VERSION')) {
			$this->ml = 'pll';
		} else {
			$this->ml = false;
		}
	}

	/**
	 * Registers a string for translation
	 *
	 * @since 4.0
	 *
	 * @param string $name A unique name for the string
	 * @param string $string The string to be translated
	 */
	public function register($name, $string) {
		if ($this->ml === 'wpml' || $this->ml === 'pll') {
			do_action('wpml_register_single_string', 'Maps Marker Pro', $name, $string);
		}
	}

	/**
	 * Translates a string into the current language
	 *
	 * @since 4.0
	 *
	 * @param string $string The string to be translated
	 * @param string $name The unique name that identifies the string
	 */
	public function __($string, $name) {
		if ($this->ml === 'wpml') {
			return apply_filters('wpml_translate_single_string', $string, 'Maps Marker Pro', $name);
		} else if ($this->ml === 'pll' && function_exists('pll__')) {
			return pll__($string);
		} else {
			return $string;
		}
	}

	/**
	 * Adds the current language to a link
	 *
	 * @since 4.0
	 *
	 * @param string $link The link to add the language to
	 */
	public function link($link) {
		if ($this->ml) {
			return add_query_arg('lang', ICL_LANGUAGE_CODE, $link);
		} else {
			return $link;
		}
	}

	/**
	 * Returns the map strings needed for JavaScript localization
	 *
	 * @since 4.0
	 */
	public function map_strings() {
		return array(
			'api' => array(
				'editMap'    => __('Edit map', 'mmp'),
				'editMarker' => __('Edit marker', 'mmp'),
				'dir'        => __('Get directions', 'mmp'),
				'fs'         => __('Open standalone map in fullscreen mode', 'mmp'),
				'geoJson'    => __('Export as GeoJSON', 'mmp'),
				'kml'        => __('Export as KML', 'mmp'),
				'geoRss'     => __('Export as GeoRSS', 'mmp')
			),
			'control' => array(
				'zoomIn'                    => __('Zoom in', 'mmp'),
				'zoomOut'                   => __('Zoom out', 'mmp'),
				'fullscreenFalse'           => __('View fullscreen', 'mmp'),
				'fullscreenTrue'            => __('Exit fullscreen', 'mmp'),
				'reset'                     => __('Reset map view', 'mmp'),
				'locateTitle'               => __('Show me where I am', 'mmp'),
				'locateMetersUnit'          => __('meters', 'mmp'),
				'locateFeetUnit'            => __('feet', 'mmp'),
				'locatePopup'               => sprintf(__('You are within %1$s %2$s from this point', 'mmp'), '{distance}', '{unit}'),
				'locateOutsideMapBoundsMsg' => __('You seem located outside the boundaries of the map', 'mmp'),
				'filtersAll'                => __('all', 'mmp'),
				'filtersNone'               => __('none', 'mmp'),
				'minimapHideText'           => __('Hide minimap', 'mmp'),
				'minimapShowText'           => __('Show minimap', 'mmp')
			),
			'popup' => array(
				'directions' => __('Directions', 'mmp'),
				'info'       => __('If a popup text is set, it will appear here', 'mmp')
			),
			'list' => array(
				'id'        => __('Marker ID', 'mmp'),
				'name'      => __('Marker name', 'mmp'),
				'address'   => __('Address', 'mmp'),
				'distance'  => __('Distance', 'mmp'),
				'icon'      => __('Icon', 'mmp'),
				'created'   => __('Created', 'mmp'),
				'updated'   => __('Updated', 'mmp'),
				'noResults' => __('No results', 'mmp'),
				'oneResult' => __('One result', 'mmp'),
				'results'   => __('results', 'mmp')
			),
			'gpx' => array(
				'metaName'      => __('Track name', 'mmp'),
				'metaStart'     => __('Start', 'mmp'),
				'metaEnd'       => __('End', 'mmp'),
				'metaTotal'     => __('Duration', 'mmp'),
				'metaMoving'    => __('Moving time', 'mmp'),
				'metaDistance'  => __('Distance', 'mmp'),
				'metaPace'      => __('Pace', 'mmp'),
				'metaHeartRate' => __('Heart rate', 'mmp'),
				'metaElevation' => __('Elevation', 'mmp'),
				'metaDownload'  => __('download GPX file', 'mmp')
			)
		);
	}

	/**
	 * Returns the admin strings needed for JavaScript localization
	 *
	 * @since 4.0
	 */
	public function admin_strings() {
		return array(
			'global' => array(
				'ajaxError' => __('Failed to send request', 'mmp')
			),
			'map' => array(
				'chooseGpx'        => __('Select or Upload GPX file', 'mmp'),
				'choose'           => __('Choose', 'mmp'),
				'saved'            => __('Map saved successfully', 'mmp'),
				'changeFilterIcon' => __('Change filter icon', 'mmp'),
				'editShape'        => __('Edit shape', 'mmp'),
				'close'            => __('Close', 'mmp')
			),
			'maps' => array(
				'bulkDuplicate'       => __('Duplicate the selected maps?', 'mmp'),
				'bulkDuplicateAssign' => __('Duplicate the selected maps and assign their respective markers?', 'mmp'),
				'bulkDelete'          => __('Delete the selected maps and unassign their markers?', 'mmp'),
				'bulkDeleteAssign'    => sprintf(__('Delete the selected maps and assign all their markers to the map with ID %1$s', 'mmp'), '{map}'),
				'qrCode'              => __('QR code for fullscreen map', 'mmp')
			),
			'marker' => array(
				'delete' => __('Are you sure you want to delete this marker?', 'mmp'),
				'saved'  => __('Marker saved successfully', 'mmp')
			),
			'markers' => array(
				'delete'        => sprintf(__('Are you sure you want to delete the marker with ID %1$s', 'mmp'), '{marker}'),
				'bulkDuplicate' => __('Duplicate the selected markers?', 'mmp'),
				'bulkDelete'    => __('Delete the selected markers?', 'mmp'),
				'bulkAssign'    => sprintf(__('Assign the selected markers to the map with ID %1$s', 'mmp'), '{map}')
			),
			'settings' => array(
				'search'       => __('Start full-text search', 'mmp'),
				'confirmReset' => __('Are you sure you want to reset the settings to their defaults? This cannot be undone!', 'mmp')
			),
			'tools' => array(
				'batchSettings'      => __('Are you sure you want to apply the chosen settings to the selected maps? This cannot be undone.', 'mmp'),
				'batchLayers'        => __('Are you sure you want to apply the chosen layers to the selected maps? This cannot be undone.', 'mmp'),
				'replaceIcon'        => __('Are you sure you want to replace these icons? This cannot be undone.', 'mmp'),
				'maxFileSize'        => sprintf(__('The maximum upload file size on your server is %1$s', 'mmp'), '{maxFileSize}'),
				'chosenFileSizeGood' => sprintf(__('Your chosen file has %1$s, we are good to go', 'mmp'), '{chosenFileSize}'),
				'chosenFileSizeBad'  => sprintf(__('Your chosen file has %1$s, cannot continue', 'mmp'), '{chosenFileSize}'),
				'close'              => __('Close', 'mmp'),
				'changeIcon'         => __('Change icon', 'mmp')
			),
			'geoJson' => array(
				'polyline'  => __('Polyline', 'mmp'),
				'rectangle' => __('Rectangle', 'mmp'),
				'polygon'   => __('Polygon', 'mmp'),
				'circle'    => __('Circle', 'mmp')
			)
		);
	}

	/**
	 * Returns the Gutenberg strings needed for JavaScript localization
	 *
	 * @since 4.0
	 */
	public function gb_strings() {
		return array(
			'selectMap' => __('Select the map you want to display', 'mmp'),
			'select'    => __('- Select -', 'mmp')
		);
	}
}
