<?php
namespace MMP;

class Update {
	private $page;

	/**
	 * Sets up the class
	 *
	 * @since 4.0
	 */
	public function __construct() {
		$this->page = isset($_GET['page']) ? $_GET['page'] : null;
	}

	/**
	 * Registers the hooks
	 *
	 * @since 4.0
	 */
	public function init() {
		add_filter('puc_check_now-maps-marker-pro', array($this, 'puc_update_check'));

		add_action('init', array($this, 'update'));
		add_action('all_admin_notices', array($this, 'check'));
		add_action('all_admin_notices', array($this, 'changelog'));
		add_action('wp_ajax_mmp_dismiss_changelog', array($this, 'dismiss_changelog'));
	}

	/**
	 * Checks for a valid license before looking for updates
	 *
	 * @since 4.0
	 *
	 * @param bool $check Whether a check for updates would occur
	 */
	public function puc_update_check($check) {
		$spbas = Maps_Marker_Pro::get_instance('MMP\SPBAS');

		if ($check !== false) {
			$check = $spbas->check_for_updates();
		}

		return $check;
	}

	/**
	 * Executes update routines
	 *
	 * @since 4.0
	 */
	public function update() {
		global $wpdb;
		$mmp_settings = Maps_Marker_Pro::get_instance('MMP\Settings');
		$setup  = Maps_Marker_Pro::get_instance('MMP\Setup');

		$version = get_option('mapsmarkerpro_version');
		if (!$version || $version === Maps_Marker_Pro::$version) {
			return;
		}

		if (!version_compare($version, '4.3', '>=')) {
			$wpdb->query(
				"ALTER TABLE `{$wpdb->prefix}mmp_layers`
				CHANGE `url` `url` VARCHAR(2048) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL"
			);
			$wpdb->query(
				"ALTER TABLE `{$wpdb->prefix}mmp_maps`
				ADD `geojson` TEXT NOT NULL AFTER `filters`"
			);
			$wpdb->query(
				"ALTER TABLE `{$wpdb->prefix}mmp_markers`
				ADD `blank` INT(1) NOT NULL AFTER `link`"
			);
		}

		if (!version_compare($version, '4.4', '>=')) {
			$wpdb->query(
				"ALTER TABLE `{$wpdb->prefix}mmp_maps`
				CHANGE `geojson` `geojson` MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL"
			);
		}

		if (!version_compare($version, '4.5', '>=')) {
			$map_ids = $wpdb->get_col(
				"SELECT `id`
				FROM `{$wpdb->prefix}mmp_maps`"
			);
			foreach ($map_ids as $map_id) {
				$map = $wpdb->get_row($wpdb->prepare(
					"SELECT `settings`
					FROM `{$wpdb->prefix}mmp_maps`
					WHERE `id` = %d",
					$map_id
				));
				if ($map === null) {
					continue;
				}
				$map->settings = json_decode($map->settings, true);
				if (isset($map->settings['layersCollapsed']) && is_bool($map->settings['layersCollapsed'])) {
					$map->settings['layersCollapsed'] = ($map->settings['layersCollapsed'] === true) ? 'collapsed' : 'expanded';
				}
				if (isset($map->settings['filtersCollapsed']) && is_bool($map->settings['filtersCollapsed'])) {
					$map->settings['filtersCollapsed'] = ($map->settings['filtersCollapsed'] === true) ? 'collapsed' : 'expanded';
				}
				if (isset($map->settings['minimapMinimized']) && is_bool($map->settings['minimapMinimized'])) {
					$map->settings['minimapMinimized'] = ($map->settings['minimapMinimized'] === true) ? 'collapsed' : 'expanded';
				}
				if (isset($map->settings['gpxChartPolylineWeight'])) {
					$map->settings['gpxChartLineWidth'] = $map->settings['gpxChartPolylineWeight'];
				}
				if (isset($map->settings['gpxChartPolylineColor'])) {
					$map->settings['gpxChartLineColor'] = $map->settings['gpxChartPolylineColor'];
				}
				if (isset($map->settings['gpxChartPolygon'])) {
					$map->settings['gpxChartFill'] = $map->settings['gpxChartPolygon'];
				}
				if (isset($map->settings['gpxChartPolygonFillColor'])) {
					$map->settings['gpxChartFillColor'] = $map->settings['gpxChartPolygonFillColor'];
				}
				$map->settings = $mmp_settings->validate_map_settings($map->settings, false, false);
				$map->settings = json_encode($map->settings, JSON_FORCE_OBJECT);
				$update = $wpdb->update(
					"{$wpdb->prefix}mmp_maps",
					array('settings' => $map->settings),
					array('id' => $map_id),
					array('%s'),
					array('%d')
				);
			}

			$map_defaults = get_option('mapsmarkerpro_map_defaults');
			if ($map_defaults) {
				if (isset($map_defaults['layersCollapsed']) && is_bool($map_defaults['layersCollapsed'])) {
					$map_defaults['layersCollapsed'] = ($map_defaults['layersCollapsed'] === true) ? 'collapsed' : 'expanded';
				}
				if (isset($map_defaults['filtersCollapsed']) && is_bool($map_defaults['filtersCollapsed'])) {
					$map_defaults['filtersCollapsed'] = ($map_defaults['filtersCollapsed'] === true) ? 'collapsed' : 'expanded';
				}
				if (isset($map_defaults['minimapMinimized']) && is_bool($map_defaults['minimapMinimized'])) {
					$map_defaults['minimapMinimized'] = ($map_defaults['minimapMinimized'] === true) ? 'collapsed' : 'expanded';
				}
				if (isset($map_defaults['gpxChartPolylineWeight'])) {
					$map_defaults['gpxChartLineWidth'] = $map_defaults['gpxChartPolylineWeight'];
				}
				if (isset($map_defaults['gpxChartPolylineColor'])) {
					$map_defaults['gpxChartLineColor'] = $map_defaults['gpxChartPolylineColor'];
				}
				if (isset($map_defaults['gpxChartPolygon'])) {
					$map_defaults['gpxChartFill'] = $map_defaults['gpxChartPolygon'];
				}
				if (isset($map_defaults['gpxChartPolygonFillColor'])) {
					$map_defaults['gpxChartFillColor'] = $map_defaults['gpxChartPolygonFillColor'];
				}
				$map_defaults = $mmp_settings->validate_map_settings($map_defaults, false, false);
				update_option('mapsmarkerpro_map_defaults', $map_defaults);
			}

			$settings = get_option('mapsmarkerpro_settings');
			if ($settings) {
				$settings = $mmp_settings->validate_settings($settings, false, false);
				update_option('mapsmarkerpro_settings', $settings);
			}
		}

		$setup->setup();

		update_option('mapsmarkerpro_version', Maps_Marker_Pro::$version);
		update_option('mapsmarkerpro_changelog', $version);
		update_option('mapsmarkerpro_key_local', null);
	}

	/**
	 * Checks whether an update is available
	 *
	 * @since 4.0
	 */
	public function check() {
		global $pagenow;
		$spbas = Maps_Marker_Pro::get_instance('MMP\SPBAS');
		$l10n = Maps_Marker_Pro::get_instance('MMP\L10n');

		if ((strpos($this->page, 'mapsmarkerpro') === false || $this->page === 'mapsmarkerpro_license') && $pagenow !== 'plugins.php') {
			return;
		}

		if ($spbas->check_for_updates()) {
			$update_plugins = get_site_transient('update_plugins');
			if (isset($plugin_updates->response[Maps_Marker_Pro::$file]->new_version)) {
				$new_version = $update_plugins->response[Maps_Marker_Pro::$file]->new_version;
				?>
				<div class="notice notice-warning">
					<p>
						<strong><?= esc_html__('Maps Marker Pro - plugin update available!', 'mmp') ?></strong><br />
						<?= sprintf($l10n->kses__('You are currently using v%1$s and the plugin author highly recommends updating to v%2$s for new features, bugfixes and updated translations (please see <a href="%3$s" target="_blank">this blog post</a> for more details about the latest release).', 'mmp'), Maps_Marker_Pro::$version, $new_version, "https://mapsmarker.com/v{$new_version}p") ?><br />
						<?php if (current_user_can('update_plugins')): ?>
							<?= sprintf($l10n->kses__('Update instruction: please start the update from the <a href="%1$s">updates page</a>.', 'mmp'), get_admin_url(null, 'update-core.php')) ?>
						<?php else: ?>
							<?= sprintf($l10n->kses__('Update instruction: as your user does not have the right to update plugins, please contact your <a href="%1$s">administrator</a>', 'mmp'), 'mailto:' . get_option('admin_email')) ?>
						<?php endif; ?>
					</p>
				</div>
				<?php
			}
		} else if ($spbas->check_for_updates(false, true)) {
			$latest_version = get_transient('mapsmarkerpro_latest');
			if ($latest_version === false) {
				$check_latest = wp_remote_get('https://www.mapsmarker.com/version.json', array(
					'sslverify' => true,
					'timeout' => 5
				));
				if (is_wp_error($check_latest) || $check_latest['response']['code'] != 200) {
					$latest_version = Maps_Marker_Pro::$version;
				} else {
					$latest_version = json_decode($check_latest['body']);
					if ($latest_version->version === null) {
						$latest_version = Maps_Marker_Pro::$version;
					} else {
						$latest_version = $latest_version->version;
					}
				}
				set_transient('mapsmarkerpro_latest', $latest_version, 60 * 60 * 24);
			}
			?>
			<div class="notice notice-warning">
				<p>
					<strong><?= esc_html__('Warning: your access to updates and support for Maps Marker Pro has expired!', 'mmp') ?></strong><br />
					<?php if ($latest_version !== false && version_compare($latest_version, Maps_Marker_Pro::$version, '>')): ?>
						<?= esc_html__('Latest available version:', 'mmp') ?> <a href="https://www.mapsmarker.com/v<?= $latest_version ?>" target="_blank" title="<?= esc_attr__('Show release notes', 'mmp') ?>"><?= $latest_version ?></a> (<a href="https://www.mapsmarker.com/changelog/pro/" target="_blank"><?= esc_html__('show all available changelogs', 'mmp') ?></a>)<br />
					<?php endif; ?>
					<?= sprintf(esc_html__('You can continue using version %1$s without any limitations. However, you will not be able access the support system or get updates including bugfixes, new features and optimizations.', 'mmp'), Maps_Marker_Pro::$version) ?><br />
					<?= sprintf($l10n->kses__('<a href="%1$s">Please renew your access to updates and support to keep your plugin up-to-date and safe</a>.', 'mmp'), get_admin_url(null, 'admin.php?page=mapsmarkerpro_license')) ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Displays the changelog after an update
	 *
	 * @since 4.0
	 */
	public function changelog() {
		$changelog = get_option('mapsmarkerpro_changelog');

		if (!$changelog || strpos($this->page, 'mapsmarkerpro') === false) {
			return;
		}

		?>
		<style>
			#mmp-changelog-wrap {
				margin: 10px 20px 0 2px;
				padding: 5px;
				background-color: #ffffe0;
				border: 1px #e6db55 solid;
				border-radius: 5px;
			}
			#mmp-changelog-wrap h2 {
				margin: 0;
				padding: 0;
				font-weight: bold;
			}
			#mmp-changelog {
				overflow: auto;
				height: 205px;
				margin: 5px 0;
				border: thin dashed #e6db55;
			}
		</style>

		<div id="mmp-changelog-wrap">
			<h2><?= sprintf(esc_html__('Maps Marker Pro has been successfully updated from version %1s to %2s!', 'mmp'), $changelog, Maps_Marker_Pro::$version) ?></h2>
			<div id="mmp-changelog"><p><?= esc_html__('Loading changelog, please wait ...', 'mmp') ?></p></div>
			<button type="button" id="mmp-hide-changelog" class="button button-secondary"><?= esc_html__('Hide changelog', 'mmp') ?></button>
		</div>

		<script>
			jQuery(document).ready(function($) {
				var link = 'https://www.mapsmarker.com/?changelog=pro&from=<?= $changelog ?>&to=<?= Maps_Marker_Pro::$version ?>';

				$('#mmp-changelog').load(link, function(response, status, xhr) {
					if (status == 'error') {
						$('#mmp-changelog').append('<p><?= esc_html__('Changelog could not be loaded, please try again later.', 'mmp') ?></p>');
					}
				});

				$('#mmp-hide-changelog').click(function() {
					$.ajax({
						type: 'POST',
						url: ajaxurl,
						context: this,
						data: {
							action: 'mmp_dismiss_changelog',
							nonce: '<?= wp_create_nonce('mmp-dismiss-changelog') ?>'
						}
					});

					$('#mmp-changelog-wrap').remove();
				});
			});
		</script>
		<?php
	}

	/**
	 * Dismisses the changelog
	 *
	 * @since 4.0
	 */
	public function dismiss_changelog() {
		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-dismiss-changelog') === false) {
			return;
		}

		update_option('mapsmarkerpro_changelog', null);

		wp_die();
	}
}
