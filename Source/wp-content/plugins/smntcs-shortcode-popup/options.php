<div class="wrap">

    <h2>Shortcode Popup</h2>

    <form method="post" action="options.php">

        <?php wp_nonce_field('update-options'); ?>
        <?php settings_fields('smntcs_shortcode_popup'); ?>

        <?php if (get_option('smntcs_shortcode_popup_title') && get_option('smntcs_shortcode_popup_shortcode')) : ?>
        <div class="inside">
	        <p class="description">
	            <label for="smntcssp-shortcode">Copy this shortcode and paste it into your post, page, or text widget content:</label>
	            <span class="shortcode wp-ui-highlight"><input type="text" id="smntcssp-shortcode" onfocus="this.select();" readonly="readonly" class="large-text code" value="[smntcs-shortcode-popup]"></span>
	        </p>
        </div>
        <?php endif; ?>

        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e('Title:', 'smntcs-shortcode-popup') ?></th>
                <td><input type="text" name="smntcs_shortcode_popup_title" value="<?php echo get_option('smntcs_shortcode_popup_title'); ?>" size="50" /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php _e('Button:', 'smntcs-shortcode-popup') ?></th>
                <td><input type="text" name="smntcs_shortcode_popup_button" value="<?php echo get_option('smntcs_shortcode_popup_button'); ?>" size="50" /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php _e('Shortcode:', 'smntcs-shortcode-popup') ?></th>
                <td><input type="text" name="smntcs_shortcode_popup_shortcode" value="<?php echo esc_textarea(get_option('smntcs_shortcode_popup_shortcode')); ?>" size="50" /></td>
            </tr>
        </table>

        <p class="submit">
            <input type="hidden" name="action" value="update" />
            <input type="submit" class="button-primary" value="<?php _e('Save Changes', 'smntcs-shortcode-popup'); ?>" />
        </p>

    </form>

</div>
