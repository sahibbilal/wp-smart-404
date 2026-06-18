<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WS404_Redirects {

    const OPTION_KEY = 'ws404_redirect_map';

    public function __construct() {
        // Run early to redirect before WordPress serves the 404.
        add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );
    }

    public function maybe_redirect() {
        if ( ! is_404() ) return;

        $map = get_option( self::OPTION_KEY, array() );
        if ( empty( $map ) ) return;

        $current = $_SERVER['REQUEST_URI'] ?? '';

        if ( isset( $map[ $current ] ) ) {
            wp_redirect( $map[ $current ], 301 );
            exit;
        }
    }

    public static function add( $from_url, $to_url ) {
        $map              = get_option( self::OPTION_KEY, array() );
        $map[ $from_url ] = esc_url_raw( $to_url );
        update_option( self::OPTION_KEY, $map );
    }

    public static function remove( $from_url ) {
        $map = get_option( self::OPTION_KEY, array() );
        unset( $map[ $from_url ] );
        update_option( self::OPTION_KEY, $map );
    }

    public static function get_all() {
        return get_option( self::OPTION_KEY, array() );
    }
}
