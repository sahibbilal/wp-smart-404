<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WS404_Database {

    public static function create_tables() {
        global $wpdb;
        $table   = $wpdb->prefix . 'smart404_logs';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            url varchar(500) NOT NULL,
            referrer varchar(500) NOT NULL DEFAULT '',
            hits int(11) NOT NULL DEFAULT 1,
            last_seen datetime NOT NULL,
            suggested_url varchar(500) DEFAULT NULL,
            suggested_title varchar(255) DEFAULT NULL,
            confidence varchar(20) DEFAULT NULL,
            redirect_saved tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY url_index (url(191))
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'smart404_logs';
    }

    public static function log_hit( $url, $referrer ) {
        global $wpdb;
        $table = self::table();
        $now   = current_time( 'mysql' );

        $existing = $wpdb->get_row(
            $wpdb->prepare( "SELECT id, hits FROM {$table} WHERE url = %s LIMIT 1", $url )
        );

        if ( $existing ) {
            $wpdb->update(
                $table,
                array( 'hits' => $existing->hits + 1, 'last_seen' => $now ),
                array( 'id' => $existing->id ),
                array( '%d', '%s' ),
                array( '%d' )
            );
        } else {
            $wpdb->insert(
                $table,
                array(
                    'url'        => $url,
                    'referrer'   => $referrer,
                    'hits'       => 1,
                    'last_seen'  => $now,
                    'created_at' => $now,
                ),
                array( '%s', '%s', '%d', '%s', '%s' )
            );
        }
    }

    public static function get_logs( $per_page = 20, $offset = 0, $filter = 'all' ) {
        global $wpdb;
        $table = self::table();

        $where = '';
        if ( $filter === 'unmatched' ) {
            $where = 'WHERE suggested_url IS NULL AND redirect_saved = 0';
        } elseif ( $filter === 'matched' ) {
            $where = 'WHERE suggested_url IS NOT NULL AND redirect_saved = 0';
        } elseif ( $filter === 'redirected' ) {
            $where = 'WHERE redirect_saved = 1';
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} {$where} ORDER BY hits DESC LIMIT %d OFFSET %d",
                $per_page, $offset
            )
        );
    }

    public static function count_logs( $filter = 'all' ) {
        global $wpdb;
        $table = self::table();

        $where = '';
        if ( $filter === 'unmatched' ) {
            $where = 'WHERE suggested_url IS NULL AND redirect_saved = 0';
        } elseif ( $filter === 'matched' ) {
            $where = 'WHERE suggested_url IS NOT NULL AND redirect_saved = 0';
        } elseif ( $filter === 'redirected' ) {
            $where = 'WHERE redirect_saved = 1';
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
    }

    public static function get_unmatched( $limit = 50 ) {
        global $wpdb;
        $table = self::table();
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE suggested_url IS NULL AND redirect_saved = 0 ORDER BY hits DESC LIMIT %d",
                $limit
            )
        );
    }

    public static function save_suggestion( $id, $suggested_url, $suggested_title, $confidence ) {
        global $wpdb;
        $wpdb->update(
            self::table(),
            array(
                'suggested_url'   => $suggested_url,
                'suggested_title' => $suggested_title,
                'confidence'      => $confidence,
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    public static function save_redirect( $id ) {
        global $wpdb;
        $wpdb->update(
            self::table(),
            array( 'redirect_saved' => 1 ),
            array( 'id' => $id ),
            array( '%d' ),
            array( '%d' )
        );
    }

    public static function delete_log( $id ) {
        global $wpdb;
        $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
    }

    public static function get_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE id = %d", $id )
        );
    }
}
