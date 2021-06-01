<?php
/*
Plugin Name: Admin Update Notifier
Plugin URI:  http://labs.alfred.mg
Description: Give admin notification when an update is available while hiding it from others.
Version:     0.0.1
Author:      Manalina Rajaona
Author URI:  http://labs.alfred.mg
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

function hide_update_notice_to_all_but_admin_users()
{
    if (!current_user_can('update_core')) {
        remove_action( 'admin_notices', 'update_nag', 3 );
    }
}
add_action( 'admin_head', 'hide_update_notice_to_all_but_admin_users', 1 );

?>