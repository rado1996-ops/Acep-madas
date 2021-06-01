<?php
namespace MMP;

class Menu_Settings extends Menu {
	/**
	 * Registers the hooks
	 *
	 * @since 4.0
	 */
	public function init() {
		add_action('admin_enqueue_scripts', array($this, 'load_resources'));
		add_action('wp_ajax_mmp_save_settings', array($this, 'save_settings'));
		add_action('wp_ajax_mmp_get_custom_layers', array($this, 'get_custom_layers'));
		add_action('wp_ajax_mmp_save_custom_layer', array($this, 'save_custom_layer'));
		add_action('wp_ajax_mmp_delete_custom_layer', array($this, 'delete_custom_layer'));
	}

	/**
	 * Loads the required resources
	 *
	 * @since 4.0
	 *
	 * @param string $hook The current admin page
	 */
	public function load_resources($hook) {
		global $wp_scripts;

		if (substr($hook, -strlen('mapsmarkerpro_settings')) !== 'mapsmarkerpro_settings') {
			return;
		}

		$this->load_global_resources($hook);

		wp_enqueue_script('mmp-admin');
		wp_add_inline_script('mmp-admin', 'settingsActions();');
	}

	/**
	 * Saves the settings
	 *
	 * @since 4.0
	 */
	public function save_settings() {
		global $wp_roles;
		$mmp_settings = Maps_Marker_Pro::get_instance('MMP\Settings');

		$settings = wp_unslash($_POST['settings']);
		parse_str($settings, $settings);

		if (!isset($settings['nonce']) || wp_verify_nonce($settings['nonce'], 'mmp-settings') === false) {
			wp_send_json(array(
				'success' => false,
				'response' => esc_html__('Security check failed', 'mmp')
			));
		}

		$basemaps = $mmp_settings->get_basemaps(true);
		foreach ($basemaps as $key => $basemap) {
			if (!in_array($key, $settings['enabledBasemaps'])) {
				$settings['disabledBasemaps'][] = $key;
			}
		}

		foreach ($wp_roles->roles as $role => $values) {
			if ($role === 'administrator') {
				continue;
			}

			foreach (Maps_Marker_Pro::$capabilities as $cap) {
				if (isset($settings['role_capabilities'][$role][$cap])) {
					$wp_roles->add_cap($role, $cap);
				} else {
					$wp_roles->remove_cap($role, $cap);
				}
			}
		}

		$settings = $mmp_settings->validate_settings($settings, false, false);
		update_option('mapsmarkerpro_settings', $settings);

		wp_send_json(array(
			'success' => true,
			'response' => esc_html__('Settings saved successfully', 'mmp')
		));
	}

	/**
	 * Returns all custom layers
	 *
	 * @since 4.0
	 */
	public function get_custom_layers() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		$layers = $db->get_all_layers();

		wp_send_json($layers);
	}

	/**
	 * Saves the custom layer
	 *
	 * @since 4.0
	 */
	public function save_custom_layer() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		$settings = wp_unslash($_POST['settings']);
		parse_str($settings, $settings);

		$data = array(
			'wms' => (isset($settings['customLayerWms'])) ? '1' : '0',
			'overlay' => $settings['customLayerType'],
			'name' => $settings['customLayerName'],
			'url' => $settings['customLayerUrl'],
			'options' => array(
				'errorTiles' => (isset($settings['customLayerErrorTiles'])),
				'subdomains' => preg_replace('/[^a-z0-9]/i', '', $settings['customLayerSubdomains']),
				'minNativeZoom' => absint($settings['customLayerMinZoom']),
				'maxNativeZoom' => absint($settings['customLayerMaxZoom']),
				'attribution' => $settings['customLayerAttribution'],
				'tms' => (isset($settings['customLayerTms'])),
				'opacity' => abs(floatval($settings['customLayerOpacity']))
			)
		);
		if (isset($settings['customLayerWms'])) {
			$data['options'] = array_merge($data['options'], array(
				'layers' => $settings['customLayerLayers'],
				'styles' => $settings['customLayerStyles'],
				'format' => $settings['customLayerFormat'],
				'transparent' => (isset($settings['customLayerTransparent'])),
				'version' => $settings['customLayerVersion'],
				'uppercase' => (isset($settings['customLayerUppercase']))
			));
		}
		$data['options'] = json_encode($data['options']);

		if (!$settings['customLayerId']) {
			$db->add_layer((object) $data);
		} else {
			$db->update_layer((object) $data, $settings['customLayerId']);
		}

		wp_die();
	}

	/**
	 * Deletes the custom layer
	 *
	 * @since 4.0
	 */
	public function delete_custom_layer() {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		$db->delete_layer($_POST['id']);

		wp_die();
	}

	/**
	 * Shows the settings page
	 *
	 * @since 4.0
	 */
	protected function show() {
		global $wp_roles;
		$db = Maps_Marker_Pro::get_instance('MMP\DB');
		$l10n = Maps_Marker_Pro::get_instance('MMP\L10n');
		$api = Maps_Marker_Pro::get_instance('MMP\API');
		$mmp_settings = Maps_Marker_Pro::get_instance('MMP\Settings');

		$user_caps = get_option('mapsmarkerpro_user_capabilities');
		if (!is_array($user_caps)) {
			$user_caps = array();
		}

		$settings = $mmp_settings->get_settings();
		$basemaps = $mmp_settings->get_basemaps(true);

		?>
		<div class="wrap mmp-wrap">
			<h1><?= esc_html__('Settings', 'mmp') ?></h1>
			<form id="settings" method="POST">
				<div id="top" class="mmp-settings-header">
					<button id="save" class="button button-primary" disabled="disabled"><?= esc_html__('Save', 'mmp') ?></button>
				</div>
				<div class="mmp-settings-wrap">
					<input type="hidden" name="nonce" value="<?= wp_create_nonce('mmp-settings') ?>" />
					<div class="mmp-settings-nav">
						<div class="mmp-settings-nav-group">
							<span><?= esc_html__('Layers', 'mmp') ?></span>
							<ul>
								<li id="layers_google_link" class="mmp-tablink">Google Maps</li>
								<li id="layers_bing_link" class="mmp-tablink">Bing Maps</li>
								<li id="layers_here_link" class="mmp-tablink">HERE Maps</li>
								<li id="layers_enable_disable_link" class="mmp-tablink"><?= esc_html__('Enable / disable', 'mmp') ?></li>
								<li id="layers_custom_link" class="mmp-tablink"><?= esc_html__('Custom', 'mmp') ?></li>
							</ul>
						</div>
						<div class="mmp-settings-nav-group">
							<span><?= esc_html__('Geocoding', 'mmp') ?></span>
							<ul>
								<li id="geocoding_provider_link" class="mmp-tablink"><?= esc_html__('Provider', 'mmp') ?></li>
								<li id="geocoding_algolia_link" class="mmp-tablink">Algolia Places</li>
								<li id="geocoding_photon_link" class="mmp-tablink">Photon@MapsMarker</li>
								<li id="geocoding_locationiq_link" class="mmp-tablink">LocationIQ</li>
								<li id="geocoding_mapquest_link" class="mmp-tablink">MapQuest</li>
								<li id="geocoding_google_link" class="mmp-tablink">Google</li>
							</ul>
						</div>
						<div class="mmp-settings-nav-group">
							<span><?= esc_html__('Directions', 'mmp') ?></span>
							<ul>
								<li id="directions_provider_link" class="mmp-tablink"><?= esc_html__('Provider', 'mmp') ?></li>
								<li id="directions_google_link" class="mmp-tablink">Google Maps</li>
								<li id="directions_your_link" class="mmp-tablink">yournavigation.org</li>
								<li id="directions_ors_link" class="mmp-tablink">openrouteservice.org</li>
							</ul>
						</div>
						<div class="mmp-settings-nav-group">
							<span><?= esc_html__('Misc', 'mmp') ?></span>
							<ul>
								<li id="misc_general_link" class="mmp-tablink"><?= esc_html__('General', 'mmp') ?></li>
								<li id="misc_icons_link" class="mmp-tablink"><?= esc_html__('Icons', 'mmp') ?></li>
								<li id="misc_capabilities_link" class="mmp-tablink"><?= esc_html__('Capabilities', 'mmp') ?></li>
								<li id="misc_sitemaps_link" class="mmp-tablink"><?= esc_html__('Sitemaps', 'mmp') ?></li>
								<li id="misc_wordpress_link" class="mmp-tablink"><?= esc_html__('WordPress integration', 'mmp') ?></li>
								<li id="misc_backup_restore_reset_link" class="mmp-tablink mmp-warning"><?= esc_html__('Backup, restore & reset', 'mmp') ?></li>
							</ul>
						</div>
					</div>
					<div class="mmp-settings-tabs">
						<div id="layers_google_tab" class="mmp-settings-tab">
							<h2>Google Maps</h2>
							<p>
								<?= sprintf(esc_html__('If you want to use Google Maps, you have to register a personal Google Maps JavaScript API key. For terms of services, pricing, usage limits and more, please visit %1$s.', 'mmp'), '<a href="https://www.mapsmarker.com/google-maps-javascript-api/" target="_blank">https://www.mapsmarker.com/google-maps-javascript-api/</a>') ?>
							</p>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Google Maps JavaScript API key', 'mmp') ?></div>
								<div class="mmp-settings-input"><input type="text" name="googleApiKey" value="<?= $settings['googleApiKey'] ?>" /></div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Default language', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<select name="googleLanguage">
										<option value="browser_setting" <?= $this->selected($settings['googleLanguage'], 'browser_setting') ?>><?= esc_html__('Automatic (use the browser language setting)', 'mmp') ?></option>
										<option value="wordpress_setting" <?= $this->selected($settings['googleLanguage'], 'wordpress_setting') ?>><?= esc_html__('Automatic (use the WordPress language setting)', 'mmp') ?></option>
										<option value="ar" <?= $this->selected($settings['googleLanguage'], 'ar') ?>><?= esc_html__('Arabic', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: ar)</option>
										<option value="be" <?= $this->selected($settings['googleLanguage'], 'be') ?>><?= esc_html__('Belarusian', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: be)</option>
										<option value="bg" <?= $this->selected($settings['googleLanguage'], 'bg') ?>><?= esc_html__('Bulgarian', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: bg)</option>
										<option value="bn" <?= $this->selected($settings['googleLanguage'], 'bn') ?>><?= esc_html__('Bengali', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: bn)</option>
										<option value="ca" <?= $this->selected($settings['googleLanguage'], 'ca') ?>><?= esc_html__('Catalan', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: ca)</option>
										<option value="cs" <?= $this->selected($settings['googleLanguage'], 'cs') ?>><?= esc_html__('Czech', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: cs)</option>
										<option value="da" <?= $this->selected($settings['googleLanguage'], 'da') ?>><?= esc_html__('Danish', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: da)</option>
										<option value="de" <?= $this->selected($settings['googleLanguage'], 'de') ?>><?= esc_html__('German', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: de)</option>
										<option value="el" <?= $this->selected($settings['googleLanguage'], 'el') ?>><?= esc_html__('Greek', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: el)</option>
										<option value="en" <?= $this->selected($settings['googleLanguage'], 'en') ?>><?= esc_html__('English', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: en)</option>
										<option value="en-AU" <?= $this->selected($settings['googleLanguage'], 'en-AU') ?>><?= esc_html__('English (Australian)', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: en-AU)</option>
										<option value="en-GB" <?= $this->selected($settings['googleLanguage'], 'en-GB') ?>><?= esc_html__('English (Great Britain)', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: en-GB)</option>
										<option value="es" <?= $this->selected($settings['googleLanguage'], 'es') ?>><?= esc_html__('Spanish', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: es)</option>
										<option value="eu" <?= $this->selected($settings['googleLanguage'], 'eu') ?>><?= esc_html__('Basque', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: eu)</option>
										<option value="fa" <?= $this->selected($settings['googleLanguage'], 'fa') ?>><?= esc_html__('Farsi', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: fa)</option>
										<option value="fi" <?= $this->selected($settings['googleLanguage'], 'fi') ?>><?= esc_html__('Finnish', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: fi)</option>
										<option value="fil" <?= $this->selected($settings['googleLanguage'], 'fil') ?>><?= esc_html__('Filipino', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: fil)</option>
										<option value="fr" <?= $this->selected($settings['googleLanguage'], 'fr') ?>><?= esc_html__('French', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: fr)</option>
										<option value="gl" <?= $this->selected($settings['googleLanguage'], 'gl') ?>><?= esc_html__('Galician', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: gl)</option>
										<option value="gu" <?= $this->selected($settings['googleLanguage'], 'gu') ?>><?= esc_html__('Gujarati', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: gu)</option>
										<option value="hi" <?= $this->selected($settings['googleLanguage'], 'hi') ?>><?= esc_html__('Hindi', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: hi)</option>
										<option value="hr" <?= $this->selected($settings['googleLanguage'], 'hr') ?>><?= esc_html__('Croatian', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: hr)</option>
										<option value="hu" <?= $this->selected($settings['googleLanguage'], 'hu') ?>><?= esc_html__('Hungarian', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: hu)</option>
										<option value="id" <?= $this->selected($settings['googleLanguage'], 'id') ?>><?= esc_html__('Indonesian', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: id)</option>
										<option value="it" <?= $this->selected($settings['googleLanguage'], 'it') ?>><?= esc_html__('Italian', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: it)</option>
										<option value="iw" <?= $this->selected($settings['googleLanguage'], 'iw') ?>><?= esc_html__('Hebrew', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: iw)</option>
										<option value="ja" <?= $this->selected($settings['googleLanguage'], 'ja') ?>><?= esc_html__('Japanese', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: ja)</option>
										<option value="kk" <?= $this->selected($settings['googleLanguage'], 'kk') ?>><?= esc_html__('Kazakh', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: kk)</option>
										<option value="kn" <?= $this->selected($settings['googleLanguage'], 'kn') ?>><?= esc_html__('Kannada', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: kn)</option>
										<option value="ko" <?= $this->selected($settings['googleLanguage'], 'ko') ?>><?= esc_html__('Korean', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: ko)</option>
										<option value="ky" <?= $this->selected($settings['googleLanguage'], 'ky') ?>><?= esc_html__('Kyrgyz', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: ky)</option>
										<option value="lt" <?= $this->selected($settings['googleLanguage'], 'lt') ?>><?= esc_html__('Lithuanian', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: lt)</option>
										<option value="lv" <?= $this->selected($settings['googleLanguage'], 'lv') ?>><?= esc_html__('Latvian', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: lv)</option>
										<option value="mk" <?= $this->selected($settings['googleLanguage'], 'mk') ?>><?= esc_html__('Macedonian', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: mk)</option>
										<option value="ml" <?= $this->selected($settings['googleLanguage'], 'ml') ?>><?= esc_html__('Malayalam', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: ml)</option>
										<option value="mr" <?= $this->selected($settings['googleLanguage'], 'mr') ?>><?= esc_html__('Marathi', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: mr)</option>
										<option value="my" <?= $this->selected($settings['googleLanguage'], 'my') ?>><?= esc_html__('Burmese', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: my)</option>
										<option value="nl" <?= $this->selected($settings['googleLanguage'], 'nl') ?>><?= esc_html__('Dutch', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: nl)</option>
										<option value="no" <?= $this->selected($settings['googleLanguage'], 'no') ?>><?= esc_html__('Norwegian', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: no)</option>
										<option value="pa" <?= $this->selected($settings['googleLanguage'], 'pa') ?>><?= esc_html__('Punjabi', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: pa)</option>
										<option value="pl" <?= $this->selected($settings['googleLanguage'], 'pl') ?>><?= esc_html__('Polish', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: pl)</option>
										<option value="pt" <?= $this->selected($settings['googleLanguage'], 'pt') ?>><?= esc_html__('Portuguese', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: pt)</option>
										<option value="pt-BR" <?= $this->selected($settings['googleLanguage'], 'pt-BR') ?>><?= esc_html__('Portuguese (Brazil)', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: pt-BR)</option>
										<option value="pt-PT" <?= $this->selected($settings['googleLanguage'], 'pt-PT') ?>><?= esc_html__('Portuguese (Portugal)', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: pt-PT)</option>
										<option value="ro" <?= $this->selected($settings['googleLanguage'], 'ro') ?>><?= esc_html__('Romanian', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: ro)</option>
										<option value="ru" <?= $this->selected($settings['googleLanguage'], 'ru') ?>><?= esc_html__('Russian', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: ru)</option>
										<option value="sk" <?= $this->selected($settings['googleLanguage'], 'sk') ?>><?= esc_html__('Slovak', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: sk)</option>
										<option value="sl" <?= $this->selected($settings['googleLanguage'], 'sl') ?>><?= esc_html__('Slovenian', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: sl)</option>
										<option value="sq" <?= $this->selected($settings['googleLanguage'], 'sq') ?>><?= esc_html__('Albanian', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: sq)</option>
										<option value="sr" <?= $this->selected($settings['googleLanguage'], 'sr') ?>><?= esc_html__('Serbian', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: sr)</option>
										<option value="sv" <?= $this->selected($settings['googleLanguage'], 'sv') ?>><?= esc_html__('Swedish', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: sv)</option>
										<option value="ta" <?= $this->selected($settings['googleLanguage'], 'ta') ?>><?= esc_html__('Tamil', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: ta)</option>
										<option value="te" <?= $this->selected($settings['googleLanguage'], 'te') ?>><?= esc_html__('Telugu', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: te)</option>
										<option value="th" <?= $this->selected($settings['googleLanguage'], 'th') ?>><?= esc_html__('Thai', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: th)</option>
										<option value="tl" <?= $this->selected($settings['googleLanguage'], 'tl') ?>><?= esc_html__('Tagalog', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: tl)</option>
										<option value="tr" <?= $this->selected($settings['googleLanguage'], 'tr') ?>><?= esc_html__('Turkish', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: tr)</option>
										<option value="uk" <?= $this->selected($settings['googleLanguage'], 'uk') ?>><?= esc_html__('Ukrainian', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: uk)</option>
										<option value="uz" <?= $this->selected($settings['googleLanguage'], 'uz') ?>><?= esc_html__('Uzbek', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: uz)</option>
										<option value="vi" <?= $this->selected($settings['googleLanguage'], 'vi') ?>><?= esc_html__('Vietnamese', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: vi)</option>
										<option value="zh-CN" <?= $this->selected($settings['googleLanguage'], 'zh-CN') ?>><?= esc_html__('Chinese (simplified)', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: zh-CN)</option>
										<option value="zh-TW" <?= $this->selected($settings['googleLanguage'], 'zh-TW') ?>><?= esc_html__('Chinese (traditional)', 'mmp') ?> (<?= esc_html__('language code', 'mmp') ?>: zh-TW)</option>
									</select><br />
									<span class="mmp-settings-footnote">
										<?= esc_html__('The language used when displaying textual information such as the names for controls, copyright notices, and labels.', 'mmp') ?>
									</span>
								</div>
							</div>
						</div>
						<div id="layers_bing_tab" class="mmp-settings-tab">
							<h2>Bing Maps</h2>
							<p>
								<?= sprintf(esc_html__('An API key is required if you want to use Bing Maps. For more information on how to get an API key, please visit %1$s.', 'mmp'), '<a href="https://www.mapsmarker.com/bing-maps/" target="_blank">https://www.mapsmarker.com/bing-maps/</a>') ?>
							</p>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Bing Maps API key', 'mmp') ?></div>
								<div class="mmp-settings-input"><input type="text" name="bingApiKey" value="<?= $settings['bingApiKey'] ?>" /></div>
							</div>
							<h3><?= esc_html__('Culture parameter', 'mmp') ?></h3>
							<p>
								<?= sprintf(esc_html__('The culture parameter allows you to select the language of the culture for geographic entities, place names and map labels on Bing map images. For supported cultures, street names are localized to the local culture. For example, if you request a location in France, the street names are localized in French. For other localized data such as country names, the level of localization will vary for each culture. For example, there may not be a localized name for the "United States" for every culture code. See %1$s for more details.', 'mmp'), '<a href="http://msdn.microsoft.com/en-us/library/hh441729.aspx" target="_blank">http://msdn.microsoft.com/en-us/library/hh441729.aspx</a>') ?>
							</p>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Default culture', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<select name="bingCulture">
										<option value="automatic" <?= $this->selected($settings['bingCulture'], 'automatic') ?>><?= esc_html__('Automatic (use the WordPress language setting)', 'mmp') ?></option>
										<option value="af" <?= $this->selected($settings['bingCulture'], 'af') ?>><?= esc_html__('Afrikaans', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: af)</option>
										<option value="am" <?= $this->selected($settings['bingCulture'], 'am') ?>><?= esc_html__('Amharic', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: am)</option>
										<option value="ar-sa" <?= $this->selected($settings['bingCulture'], 'ar-sa') ?>><?= esc_html__('Arabic (Saudi Arabia)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: ar-sa)</option>
										<option value="as" <?= $this->selected($settings['bingCulture'], 'as') ?>><?= esc_html__('Assamese', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: as)</option>
										<option value="az-Latn" <?= $this->selected($settings['bingCulture'], 'az-Latn') ?>><?= esc_html__('Azerbaijani (Latin)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: az-Latn)</option>
										<option value="be" <?= $this->selected($settings['bingCulture'], 'be') ?>><?= esc_html__('Belarusian', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: be)</option>
										<option value="bg" <?= $this->selected($settings['bingCulture'], 'bg') ?>><?= esc_html__('Bulgarian', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: bg)</option>
										<option value="bn-BD" <?= $this->selected($settings['bingCulture'], 'bn-BD') ?>><?= esc_html__('Bangla (Bangladesh)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: bn-BD)</option>
										<option value="bn-IN" <?= $this->selected($settings['bingCulture'], 'bn-IN') ?>><?= esc_html__('Bangla (India)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: bn-IN)</option>
										<option value="bs" <?= $this->selected($settings['bingCulture'], 'bs') ?>><?= esc_html__('Bosnian (Latin)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: bs)</option>
										<option value="ca" <?= $this->selected($settings['bingCulture'], 'ca') ?>><?= esc_html__('Catalan Spanish', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: ca)</option>
										<option value="ca-ES-valencia" <?= $this->selected($settings['bingCulture'], 'ca-ES-valencia') ?>><?= esc_html__('Valencian', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: ca-ES-valencia)</option>
										<option value="cs" <?= $this->selected($settings['bingCulture'], 'cs') ?>><?= esc_html__('Czech', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: cs)</option>
										<option value="cy" <?= $this->selected($settings['bingCulture'], 'cy') ?>><?= esc_html__('Welsh', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: cy)</option>
										<option value="da" <?= $this->selected($settings['bingCulture'], 'da') ?>><?= esc_html__('Danish', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: da)</option>
										<option value="de" <?= $this->selected($settings['bingCulture'], 'de') ?>><?= esc_html__('German (Germany)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: de)</option>
										<option value="de-de" <?= $this->selected($settings['bingCulture'], 'de-de') ?>><?= esc_html__('German (Germany)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: de-de)</option>
										<option value="el" <?= $this->selected($settings['bingCulture'], 'el') ?>><?= esc_html__('Greek', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: el)</option>
										<option value="en-GB" <?= $this->selected($settings['bingCulture'], 'en-GB') ?>><?= esc_html__('English (United Kingdom)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: en-GB)</option>
										<option value="en-US" <?= $this->selected($settings['bingCulture'], 'en-US') ?>><?= esc_html__('English (United States)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: en-US)</option>
										<option value="es" <?= $this->selected($settings['bingCulture'], 'es') ?>><?= esc_html__('Spanish (Spain)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: es)</option>
										<option value="es-ES" <?= $this->selected($settings['bingCulture'], 'es-ES') ?>><?= esc_html__('Spanish (Spain)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: es-ES)</option>
										<option value="es-US" <?= $this->selected($settings['bingCulture'], 'es-US') ?>><?= esc_html__('Spanish (United States)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: es-US)</option>
										<option value="es-MX" <?= $this->selected($settings['bingCulture'], 'es-MX') ?>><?= esc_html__('Spanish (Mexico)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: es-MX)</option>
										<option value="et" <?= $this->selected($settings['bingCulture'], 'et') ?>><?= esc_html__('Estonian', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: et)</option>
										<option value="eu" <?= $this->selected($settings['bingCulture'], 'eu') ?>><?= esc_html__('Basque', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: eu)</option>
										<option value="fa" <?= $this->selected($settings['bingCulture'], 'fa') ?>><?= esc_html__('Persian', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: fa)</option>
										<option value="fi" <?= $this->selected($settings['bingCulture'], 'fi') ?>><?= esc_html__('Finnish', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: fi)</option>
										<option value="fil-Latn" <?= $this->selected($settings['bingCulture'], 'fil-Latn') ?>><?= esc_html__('Filipino', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: fil-Latn)</option>
										<option value="fr" <?= $this->selected($settings['bingCulture'], 'fr') ?>><?= esc_html__('French (France)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: fr)</option>
										<option value="fr-FR" <?= $this->selected($settings['bingCulture'], 'fr-FR') ?>><?= esc_html__('French (France)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: fr-FR)</option>
										<option value="fr-CA" <?= $this->selected($settings['bingCulture'], 'fr-CA') ?>><?= esc_html__('French (Canada)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: fr-CA)</option>
										<option value="ga" <?= $this->selected($settings['bingCulture'], 'ga') ?>><?= esc_html__('Irish', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: ga)</option>
										<option value="gd-Latn" <?= $this->selected($settings['bingCulture'], 'gd-Latn') ?>><?= esc_html__('Scottish Gaelic', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: gd-Latn)</option>
										<option value="gl" <?= $this->selected($settings['bingCulture'], 'gl') ?>><?= esc_html__('Galician', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: gl)</option>
										<option value="gu" <?= $this->selected($settings['bingCulture'], 'gu') ?>><?= esc_html__('Gujarati', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: gu)</option>
										<option value="ha-Latn" <?= $this->selected($settings['bingCulture'], 'ha-Latn') ?>><?= esc_html__('Hausa (Latin)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: ha-Latn)</option>
										<option value="he" <?= $this->selected($settings['bingCulture'], 'he') ?>><?= esc_html__('Hebrew', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: he)</option>
										<option value="hi" <?= $this->selected($settings['bingCulture'], 'hi') ?>><?= esc_html__('Hindi', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: hi)</option>
										<option value="hr" <?= $this->selected($settings['bingCulture'], 'hr') ?>><?= esc_html__('Croatian', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: hr)</option>
										<option value="hu" <?= $this->selected($settings['bingCulture'], 'hu') ?>><?= esc_html__('Hungarian', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: hu)</option>
										<option value="hy" <?= $this->selected($settings['bingCulture'], 'hy') ?>><?= esc_html__('Armenian', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: hy)</option>
										<option value="id" <?= $this->selected($settings['bingCulture'], 'id') ?>><?= esc_html__('Indonesian', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: id)</option>
										<option value="ig-Latn" <?= $this->selected($settings['bingCulture'], 'ig-Latn') ?>><?= esc_html__('Igbo', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: ig-Latn)</option>
										<option value="is" <?= $this->selected($settings['bingCulture'], 'is') ?>><?= esc_html__('Icelandic', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: )</option>
										<option value="it" <?= $this->selected($settings['bingCulture'], 'it') ?>><?= esc_html__('Italian (Italy)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: it)</option>
										<option value="it-it" <?= $this->selected($settings['bingCulture'], 'it-it') ?>><?= esc_html__('Italian (Italy)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: it-it)</option>
										<option value="ja" <?= $this->selected($settings['bingCulture'], 'ja') ?>><?= esc_html__('Japanese', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: ja)</option>
										<option value="ka" <?= $this->selected($settings['bingCulture'], 'ka') ?>><?= esc_html__('Georgian', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: ka)</option>
										<option value="kk" <?= $this->selected($settings['bingCulture'], 'kk') ?>><?= esc_html__('Kazakh', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: kk)</option>
										<option value="km" <?= $this->selected($settings['bingCulture'], 'km') ?>><?= esc_html__('Khmer', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: km)</option>
										<option value="kn" <?= $this->selected($settings['bingCulture'], 'kn') ?>><?= esc_html__('Kannada', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: kn)</option>
										<option value="ko" <?= $this->selected($settings['bingCulture'], 'ko') ?>><?= esc_html__('Korean', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: ko)</option>
										<option value="kok" <?= $this->selected($settings['bingCulture'], 'kok') ?>><?= esc_html__('Konkani', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: kok)</option>
										<option value="ku-Arab" <?= $this->selected($settings['bingCulture'], 'ku-Arab') ?>><?= esc_html__('Central Curdish', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: ku-Arab)</option>
										<option value="ky-Cyrl" <?= $this->selected($settings['bingCulture'], 'ky-Cyrl') ?>><?= esc_html__('Kyrgyz', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: ky-Cyrl)</option>
										<option value="lb" <?= $this->selected($settings['bingCulture'], 'lb') ?>><?= esc_html__('Luxembourgish', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: lb)</option>
										<option value="lt" <?= $this->selected($settings['bingCulture'], 'lt') ?>><?= esc_html__('Lithuanian', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: lt)</option>
										<option value="lv" <?= $this->selected($settings['bingCulture'], 'lv') ?>><?= esc_html__('Latvian', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: lv)</option>
										<option value="mi-Latn" <?= $this->selected($settings['bingCulture'], 'mi-Latn') ?>><?= esc_html__('Maori', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: mi-Latn)</option>
										<option value="mk" <?= $this->selected($settings['bingCulture'], 'mk') ?>><?= esc_html__('Macedonian', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: mk)</option>
										<option value="ml" <?= $this->selected($settings['bingCulture'], 'ml') ?>><?= esc_html__('Malayalam', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: ml)</option>
										<option value="mn-Cyrl" <?= $this->selected($settings['bingCulture'], 'mn-Cyrl') ?>><?= esc_html__('Mongolian (Cyrillic)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: mn-Cyrl)</option>
										<option value="mr" <?= $this->selected($settings['bingCulture'], 'mr') ?>><?= esc_html__('Marathi', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: mr)</option>
										<option value="ms" <?= $this->selected($settings['bingCulture'], 'ms') ?>><?= esc_html__('Malay (Malaysia)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: ms)</option>
										<option value="mt" <?= $this->selected($settings['bingCulture'], 'mt') ?>><?= esc_html__('Maltese', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: mt)</option>
										<option value="nb" <?= $this->selected($settings['bingCulture'], 'nb') ?>><?= esc_html__('Norwegian (Bokmål)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: nb)</option>
										<option value="ne" <?= $this->selected($settings['bingCulture'], 'ne') ?>><?= esc_html__('Nepali (Nepal)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: ne)</option>
										<option value="nl" <?= $this->selected($settings['bingCulture'], 'nl') ?>><?= esc_html__('Dutch (Netherlands)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: nl)</option>
										<option value="nl-BE" <?= $this->selected($settings['bingCulture'], 'nl-BE') ?>><?= esc_html__('Dutch (Netherlands)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: nl-BE)</option>
										<option value="nn" <?= $this->selected($settings['bingCulture'], 'nn') ?>><?= esc_html__('Norwegian (Nynorsk)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: nn)</option>
										<option value="nso" <?= $this->selected($settings['bingCulture'], 'nso') ?>><?= esc_html__('Sesotho sa Leboa', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: nso)</option>
										<option value="or" <?= $this->selected($settings['bingCulture'], 'or') ?>><?= esc_html__('Odia', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: or)</option>
										<option value="pa" <?= $this->selected($settings['bingCulture'], 'pa') ?>><?= esc_html__('Punjabi (Gurmukhi)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: pa)</option>
										<option value="pa-Arab" <?= $this->selected($settings['bingCulture'], 'pa-Arab') ?>><?= esc_html__('Punjabi (Arabic)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: pa-Arab)</option>
										<option value="pl" <?= $this->selected($settings['bingCulture'], 'pl') ?>><?= esc_html__('Polish', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: pl)</option>
										<option value="prs-Arab" <?= $this->selected($settings['bingCulture'], 'prs-Arab') ?>><?= esc_html__('Dari', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: prs-Arab)</option>
										<option value="pt-BR" <?= $this->selected($settings['bingCulture'], 'pt-BR') ?>><?= esc_html__('Portuguese (Brazil)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: pt-BR)</option>
										<option value="pt-PT" <?= $this->selected($settings['bingCulture'], 'pt-PT') ?>><?= esc_html__('Portuguese (Portugal)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: pt-PT)</option>
										<option value="qut-Latn" <?= $this->selected($settings['bingCulture'], 'qut-Latn') ?>><?= esc_html__("K'iche'", 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: qut-Latn)</option>
										<option value="quz" <?= $this->selected($settings['bingCulture'], 'quz') ?>><?= esc_html__('Quechua (Peru)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: quz)</option>
										<option value="ro" <?= $this->selected($settings['bingCulture'], 'ro') ?>><?= esc_html__('Romanian (Romania)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: ro)</option>
										<option value="ru" <?= $this->selected($settings['bingCulture'], 'ru') ?>><?= esc_html__('Russian', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: ru)</option>
										<option value="rw" <?= $this->selected($settings['bingCulture'], 'rw') ?>><?= esc_html__('Kinyarwanda', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: rw)</option>
										<option value="sd-Arab" <?= $this->selected($settings['bingCulture'], 'sd-Arab') ?>><?= esc_html__('Sindhi (Arabic)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: sd-Arab)</option>
										<option value="si" <?= $this->selected($settings['bingCulture'], 'si') ?>><?= esc_html__('Sinhala', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: si)</option>
										<option value="sk" <?= $this->selected($settings['bingCulture'], 'sk') ?>><?= esc_html__('Slovak', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: sk)</option>
										<option value="sl" <?= $this->selected($settings['bingCulture'], 'sl') ?>><?= esc_html__('Slovenian', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: sl)</option>
										<option value="sq" <?= $this->selected($settings['bingCulture'], 'sq') ?>><?= esc_html__('Albanian', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: sq)</option>
										<option value="sr-Cyrl-BA" <?= $this->selected($settings['bingCulture'], 'sr-Cyrl-BA') ?>><?= esc_html__('Serbian (Cyrillic, Bosnia and Herzegovina)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: sr-Cyrl-BA)</option>
										<option value="sr-Cyrl-RS" <?= $this->selected($settings['bingCulture'], 'sr-Cyrl-RS') ?>><?= esc_html__('Serbian (Cyrillic, Serbia)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: sr-Cyrl-RS)</option>
										<option value="sr-Latn-RS" <?= $this->selected($settings['bingCulture'], 'sr-Latn-RS') ?>><?= esc_html__('Serbian (Latin, Serbia)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: sr-Latn-RS)</option>
										<option value="sv" <?= $this->selected($settings['bingCulture'], 'sv') ?>><?= esc_html__('Swedish (Sweden)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: sv)</option>
										<option value="sw" <?= $this->selected($settings['bingCulture'], 'sw') ?>><?= esc_html__('Kiswahili', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: sw)</option>
										<option value="ta" <?= $this->selected($settings['bingCulture'], 'ta') ?>><?= esc_html__('Tamil', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: ta)</option>
										<option value="te" <?= $this->selected($settings['bingCulture'], 'te') ?>><?= esc_html__('Telugu', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: te)</option>
										<option value="tg-Cyrl" <?= $this->selected($settings['bingCulture'], 'tg-Cyrl') ?>><?= esc_html__('Tajik (Cyrillic)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: tg-Cyrl)</option>
										<option value="th" <?= $this->selected($settings['bingCulture'], 'th') ?>><?= esc_html__('Thai', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: th)</option>
										<option value="ti" <?= $this->selected($settings['bingCulture'], 'ti') ?>><?= esc_html__('Tigrinya', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: ti)</option>
										<option value="tk-Latn" <?= $this->selected($settings['bingCulture'], 'tk-Latn') ?>><?= esc_html__('Turkmen (Latin)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: tk-Latn)</option>
										<option value="tn" <?= $this->selected($settings['bingCulture'], 'tn') ?>><?= esc_html__('Setswana', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: tn)</option>
										<option value="tr" <?= $this->selected($settings['bingCulture'], 'tr') ?>><?= esc_html__('Turkish', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: tr)</option>
										<option value="tt-Cyrl" <?= $this->selected($settings['bingCulture'], 'tt-Cyrl') ?>><?= esc_html__('Tatar (Cyrillic)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: tt-Cyrl)</option>
										<option value="ug-Arab" <?= $this->selected($settings['bingCulture'], 'ug-Arab') ?>><?= esc_html__('Uyghur', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: ug-Arab)</option>
										<option value="uk" <?= $this->selected($settings['bingCulture'], 'uk') ?>><?= esc_html__('Ukrainian', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: uk)</option>
										<option value="ur" <?= $this->selected($settings['bingCulture'], 'ur') ?>><?= esc_html__('Urdu', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: ur)</option>
										<option value="uz-Latn" <?= $this->selected($settings['bingCulture'], 'uz-Latn') ?>><?= esc_html__('Uzbek (Latin)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: uz-Latn)</option>
										<option value="vi" <?= $this->selected($settings['bingCulture'], 'vi') ?>><?= esc_html__('Vietnamese', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: vi)</option>
										<option value="wo" <?= $this->selected($settings['bingCulture'], 'wo') ?>><?= esc_html__('Wolof', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: wo)</option>
										<option value="xh" <?= $this->selected($settings['bingCulture'], 'xh') ?>><?= esc_html__('isiXhosa', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: xh)</option>
										<option value="yo-Latn" <?= $this->selected($settings['bingCulture'], 'yo-Latn') ?>><?= esc_html__('Yoruba', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: yo-Latn)</option>
										<option value="zh-Hans" <?= $this->selected($settings['bingCulture'], 'zh-Hans') ?>><?= esc_html__('Chinese (Simplified)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: zh-Hans)</option>
										<option value="zh-Hant" <?= $this->selected($settings['bingCulture'], 'zh-Hant') ?>><?= esc_html__('Chinese (Traditional)', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: zh-Hant)</option>
										<option value="zu" <?= $this->selected($settings['bingCulture'], 'zu') ?>><?= esc_html__('isiZulu', 'mmp') ?> (<?= esc_html__('culture code', 'mmp') ?>: zu)</option>
									</select>
								</div>
							</div>
						</div>
						<div id="layers_here_tab" class="mmp-settings-tab">
							<h2>HERE Maps</h2>
							<p>
								<?= sprintf(esc_html__('If you want to use HERE Maps, you have to register a personal HERE account. For a tutorial, terms of services, pricing, usage limits and more, please visit %1$s.', 'mmp'), '<a href="https://www.mapsmarker.com/here-maps/" target="_blank">https://www.mapsmarker.com/here-maps/</a>') ?>
							</P>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('App ID', 'mmp') ?></div>
								<div class="mmp-settings-input"><input type="text" name="hereAppId" value="<?= $settings['hereAppId'] ?>" /></div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('App Code', 'mmp') ?></div>
								<div class="mmp-settings-input"><input type="text" name="hereAppCode" value="<?= $settings['hereAppCode'] ?>" /></div>
							</div>
						</div>
						<div id="layers_enable_disable_tab" class="mmp-settings-tab">
							<h2><?= esc_html__('Enable / disable', 'mmp') ?></h2>
							<p>
								<?= esc_html__('You can enable or disable the built-in layers. When a layer is disabled, it will not be available when creating, editing or viewing maps. Please note that layers that require registration will not be available until credentials (API key etc.) have been added, even if they are enabled here.', 'mmp') ?>
							</p>
							<h3><?= esc_html__('Basemaps', 'mmp') ?></h3>
							<?php foreach ($basemaps as $key => $basemap): ?>
								<div class="mmp-settings-setting">
									<div class="mmp-settings-desc"><?= $basemap['name'] ?></div>
									<div class="mmp-settings-input">
										<label><input type="checkbox" name="enabledBasemaps[]" value="<?= $key ?>" <?= (in_array($key, $settings['disabledBasemaps'])) ?: 'checked="checked"' ?> /> <?= esc_html__('enabled', 'mmp') ?></label>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
						<div id="layers_custom_tab" class="mmp-settings-tab">
							<h2><?= esc_html__('Custom layers', 'mmp') ?></h2>
							<p>
								<?= sprintf(esc_html__('For a community-curated list of custom layers and WMS services, please visit %1$s.', 'mmp'), '<a href="https://www.mapsmarker.com/custom-layers/" target="_blank">https://www.mapsmarker.com/custom-layers/</a>') ?>
							</p>
							<h3><?= esc_html__('Basemaps', 'mmp') ?></h3>
							<div id="custom-basemaps"></div>
							<h3><?= esc_html__('Overlays', 'mmp') ?></h3>
							<div id="custom-overlays"></div>
							<button type="button" id="mmp-custom-layer-add" class="button button-secondary"><?= esc_html__('Add new layer', 'mmp') ?></button>
						</div>
						<div id="geocoding_provider_tab" class="mmp-settings-tab">
							<h2><?= esc_html__('Geocoding provider', 'mmp') ?></h2>
							<p>
								<?= esc_html__("Geocoding is the process of transforming a description of a location - like an address, name or place - to a location on the earth's surface.", 'mmp') ?><br />
								<?= esc_html__('You can choose from different geocoding providers, which enables you to get the best results according to your needs.', 'mmp') ?><br />
								<?= sprintf(esc_html__('For a comparison of supported geocoding providers, please visit %1$s.', 'mmp'), '<a href="https://www.mapsmarker.com/geocoding/" target="_blank">https://www.mapsmarker.com/geocoding/</a>') ?>
							</p>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Main geocoding provider', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="geocodingProvider" value="algolia" <?= $this->checked($settings['geocodingProvider'], 'algolia') ?> /> Algolia Places</label></li>
										<li><label><input type="radio" name="geocodingProvider" value="photon" <?= $this->checked($settings['geocodingProvider'], 'photon') ?> /> Photon@MapsMarker</label></li>
										<li><label><input type="radio" name="geocodingProvider" value="locationiq" <?= $this->checked($settings['geocodingProvider'], 'locationiq') ?> /> LocationIQ (<a href="https://www.mapsmarker.com/locationiq-geocoding/" target="_blank"><?= esc_html__('API key required', 'mmp') ?></a>)</label></li>
										<li><label><input type="radio" name="geocodingProvider" value="mapquest" <?= $this->checked($settings['geocodingProvider'], 'mapquest') ?> /> MapQuest (<a href="https://www.mapsmarker.com/mapquest-geocoding/" target="_blank"><?= esc_html__('API key required', 'mmp') ?></a>)</label></li>
										<li><label><input type="radio" name="geocodingProvider" value="google" <?= $this->checked($settings['geocodingProvider'], 'google') ?> /> Google (<a href="https://www.mapsmarker.com/google-geocoding/" target="_blank"><?= esc_html__('API key required', 'mmp') ?>)</a></label></li>
									</ul>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Fallback geocoding provider', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="geocodingProviderFallback" value="algolia" <?= $this->checked($settings['geocodingProviderFallback'], 'algolia') ?> /> Algolia Places</label></li>
										<li><label><input type="radio" name="geocodingProviderFallback" value="photon" <?= $this->checked($settings['geocodingProviderFallback'], 'photon') ?> /> Photon@MapsMarker</label></li>
										<li><label><input type="radio" name="geocodingProviderFallback" value="locationiq" <?= $this->checked($settings['geocodingProviderFallback'], 'locationiq') ?> /> LocationIQ (<a href="https://www.mapsmarker.com/locationiq-geocoding/" target="_blank"><?= esc_html__('API key required', 'mmp') ?></a>)</label></li>
										<li><label><input type="radio" name="geocodingProviderFallback" value="mapquest" <?= $this->checked($settings['geocodingProviderFallback'], 'mapquest') ?> /> MapQuest (<a href="https://www.mapsmarker.com/mapquest-geocoding/" target="_blank"><?= esc_html__('API key required', 'mmp') ?></a>)</label></li>
										<li><label><input type="radio" name="geocodingProviderFallback" value="google" <?= $this->checked($settings['geocodingProviderFallback'], 'google') ?> /> Google (<a href="https://www.mapsmarker.com/google-geocoding/" target="_blank"><?= esc_html__('API key required', 'mmp') ?>)</a></label></li>
									</ul>
									<span class="mmp-settings-footnote">
										<?= esc_html__('The fallback geocoding provider is used automatically if the main geocoding provider is unavailable.', 'mmp') ?>
									</span>
								</div>
							</div>
							<h3><?= esc_html__('Rate limit savings and performance', 'mmp') ?></h3>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Typing interval delay', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="number" name="geocodingTypingDelay" placeholder="400" value="<?= $settings['geocodingTypingDelay'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= esc_html__('Delay in milliseconds between character inputs before a request to the geocoding provider is sent.', 'mmp') ?>
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Typeahead suggestions character limit', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="number" name="geocodingMinChars" placeholder="3" value="<?= $settings['geocodingMinChars'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= esc_html__('Minimum amount of characters that need to be typed before a request to the geocoding provider is sent.', 'mmp') ?>
									</span>
								</div>
							</div>
						</div>
						<div id="geocoding_algolia_tab" class="mmp-settings-tab">
							<h2><?= esc_html__('Algolia Places', 'mmp') ?></h2>
							<p>
								<a href="https://www.mapsmarker.com/algolia-places/" target="_blank"><img src="<?= plugins_url('images/geocoding/algolia-places.png', __DIR__) ?>" /></a><br />
								<?= sprintf(esc_html__('%1$s allows up to %2$s requests/domain/day and a maximum of %3$s requests/second without registration - just select "%4$s" as preferred geocoding provider in the according tab on the left to start using the service.', 'mmp'), '<a href="https://www.mapsmarker.com/algolia-places/" target="_blank">Algolia Places</a>', '1.000', '15', 'Algolia Places') ?>
							</p>
							<h3><?= esc_html__('Authentication', 'mmp') ?></h3>
							<p>
								<?= sprintf(esc_html__('With free authentication, up to %1$s request/domain/month are allowed (%2$s). Paid plans with even higher limits are available upon request (%3$s).', 'mmp'), '100.000', '<a href="https://www.algolia.com/users/sign_up/places" target="_blank">' . esc_html__('link') . '</a>', '<a href="https://community.algolia.com/places/contact.html" target="_blank">' . esc_html__('link') . '</a>') ?>
							</p>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc">appId</div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingAlgoliaAppId" value="<?= $settings['geocodingAlgoliaAppId'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= esc_html__('If using the authenticated API, the Application ID to use.', 'mmp') ?>
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc">apiKey</div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingAlgoliaApiKey" value="<?= $settings['geocodingAlgoliaApiKey'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= esc_html__('If using the authenticated API, the API key to use.', 'mmp') ?>
									</span>
								</div>
							</div>
							<h3><?= esc_html__('Location biasing', 'mmp') ?></h3>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc">aroundLatLng</div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingAlgoliaAroundLatLng" value="<?= $settings['geocodingAlgoliaAroundLatLng'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= esc_html__('Force to first search around a specific latitude longitude. The option value must be provided as a string: latitude,longitude (e.g. 12.232,23.1). The default is to search around the location of the user determined via his IP address (geoip).', 'mmp') ?>
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc">aroundLatLngViaIP</div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="geocodingAlgoliaAroundLatLngViaIp" value="false" <?= $this->checked($settings['geocodingAlgoliaAroundLatLngViaIp'], false) ?> /> <?= esc_html__('false', 'mmp') ?></label></li>
										<li><label><input type="radio" name="geocodingAlgoliaAroundLatLngViaIp" value="true" <?= $this->checked($settings['geocodingAlgoliaAroundLatLngViaIp'], true) ?> /> <?= esc_html__('true', 'mmp') ?></label></li>
									</ul>
									<span class="mmp-settings-footnote">
										<?= esc_html__('Whether or not to first search around the geolocation of the user found via his IP address.', 'mmp') ?>
									</span>
								</div>
							</div>
							<h3><?= esc_html__('Advanced', 'mmp') ?></h3>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Language', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingAlgoliaLanguage" value="<?= $settings['geocodingAlgoliaLanguage'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= sprintf(esc_html__('Changes the language of the results. You can pass two letters country codes (%1$s).', 'mmp'), '<a href="https://en.wikipedia.org/wiki/ISO_3166-1#Officially_assigned_code_elements" target="_blank">ISO 639-1</a>') ?><br />
										<?= esc_html__('If empty, the language set in WordPress will be used.', 'mmp') ?>
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Countries', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingAlgoliaCountries" value="<?= $settings['geocodingAlgoliaCountries'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= sprintf(esc_html__('Changes the countries to search in. You can pass two letters country codes (%1$s).', 'mmp'), '<a href="https://en.wikipedia.org/wiki/ISO_3166-1#Officially_assigned_code_elements" target="_blank">ISO 639-1</a>') ?><br />
										<?= esc_html__('If empty, the entire planet will be searched.', 'mmp') ?>
									</span>
								</div>
							</div>
						</div>
						<div id="geocoding_photon_tab" class="mmp-settings-tab">
							<h2><?= esc_html__('Photon@MapsMarker', 'mmp') ?></h2>
							<p>
								<a href="https://www.mapsmarker.com/photon/" target="_blank"><img src="<?= plugins_url('images/geocoding/photon-mapsmarker.png', __DIR__) ?>" /></a><br />
								<?= sprintf(esc_html__('%1$s allows up to %2$s requests/domain/day and a maximum of %3$s requests/second without registration - just select "%4$s" as preferred geocoding provider in the according tab on the left to start using the service.', 'mmp'), '<a href="https://www.mapsmarker.com/photon/" target="_blank">Photon@MapsMarker</a>', '10.000', '20', 'Photon@MapsMarker') ?>
							</p>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Language', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="geocodingPhotonLanguage" value="automatic" <?= $this->checked($settings['geocodingPhotonLanguage'], 'automatic') ?> /> <?= esc_html__('Automatic', 'mmp') ?></label></li>
										<li><label><input type="radio" name="geocodingPhotonLanguage" value="en" <?= $this->checked($settings['geocodingPhotonLanguage'], 'en') ?> /> <?= esc_html__('English', 'mmp') ?></label></li>
										<li><label><input type="radio" name="geocodingPhotonLanguage" value="de" <?= $this->checked($settings['geocodingPhotonLanguage'], 'de') ?> /> <?= esc_html__('German', 'mmp') ?></label></li>
										<li><label><input type="radio" name="geocodingPhotonLanguage" value="fr" <?= $this->checked($settings['geocodingPhotonLanguage'], 'fr') ?> /> <?= esc_html__('French', 'mmp') ?></label></li>
										<li><label><input type="radio" name="geocodingPhotonLanguage" value="it" <?= $this->checked($settings['geocodingPhotonLanguage'], 'it') ?> /> <?= esc_html__('Italian', 'mmp') ?></label></li>
									</ul>
									<span class="mmp-settings-footnote">
										<?= esc_html__('If set to automatic, the language set in WordPress will be used.', 'mmp') ?>
									</span>
								</div>
							</div>
							<h3><?= esc_html__('Location biasing', 'mmp') ?></h3>
							<p>
								<?= esc_html__('To focus your search on a geographical area, please provide latitude and longitude values.', 'mmp') ?>
							</p>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Latitude', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingPhotonBiasLat" value="<?= $settings['geocodingPhotonBiasLat'] ?>" />
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Longitude', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingPhotonBiasLon" value="<?= $settings['geocodingPhotonBiasLon'] ?>" />
								</div>
							</div>
							<h3><?= esc_html__('Filter results by tags and values', 'mmp') ?></h3>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Filter to use', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingPhotonFilter" value="<?= $settings['geocodingPhotonFilter'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= sprintf(esc_html__('For a detailed documentation, please visit %1$s.', 'mmp'), '<a href="https://github.com/komoot/photon#filter-results-by-tags-and-values" target="_blank">https://github.com/komoot/photon#filter-results-by-tags-and-values</a>') ?>
									</span>
								</div>
							</div>
						</div>
						<div id="geocoding_locationiq_tab" class="mmp-settings-tab">
							<h2><?= esc_html__('LocationIQ', 'mmp') ?></h2>
							<p>
								<a href="https://www.mapsmarker.com/locationiq-geocoding/" target="_blank"><img src="<?= plugins_url('images/geocoding/locationiq.png', __DIR__) ?>" /></a>
							</p>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('LocationIQ Geocoding API key', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingLocationIqApiKey" value="<?= $settings['geocodingLocationIqApiKey'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= sprintf(esc_html__('For a tutorial on how to get your free LocationIQ Geocoding API key, please visit %1$s.', 'mmp'),'<a href="https://www.mapsmarker.com/locationiq-geocoding/" target="_blank">https://www.mapsmarker.com/locationiq-geocoding/</a>') ?>
									</span>
								</div>
							</div>
							<h3><?= esc_html__('Location biasing', 'mmp') ?></h3>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Geocoding bounds', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="geocodingLocationIqBounds" value="enabled" <?= $this->checked($settings['geocodingLocationIqBounds'], true) ?> /> <?= esc_html__('enabled', 'mmp') ?></label></li>
										<li><label><input type="radio" name="geocodingLocationIqBounds" value="disabled" <?= $this->checked($settings['geocodingLocationIqBounds'], false) ?> /> <?= esc_html__('disabled', 'mmp') ?></label></li>
									</ul>
									<span class="mmp-settings-footnote">
										<?= esc_html__('When using batch geocoding or when ambiguous results are returned, any results within the provided bounding box will be moved to the top of the results list. Below you will find an example for Vienna/Austria:', 'mmp') ?><br />
										<img src="<?= plugins_url('images/options/bounds-example.jpg', __DIR__) ?>" />
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Latitude', 'mmp') ?> 1</div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingLocationIqBoundsLat1" placeholder="48.326583" value="<?= $settings['geocodingLocationIqBoundsLat1'] ?>" />
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Longitude', 'mmp') ?> 1</div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingLocationIqBoundsLon1" placeholder="16.55056" value="<?= $settings['geocodingLocationIqBoundsLon1'] ?>" />
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Latitude', 'mmp') ?> 2</div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingLocationIqBoundsLat2" placeholder="48.114308" value="<?= $settings['geocodingLocationIqBoundsLat2'] ?>" />
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Longitude', 'mmp') ?> 2</div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingLocationIqBoundsLon2" placeholder="16.187325" value="<?= $settings['geocodingLocationIqBoundsLon2'] ?>" />
								</div>
							</div>
							<h3><?= esc_html__('Advanced', 'mmp') ?></h3>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Language', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingLocationIqLanguage" value="<?= $settings['geocodingLocationIqLanguage'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= sprintf(esc_html__('Changes the language of the results. You can pass two letters country codes (%1$s).', 'mmp'), '<a href="https://en.wikipedia.org/wiki/ISO_3166-1#Officially_assigned_code_elements" target="_blank">ISO 639-1</a>') ?><br />
										<?= esc_html__('If empty, the language set in WordPress will be used.', 'mmp') ?>
									</span>
								</div>
							</div>
						</div>
						<div id="geocoding_mapquest_tab" class="mmp-settings-tab">
							<h2>MapQuest</h2>
							<p>
								<a href="https://www.mapsmarker.com/mapquest-geocoding/" target="_blank"><img src="<?= plugins_url('images/geocoding/mapquest-logo.png', __DIR__) ?>" /></a><br />
								<?= sprintf(esc_html__('MapQuest Geocoding API allows up to %1$s transactions/month and a maximum of %2$s requests/second with a free API key. Higher quotas are available on demand - %3$s.', 'mmp'), '15.000', '10', '<a href="https://developer.mapquest.com/plans" target="_blank">' . esc_html__('click here for more details', 'mmp') . '</a>') ?>
							</p>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('MapQuest Geocoding API key', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingMapQuestApiKey" value="<?= $settings['geocodingMapQuestApiKey'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= sprintf(esc_html__('For a tutorial on how to get your free MapQuest Geocoding API key, please visit %1$s.', 'mmp'),'<a href="https://www.mapsmarker.com/mapquest-geocoding/" target="_blank">https://www.mapsmarker.com/mapquest-geocoding/</a>') ?>
									</span>
								</div>
							</div>
							<h3><?= esc_html__('Location biasing', 'mmp') ?></h3>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Geocoding bounds', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="geocodingMapQuestBounds" value="enabled" <?= $this->checked($settings['geocodingMapQuestBounds'], true) ?> /> <?= esc_html__('enabled', 'mmp') ?></label></li>
										<li><label><input type="radio" name="geocodingMapQuestBounds" value="disabled" <?= $this->checked($settings['geocodingMapQuestBounds'], false) ?> /> <?= esc_html__('disabled', 'mmp') ?></label></li>
									</ul>
									<span class="mmp-settings-footnote">
										<?= esc_html__('When using batch geocoding or when ambiguous results are returned, any results within the provided bounding box will be moved to the top of the results list. Below you will find an example for Vienna/Austria:', 'mmp') ?><br />
										<img src="<?= plugins_url('images/options/bounds-example.jpg', __DIR__) ?>" />
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Latitude', 'mmp') ?> 1</div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingMapQuestBoundsLat1" placeholder="48.326583" value="<?= $settings['geocodingMapQuestBoundsLat1'] ?>" />
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Longitude', 'mmp') ?> 1</div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingMapQuestBoundsLon1" placeholder="16.55056" value="<?= $settings['geocodingMapQuestBoundsLon1'] ?>" />
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Latitude', 'mmp') ?> 2</div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingMapQuestBoundsLat2" placeholder="48.114308" value="<?= $settings['geocodingMapQuestBoundsLat2'] ?>" />
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Longitude', 'mmp') ?> 2</div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingMapQuestBoundsLon2" placeholder="16.187325" value="<?= $settings['geocodingMapQuestBoundsLon2'] ?>" />
								</div>
							</div>
						</div>
						<div id="geocoding_google_tab" class="mmp-settings-tab">
							<h2>Google</h2>
							<p>
								<?= sprintf(esc_html__('For terms of services, pricing, usage limits and a tutorial on how to register a Google Geocoding API key, please visit %1$s.', 'mmp'), '<a href="https://www.mapsmarker.com/google-geocoding/" target="_blank">https://www.mapsmarker.com/google-geocoding/</a>') ?>
							</p>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Authentication method', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="geocodingGoogleAuthMethod" value="api-key" <?= $this->checked($settings['geocodingGoogleAuthMethod'], 'api-key') ?> /> server key</label></li>
										<li><label><input type="radio" name="geocodingGoogleAuthMethod" value="clientid-signature" <?= $this->checked($settings['geocodingGoogleAuthMethod'], 'clientid-signature') ?> /> client ID + signature (<?= esc_html__('Google Maps APIs Premium Plan customers only', 'mmp') ?>)</label></li>
									</ul>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc">server key</div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingGoogleApiKey" placeholder="" value="<?= $settings['geocodingGoogleApiKey'] ?>" /><br />
								</div>
							</div>
							<h3><?= esc_html__('Authentication for Google Maps APIs Premium Plan customers', 'mmp') ?></h3>
							<p>
								<?= sprintf(esc_html__('For terms of services, pricing, usage limits and more please visit %1$s.', 'mmp'), '<a href="https://developers.google.com/maps/premium/overview" target="_blank">https://developers.google.com/maps/premium/overview</a>') ?>
							</p>
							<p>
								<?= sprintf(esc_html__('If you are a Google Maps APIs Premium Plan customer, please change the authentication method above to "%1$s" and fill in the credentials below, which you received in the welcome email from Google.', 'mmp'), 'client ID + signature') ?>
							</p>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc">client ID (<?= esc_html__('required', 'mmp') ?>)</div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingGoogleClient" value="<?= $settings['geocodingGoogleClient'] ?>" />
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc">signature (<?= esc_html__('required', 'mmp') ?>)</div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingGoogleSignature" value="<?= $settings['geocodingGoogleSignature'] ?>" />
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc">channel (<?= esc_html__('optional', 'mmp') ?>)</div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingGoogleChannel" value="<?= $settings['geocodingGoogleChannel'] ?>" />
								</div>
							</div>
							<h3><?= esc_html__('Location biasing', 'mmp') ?></h3>
							<p>
								<?= esc_html__('You may bias results to a specified circle by passing a location and a radius parameter. This instructs the Place Autocomplete service to prefer showing results within that circle. Results outside of the defined area may still be displayed. You can use the components parameter to filter results to show only those places within a specified country.', 'mmp') ?> <?= esc_html__('If you would prefer to have no location bias, set the location to 0,0 and radius to 20000000 (20 thousand kilometers), to encompass the entire world.', 'mmp') ?>
							</p>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Location', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingGoogleLocation" placeholder="0,0" value="<?= $settings['geocodingGoogleLocation'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= sprintf(esc_html__('The point around which you wish to retrieve place information. Must be specified as latitude,longitude (e.g. %1$s).', 'mmp'), '48.216038,16.378984') ?>
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Radius', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingGoogleRadius" placeholder="20000000" value="<?= $settings['geocodingGoogleRadius'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= esc_html__('The distance (in meters) within which to return place results. Note that setting a radius biases results to the indicated area, but may not fully restrict results to the specified area.', 'mmp') ?>
									</span>
								</div>
							</div>
							<h3><?= esc_html__('Advanced', 'mmp') ?></h3>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Language', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingGoogleLanguage" value="<?= $settings['geocodingGoogleLanguage'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= sprintf(esc_html__('The language in which to return results. For a list of supported languages, please visit %1$s.', 'mmp'), '<a href="https://developers.google.com/maps/faq#languagesupport" target="_blank">https://developers.google.com/maps/faq#languagesupport</a>') ?>
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Region', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingGoogleRegion" value="<?= $settings['geocodingGoogleRegion'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= sprintf(esc_html__('Optional region code, specified as a ccTLD (country code top-level domain). This parameter will only influence, not fully restrict, results from the geocoder. For a list of ccTLDs, please visit %1$s.', 'mmp'), '<a href="https://en.wikipedia.org/wiki/List_of_Internet_top-level_domains#Country_code_top-level_domains" target="_blank">https://en.wikipedia.org/wiki/List_of_Internet_top-level_domains#Country_code_top-level_domains</a>') ?>
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Components', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="text" name="geocodingGoogleComponents" placeholder="" value="<?= $settings['geocodingGoogleComponents'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= sprintf(esc_html__('Optional component filters, separated by a pipe (|). Each component filter consists of a component:value pair and will fully restrict the results from the geocoder. For more information, please visit %1$s.', 'mmp'), '<a href="https://developers.google.com/maps/documentation/geocoding/intro#ComponentFiltering" target="_blank">https://developers.google.com/maps/documentation/geocoding/intro#ComponentFiltering</a>') ?>
									</span>
								</div>
							</div>
						</div>
						<div id="directions_provider_tab" class="mmp-settings-tab">
							<h2><?= esc_html__('Directions provider', 'mmp') ?></h2>
							<p>
								<?= esc_html__('Please select your preferred directions provider. This setting will be used for the directions link that gets attached to the popup text on each marker if enabled.', 'mmp') ?>
							</p>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Use the following directions provider', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="directionsProvider" value="googlemaps" <?= $this->checked($settings['directionsProvider'], 'googlemaps') ?> /> <?= esc_html__('Google Maps (worldwide)', 'mmp') ?> - <a href="https://maps.google.com/maps?saddr=Vienna&daddr=Linz&hl=de&sll=37.0625,-95.677068&sspn=59.986788,135.263672&geocode=FS6Z3wIdO9j5ACmfyjZRngdtRzFGW6JRiuXC_Q%3BFfwa4QIdBvzZAClNhZn6lZVzRzHEdXlXLClTfA&vpsrc=0&mra=ls&t=m&z=9&layer=t" target="_blank">Demo</a></label></li>
										<li><label><input type="radio" name="directionsProvider" value="yours" <?= $this->checked($settings['directionsProvider'], 'yours') ?> /> <?= esc_html__('yournavigation.org (based on OpenStreetMap, worldwide)', 'mmp') ?> - <a href="http://www.yournavigation.org/?flat=52.215636&flon=6.963946&tlat=52.2573&tlon=6.1799&v=motorcar&fast=1&layer=mapnik" target="_blank">Demo</a></label></li>
										<li><label><input type="radio" name="directionsProvider" value="ors" <?= $this->checked($settings['directionsProvider'], 'ors') ?> /> <?= esc_html__('openrouteservice.org (based on OpenStreetMap, Europe only)', 'mmp') ?> - <a href="https://maps.openrouteservice.org/directions?n1=48.156615&n2=16.327391&n3=13&a=48.1083,16.2725,48.2083,16.3725&b=0&c=0&k1=en-US&k2=km" target="_blank">Demo</a></label></li>
										<li><label><input type="radio" name="directionsProvider" value="bingmaps" <?= $this->checked($settings['directionsProvider'], 'bingmaps') ?> /> <?= esc_html__('Bing Maps (worldwide)', 'mmp') ?> - <a href="http://www.bing.com/maps/default.aspx?v=2&rtp=pos.48.208614_16.370541___e_~pos.48.207321_16.330513" target="_blank">Demo</a></label></li>
									</ul>
								</div>
							</div>
						</div>
						<div id="directions_google_tab" class="mmp-settings-tab">
							<h2>Google Maps</h2>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Map type', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="directionsGoogleType" value="m" <?= $this->checked($settings['directionsGoogleType'], 'm') ?> /> <?= esc_html__('Map', 'mmp') ?></label></li>
										<li><label><input type="radio" name="directionsGoogleType" value="k" <?= $this->checked($settings['directionsGoogleType'], 'k') ?> /> <?= esc_html__('Satellite', 'mmp') ?></label></li>
										<li><label><input type="radio" name="directionsGoogleType" value="h" <?= $this->checked($settings['directionsGoogleType'], 'h') ?> /> <?= esc_html__('Hybrid', 'mmp') ?></label></li>
										<li><label><input type="radio" name="directionsGoogleType" value="p" <?= $this->checked($settings['directionsGoogleType'], 'p') ?> /> <?= esc_html__('Terrain', 'mmp') ?></label></li>
									</ul>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Show traffic layer?', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="directionsGoogleTraffic" value="1" <?= $this->checked($settings['directionsGoogleTraffic'], true) ?> /> <?= esc_html__('yes', 'mmp') ?></label></li>
										<li><label><input type="radio" name="directionsGoogleTraffic" value="0" <?= $this->checked($settings['directionsGoogleTraffic'], false) ?> /> <?= esc_html__('no', 'mmp') ?></label></li>
									</ul>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Distance units', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="directionsGoogleUnits" value="ptk" <?= $this->checked($settings['directionsGoogleUnits'], 'ptk') ?> /> <?= esc_html__('metric (km)', 'mmp') ?></label></li>
										<li><label><input type="radio" class="radio" name="directionsGoogleUnits" value="ptm" <?= $this->checked($settings['directionsGoogleUnits'], 'ptm') ?> /> <?= esc_html__('imperial (miles)', 'mmp') ?></label></li>
									</ul>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Route type', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<label><input type="checkbox" name="directionsGoogleAvoidHighways" <?= $this->checked($settings['directionsGoogleAvoidHighways']) ?> /> <?= esc_html__('Avoid highways', 'mmp') ?></label><br />
									<label><input type="checkbox" name="directionsGoogleAvoidTolls" <?= $this->checked($settings['directionsGoogleAvoidTolls']) ?> /> <?= esc_html__('Avoid tolls', 'mmp') ?></label><br />
									<label><input type="checkbox" name="directionsGooglePublicTransport" <?= $this->checked($settings['directionsGooglePublicTransport']) ?> /> <?= esc_html__('Public transport (works only in some areas)', 'mmp') ?></label><br />
									<label><input type="checkbox" name="directionsGoogleWalking" <?= $this->checked($settings['directionsGoogleWalking']) ?> /> <?= esc_html__('Walking directions', 'mmp') ?></label>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Overview map', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="directionsGoogleOverview" value="0" <?= $this->checked($settings['directionsGoogleOverview'], false) ?> /> <?= esc_html__('hidden', 'mmp') ?></label></li>
										<li><label><input type="radio" name="directionsGoogleOverview" value="1" <?= $this->checked($settings['directionsGoogleOverview'], true) ?> /> <?= esc_html__('visible', 'mmp') ?></label></li>
									</ul>
								</div>
							</div>
						</div>
						<div id="directions_your_tab" class="mmp-settings-tab">
							<h2>yournavigation.org</h2>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Type of transport', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="directionsYoursType" value="motorcar" <?= $this->checked($settings['directionsYoursType'], 'motorcar') ?> /> <?= esc_html__('Motorcar', 'mmp') ?></label></li>
										<li><label><input type="radio" name="directionsYoursType" value="bicycle" <?= $this->checked($settings['directionsYoursType'], 'bicycle') ?> /> <?= esc_html__('Bicycle', 'mmp') ?></label></li>
										<li><label><input type="radio" name="directionsYoursType" value="foot" <?= $this->checked($settings['directionsYoursType'], 'foot') ?> /> <?= esc_html__('Foot', 'mmp') ?></label></li>
									</ul>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Route type', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="directionsYoursRoute" value="fastest" <?= $this->checked($settings['directionsYoursRoute'], 'fastest') ?> /> <?= esc_html__('Fastest route', 'mmp') ?></label></li>
										<li><label><input type="radio" name="directionsYoursRoute" value="shortest" <?= $this->checked($settings['directionsYoursRoute'], 'shortest') ?> /> <?= esc_html__('Shortest route', 'mmp') ?></label></li>
									</ul>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Gosmore instance to calculate the route', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="directionsYoursLayer" value="mapnik" <?= $this->checked($settings['directionsYoursLayer'], 'mapnik') ?> /> <?= esc_html__('mapnik (for normal routing using car, bicycle or foot)', 'mmp') ?></label></li>
										<li><label><input type="radio" name="directionsYoursLayer" value="cn" <?= $this->checked($settings['directionsYoursLayer'], 'cn') ?> /> <?= esc_html__('cn (for using bicycle routing using cycle route networks only)', 'mmp') ?></label></li>
									</ul>
								</div>
							</div>
						</div>
						<div id="directions_ors_tab" class="mmp-settings-tab">
							<h2>openrouteservice.org</h2>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc">routeWeigh</div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="directionsOrsRoute" value="Fastest" <?= $this->checked($settings['directionsOrsRoute'], 'Fastest') ?> /> <?= esc_html__('Fastest', 'mmp') ?></label></li>
										<li><label><input type="radio" name="directionsOrsRoute" value="Shortest" <?= $this->checked($settings['directionsOrsRoute'], 'Shortest') ?> /> <?= esc_html__('Shortest', 'mmp') ?></label></li>
										<li /><label><input type="radio" name="directionsOrsRoute" value="Recommended" <?= $this->checked($settings['directionsOrsRoute'], 'Recommended') ?>> <?= esc_html__('Recommended', 'mmp') ?></label></li>
									</ul>
									<span class="mmp-settings-footnote"><?= esc_html__('Weighting method of routing', 'mmp') ?></span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc">routeOpt</div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="directionsOrsType" value="Car" <?= $this->checked($settings['directionsOrsType'], 'Car') ?> /> <?= esc_html__('Car', 'mmp') ?></label></li>
										<li><label><input type="radio" name="directionsOrsType" value="Bicycle" <?= $this->checked($settings['directionsOrsType'], 'Bicycle') ?> /> <?= esc_html__('Bicycle', 'mmp') ?></label></li>
										<li><label><input type="radio" name="directionsOrsType" value="Pedestrian" <?= $this->checked($settings['directionsOrsType'], 'Pedestrian') ?> /> <?= esc_html__('Pedestrian', 'mmp') ?></label></li>
										<li><label><input type="radio" name="directionsOrsType" value="HeavyVehicle" <?= $this->checked($settings['directionsOrsType'], 'HeavyVehicle') ?> /> <?= esc_html__('HeavyVehicle', 'mmp') ?></label></li>
									</ul>
									<span class="mmp-settings-footnote"><?= esc_html__('Preferred route profile', 'mmp') ?></span>
								</div>
							</div>
						</div>
						<div id="misc_general_tab" class="mmp-settings-tab">
							<h2><?= esc_html__('General', 'mmp') ?></h2>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Affiliate ID', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="text" name="affiliateId" value="<?= $settings['affiliateId'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= esc_html__('Enter your affiliate ID to replace the default MapsMarker.com backlink on all maps with your personal affiliate link - enabling you to receive commissions up to 50% from sales of the pro version.', 'mmp') ?><br />
										<?= sprintf(esc_html__('For more info on the Maps Marker affiliate program and how to get your affiliate ID, please visit %1$s.', 'mmp'), '<a href="https://www.mapsmarker.com/affiliateid/" target="_blank">https://www.mapsmarker.com/affiliateid/</a>') ?>
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Beta testing', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="betaTesting" value="disabled" <?= $this->checked($settings['betaTesting'], false) ?> /> <?= esc_html__('disabled', 'mmp') ?></label></li>
										<li><label><input type="radio" name="betaTesting" value="enabled" <?= $this->checked($settings['betaTesting'], true) ?> /> <?= esc_html__('enabled', 'mmp') ?></label></li>
									</ul>
									<span class="mmp-settings-footnote">
										<?= esc_html__('Set to enabled if you want to easily upgrade to beta releases.', 'mmp') ?><br />
										<span class="mmp-settings-warning"><?= esc_html__('Warning: not recommended on production sites - use at your own risk!', 'mmp') ?></span>
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('App icon URL', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="text" name="appIcon" value="<?= $settings['appIcon'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= esc_html__('Will be used if a link to a fullscreen map gets added to the homescreen on mobile devices. If empty, the Maps Marker Pro logo will be used.', 'mmp') ?>
									</span>
								</div>
							</div>
							<h3><?= esc_html__('Attribution', 'mmp') ?></h3>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Whitelabel backend', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="whitelabelBackend" value="disabled" <?= $this->checked($settings['whitelabelBackend'], false) ?> /> <?= esc_html__('disabled', 'mmp') ?></label></li>
										<li><label><input type="radio" name="whitelabelBackend" value="enabled" <?= $this->checked($settings['whitelabelBackend'], true) ?> /> <?= esc_html__('enabled', 'mmp') ?></label></li>
									</ul>
									<span class="mmp-settings-footnote">
										<?= esc_html__('Set to enabled if you want to remove all backlinks and logos on backend as well as making the pages and menu entries for Tools, Settings, Support, License visible to admins only (user capability activate_plugins).', 'mmp') ?>
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('MapsMarker.com backlinks', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="backlinks" value="show" <?= $this->checked($settings['backlinks'], true) ?> /> <?= esc_html__('show', 'mmp') ?></label></li>
										<li><label><input type="radio" name="backlinks" value="hide" <?= $this->checked($settings['backlinks'], false) ?> /> <?= esc_html__('hide', 'mmp') ?></label></li>
									</ul>
									<span class="mmp-settings-footnote">
										<?= esc_html__('Option to hide backlinks to Mapsmarker.com on maps and screen overlays in KML files.', 'mmp') ?><br />
										<img src="<?= plugins_url('images/options/backlink.jpg', __DIR__) ?>" /><br />
										<img src="<?= plugins_url('images/options/backlink-kml.jpg', __DIR__) ?>" />
									</span>
								</div>
							</div>
						</div>
						<div id="misc_icons_tab" class="mmp-settings-tab">
							<h2><?= esc_html__('Icons', 'mmp') ?></h2>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Marker shadow', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="iconCustomShadow" value="default" <?= $this->checked($settings['iconCustomShadow'], 'default') ?> /> <?= esc_html__('use default shadow', 'mmp') ?> (<?= esc_html__('preview', 'mmp') ?>: <img src="<?= plugins_url('images/leaflet/marker-shadow.png', __DIR__) ?>" />)</label></li>
										<li><label><input type="radio" name="iconCustomShadow" value="custom" <?= $this->checked($settings['iconCustomShadow'], 'custom') ?> /> <?= esc_html__('use custom shadow (please enter URL below)', 'mmp') ?></label></li>
									</ul>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Custom marker shadow URL', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="text" name="iconCustomShadowUrl" value="<?= $settings['iconCustomShadowUrl'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= esc_html__('The URL to the custom icons shadow image. If empty, no shadow image will be used.', 'mmp') ?>
									</span>
								</div>
							</div>
							<p>
								<?= sprintf(esc_html__('Only change the values below if you are not using marker or shadow icons from the %1$s!', 'mmp'), '<a href="https://mapicons.mapsmarker.com" target="_blank">' . esc_html__('Map Icons Collection', 'mmp') . '</a>') ?>
							</p>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Icon size', 'mmp') ?> (x)</div>
								<div class="mmp-settings-input">
									<input type="text" name="iconSizeX" placeholder="32" value="<?= $settings['iconSizeX'] ?>" /><br />
									<span class="mmp-settings-footnote"><?= esc_html__('Width of the icons in pixels.', 'mmp') ?></span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Icon size', 'mmp') ?> (y)</div>
								<div class="mmp-settings-input">
									<input type="text" name="iconSizeY" placeholder="37" value="<?= $settings['iconSizeY'] ?>" /><br />
									<span class="mmp-settings-footnote"><?= esc_html__('Height of the icons in pixels.', 'mmp') ?></span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Icon anchor', 'mmp') ?> (x)</div>
								<div class="mmp-settings-input">
									<input type="text" name="iconAnchorX" placeholder="17" value="<?= $settings['iconAnchorX'] ?>" /><br />
									<span class="mmp-settings-footnote"><?= esc_html__('The x-coordinate of the "tip" of the icons (relative to its top left corner).', 'mmp') ?></span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Icon anchor', 'mmp') ?> (y)</div>
								<div class="mmp-settings-input">
									<input type="text" name="iconAnchorY" placeholder="36" value="<?= $settings['iconAnchorY'] ?>" /><br />
									<span class="mmp-settings-footnote"><?= esc_html__('The y-coordinate of the "tip" of the icons (relative to its top left corner).', 'mmp') ?></span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Popup anchor', 'mmp') ?> (x)</div>
								<div class="mmp-settings-input">
									<input type="text" name="iconPopupAnchorX" placeholder="-1" value="<?= $settings['iconPopupAnchorX'] ?>" /><br />
									<span class="mmp-settings-footnote"><?= esc_html__('The x-coordinate of the popup anchor (relative to its top left corner).', 'mmp') ?></span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Popup anchor', 'mmp') ?> (y)</div>
								<div class="mmp-settings-input">
									<input type="text" name="iconPopupAnchorY" placeholder="-32" value="<?= $settings['iconPopupAnchorY'] ?>" /><br />
									<span class="mmp-settings-footnote"><?= esc_html__('The y-coordinate of the popup anchor (relative to its top left corner).', 'mmp') ?></span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Shadow size', 'mmp') ?> (x)</div>
								<div class="mmp-settings-input">
									<input type="text" name="iconShadowSizeX" placeholder="41" value="<?= $settings['iconShadowSizeX'] ?>" /><br />
									<span class="mmp-settings-footnote"><?= esc_html__('Width of the shadow icon in pixels.', 'mmp') ?></span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Shadow size', 'mmp') ?> (y)</div>
								<div class="mmp-settings-input">
									<input type="text" name="iconShadowSizeY" placeholder="41" value="<?= $settings['iconShadowSizeY'] ?>" /><br />
									<span class="mmp-settings-footnote"><?= esc_html__('Height of the shadow icon in pixels.', 'mmp') ?></span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Shadow anchor', 'mmp') ?> (x)</div>
								<div class="mmp-settings-input">
									<input type="text" name="iconShadowAnchorX" placeholder="16" value="<?= $settings['iconShadowAnchorX'] ?>" /><br />
									<span class="mmp-settings-footnote"><?= esc_html__('The x-coordinate of the "tip" of the shadow icon (relative to its top left corner).', 'mmp') ?></span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Shadow anchor', 'mmp') ?> (y)</div>
								<div class="mmp-settings-input">
									<input type="text" name="iconShadowAnchorY" placeholder="43" value="<?= $settings['iconShadowAnchorY'] ?>" /><br />
									<span class="mmp-settings-footnote"><?= esc_html__('The y-coordinate of the "tip" of the shadow icon (relative to its top left corner).', 'mmp') ?></span>
								</div>
							</div>
						</div>
						<div id="misc_capabilities_tab" class="mmp-settings-tab">
							<h2><?= esc_html__('Capabilities', 'mmp') ?></h2>
							<p>
								<?= esc_html__('Here you can set the backend capabilities for each user role. Administrators always have all capabilities.', 'mmp') ?>
							</p>
							<table id="user_capabilities" class="mmp-role-capabilities">
								<tr>
									<th><?= esc_html__('Role', 'mmp') ?></th>
									<th><?= esc_html__('View maps', 'mmp') ?></th>
									<th><?= esc_html__('Add maps', 'mmp') ?></th>
									<th><?= esc_html__('Edit other maps', 'mmp') ?></th>
									<th><?= esc_html__('Delete other maps', 'mmp') ?></th>
									<th><?= esc_html__('View markers', 'mmp') ?></th>
									<th><?= esc_html__('Add markers', 'mmp') ?></th>
									<th><?= esc_html__('Edit other markers', 'mmp') ?></th>
									<th><?= esc_html__('Delete other markers', 'mmp') ?></th>
									<th><?= esc_html__('Use tools', 'mmp') ?></th>
									<th><?= esc_html__('Change settings', 'mmp') ?></th>
								</tr>
								<?php foreach($wp_roles->roles as $role => $values): ?>
									<?php if ($role === 'administrator') continue ?>
									<tr>
										<td><?= translate_user_role($values['name']) ?></td>
										<td><input type="checkbox" name="role_capabilities[<?= $role ?>][mmp_view_maps]" <?= $this->checked((isset($values['capabilities']['mmp_view_maps']) && $values['capabilities']['mmp_view_maps'])) ?> /></td>
										<td><input type="checkbox" name="role_capabilities[<?= $role ?>][mmp_add_maps]" <?= $this->checked((isset($values['capabilities']['mmp_add_maps']) && $values['capabilities']['mmp_add_maps'])) ?> /></td>
										<td><input type="checkbox" name="role_capabilities[<?= $role ?>][mmp_edit_other_maps]" <?= $this->checked((isset($values['capabilities']['mmp_edit_other_maps']) && $values['capabilities']['mmp_edit_other_maps'])) ?> /></td>
										<td><input type="checkbox" name="role_capabilities[<?= $role ?>][mmp_delete_other_maps]" <?= $this->checked((isset($values['capabilities']['mmp_delete_other_maps']) && $values['capabilities']['mmp_delete_other_maps'])) ?> /></td>
										<td><input type="checkbox" name="role_capabilities[<?= $role ?>][mmp_view_markers]" <?= $this->checked((isset($values['capabilities']['mmp_view_markers']) && $values['capabilities']['mmp_view_markers'])) ?> /></td>
										<td><input type="checkbox" name="role_capabilities[<?= $role ?>][mmp_add_markers]" <?= $this->checked((isset($values['capabilities']['mmp_add_markers']) && $values['capabilities']['mmp_add_markers'])) ?> /></td>
										<td><input type="checkbox" name="role_capabilities[<?= $role ?>][mmp_edit_other_markers]" <?= $this->checked((isset($values['capabilities']['mmp_edit_other_markers']) && $values['capabilities']['mmp_edit_other_markers'])) ?> /></td>
										<td><input type="checkbox" name="role_capabilities[<?= $role ?>][mmp_delete_other_markers]" <?= $this->checked((isset($values['capabilities']['mmp_delete_other_markers']) && $values['capabilities']['mmp_delete_other_markers'])) ?> /></td>
										<td><input type="checkbox" name="role_capabilities[<?= $role ?>][mmp_use_tools]" <?= $this->checked((isset($values['capabilities']['mmp_use_tools']) && $values['capabilities']['mmp_use_tools'])) ?> /></td>
										<td><input type="checkbox" name="role_capabilities[<?= $role ?>][mmp_change_settings]" <?= $this->checked((isset($values['capabilities']['mmp_change_settings']) && $values['capabilities']['mmp_change_settings'])) ?> /></td>
									</tr>
								<?php endforeach; ?>
							</table>
						</div>
						<div id="misc_sitemaps_tab" class="mmp-settings-tab">
							<h2><?= esc_html__('Sitemaps', 'mmp') ?></h2>
							<p>
								<?= esc_html__('XML sitemaps help search engines like Google, Bing, Yahoo and Ask.com to better index your blog. With such a sitemap, it is much easier for the crawlers to see the complete structure of your site and retrieve it more efficiently. Geolocation information can also be added to sitemaps in order to improve your local SEO value for services like Google Places.', 'mmp') ?>
							</p>
							<p>
								<?= sprintf($l10n->kses__('Maps Marker Pro includes a <a href="%1$s" target="_blank">geo sitemap</a>. To learn how to manually register this sitemap, please visit <a href="%2$s" target="_blank">this tutorial</a>. Alternatively, you can use one of the supported plugins to automate the process.', 'mmp'), $api->link('/geo-sitemap/'), 'https://www.mapsmarker.com/geo-sitemap/') ?>
							</p>
							<h3>Google XML Sitemaps</h3>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Integration', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="sitemapGoogle" value="enabled" <?= $this->checked($settings['sitemapGoogle'], true) ?> /> <?= esc_html__('enabled', 'mmp') ?></label></li>
										<li><label><input type="radio" name="sitemapGoogle" value="disabled" <?= $this->checked($settings['sitemapGoogle'], false) ?> /> <?= esc_html__('disabled', 'mmp') ?></label></li>
									</ul>
									<span class="mmp-settings-footnote">
										<?= sprintf(esc_html__('If enabled, and the %1$s plugin is active, KML links will automatically be added to the sitemap.', 'mmp'), '<a href="https://wordpress.org/plugins/google-sitemap-generator/" target="_blank">Google XML Sitemaps</a>') ?>
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Include specific maps', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="text" name="sitemapGoogleInclude" value="<?= $settings['sitemapGoogleInclude'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= esc_html__('Please enter a comma-separted list of IDs (e.g. 1,2,3).', 'mmp') ?>
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Exclude specific maps', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="text" name="sitemapGoogleExclude" value="<?= $settings['sitemapGoogleExclude'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= esc_html__('Please enter a comma-separted list of IDs (e.g. 1,2,3).', 'mmp') ?>
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Priority for maps', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<select name="sitemapGooglePriority">
										<option value="0" <?= $this->selected($settings['sitemapGooglePriority'], '0') ?>>0</option>
										<option value="0.1" <?= $this->selected($settings['sitemapGooglePriority'], '0.1') ?>>0.1</option>
										<option value="0.2" <?= $this->selected($settings['sitemapGooglePriority'], '0.2') ?>>0.2</option>
										<option value="0.3" <?= $this->selected($settings['sitemapGooglePriority'], '0.3') ?>>0.3</option>
										<option value="0.4" <?= $this->selected($settings['sitemapGooglePriority'], '0.4') ?>>0.4</option>
										<option value="0.5" <?= $this->selected($settings['sitemapGooglePriority'], '0.5') ?>>0.5</option>
										<option value="0.6" <?= $this->selected($settings['sitemapGooglePriority'], '0.6') ?>>0.6</option>
										<option value="0.7" <?= $this->selected($settings['sitemapGooglePriority'], '0.7') ?>>0.7</option>
										<option value="0.8" <?= $this->selected($settings['sitemapGooglePriority'], '0.8') ?>>0.8</option>
										<option value="0.9" <?= $this->selected($settings['sitemapGooglePriority'], '0.9') ?>>0.9</option>
										<option value="1" <?= $this->selected($settings['sitemapGooglePriority'], '1') ?>>1</option>
									</select><br />
									<span class="mmp-settings-footnote"><?= esc_html__('The priority of maps relative to other URLs on your site.', 'mmp') ?></span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Update frequency', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<select name="sitemapGoogleFrequency">
										<option value="always" <?= $this->selected($settings['sitemapGoogleFrequency'], 'always') ?>><?= esc_html__('Always', 'mmp') ?></option>
										<option value="hourly" <?= $this->selected($settings['sitemapGoogleFrequency'], 'hourly') ?>><?= esc_html__('Hourly', 'mmp') ?></option>
										<option value="daily" <?= $this->selected($settings['sitemapGoogleFrequency'], 'daily') ?>><?= esc_html__('Daily', 'mmp') ?></option>
										<option value="weekly" <?= $this->selected($settings['sitemapGoogleFrequency'], 'weekly') ?>><?= esc_html__('Weekly', 'mmp') ?></option>
										<option value="monthly" <?= $this->selected($settings['sitemapGoogleFrequency'], 'monthly') ?>><?= esc_html__('Monthly', 'mmp') ?></option>
										<option value="yearly" <?= $this->selected($settings['sitemapGoogleFrequency'], 'yearly') ?>><?= esc_html__('Yearly', 'mmp') ?></option>
										<option value="never" <?= $this->selected($settings['sitemapGoogleFrequency'], 'never') ?>><?= esc_html__('Never', 'mmp') ?></option>
									</select><br />
									<span class="mmp-settings-footnote">
										<?= sprintf(esc_html__('How frequently the maps are likely to change. This value provides general information to search engines and may not correlate exactly to how often they crawl the page. Additional information available at %1$s.', 'mmp'), '<a href="http://www.sitemaps.org/protocol.html" target="_blank">sitemaps.org</a>') ?>
									</span>
								</div>
							</div>
							<h3>Yoast SEO</h3>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Integration', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="sitemapYoast" value="enabled" <?= $this->checked($settings['sitemapYoast'], true) ?> /> <?= esc_html__('enabled', 'mmp') ?></label></li>
										<li><label><input type="radio" name="sitemapYoast" value="disabled" <?= $this->checked($settings['sitemapYoast'], false) ?> /> <?= esc_html__('disabled', 'mmp') ?></label></li>
									</ul>
									<span class="mmp-settings-footnote">
										<?= sprintf(esc_html__('If enabled, and the %1$s plugin is active, the geo sitemap will automatically be added to the sitemap index.', 'mmp'), '<a href="https://wordpress.org/plugins/wordpress-seo/" target="_blank">Yoast SEO</a>') ?>
									</span>
								</div>
							</div>
						</div>
						<div id="misc_wordpress_tab" class="mmp-settings-tab">
							<h2><?= esc_html__('WordPress integration', 'mmp') ?></h2>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Shortcode', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="text" name="shortcode" placeholder="mapsmarker" value="<?= $settings['shortcode'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= esc_html__('Shortcode to add maps - Example: [mapsmarker map="1"]', 'mmp') ?><br />
										<?= esc_html__('Attention: if you change the shortcode after having embedded shortcodes into content, the shortcode on these pages has to be changed manually. Otherwise, these maps will not be show!', 'mmp') ?>
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('TinyMCE button', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="tinyMce" value="enabled" <?= $this->checked($settings['tinyMce'], true) ?> /> <?= esc_html__('enabled', 'mmp') ?></label></li>
										<li><label><input type="radio" name="tinyMce" value="disabled" <?= $this->checked($settings['tinyMce'], false) ?> /> <?= esc_html__('disabled', 'mmp') ?></label></li>
									</ul>
									<span class="mmp-settings-footnote">
										<?= esc_html__('If enabled, an "Insert map" button gets added above the TinyMCE editor on post and page edit screens for easily searching and inserting maps.', 'mmp') ?>
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('WordPress Admin Bar integration', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="adminBar" value="enabled" <?= $this->checked($settings['adminBar'], true) ?> /> <?= esc_html__('enabled', 'mmp') ?></label></li>
										<li><label><input type="radio" name="adminBar" value="disabled" <?= $this->checked($settings['adminBar'], false) ?> /> <?= esc_html__('disabled', 'mmp') ?></label></li>
									</ul>
									<span class="mmp-settings-footnote">
										<?= esc_html__('If enabled, show a dropdown menu in the Wordpress Admin Bar.', 'mmp') ?>
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('WordPress admin dashboard widget', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="dashboardWidget" value="enabled" <?= $this->checked($settings['dashboardWidget'], true) ?> /> <?= esc_html__('enabled', 'mmp') ?></label></li>
										<li><label><input type="radio" name="dashboardWidget" value="disabled" <?= $this->checked($settings['dashboardWidget'], false) ?> /> <?= esc_html__('disabled', 'mmp') ?></label></li>
									</ul>
									<span class="mmp-settings-footnote">
										<?= esc_html__('If enabled, shows a widget on the admin dashboard which displays latest markers and blog posts from mapsmarker.com.', 'mmp') ?>
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Admin notices', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="adminNotices" value="show" <?= $this->checked($settings['adminNotices'], true) ?> /> <?= esc_html__('show', 'mmp') ?></label></li>
										<li><label><input type="radio" name="adminNotices" value="hide" <?= $this->checked($settings['adminNotices'], false) ?> /> <?= esc_html__('hide', 'mmp') ?></label></li>
									</ul>
									<span class="mmp-settings-footnote">
										<?= esc_html__('Option for global admin notices in backend (showing infos about plugin incompatibilities or invalid shortcodes, for example). Please be aware that hiding them results in not being informed about plugin incompatibilites discovered in future releases too!', 'mmp') ?>
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Permalinks slug', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="text" name="permalinkSlug" placeholder="maps" value="<?= $settings['permalinkSlug'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= esc_html__('Used to create pretty links to fullscreen maps or API endpoints.', 'mmp') ?><br />
										<?= sprintf(esc_html__('Example link to fullscreen map ID 1: %1$s', 'mmp'), $api->link('/fullscreen/1/')) ?>
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Permalinks base URL', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<input type="text" name="permalinkBaseUrl" value="<?= $settings['permalinkBaseUrl'] ?>" /><br />
									<span class="mmp-settings-footnote">
										<?= esc_html__('Needed for creating pretty links to fullscreen maps or API endpoints.', 'mmp') ?><br />
										<?= esc_html__('Only set this option to the URL of your WordPress folder if you are experiencing issues or recommended so by support!', 'mmp') ?><br />
										<?= sprintf(esc_html__('If empty, "WordPress Address (URL)" - %1$s - will be used.', 'mmp'), get_site_url()) ?>
									</span>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('HTML filter for popups', 'mmp') ?> (wp_kses)</div>
								<div class="mmp-settings-input">
									<ul>
										<li><label><input type="radio" name="popupKses" value="enabled" <?= $this->checked($settings['popupKses'], true) ?> /> <?= esc_html__('enabled', 'mmp') ?></label></li>
										<li><label><input type="radio" name="popupKses" value="disabled" <?= $this->checked($settings['popupKses'], false) ?> /> <?= esc_html__('disabled', 'mmp') ?></label></li>
									</ul>
									<span class="mmp-settings-footnote">
										<?= esc_html__('If enabled, unsupported code tags are stripped from popups to prevent injection of malicious code.', 'mmp') ?><br />
										<?= esc_html__('Disabling this option allows you to display unfiltered popups and is only recommended if special HTML tags are needed.', 'mmp') ?>
									</span>
								</div>
							</div>
							<h3><?= esc_html__('Interface language', 'mmp') ?></h3>
							<p>
								<?= esc_html__('The interface language to use on backend and/or on maps on frontend. Please note that the language for Google Maps and Bing maps can be set separately via the according basemap settings section.', 'mmp') ?><br />
								<?= esc_html__('If your language is missing or not fully translated yet, you are invited to help on the web-based translation plattform:', 'mmp') ?> <a href="https://translate.mapsmarker.com/" target="_blank">https://translate.mapsmarker.com/</a>
							</p>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Admin area', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<select name="pluginLanguageAdmin">
										<option value="automatic" <?= $this->selected($settings['pluginLanguageAdmin'], 'automatic') ?>><?= esc_html__('Automatic (use WordPress default)', 'mmp') ?></option>
										<option value="ar" <?= $this->selected($settings['pluginLanguageAdmin'], 'ar') ?>><?= esc_html__('Arabic', 'mmp') ?> (ar)</option>
										<option value="af" <?= $this->selected($settings['pluginLanguageAdmin'], 'af') ?>><?= esc_html__('Afrikaans', 'mmp') ?> (af)</option>
										<option value="bn_BD" <?= $this->selected($settings['pluginLanguageAdmin'], 'bn_BD') ?>><?= esc_html__('Bengali', 'mmp') ?> (bn_BD)</option>
										<option value="bs_BA" <?= $this->selected($settings['pluginLanguageAdmin'], 'bs_BA') ?>><?= esc_html__('Bosnian', 'mmp') ?> (bs_BA)</option>
										<option value="bg_BG" <?= $this->selected($settings['pluginLanguageAdmin'], 'bg_BG') ?>><?= esc_html__('Bulgarian', 'mmp') ?> (bg_BG)</option>
										<option value="ca" <?= $this->selected($settings['pluginLanguageAdmin'], 'ca') ?>><?= esc_html__('Catalan', 'mmp') ?> (ca)</option>
										<option value="zh_CN" <?= $this->selected($settings['pluginLanguageAdmin'], 'zh_CN') ?>><?= esc_html__('Chinese', 'mmp') ?> (zh_CN)</option>
										<option value="zh_TW" <?= $this->selected($settings['pluginLanguageAdmin'], 'zh_TW') ?>><?= esc_html__('Chinese', 'mmp') ?> (zh_TW)</option>
										<option value="hr" <?= $this->selected($settings['pluginLanguageAdmin'], 'hr') ?>><?= esc_html__('Croatian', 'mmp') ?> (hr)</option>
										<option value="cs_CZ" <?= $this->selected($settings['pluginLanguageAdmin'], 'cs_CZ') ?>><?= esc_html__('Czech', 'mmp') ?> (cs_CZ)</option>
										<option value="da_DK" <?= $this->selected($settings['pluginLanguageAdmin'], 'da_DK') ?>><?= esc_html__('Danish', 'mmp') ?> (da_DK)</option>
										<option value="nl_NL" <?= $this->selected($settings['pluginLanguageAdmin'], 'nl_NL') ?>><?= esc_html__('Dutch', 'mmp') ?> (nl_NL)</option>
										<option value="en_US" <?= $this->selected($settings['pluginLanguageAdmin'], 'en_US') ?>><?= esc_html__('English', 'mmp') ?> (en_US)</option>
										<option value="fi_FI" <?= $this->selected($settings['pluginLanguageAdmin'], 'fi_FI') ?>><?= esc_html__('Finnish', 'mmp') ?> (fi_FI)</option>
										<option value="fr_FR" <?= $this->selected($settings['pluginLanguageAdmin'], 'fr_FR') ?>><?= esc_html__('French', 'mmp') ?> (fr_FR)</option>
										<option value="gl_ES" <?= $this->selected($settings['pluginLanguageAdmin'], 'gl_ES') ?>><?= esc_html__('Galician', 'mmp') ?> (gl_ES)</option>
										<option value="de_DE" <?= $this->selected($settings['pluginLanguageAdmin'], 'de_DE') ?>><?= esc_html__('German', 'mmp') ?> (de_DE)</option>
										<option value="el" <?= $this->selected($settings['pluginLanguageAdmin'], 'el') ?>><?= esc_html__('Greek', 'mmp') ?> (el)</option>
										<option value="he_IL" <?= $this->selected($settings['pluginLanguageAdmin'], 'he_IL') ?>><?= esc_html__('Hebrew', 'mmp') ?> (he_IL)</option>
										<option value="hi_IN" <?= $this->selected($settings['pluginLanguageAdmin'], 'hi_IN') ?>><?= esc_html__('Hindi', 'mmp') ?> (hi_IN)</option>
										<option value="hu_HU" <?= $this->selected($settings['pluginLanguageAdmin'], 'hu_HU') ?>><?= esc_html__('Hungarian', 'mmp') ?> (hu_HU)</option>
										<option value="id_ID" <?= $this->selected($settings['pluginLanguageAdmin'], 'id_ID') ?>><?= esc_html__('Indonesian', 'mmp') ?> (id_ID)</option>
										<option value="it_IT" <?= $this->selected($settings['pluginLanguageAdmin'], 'it_IT') ?>><?= esc_html__('Italian', 'mmp') ?> (it_IT)</option>
										<option value="ja" <?= $this->selected($settings['pluginLanguageAdmin'], 'ja') ?>><?= esc_html__('Japanese', 'mmp') ?> (ja)</option>
										<option value="ko_KR" <?= $this->selected($settings['pluginLanguageAdmin'], 'ko_KR') ?>><?= esc_html__('Korean', 'mmp') ?> (ko_KR)</option>
										<option value="lv" <?= $this->selected($settings['pluginLanguageAdmin'], 'lv') ?>><?= esc_html__('Latvian', 'mmp') ?> (lv)</option>
										<option value="lt_LT" <?= $this->selected($settings['pluginLanguageAdmin'], 'lt_LT') ?>><?= esc_html__('Lithuanian', 'mmp') ?> (lt_LT)</option>
										<option value="ms_MY" <?= $this->selected($settings['pluginLanguageAdmin'], 'ms_MY') ?>><?= esc_html__('Malay', 'mmp') ?> (ms_MY)</option>
										<option value="nb_NO" <?= $this->selected($settings['pluginLanguageAdmin'], 'nb_NO') ?>><?= esc_html__('Norwegian (Bokmål)', 'mmp') ?> (nb_NO)</option>
										<option value="pl_PL" <?= $this->selected($settings['pluginLanguageAdmin'], 'pl_PL') ?>><?= esc_html__('Polish', 'mmp') ?> (pl_PL)</option>
										<option value="pt_BR" <?= $this->selected($settings['pluginLanguageAdmin'], 'pt_BR') ?>><?= esc_html__('Portuguese', 'mmp') ?> (pt_BR)</option>
										<option value="pt_PT" <?= $this->selected($settings['pluginLanguageAdmin'], 'pt_PT') ?>><?= esc_html__('Portuguese', 'mmp') ?> (pt_PT)</option>
										<option value="ro_RO" <?= $this->selected($settings['pluginLanguageAdmin'], 'ro_RO') ?>><?= esc_html__('Romanian', 'mmp') ?> (ro_RO)</option>
										<option value="ru_RU" <?= $this->selected($settings['pluginLanguageAdmin'], 'ru_RU') ?>><?= esc_html__('Russian', 'mmp') ?> (ru_RU)</option>
										<option value="sk_SK" <?= $this->selected($settings['pluginLanguageAdmin'], 'sk_SK') ?>><?= esc_html__('Slovak', 'mmp') ?> (sk_SK)</option>
										<option value="sl_SI" <?= $this->selected($settings['pluginLanguageAdmin'], 'sl_SI') ?>><?= esc_html__('Slovenian', 'mmp') ?> (sl_SI)</option>
										<option value="sv_SE" <?= $this->selected($settings['pluginLanguageAdmin'], 'sv_SE') ?>><?= esc_html__('Swedish', 'mmp') ?> (sv_SE)</option>
										<option value="es_ES" <?= $this->selected($settings['pluginLanguageAdmin'], 'es_ES') ?>><?= esc_html__('Spanish', 'mmp') ?> (es_ES)</option>
										<option value="es_MX" <?= $this->selected($settings['pluginLanguageAdmin'], 'es_MX') ?>><?= esc_html__('Spanish', 'mmp') ?> (es_MX)</option>
										<option value="th" <?= $this->selected($settings['pluginLanguageAdmin'], 'th') ?>><?= esc_html__('Thai', 'mmp') ?> (th)</option>
										<option value="tr_TR" <?= $this->selected($settings['pluginLanguageAdmin'], 'tr_TR') ?>><?= esc_html__('Turkish', 'mmp') ?> (tr_TR)</option>
										<option value="ug" <?= $this->selected($settings['pluginLanguageAdmin'], 'ug') ?>><?= esc_html__('Uighur', 'mmp') ?> (ug)</option>
										<option value="uk_UK" <?= $this->selected($settings['pluginLanguageAdmin'], 'uk_UK') ?>><?= esc_html__('Ukrainian', 'mmp') ?> (uk_UK)</option>
										<option value="vi" <?= $this->selected($settings['pluginLanguageAdmin'], 'vi') ?>><?= esc_html__('Vietnamese', 'mmp') ?> (vi)</option>
										<option value="yi" <?= $this->selected($settings['pluginLanguageAdmin'], 'yi') ?>><?= esc_html__('Yiddish', 'mmp') ?> (yi)</option>
									</select>
								</div>
							</div>
							<div class="mmp-settings-setting">
								<div class="mmp-settings-desc"><?= esc_html__('Frontend', 'mmp') ?></div>
								<div class="mmp-settings-input">
									<select name="pluginLanguageFrontend">
										<option value="automatic" <?= $this->selected($settings['pluginLanguageFrontend'], 'automatic') ?>><?= esc_html__('Automatic (use WordPress default)', 'mmp') ?></option>
										<option value="ar" <?= $this->selected($settings['pluginLanguageFrontend'], 'ar') ?>><?= esc_html__('Arabic', 'mmp') ?> (ar)</option>
										<option value="af" <?= $this->selected($settings['pluginLanguageFrontend'], 'af') ?>><?= esc_html__('Afrikaans', 'mmp') ?> (af)</option>
										<option value="bn_BD" <?= $this->selected($settings['pluginLanguageFrontend'], 'bn_BD') ?>><?= esc_html__('Bengali', 'mmp') ?> (bn_BD)</option>
										<option value="bs_BA" <?= $this->selected($settings['pluginLanguageFrontend'], 'bs_BA') ?>><?= esc_html__('Bosnian', 'mmp') ?> (bs_BA)</option>
										<option value="bg_BG" <?= $this->selected($settings['pluginLanguageFrontend'], 'bg_BG') ?>><?= esc_html__('Bulgarian', 'mmp') ?> (bg_BG)</option>
										<option value="ca" <?= $this->selected($settings['pluginLanguageFrontend'], 'ca') ?>><?= esc_html__('Catalan', 'mmp') ?> (ca)</option>
										<option value="zh_CN" <?= $this->selected($settings['pluginLanguageFrontend'], 'zh_CN') ?>><?= esc_html__('Chinese', 'mmp') ?> (zh_CN)</option>
										<option value="zh_TW" <?= $this->selected($settings['pluginLanguageFrontend'], 'zh_TW') ?>><?= esc_html__('Chinese', 'mmp') ?> (zh_TW)</option>
										<option value="hr" <?= $this->selected($settings['pluginLanguageFrontend'], 'hr') ?>><?= esc_html__('Croatian', 'mmp') ?> (hr)</option>
										<option value="cs_CZ" <?= $this->selected($settings['pluginLanguageFrontend'], 'cs_CZ') ?>><?= esc_html__('Czech', 'mmp') ?> (cs_CZ)</option>
										<option value="da_DK" <?= $this->selected($settings['pluginLanguageFrontend'], 'da_DK') ?>><?= esc_html__('Danish', 'mmp') ?> (da_DK)</option>
										<option value="nl_NL" <?= $this->selected($settings['pluginLanguageFrontend'], 'nl_NL') ?>><?= esc_html__('Dutch', 'mmp') ?> (nl_NL)</option>
										<option value="en_US" <?= $this->selected($settings['pluginLanguageFrontend'], 'en_US') ?>><?= esc_html__('English', 'mmp') ?> (en_US)</option>
										<option value="fi_FI" <?= $this->selected($settings['pluginLanguageFrontend'], 'fi_FI') ?>><?= esc_html__('Finnish', 'mmp') ?> (fi_FI)</option>
										<option value="fr_FR" <?= $this->selected($settings['pluginLanguageFrontend'], 'fr_FR') ?>><?= esc_html__('French', 'mmp') ?> (fr_FR)</option>
										<option value="gl_ES" <?= $this->selected($settings['pluginLanguageFrontend'], 'gl_ES') ?>><?= esc_html__('Galician', 'mmp') ?> (gl_ES)</option>
										<option value="de_DE" <?= $this->selected($settings['pluginLanguageFrontend'], 'de_DE') ?>><?= esc_html__('German', 'mmp') ?> (de_DE)</option>
										<option value="el" <?= $this->selected($settings['pluginLanguageFrontend'], 'el') ?>><?= esc_html__('Greek', 'mmp') ?> (el)</option>
										<option value="he_IL" <?= $this->selected($settings['pluginLanguageFrontend'], 'he_IL') ?>><?= esc_html__('Hebrew', 'mmp') ?> (he_IL)</option>
										<option value="hi_IN" <?= $this->selected($settings['pluginLanguageFrontend'], 'hi_IN') ?>><?= esc_html__('Hindi', 'mmp') ?> (hi_IN)</option>
										<option value="hu_HU" <?= $this->selected($settings['pluginLanguageFrontend'], 'hu_HU') ?>><?= esc_html__('Hungarian', 'mmp') ?> (hu_HU)</option>
										<option value="id_ID" <?= $this->selected($settings['pluginLanguageFrontend'], 'id_ID') ?>><?= esc_html__('Indonesian', 'mmp') ?> (id_ID)</option>
										<option value="it_IT" <?= $this->selected($settings['pluginLanguageFrontend'], 'it_IT') ?>><?= esc_html__('Italian', 'mmp') ?> (it_IT)</option>
										<option value="ja" <?= $this->selected($settings['pluginLanguageFrontend'], 'ja') ?>><?= esc_html__('Japanese', 'mmp') ?> (ja)</option>
										<option value="ko_KR" <?= $this->selected($settings['pluginLanguageFrontend'], 'ko_KR') ?>><?= esc_html__('Korean', 'mmp') ?> (ko_KR)</option>
										<option value="lv" <?= $this->selected($settings['pluginLanguageFrontend'], 'lv') ?>><?= esc_html__('Latvian', 'mmp') ?> (lv)</option>
										<option value="lt_LT" <?= $this->selected($settings['pluginLanguageFrontend'], 'lt_LT') ?>><?= esc_html__('Lithuanian', 'mmp') ?> (lt_LT)</option>
										<option value="ms_MY" <?= $this->selected($settings['pluginLanguageFrontend'], 'ms_MY') ?>><?= esc_html__('Malay', 'mmp') ?> (ms_MY)</option>
										<option value="nb_NO" <?= $this->selected($settings['pluginLanguageFrontend'], 'nb_NO') ?>><?= esc_html__('Norwegian (Bokmål)', 'mmp') ?> (nb_NO)</option>
										<option value="pl_PL" <?= $this->selected($settings['pluginLanguageFrontend'], 'pl_PL') ?>><?= esc_html__('Polish', 'mmp') ?> (pl_PL)</option>
										<option value="pt_BR" <?= $this->selected($settings['pluginLanguageFrontend'], 'pt_BR') ?>><?= esc_html__('Portuguese', 'mmp') ?> (pt_BR)</option>
										<option value="pt_PT" <?= $this->selected($settings['pluginLanguageFrontend'], 'pt_PT') ?>><?= esc_html__('Portuguese', 'mmp') ?> (pt_PT)</option>
										<option value="ro_RO" <?= $this->selected($settings['pluginLanguageFrontend'], 'ro_RO') ?>><?= esc_html__('Romanian', 'mmp') ?> (ro_RO)</option>
										<option value="ru_RU" <?= $this->selected($settings['pluginLanguageFrontend'], 'ru_RU') ?>><?= esc_html__('Russian', 'mmp') ?> (ru_RU)</option>
										<option value="sk_SK" <?= $this->selected($settings['pluginLanguageFrontend'], 'sk_SK') ?>><?= esc_html__('Slovak', 'mmp') ?> (sk_SK)</option>
										<option value="sl_SI" <?= $this->selected($settings['pluginLanguageFrontend'], 'sl_SI') ?>><?= esc_html__('Slovenian', 'mmp') ?> (sl_SI)</option>
										<option value="sv_SE" <?= $this->selected($settings['pluginLanguageFrontend'], 'sv_SE') ?>><?= esc_html__('Swedish', 'mmp') ?> (sv_SE)</option>
										<option value="es_ES" <?= $this->selected($settings['pluginLanguageFrontend'], 'es_ES') ?>><?= esc_html__('Spanish', 'mmp') ?> (es_ES)</option>
										<option value="es_MX" <?= $this->selected($settings['pluginLanguageFrontend'], 'es_MX') ?>><?= esc_html__('Spanish', 'mmp') ?> (es_MX)</option>
										<option value="th" <?= $this->selected($settings['pluginLanguageFrontend'], 'th') ?>><?= esc_html__('Thai', 'mmp') ?> (th)</option>
										<option value="tr_TR" <?= $this->selected($settings['pluginLanguageFrontend'], 'tr_TR') ?>><?= esc_html__('Turkish', 'mmp') ?> (tr_TR)</option>
										<option value="ug" <?= $this->selected($settings['pluginLanguageFrontend'], 'ug') ?>><?= esc_html__('Uighur', 'mmp') ?> (ug)</option>
										<option value="uk_UK" <?= $this->selected($settings['pluginLanguageFrontend'], 'uk_UK') ?>><?= esc_html__('Ukrainian', 'mmp') ?> (uk_UK)</option>
										<option value="vi" <?= $this->selected($settings['pluginLanguageFrontend'], 'vi') ?>><?= esc_html__('Vietnamese', 'mmp') ?> (vi)</option>
										<option value="yi" <?= $this->selected($settings['pluginLanguageFrontend'], 'yi') ?>><?= esc_html__('Yiddish', 'mmp') ?> (yi)</option>
									</select>
								</div>
							</div>
						</div>
						<div id="misc_backup_restore_reset_tab" class="mmp-settings-tab">
							<h2><?= esc_html__('Backup, restore & reset', 'mmp') ?></h2>
							<p>
								<?= sprintf($l10n->kses__('You can backup, restore and reset the settings on the <a href="%1$s">tools page</a>.', 'mmp'), get_admin_url(null, 'admin.php?page=mapsmarkerpro_tools')) ?>
							</p>
						</div>
					</div>
				</div>
			</form>
			<div id="mmp-custom-layer-modal" class="mmp-hidden">
				<form id="mmp-custom-layer-form" method="POST">
					<input type="hidden" id="customLayerId" name="customLayerId" value="0" />
					<input type="radio" id="customLayerTypeBasemap" name="customLayerType" value="0" checked="checked" /> <?= esc_html__('Basemap', 'mmp') ?> <input type="radio" id="customLayerTypeOverlay" name="customLayerType" value="1" /> <?= esc_html__('Overlay', 'mmp') ?><br />
					<input type="checkbox" id="customLayerWms" name="customLayerWms" /> <?= esc_html__('WMS', 'mmp') ?>?<br />
					<input type="checkbox" id="customLayerTms" name="customLayerTms" /> <?= esc_html__('TMS', 'mmp') ?>?<br />
					<?= esc_html__('Name', 'mmp') ?>: <input type="text" id="customLayerName" name="customLayerName" /><br />
					<?= esc_html__('URL', 'mmp') ?>: <input type="text" id="customLayerUrl" name="customLayerUrl" /><br />
					<input type="checkbox" id="customLayerErrorTiles" name="customLayerErrorTiles" checked="checked" /> <?= esc_html__('Show error tiles', 'mmp') ?>?<br />
					<?= esc_html__('Subdomains', 'mmp') ?>: <input type="text" id="customLayerSubdomains" name="customLayerSubdomains" value="abc" /><br />
					<?= esc_html__('Min zoom', 'mmp') ?>: <input type="text" id="customLayerMinZoom" name="customLayerMinZoom" value="0" /><br />
					<?= esc_html__('Max zoom', 'mmp') ?>: <input type="text" id="customLayerMaxZoom" name="customLayerMaxZoom" value="21" /><br />
					<?= esc_html__('Attribution', 'mmp') ?>: <input type="text" id="customLayerAttribution" name="customLayerAttribution" /><br />
					<?= esc_html__('Opacity', 'mmp') ?>: <input type="text" id="customLayerOpacity" name="customLayerOpacity" value="1" /><br />
					<div id="custom-layer-wms">
						<?= esc_html__('Layers', 'mmp') ?>: <input type="text" id="customLayerLayers" name="customLayerLayers" /><br />
						<?= esc_html__('Styles', 'mmp') ?>: <input type="text" id="customLayerStyles" name="customLayerStyles" /><br />
						<?= esc_html__('Format', 'mmp') ?>: <input type="text" id="customLayerFormat" name="customLayerFormat" value="image/jpeg" /><br />
						<input type="checkbox" id="customLayerTransparent" name="customLayerTransparent" /> <?= esc_html__('Transparent', 'mmp') ?>?<br />
						<?= esc_html__('Version', 'mmp') ?>: <input type="text" id="customLayerVersion" name="customLayerVersion" value="1.1.1" /><br />
						<input type="checkbox" id="customLayerUppercase" name="customLayerUppercase" /> <?= esc_html__('Uppercase', 'mmp') ?>?
					</div>
				</form>
			</div>
		</div>
		<?php
	}
}
