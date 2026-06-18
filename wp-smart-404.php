<?php
/**
 * Plugin Name: WP Smart 404
 * Plugin URI:  https://bilalmahmood.dev
 * Description: Logs every 404 on your site. Claude AI finds the closest matching page and suggests a redirect — one click to save it.
 * Version:     1.0.0
 * Author:      Bilal Mahmood
 * Author URI:  https://bilalmahmood.dev
 * License:     GPL-2.0+
 * Text Domain: wp-smart-404
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WS404_VERSION', '1.0.0' );
define( 'WS404_PATH',    plugin_dir_path( __FILE__ ) );
define( 'WS404_URL',     plugin_dir_url( __FILE__ ) );

require_once WS404_PATH . 'includes/class-database.php';
require_once WS404_PATH . 'includes/class-logger.php';
require_once WS404_PATH . 'includes/class-matcher.php';
require_once WS404_PATH . 'includes/class-redirects.php';
require_once WS404_PATH . 'includes/class-admin.php';

register_activation_hook( __FILE__, array( 'WS404_Database', 'create_tables' ) );

add_action( 'plugins_loaded', 'ws404_init' );

function ws404_init() {
    new WS404_Logger();
    new WS404_Redirects();
    new WS404_Admin();
}
