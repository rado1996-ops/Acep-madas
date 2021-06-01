<!doctype html>
<html class="no-js" <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <!--meta http-equiv="X-UA-Compatible" content="IE=Edge"-->
  <?php wp_head() ?>
<style>
	.navbar {
		background: #fff!important;	
		margin-bottom: -73px;
	}
.navbar-light .navbar-nav li .nav-link, .navbar-dark .navbar-nav li .nav-link {
color: #5cbeba;
}
.navbar-light .navbar-nav li .nav-link:hover, .navbar-dark .navbar-nav li .nav-link:hover {
color: #02564a;
}
#floated {
    position: fixed;
     bottom: 72px;
    right: 14px;
    list-style: none;
    z-index: 999;
}
@media screen and (max-width: 425px) {
	#floated {
		right: 23.5px;
	}
}
#floated li {
	margin: 5px;
}
#floated li a {
	text-decoration: none;
	padding: 10px;
    background: #02564A;
    border-radius: 50%;
    height: 60px;
    width: 60px;
    display: block;
    margin: 0;
    position: relative;
    text-align: center;

    box-shadow: 0px 1px 2px 2px #00000024;
}
.theme-background-color {
    background: #02564A!important;

}
.navbar-light .navbar-nav .show > .nav-link, .navbar-light .navbar-nav .active > .nav-link, .navbar-light .navbar-nav .nav-link.show, .navbar-light .navbar-nav .nav-link.active {
	color:#02564a;
}
.page-id-2978 .title-bar {
	background-image: url(/wp-content/uploads/2019/06/62347165_341570253138740_5891601055912820736_n-1.jpg);
}
.page-id-3015  .title-bar {
	background-image: url(/wp-content/uploads/2019/06/RH0_6346-1.png);

}
.title-bar .heading h2 {
	color: #4FC9BF;
}
.title-bar .heading h2:after {
	background-color: #4FC9BF;
}
.page-id-2140 .title-bar, .page-id-3293 .title-bar {
	background-image: url(/wp-content/uploads/2019/06/62189127_359706938016371_4612511004335538176_n-1.jpg);
} 
.page-id-3054 .title-bar {
	background-image: url(/wp-content/uploads/2019/06/62135672_2621660634570687_3125442031942369280_n.jpg);
}
.page-id-3092 .title-bar {
	background-image: url(/wp-content/uploads/2019/06/62352505_414543222469651_32555452671721472_n-1.jpg);
}
.page-id-3304  .title-bar {
  background-image: url(/wp-content/uploads/2019/07/RSE-photo-header-kely.jpg);
}
.number {
	position:relative;
}
.navbar-light .navbar-nav li .nav-link, .navbar-dark .navbar-nav li .nav-link {
  font-size: 12px!important;
}
.number p {
position: absolute;
    top: -1px;
    background: #03a84eba;
    padding: 10px 15px;
    color: #fff;
    transition: all .5s ease;
    left: 0;
    opacity: 0;
    border-radius: 20px;
}
.number:hover p {
	opacity: 1;
	transition: all .5s ease;
	left: -92px;
    
}
#rev_slider_1_1 .tp-revslider-mainul {
border-radius: 0;
}
.navbar svg {
	width: 60%;
}
.mailpoet_paragraph input[type="submit"] {
	background: #02564A;
}
.mailpoet_paragraph input[type="submit"]:before {
	    content: '';
    display: inline;
    background: #dadada;
    padding: 10px;
    border-radius: 50%;
    height: 45px;
    width: 45px;
    position: absolute;
    left: -29px;
    top: 21px;
}
/*
.preloader:after{
	display: none!important;
}
*/
@keyframes lds-ellipsis3 {
  0%, 25% {
    left: 32px;
    -webkit-transform: scale(0);
    transform: scale(0);
  }
  50% {
    left: 32px;
    -webkit-transform: scale(1);
    transform: scale(1);
  }
  75% {
    left: 100px;
  }
  100% {
    left: 168px;
    -webkit-transform: scale(1);
    transform: scale(1);
  }
}
@-webkit-keyframes lds-ellipsis3 {
  0%, 25% {
    left: 32px;
    -webkit-transform: scale(0);
    transform: scale(0);
  }
  50% {
    left: 32px;
    -webkit-transform: scale(1);
    transform: scale(1);
  }
  75% {
    left: 100px;
  }
  100% {
    left: 168px;
    -webkit-transform: scale(1);
    transform: scale(1);
  }
}
@keyframes lds-ellipsis2 {
  0% {
    -webkit-transform: scale(1);
    transform: scale(1);
  }
  25%, 100% {
    -webkit-transform: scale(0);
    transform: scale(0);
  }
}
@-webkit-keyframes lds-ellipsis2 {
  0% {
    -webkit-transform: scale(1);
    transform: scale(1);
  }
  25%, 100% {
    -webkit-transform: scale(0);
    transform: scale(0);
  }
}
@keyframes lds-ellipsis {
  0% {
    left: 32px;
    -webkit-transform: scale(0);
    transform: scale(0);
  }
  25% {
    left: 32px;
    -webkit-transform: scale(1);
    transform: scale(1);
  }
  50% {
    left: 100px;
  }
  75% {
    left: 168px;
    -webkit-transform: scale(1);
    transform: scale(1);
  }
  100% {
    left: 168px;
    -webkit-transform: scale(0);
    transform: scale(0);
  }
}
@-webkit-keyframes lds-ellipsis {
  0% {
    left: 32px;
    -webkit-transform: scale(0);
    transform: scale(0);
  }
  25% {
    left: 32px;
    -webkit-transform: scale(1);
    transform: scale(1);
  }
  50% {
    left: 100px;
  }
  75% {
    left: 168px;
    -webkit-transform: scale(1);
    transform: scale(1);
  }
  100% {
    left: 168px;
    -webkit-transform: scale(0);
    transform: scale(0);
  }
}
.lds-ellipsis {
  position: relative;
}
.lds-ellipsis > div {
  position: absolute;
  -webkit-transform: translate(-50%, -50%);
  transform: translate(-50%, -50%);
  width: 40px;
  height: 40px;
}
.lds-ellipsis div > div {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #f00;
  position: absolute;
  top: 100px;
  left: 32px;
  -webkit-animation: lds-ellipsis 2.2s cubic-bezier(0, 0.5, 0.5, 1) infinite forwards;
  animation: lds-ellipsis 2.2s cubic-bezier(0, 0.5, 0.5, 1) infinite forwards;
}
.lds-ellipsis div:nth-child(1) div {
  -webkit-animation: lds-ellipsis2 2.2s cubic-bezier(0, 0.5, 0.5, 1) infinite forwards;
  animation: lds-ellipsis2 2.2s cubic-bezier(0, 0.5, 0.5, 1) infinite forwards;
  background: #dadada;
}
.lds-ellipsis div:nth-child(2) div {
  -webkit-animation-delay: -1.1s;
  animation-delay: -1.1s;
  background: #dadada;
}
.lds-ellipsis div:nth-child(3) div {
  -webkit-animation-delay: -0.55s;
  animation-delay: -0.55s;
  background: #dadada;
}
.lds-ellipsis div:nth-child(4) div {
  -webkit-animation-delay: 0s;
  animation-delay: 0s;
  background: #dadada;
}
.lds-ellipsis div:nth-child(5) div {
  -webkit-animation: lds-ellipsis3 2.2s cubic-bezier(0, 0.5, 0.5, 1) infinite forwards;
  animation: lds-ellipsis3 2.2s cubic-bezier(0, 0.5, 0.5, 1) infinite forwards;
  background: #dadada;
}
.lds-ellipsis {
  width: 124px !important;
  height: 124px !important;
  -webkit-transform: translate(-62px, -62px) scale(0.62) translate(62px, 62px);
  transform: translate(-62px, -62px) scale(0.62) translate(62px, 62px);
}
.preloader {
	border: 0px solid transparent;
}
.fa-twitter:before {
	content:"\f232"!important;
}
#menu-footer li {
    margin-left: 0px!important;
    padding: 0px 0px!important;
}
.navbar svg {

    width: 62%;

}
.navbar-brand {
width: 252px;
}
.bg-preloader {
  display: none;
}
.no-js img.lazyload {
  display: block;
}

.vc_custom_1566908404950 {
    background-image: none!important;
} 
	.wpb_wrapper .pum-trigger {
		    padding: 10px 7px 9px 8px!important;
	}
</style>
</head>
<body <?php body_class() ?>>
<div class="bg-preloader">
  <div class="mask">
    	<!--span class="preloader"-->
		<div class="lds-css ng-scope">
			<div style="width:100%;height:100%" class="lds-ellipsis">
			<div>
			<div>
			</div>
			</div>
			<div>
			<div>
			</div>
			</div>
			<div>
			<div>
			</div>
			</div>
			<div>
			<div>
			</div>
			</div>
			<div>
			<div>
			</div>
			</div>
			</div>
			</div>
	<!--/span-->
    <?php esc_html__('Loading...', 'represent'); ?>
  </div>	
</div>
<?php get_template_part('parts/menu/nav') ?>