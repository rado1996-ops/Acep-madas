<?php
namespace MMP;

class Upload {
	/**
	 * Registers the hooks
	 *
	 * @since 4.0
	 */
	public function init() {
		add_action('wp_ajax_mmp_icon_upload', array($this, 'icon_upload'));
	}

	/**
	 * Uploads an icon to the icons directory
	 *
	 * @since 4.0
	 */
	public function icon_upload() {
		if (!isset($_POST['nonce']) || wp_verify_nonce($_POST['nonce'], 'mmp-icon-upload') === false) {
			wp_send_json(array(
				'success' => false,
				'response' => esc_html__('Security check failed', 'mmp')
			));
		}

		if (!isset($_FILES['upload'])) {
			wp_send_json(array(
				'success' => false,
				'response' => esc_html__('File missing', 'mmp')
			));
		}

		add_filter('upload_dir', function($upload) {
			$upload['subdir'] = '';
			$upload['path'] = untrailingslashit(Maps_Marker_Pro::$icons_dir);
			$upload['url'] = untrailingslashit(Maps_Marker_Pro::$icons_url);

			return $upload;
		});

		$upload = wp_handle_upload($_FILES['upload'], array(
			'test_form' => false,
			'mimes' => array(
				'png' => 'image/png',
				'gif' => 'image/gif',
				'jpg' => 'image/jpeg'
			)
		));
		$upload['name'] = basename($upload['file']);

		wp_send_json(array(
			'success' => true,
			'response' => $upload
		));
	}

	/**
	 * Determines the maximum permitted file size for uploads
	 *
	 * @since 4.0
	 */
	public function get_max_upload_size() {
		$post = $this->parse_size(ini_get('post_max_size'));
		$upload = $this->parse_size(ini_get('upload_max_filesize'));
		$memory = $this->parse_size(ini_get('memory_limit'));
		$max = min($post, $upload, $memory);

		return $max;
	}

	/**
	 * Parses a size string (e.g. 8M) into bytes
	 *
	 * @since 4.0
	 *
	 * @param string $size The size string to parse
	 */
	public function parse_size($size) {
		if (intval($size) <= 0 ) {
			return 0;
		}

		$unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
		$size = preg_replace('/[^0-9\.]/', '', $size);
		if ($unit) {
			$size = $size * pow(1024, stripos('bkmgtpezy', $unit[0]));
		}

		return round($size);
	}

	/**
	 * Returns a list of available marker icons
	 *
	 * @since 4.0
	 */
	public function get_icons() {
		$allowed = array('png', 'gif', 'jpg', 'jpeg');
		$icons = array();
		if (($dir = @opendir(Maps_Marker_Pro::$icons_dir)) !== false) {
			while (($file = readdir($dir)) !== false) {
				$info = pathinfo($file);
				$ext = strtolower($info['extension']);
				if (!is_dir($dir . $file) && in_array($ext, $allowed)) {
					$icons[] = $file;
				}
			}
			closedir($dir);
			sort($icons);
		}

		return $icons;
	}
}
