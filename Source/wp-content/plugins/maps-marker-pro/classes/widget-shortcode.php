<?php
namespace MMP;

class Widget_Shortcode extends \WP_Widget {

	/**
	 * Sets up the class
	 *
	 * @since 4.0
	 */
	public function __construct() {
		if (Maps_Marker_Pro::$settings['whitelabelBackend']) {
			$prefix = esc_html__('Maps', 'mmp');
		} else {
			$prefix = 'Maps Marker Pro';
		}

		parent::__construct(
			'mmp_shortcode',
			$prefix . ' - ' . esc_html__('Shortcode'),
			array(
				'description' => esc_html__('Adds a map shortcode.', 'mmp')
			)
		);
	}

	/**
	 * Registers the hooks
	 *
	 * @since 4.0
	 */
	public function init() {
		add_action('widgets_init', function() {
			register_widget('MMP\Widget_Shortcode');
		});
	}

	/**
	 * Displays the widget on the frontend
	 *
	 * @since 4.0
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args The widget arguments
	 * @param array $instance The saved values
	 */
	public function widget($args, $instance) {
		$map_id = (isset($instance['map'])) ? $instance['map'] : 0;

		if (!$map_id) {
			return;
		}

		echo do_shortcode('[' . Maps_Marker_Pro::$settings['shortcode'] . ' map="' . $map_id . '"]');
	}

	/**
	 * Displays the widget form on the backend
	 *
	 * @since 4.0
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance The previously saved values
	 */
	public function form($instance) {
		$db = Maps_Marker_Pro::get_instance('MMP\DB');

		$map_id = (isset($instance['map'])) ? $instance['map'] : 0;

		$maps = $db->get_all_maps();

		?>
		<p>
			<select class="widefat" id="<?= $this->get_field_id('map') ?>" name="<?= $this->get_field_name('map') ?>">
				<option value="0" <?php selected($map_id, '0') ?>><?= esc_html__('Please select the map you want to display', 'mmp') ?></option>
				<?php foreach ($maps as $map): ?>
					<option value="<?= $map->id ?>" <?php selected($map_id, $map->id) ?>>
						[<?= $map->id ?>] <?= ($map->name) ? esc_html($map->name) : esc_html__('(no name)', 'mmp') ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Proccesses the saving of the widget values
	 *
	 * @since 4.0
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance The values to be saved
	 * @param array $old_instance The previously saved values
	 */
	public function update($new_instance, $old_instance) {
		$instance['map'] = (isset($new_instance['map'])) ? absint($new_instance['map']) : 0;

		return $instance;
	}

}
