<?php
namespace MMP;

class Fullscreen {
	/**
	 * Replaces the standard title with the map name
	 *
	 * @since 4.0
	 *
	 * @param string $title The current title
	 */
	public function filter_title($title) {
		global $wp;
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		if (!isset($wp->query_vars['map'])) {
			return $title;
		}

		$map = $db->get_map($wp->query_vars['map']);
		if (!$map) {
			return $title;
		}

		return esc_html($map->name);
	}

	/**
	 * Shows the fullscreen map
	 *
	 * @since 4.0
	 */
	public function show() {
		global $wp;

		add_filter('pre_get_document_title', array($this, 'filter_title'));
		add_filter('wpseo_title', array($this, 'filter_title'));

		$map = (isset($wp->query_vars['map'])) ? absint($wp->query_vars['map']) : 0;

		?>
		<html>
		<head>
			<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
			<?php wp_head(); ?>
		</head>
		<body>
			<?= do_shortcode('[' . Maps_Marker_Pro::$settings['shortcode'] . ' map="' . $map . '" fullscreen="true"]'); ?>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
	}
}
