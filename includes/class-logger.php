<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WS404_Logger {

    public function __construct() {
        add_action( 'template_redirect', array( $this, 'maybe_log' ), 5 );
    }

    public function maybe_log() {
        if ( ! is_404() ) return;

        // Skip admin, REST, cron requests.
        if ( is_admin() || defined( 'REST_REQUEST' ) || defined( 'DOING_CRON' ) ) return;

        WS404_Database::create_tables();

        $url      = esc_url_raw( $_SERVER['REQUEST_URI'] ?? '' );
        $referrer = esc_url_raw( $_SERVER['HTTP_REFERER'] ?? '' );

        // Skip common noise — favicons, robots, sitemaps, etc.
        $skip = array( 'favicon', 'robots.txt', 'sitemap', '.php', 'wp-login', 'xmlrpc', 'apple-touch' );
        foreach ( $skip as $pattern ) {
            if ( stripos( $url, $pattern ) !== false ) return;
        }

        WS404_Database::log_hit( $url, $referrer );
    }
}
