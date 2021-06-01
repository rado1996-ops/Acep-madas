<?php
/** Enable W3 Total Cache */
define('WP_CACHE', true); // Added by W3 Total Cache

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'acep09');

/** MySQL database username */
define('DB_USER', '912764_23ae8b');

/** MySQL database password */
define('DB_PASSWORD', '@cEp08_@9!');

/** MySQL hostname */
define('DB_HOST', 'hostingmysql230.amen.fr');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         's*zO0[XvMxk|b.k*-qU_q>a?^G*DJ3v2=M?1]*3GoMPi>y`[WyQg^q#9Ma?}c(>u');
define('SECURE_AUTH_KEY',  '/e0zly?`UXi%_z)gQ1Ny0FfzJrbp!( WZM4u+5A%DZ![%dNq`%Ll#oJlA=gdhsCB');
define('LOGGED_IN_KEY',    'u`5fMBNFt),%-$4./l}6Dm<WLi=NqO( ]wNVyQ*d}T5_c&_lS~g^RM({>wK6%1Ex');
define('NONCE_KEY',        'LXg#P?Z(>5c$ms5u~Th}JK$@o_p@x)T@MNw_`0WY(02USXkgo{P.Dy2Q~p[&n@|)');
define('AUTH_SALT',        '9YZK6w/qIW;(vM@GvXV%.v>Tg^z-%k]iV6QK2>`+d+Y@EOpPxL%GDE7L_OB|=n<E');
define('SECURE_AUTH_SALT', '>4^] J<ywWU,O]!l*n[oQMY7daE%o/,7w!:Le&t#NbMHEWH&B_pKWB|,|9<cIK3X');
define('LOGGED_IN_SALT',   'E`z1_M}Fk4G%S h#t]t8{u@`YThD.uB7ovoq6{8Z,0n;]T)jH^VV)a08&oVbfR_P');
define('NONCE_SALT',       '7x=rT,lhr66)m{y5=Y8zE_EFXj$^),&:yYan,=DcCL`7*{I/1>&FK]1!^/q.4Cnz');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
