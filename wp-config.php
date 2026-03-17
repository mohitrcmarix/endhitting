<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'test1' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'Admin@123#@!' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );


define( 'FS_METHOD', 'direct' );
		
/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'SQ=d%;5tTUX3#(UvFKy1Y><fHQ=,I;J;9S>|.[d n`H,PH!`0!0^vL^E_<es yvG' );
define( 'SECURE_AUTH_KEY',  '%wo;V27K8{5[4N1y+Tx97rPWSF-$h[e]A9s&q|,9GrX[;1(j-6;{R3*R60X8Gj6R' );
define( 'LOGGED_IN_KEY',    'GTW<w9b,8Vq)`=ogmtBT.Kh2xcIuqF&zQNR*yRG14)VU;[]4W>g85bto5Q@3XV)8' );
define( 'NONCE_KEY',        '@$u-EgAMWe{Bl ~A}McYEy%lf]c;!vO5 *OEFr?Ch56#jpR$]k/V#w3YAY)`mD@+' );
define( 'AUTH_SALT',        'hX[4Sip825Um98$mc<;xl@G6G4CXN`z/&%]xKTzWZn{B0%WOGc`T_-BI|Bcjn_gr' );
define( 'SECURE_AUTH_SALT', 'Xq7o7<y+@<@@1~IpSE+M=nlCZhV>+Tq_M5SaeawQ5PWj<UIk{F4o96UGf0,7[a+G' );
define( 'LOGGED_IN_SALT',   'iRdQq?pDRy&q>|9v?ZpbK?sh:UMBLCn@s).!cxoey*;0G1pN>t>Sz;oA&pEf5^4k' );
define( 'NONCE_SALT',       '%U;KBKNJQtx1&{g>e*n~E .wIe:>0NRL!$7,,lHy#Et/;2hykXoXyf BG.InVkkN' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
