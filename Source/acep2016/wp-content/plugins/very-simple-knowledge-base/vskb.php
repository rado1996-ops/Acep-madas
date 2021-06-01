<?php
/*
 * Plugin Name: Very Simple Knowledge Base
 * Description: This is a very simple plugin to create a knowledgebase. Use a shortcode to display your categories and posts in 3 or 4 columns on a page. For more info please check readme file.
 * Version: 2.8
 * Author: Guido van der Leest
 * Author URI: http://www.guidovanderleest.nl
 * License: GNU General Public License v3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: very-simple-knowledge-base
 * Domain Path: /translation
 */


// load plugin text domain
function vskb_init() { 
	load_plugin_textdomain( 'very-simple-knowledge-base', false, dirname( plugin_basename( __FILE__ ) ) . '/translation' );
}
add_action('plugins_loaded', 'vskb_init');
 

// enqueues plugin scripts
function vskb_scripts() {	
	if(!is_admin()) {
		wp_enqueue_style('vskb_style', plugins_url('/css/vskb-style.css',__FILE__));
	}
}
add_action('wp_enqueue_scripts', 'vskb_scripts');


// include the shortcode files
include 'vskb-three-columns.php';
include 'vskb-four-columns.php';

?>