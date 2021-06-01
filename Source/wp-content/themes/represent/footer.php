<footer>
  <?php if (is_active_sidebar('footer_1')) { ?>
    <div class="footer-content">
      <div class="container">
        <div class="row">
          <div class="col-sm-3">
            <?php dynamic_sidebar('footer_1') ?>
          </div>
          <div class="col-sm-3">
            <?php dynamic_sidebar('footer_2') ?>
          </div>
          <div class="col-sm-3">
            <?php dynamic_sidebar('footer_3') ?>
          </div>
          <div class="col-sm-3">
            <?php dynamic_sidebar('footer_4') ?>
          </div>
        </div>
      </div>
    </div>
  <?php } ?>
  <div class="copyright">
    <div class="container">
      <div class="row">
        <div class="col-sm-6">
          <?php
          global $represent;
          ?>
          <p class="copyright-text">
            <?php
            if(isset($represent["opt-copyright"]) ) {
              echo esc_html($represent["opt-copyright"]);
            } else {
              echo esc_html__('Copyright PixelWay 2019', 'represent');
            }

            ?>
          </p>

        </div>
        <div class="col-sm-6 text-right">
          <?php echo represent_socialMedias() ?>
        </div>
      </div>
    </div>
  </div>
</footer>
<?php
function isMobileDevice() {
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}

?>
<ul id="floated">
	<li>
	<?php
	if(isMobileDevice()){
	?>
	<a href="tel:020 22 393 44">
	<?php
	} else {
	?>
	<a href="#" class="number">
	<?php
	}	
	?>
	<svg id="Layer_1" data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 300"><defs><style>.cls-4{fill:#fff;}</style></defs><title>tel1</title><path class="cls-4" d="M45.08,192.4s3.09,30.91,26.15,35.67,74.12,12.23,139.54-42.83c23.58-25.83,68.25-76.47,27.91-114.36-9.39-11.49-54.07,24.11-38.25,52.93,6.17,13.68-11.32,70-81.58,62.25-10.66-9.82-18.57-24.23-50.7-12.26C53.83,180.51,47.11,177.89,45.08,192.4Z"/></svg>
		<p>
			020 22 393 44
		</p>
	</a>	
	</li>
	<li>
	<a href="https://www.facebook.com/AcepMadagascarSa/" target="_blank">
		<svg id="Layer_2" data-name="Layer 2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 300"><defs><style>.cls-4{fill:#fff;}</style></defs><title>facebook</title><path class="cls-4" d="M165,88c2-2.37,6-4.07,11.77-4.07a67.92,67.92,0,0,1,17.78,2.49l5.07-27.3c-10.41-3.64-21.15-4.67-32.25-4.67s-19.84,1.7-26.87,5.76C133.38,64.1,128.68,68.52,126,75c-2.7,5.77-4,15.41-4,27.55v10.74H100.43v31.18H122v98.1a126.83,126.83,0,0,0,28.21,3,62.34,62.34,0,0,0,11.07-.66V144.42H189.8V113.24H161.25V103.17C161.25,95.46,162.29,90.73,165,88Z"/></svg>
	</a>
	</li>
	<li>
	<a href="index.php/agences-bureaux/" target="_blank">
		<svg id="Layer_3" data-name="Layer 3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 300"><defs><style>.cls-4{fill:#fff;}</style></defs><title>localisation</title><path class="cls-4" d="M151.46,52.28a66.84,66.84,0,0,0-66.84,66.83C84.62,156,114.55,245,151.46,245s66.83-89,66.83-125.89A66.83,66.83,0,0,0,151.46,52.28Zm0,116.48a48.25,48.25,0,1,1,48.24-48.25A48.25,48.25,0,0,1,151.46,168.76Z"/></svg>
	</a>
	</li>
</ul>
<?php wp_footer() ?>
</body>
</html>
