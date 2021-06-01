<?php
namespace MMP;

class API {
	public $base_url;
	public $slug;

	/**
	 * Sets up the class
	 *
	 * @since 4.0
	 */
	public function __construct() {
		if (Maps_Marker_Pro::$settings['permalinkBaseUrl']) {
			$this->base_url = trailingslashit(Maps_Marker_Pro::$settings['permalinkBaseUrl']);
		} else {
			$this->base_url = trailingslashit(get_site_url());
		}
		if (Maps_Marker_Pro::$settings['permalinkSlug']) {
			$this->slug = Maps_Marker_Pro::$settings['permalinkSlug'];
		} else {
			$this->slug = 'maps';
		}
	}

	/**
	 * Registers the hooks
	 *
	 * @since 4.0
	 */
	public function init() {
		add_filter('query_vars', array($this, 'add_query_vars'));

		add_action('init', array($this, 'add_rewrite_rules'));
		add_action('wp', array($this, 'redirect_endpoints'));
	}

	/**
	 * Adds additional query vars
	 *
	 * @since 4.0
	 *
	 * @param array $vars Current query vars
	 */
	public function add_query_vars($vars) {
		$vars[] = 'mapsmarkerpro';
		$vars[] = 'map';
		$vars[] = 'marker';
		$vars[] = 'format';
		$vars[] = 'address';
		$vars[] = 'place_id';

		return $vars;
	}

	/**
	 * Adds additional rewrite rules
	 *
	 * @since 4.0
	 */
	public function add_rewrite_rules() {
		add_rewrite_rule(
			"^{$this->slug}/fullscreen/(.+)/?",
			'index.php?mapsmarkerpro=fullscreen&map=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			"^{$this->slug}/export/(geojson|kml|georss|atom)/(.+)/?",
			'index.php?mapsmarkerpro=export&map=$matches[2]&format=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			"^{$this->slug}/(geo-sitemap)/?",
			'index.php?mapsmarkerpro=$matches[1]',
			'top'
		);
	}

	/**
	 * Redirects the API endpoints
	 *
	 * @since 4.0
	 */
	public function redirect_endpoints($wp) {
		if (!isset($wp->query_vars['mapsmarkerpro'])) {
			return;
		}

		switch ($wp->query_vars['mapsmarkerpro']) {
			case 'fullscreen':
				Maps_Marker_Pro::get_instance('MMP\Fullscreen')->show();
				die;
			case 'export':
				Maps_Marker_Pro::get_instance('MMP\Export')->request();
				die;
			case 'geo-sitemap':
				Maps_Marker_Pro::get_instance('MMP\Geo_Sitemap')->show_sitemap();
				die;
			case 'google-places':
				Maps_Marker_Pro::get_instance('MMP\Google_Places')->request();
				die;
			case 'download_gpx':
				Maps_Marker_Pro::get_instance('MMP\Download')->download_gpx();
				die;
			case 'download_file':
				Maps_Marker_Pro::get_instance('MMP\Download')->download_file();
				die;
			default:
				break;
		}
	}

	/**
	 * Builds the link to the API endpoint
	 *
	 * @since 4.0
	 *
	 * @param string $endpoint The API endpoint
	 */
	public function link($endpoint) {
		$l10n = Maps_Marker_Pro::get_instance('MMP\L10n');

		$endpoint = '/' . ltrim($endpoint, '/\\');

		return $l10n->link($this->base_url . $this->slug . $endpoint);
	}
}
