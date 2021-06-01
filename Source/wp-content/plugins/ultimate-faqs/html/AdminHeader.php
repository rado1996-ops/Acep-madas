		<div class="wrap">
		<div class="Header"><h2><?php _e("Ultimate FAQ Options", 'ultimate-faqs') ?></h2></div>

		
		<?php if ($UFAQ_Full_Version != "Yes" or get_option("EWD_UFAQ_Trial_Happening") == "Yes") { ?>
			<?php $display_trial_banner = ( time() < 1575331200 and ( time() > 1574917200 or ( time() > 1574467200 and get_option('EWD_URP_Install_Time') < time() - 7*24*3600) ) ); ?>
			<div class="ewd-ufaq-dashboard-new-upgrade-banner">
				<div class="ewd-ufaq-dashboard-banner-icon"></div>
				<div class="ewd-ufaq-dashboard-banner-buttons">
					<a class="ewd-ufaq-dashboard-new-upgrade-button" href="https://www.etoilewebdesign.com/license-payment/?Selected=UFAQ&Quantity=1" target="_blank">UPGRADE NOW</a>
				</div>
				<div class="ewd-ufaq-dashboard-banner-text <?php echo ( $display_trial_banner ? 'ewd-ufaq-bf-banner' : '' ); ?>">
					<!-- Start Black Friday -->
					<?php if ( $display_trial_banner ) { ?>
					<div class="ewd-ufaq-dashboard-banner-black-friday-text">
						<div class="ewd-ufaq-dashboard-banner-black-friday-title">
							30% OFF PREMIUM FOR BLACK FRIDAY!
						</div>
						<div class="ewd-ufaq-dashboard-banner-black-friday-brief">
							Upgrade now to receive this great discount. No coupon code necessary. November 28th to December 2nd EST.
						</div>
						<div class="ewd-ufaq-dashboard-banner-black-friday-brief-mobile">
							November 28th to December 2nd EST.
						</div>
					</div>
					<?php } ?>
					<!-- End Black Friday -->
					<div class="ewd-ufaq-dashboard-banner-title">
						GET FULL ACCESS WITH OUR PREMIUM VERSION
					</div>
					<div class="ewd-ufaq-dashboard-banner-brief">
						Easily customize, administer and share your FAQs
					</div>
				</div>
			</div>
		<?php } ?>

		<?php EWD_UFAQ_Add_Header_Bar("Yes"); ?>
		