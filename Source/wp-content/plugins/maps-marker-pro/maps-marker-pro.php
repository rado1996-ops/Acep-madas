<?php
/*
+----+-----+-----+-----+-----+----+-----+-----+-----+-----+-----+-----+
|          . _..::__:  ,-"-"._       |7       ,     _,.__             |
|  _.___ _ _<_>`!(._`.`-.    /        _._     `_ ,_/  '  '-._.---.-.__|
|.{     " " `-==,',._\{  \  / {)     / _ ">_,-' `                mt-2_|
+ \_.:--.       `._ )`^-. "'      , [_/(                       __,/-' +
|'"'     \         "    _L       oD_,--'                )     /. (|   |
|         |           ,'         _)_.\\._<> 6              _,' /  '   |
|         `.         /          [_/_'` `"(                <'}  )      |
+          \\    .-. )          /   `-'"..' `:._          _)  '       +
|   `        \  (  `(          /         `:\  > \  ,-^.  /' '         |
|             `._,   ""        |           \`'   \|   ?_)  {\         |
|                `=.---.       `._._       ,'     "`  |' ,- '.        |
+                  |    `-._        |     /          `:`<_|h--._      +
|                  (        >       .     | ,          `=.__.`-'\     |
|                   `.     /        |     |{|              ,-.,\     .|
|                    |   ,'          \   / `'            ,"     \     |
+                    |  /             |_'                |  __  /     +
|                    | |                                 '-'  `-'   \.|
|                    |/                Maps Marker Pro              / |
|                    \.    The most comprehensive & user-friendly   ' |
+                              mapping solution for WordPress         +
|                     ,/           ______._.--._ _..---.---------._   |
|    ,-----"-..?----_/ )      _,-'"             "                  (  |
|.._(                  `-----'                                      `-|
+----+-----+-----+-----+-----+----+-----+-----+-----+-----+-----+-----+
ASCII Map (C) 1998 Matthew Thomas (freely usable as long as this line is included)

Plugin Name: Maps Marker Pro &reg;
Plugin URI: https://www.mapsmarker.com
Description: The most comprehensive & user-friendly mapping solution for WordPress

Author: MapsMarker.com e.U.
Author URI: https://www.mapsmarker.com

Version: 4.5
Requires at least: 4.5
Tested up to: 5.2.2

Text Domain: mmp
Domain Path: /languages

License: All rights reserved
License URI: https://www.mapsmarker.com/tos/
Privacy Policy: https://www.mapsmarker.com/privacy/

Copyright 2011-2019 - MapsMarker.com e.U., MapsMarker &reg;
*/

if (!defined('ABSPATH')) {
	die;
}

spl_autoload_register(function($class) {
	static $map;
	if (!$map) {
		$map = include 'classmap.php';
	}
	if (!isset($map[$class])) {
		return false;
	}
	require_once $map[$class];
});

(new MMP\Maps_Marker_Pro(__FILE__))->init();
