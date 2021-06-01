<?php
/**
 * La configuration de base de votre installation WordPress.
 *
 * Ce fichier contient les réglages de configuration suivants : réglages MySQL,
 * préfixe de table, clefs secrètes, langue utilisée, et ABSPATH.
 * Vous pouvez en savoir plus à leur sujet en allant sur
 * {@link http://codex.wordpress.org/fr:Modifier_wp-config.php Modifier
 * wp-config.php}. C'est votre hébergeur qui doit vous donner vos
 * codes MySQL.
 *
 * Ce fichier est utilisé par le script de création de wp-config.php pendant
 * le processus d'installation. Vous n'avez pas à utiliser le site web, vous
 * pouvez simplement renommer ce fichier en "wp-config.php" et remplir les
 * valeurs.
 *
 * @package WordPress
 */

// ** Réglages MySQL - Votre hébergeur doit vous fournir ces informations. ** //
/** Nom de la base de données de WordPress. */
define('DB_NAME', 'Base_acep');

/** Utilisateur de la base de données MySQL. */
define('DB_USER', 'manalina');

/** Mot de passe de la base de données MySQL. */
define('DB_PASSWORD', 'acep_123_b');

/** Adresse de l'hébergement MySQL. */
define('DB_HOST', 'hostingmysql230.amen.fr');

/** Jeu de caractères à utiliser par la base de données lors de la création des tables. */
define('DB_CHARSET', 'utf8');

/** Type de collation de la base de données.
  * N'y touchez que si vous savez ce que vous faites.
  */
define('DB_COLLATE', '');

/**#@+
 * Clefs uniques d'authentification et salage.
 *
 * Remplacez les valeurs par défaut par des phrases uniques !
 * Vous pouvez générer des phrases aléatoires en utilisant
 * {@link https://api.wordpress.org/secret-key/1.1/salt/ le service de clefs secrètes de WordPress.org}.
 * Vous pouvez modifier ces phrases à n'importe quel moment, afin d'invalider tous les cookies existants.
 * Cela forcera également tous les utilisateurs à se reconnecter.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'BWYs?!]`b:L:Yi|xnC%-EvSzu.(I=QSeRy}kYoFX-yg]L[}ivaH66rPZ+]nmid-%');
define('SECURE_AUTH_KEY',  'v7|p>};:$yM 2*^Wl3^>8MEOj7#:[-pA~sn`Q+)&]etTbI`6 K/pz;Cn~{5zJv I');
define('LOGGED_IN_KEY',    'W#OuMwc0m7@vZ_sWGHZ@B 6[*<x6s TYV0M9it-gV-gyw,,P+?j<!>m=jIQ e9+q');
define('NONCE_KEY',        '@+/e5fmeP{M-DwdtD4 ID6Y4 u{|QrRCABW^)~]haR$CE;MU?xGJ`^Tp=r$x,5]M');
define('AUTH_SALT',        '_fUaBgW5%*^3+o~uk|~km8}8_~#7NQOE!L7TZPj0<v~T$H+/w?Q)zYqzoL%Vl-6E');
define('SECURE_AUTH_SALT', '$Sqc?kI]3u)iP}7gv)ky-6}p^Ds]N$Ok13)|~QX$Y3]p1|{c@A35r+F#[{BBG#xg');
define('LOGGED_IN_SALT',   '3plQ.kEIR{(SVeh6Dd}[?RR3@~GCGV8fE,gVkj.zu!)d]w43^<Pbh*CnsIT|ZQa%');
define('NONCE_SALT',       '3[s|1&`#AN`[X=<Yu.rS&j=n![l9%~4/v,F[N^s-1X|Bu1gn$axq9aSQl,`|c%5C');
/**#@-*/

/**
 * Préfixe de base de données pour les tables de WordPress.
 *
 * Vous pouvez installer plusieurs WordPress sur une seule base de données
 * si vous leur donnez chacune un préfixe unique.
 * N'utilisez que des chiffres, des lettres non-accentuées, et des caractères soulignés!
 */
$table_prefix  = 'wp2k16_';

/**
 * Pour les développeurs : le mode déboguage de WordPress.
 *
 * En passant la valeur suivante à "true", vous activez l'affichage des
 * notifications d'erreurs pendant vos essais.
 * Il est fortemment recommandé que les développeurs d'extensions et
 * de thèmes se servent de WP_DEBUG dans leur environnement de
 * développement.
 *
 * Pour plus d'information sur les autres constantes qui peuvent être utilisées
 * pour le déboguage, rendez-vous sur le Codex.
 * 
 * @link https://codex.wordpress.org/Debugging_in_WordPress 
 */
define('WP_DEBUG', false);

/* C'est tout, ne touchez pas à ce qui suit ! Bon blogging ! */

/** Chemin absolu vers le dossier de WordPress. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Réglage des variables de WordPress et de ses fichiers inclus. */
require_once(ABSPATH . 'wp-settings.php');