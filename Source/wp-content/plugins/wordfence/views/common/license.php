<?php
if (!defined('WORDFENCE_VERSION')) { exit; }

/**
 * Presents an install license prompt.
 *
 * Expects $state to be defined when applicable.
 *
 * @var string $error The error message. Optional.
 * @var string $state The state of the installation. 'prompt' is the installation prompt. 'installed' is the completion view. 'bad' is if an error is encountered.
 */

switch ($state) {
	case 'installed':
		$title = __('Wordfence License Installation Successful', 'wordfence');
		break;
	case 'bad':
		$title = __('Wordfence License Installation Failed', 'wordfence');
		break;
	case 'prompt':
		$title = __('Install Wordfence License', 'wordfence');
		break;
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title><?php echo $title; ?></title>
	<style>
		html {
			font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
			font-size: 14px;
			line-height: 1.42857143;
			color: #333;
			background-color: #fff;
		}
		
		h1, h2, h3, h4, h45, h6 {
			font-weight: 500;
			line-height: 1.1;
		}
		
		h1 { font-size: 36px; }
		h2 { font-size: 30px; }
		h3 { font-size: 24px; }
		h4 { font-size: 18px; }
		h5 { font-size: 14px; }
		h6 { font-size: 12px; }
		
		h1, h2, h3 {
			margin-top: 20px;
			margin-bottom: 10px;
		}
		h4, h5, h6 {
			margin-top: 10px;
			margin-bottom: 10px;
		}
		
		.btn {
			background-color: #00709e;
			border: 1px solid #09486C;
			border-radius: 4px;
			box-sizing: border-box;
			color: #ffffff;
			cursor: pointer;
			display: inline-block;
			font-size: 14px;
			font-weight: normal;
			letter-spacing: normal;
			line-height: 20px;
			margin: 5px 0px;
			padding: 12px 6px;
			text-align: center;
			text-decoration: none;
			vertical-align: middle;
			white-space: nowrap;
			word-spacing: 0px;
		}
		
		hr {
			margin-top: 20px;
			margin-bottom: 20px;
			border: 0;
			border-top: 1px solid #eee
		}
		
		.btn.disabled, .btn[disabled] {
			background-color: #9f9fa0;
			border: 1px solid #7E7E7F;
			cursor: not-allowed;
			filter: alpha(opacity=65);
			-webkit-box-shadow: none;
			box-shadow: none;
			opacity: .65;
			pointer-events: none;
		}
	</style>
</head>
<body>

<h3><?php echo $title; ?></h3>

<?php if ($state == 'installed'): ?>
	<p><?php _e('The Wordfence license provided has been installed.', 'wordfence'); ?></p>
	<p><?php printf(__('Return to the <a href="%s">Wordfence Admin Page</a>', 'wordfence'), network_admin_url('admin.php?page=Wordfence')); ?></p>
<?php elseif ($state == 'bad'): ?>
	<p><?php _e('The Wordfence license could not be installed.', 'wordfence'); echo ' ' . esc_html($error); ?></p>
<?php elseif ($state == 'prompt'): ?>
	<p><?php _e('Please enter the license to install.', 'wordfence'); ?></p>
	<form method="POST" action="<?php echo esc_attr(wfUtils::getSiteBaseURL() . '?_wfsf=installLicense'); ?>">
		<p><input type="text" name="license"></p>
		<?php wp_nonce_field('wf-form', 'nonce'); ?>
		<p><input type="submit" class="btn" value="Install"></p>
	</form>
<?php endif; ?>

<p style="color: #999999;margin-top: 2rem;"><em><?php _e('Generated by Wordfence at ', 'wordfence'); ?><?php echo gmdate('D, j M Y G:i:s T', wfWAFUtils::normalizedTime()); ?>.<br><?php _e('Your computer\'s time: ', 'wordfence'); ?><script type="application/javascript">document.write(new Date().toUTCString());</script>.</em></p>

</body>
</html>
