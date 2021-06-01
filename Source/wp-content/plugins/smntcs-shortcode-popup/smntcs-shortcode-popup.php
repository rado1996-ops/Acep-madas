<?php
/**
 * Plugin Name: SMNTCS Shortcode Popup
 * Plugin URI: https://github.com/nielslange/smntcs-shortcode-popup
 * Description: Easily open any shortcode content in a pop-up
 * Author: Niels Lange <info@nielslange.de>
 * Author URI: https://nielslange.com
 * Text Domain: smntcs-shortcode-popup
 * Domain Path: /languages/
 * Version: 1.5
 * Requires at least: 3.4
 * Tested up to: 5.2
 * Requires PHP: 5.6
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @category   Plugin
 * @package    WordPress
 * @subpackage SMNTCS Shortcode Popup
 * @author     Niels Lange <info@nielslange.de>
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 */

// Activate plugin
register_activation_hook(__FILE__, 'smntcs_shortcode_popup_activate_plugin');
function smntcs_shortcode_popup_activate_plugin() {
	add_option('smntcs_shortcode_popup_title', '');
	add_option('smntcs_shortcode_popup_shortcode', '');
}

// Deactivate plugin
register_deactivation_hook(__FILE__, 'smntcs_shortcode_popup_deactivate_plugin');
function smntcs_shortcode_popup_deactivate_plugin() {
	delete_option('smntcs_shortcode_popup_title');
	delete_option('smntcs_shortcode_popup_button');
	delete_option('smntcs_shortcode_popup_shortcode');
}

// Initialize plugin
function smntcs_shortcode_popup_admin_init() {
	register_setting('smntcs_shortcode_popup', 'smntcs_shortcode_popup_title');
	register_setting('smntcs_shortcode_popup', 'smntcs_shortcode_popup_button');
	register_setting('smntcs_shortcode_popup', 'smntcs_shortcode_popup_shortcode');
}

// Add menu item in backend
function smntcs_shortcode_popup_admin_menu() {
	add_options_page('Shortcode Popup', 'Shortcode Popup', 'manage_options', 'shortcode-popup', 'smntcs_shortcode_popup_options_page');
}

// Add options page in backend
function smntcs_shortcode_popup_options_page() {
	include(WP_PLUGIN_DIR . '/smntcs-shortcode-popup/options.php');
}

// Initialize show plugin in backend
if (is_admin()) {
	add_action('admin_init', 'smntcs_shortcode_popup_admin_init');
	add_action('admin_menu', 'smntcs_shortcode_popup_admin_menu');
}

// Load translation(s)
add_action('plugins_loaded', 'smntcs_shortcode_popup_load_textdomain');
function smntcs_shortcode_popup_load_textdomain() {
	load_plugin_textdomain( 'smntcs-shortcode-popup', false, false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
}

// Add settings link on plugin page
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'smntcs_shortcode_popup_plugin_settings_link' );
function smntcs_shortcode_popup_plugin_settings_link($links) {
	$settings_link = '<a href="options-general.php?page=shortcode-popup">' . __('Settings', 'smntcs-shortcode-popup') . '</a>';
	array_unshift($links, $settings_link);
	return $links;
}

// Define shortcode
add_shortcode('smntcs-modal', 'smntcs_shortcode_popup_define_shortcode');
function smntcs_shortcode_popup_define_shortcode() {
	?>
    <p><button type="button" class="btn btn-primary" data-toggle="modal" data-target="#myModal"><?php echo get_option('smntcs_shortcode_popup_button'); ?></button></p>
    <?php
}

// Apply custom CSS to frontend
add_action('wp_head', 'smntcs_shortcode_popup_css_frontend');
function smntcs_shortcode_popup_css_frontend() {
	?>
<style type="text/css" media="screen">
	.modal-open { overflow: visible; }
	.modal-open, .modal-open .navbar-fixed-top, .modal-open .navbar-fixed-bottom { padding-right:0px!important; }
</style>
    <?php
}

// Apply custom CSS to backend
add_action('admin_head', 'smntcs_shortcode_popup_css_backend');
function smntcs_shortcode_popup_css_backend() {
	?>
<style type="text/css" media="screen">
    span.shortcode { display: block; margin: 2px 0;}
    span.shortcode > input { background: inherit; color: inherit; font-size: 12px; border: none; box-shadow: none; padding: 4px 8px; margin: 0; }
</style>
    <?php
}

// Show site verification code in frontend
add_action('wp_footer', 'smntcs_shortcode_popup');
function smntcs_shortcode_popup() {
	wp_register_style( 'bootstrap-css', plugins_url('assets/bootstrap-3.3.6/css/bootstrap.min.css', __FILE__), '' , '3.3.6', 'all' );
	wp_enqueue_style( 'bootstrap-css' );
	wp_register_script( 'bootstrap-js', plugins_url('assets/bootstrap-3.3.6/js/bootstrap.min.js', __FILE__), array( 'jquery' ), '3.3.6', true );
	wp_enqueue_script( 'bootstrap-js' );
	?>
    <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="myModalLabel"><?php echo get_option('smntcs_shortcode_popup_title'); ?></h4>
                </div>
                <div class="modal-body">
                    <?php echo do_shortcode(get_option('smntcs_shortcode_popup_shortcode')); ?>
                </div>
                <div class="modal-footer">
			        <button type="button" class="btn btn-default" data-dismiss="modal"><?php _e('Close', 'smntcs-shortcode-popup'); ?></button>
	           </div>
            </div>
        </div>
    </div>
    <?php
}
