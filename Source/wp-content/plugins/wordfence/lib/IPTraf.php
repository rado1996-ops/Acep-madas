<?php if (!defined('WORDFENCE_VERSION')) { exit; } ?>
<?php if(! wfUtils::isAdmin()){ exit(); } ?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"  dir="ltr" lang="en-US">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<link rel='stylesheet' id='wordfence-main-style-css'  href='<?php echo wfUtils::getBaseURL() . wfUtils::versionedAsset('css/iptraf.css'); ?>?ver=<?php echo WORDFENCE_VERSION; ?>' type='text/css' media='all' />
<body>
<h1>Wordfence: All recent hits for IP address <?php echo wp_kses($IP, array()); if($reverseLookup){ echo '[' . wp_kses($reverseLookup, array()) . ']'; } ?></h1>
<div class="footer">&copy;&nbsp;2011 to <?php echo date('Y'); ?> Wordfence &mdash; Visit <a href="http://wordfence.com/">Wordfence.com</a> for help, security updates and more.</div>
</body>
</html>
