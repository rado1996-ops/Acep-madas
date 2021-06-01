<?php
namespace MMP;

class Download {
	/**
	 * Downloads the GPX file attached to a map
	 *
	 * @since 4.0
	 */
	public function download_gpx() {
		if (!isset($_GET['url'])) {
			die(esc_html__('Error', 'mmp') . ': ' . esc_html__('URL missing', 'mmp'));
		}

		$id = attachment_url_to_postid($_GET['url']);
		if ($id === 0) {
			$file = wp_remote_get($_GET['url']);
			if (is_wp_error($file) || $file['response']['code'] != 200) {
				die(esc_html__('Error', 'mmp') . ': ' . esc_html__('File not found', 'mmp'));
			}
			$content = $file['body'];
			$filename = basename($_GET['url']);
			$filesize = $file['headers']['content-length'];
		} else {
			$file = get_attached_file($id);
			if (!file_exists($file)) {
				die(esc_html__('Error', 'mmp') . ': ' . esc_html__('File not found', 'mmp'));
			}
			$content = file_get_contents($file);
			$filename = basename($file);
			$filesize = filesize($file);
		}

		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Type: application/gpx+xml');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . $filesize);

		echo $content;
	}

	/**
	 * Downloads a file stored in the temp directory
	 *
	 * @since 4.0
	 */
	public function download_file() {
		if (!isset($_GET['filename'])) {
			die(esc_html__('Error', 'mmp') . ': ' . esc_html__('Filename missing', 'mmp'));
		}

		$filename = basename($_GET['filename']);
		$file = Maps_Marker_Pro::$temp_dir . $filename;
		if (!file_exists($file)) {
			die(esc_html__('Error', 'mmp') . ': ' . esc_html__('File not found', 'mmp'));
		}

		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($file));

		readfile($file);
	}
}
