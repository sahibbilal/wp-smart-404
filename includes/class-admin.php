<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WS404_Admin {

    public function __construct() {
        add_action( 'admin_menu',            array( $this, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
        add_action( 'wp_ajax_ws404_find_match',   array( $this, 'handle_find_match' ) );
        add_action( 'wp_ajax_ws404_save_redirect', array( $this, 'handle_save_redirect' ) );
        add_action( 'wp_ajax_ws404_delete_log',   array( $this, 'handle_delete_log' ) );
        add_action( 'wp_ajax_ws404_auto_match_all', array( $this, 'handle_auto_match_all' ) );
        add_action( 'wp_ajax_ws404_save_settings', array( $this, 'handle_save_settings' ) );
    }

    public function add_menu() {
        add_menu_page(
            'Smart 404',
            'Smart 404',
            'manage_options',
            'wp-smart-404',
            array( $this, 'render_page' ),
            'dashicons-networking',
            80
        );
        add_submenu_page(
            'wp-smart-404',
            'Settings',
            'Settings',
            'manage_options',
            'wp-smart-404-settings',
            array( $this, 'render_settings' )
        );
    }

    public function enqueue( $hook ) {
        if ( strpos( $hook, 'wp-smart-404' ) === false ) return;

        wp_enqueue_style(  'ws404-admin', WS404_URL . 'assets/admin.css', array(), WS404_VERSION );
        wp_enqueue_script( 'ws404-admin', WS404_URL . 'assets/admin.js', array( 'jquery' ), WS404_VERSION, true );
        wp_localize_script( 'ws404-admin', 'ws404Data', array(
            'nonce'   => wp_create_nonce( 'ws404_nonce' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ) );
    }

    public function render_page() {
        WS404_Database::create_tables();

        $filter   = sanitize_text_field( $_GET['filter'] ?? 'all' );
        $per_page = 20;
        $page_num = max( 1, intval( $_GET['paged'] ?? 1 ) );
        $offset   = ( $page_num - 1 ) * $per_page;

        $logs      = WS404_Database::get_logs( $per_page, $offset, $filter );
        $total     = WS404_Database::count_logs( $filter );
        $pages     = ceil( $total / $per_page );

        $counts = array(
            'all'        => WS404_Database::count_logs( 'all' ),
            'unmatched'  => WS404_Database::count_logs( 'unmatched' ),
            'matched'    => WS404_Database::count_logs( 'matched' ),
            'redirected' => WS404_Database::count_logs( 'redirected' ),
        );

        $base_url = admin_url( 'admin.php?page=wp-smart-404' );
        ?>
        <div class="wrap ws404-wrap">
            <h1 style="display:flex;align-items:center;gap:10px;">
                <span class="dashicons dashicons-networking" style="font-size:28px;width:28px;height:28px;color:#2271b1;"></span>
                WP Smart 404
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-smart-404-settings' ) ); ?>" class="page-title-action">Settings</a>
            </h1>

            <!-- Summary cards -->
            <div class="ws404-cards">
                <div class="ws404-card">
                    <span class="ws404-card-num"><?php echo esc_html( $counts['all'] ); ?></span>
                    <span class="ws404-card-label">Total 404s</span>
                </div>
                <div class="ws404-card ws404-card-warn">
                    <span class="ws404-card-num"><?php echo esc_html( $counts['unmatched'] ); ?></span>
                    <span class="ws404-card-label">Unmatched</span>
                </div>
                <div class="ws404-card ws404-card-info">
                    <span class="ws404-card-num"><?php echo esc_html( $counts['matched'] ); ?></span>
                    <span class="ws404-card-label">AI Matched</span>
                </div>
                <div class="ws404-card ws404-card-ok">
                    <span class="ws404-card-num"><?php echo esc_html( $counts['redirected'] ); ?></span>
                    <span class="ws404-card-label">Redirected</span>
                </div>
            </div>

            <!-- Filter tabs -->
            <ul class="subsubsub" style="margin-bottom:12px;">
                <?php
                $filters = array(
                    'all'        => 'All',
                    'unmatched'  => 'Unmatched',
                    'matched'    => 'AI Matched',
                    'redirected' => 'Redirected',
                );
                $items = array();
                foreach ( $filters as $key => $label ) {
                    $active = $filter === $key ? ' class="current"' : '';
                    $items[] = "<li><a href='" . esc_url( $base_url . '&filter=' . $key ) . "'{$active}>{$label} <span class='count'>({$counts[$key]})</span></a></li>";
                }
                echo implode( ' | ', $items );
                ?>
            </ul>

            <!-- Action buttons -->
            <div class="ws404-actions-bar">
                <?php if ( $counts['unmatched'] > 0 ) : ?>
                <button id="ws404-auto-match-all" class="button button-primary">
                    ✨ Auto-Match All Unmatched (<?php echo esc_html( $counts['unmatched'] ); ?>)
                </button>
                <?php endif; ?>
                <span id="ws404-bulk-status" style="display:none;margin-left:12px;color:#555;font-size:13px;"></span>
            </div>

            <!-- 404 log table -->
            <?php if ( empty( $logs ) ) : ?>
            <div class="ws404-empty">
                <p>🎉 No 404 errors logged<?php echo $filter !== 'all' ? ' in this filter' : ''; ?>.</p>
                <?php if ( $filter === 'all' ) : ?>
                <p style="color:#888;">Visit a non-existent URL on your site to test the logger.</p>
                <?php endif; ?>
            </div>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped ws404-table">
                <thead>
                    <tr>
                        <th style="width:32%;">Broken URL</th>
                        <th style="width:8%;text-align:center;">Hits</th>
                        <th style="width:14%;">Last Seen</th>
                        <th style="width:30%;">AI Suggestion</th>
                        <th style="width:16%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $logs as $log ) : ?>
                    <tr id="ws404-row-<?php echo esc_attr( $log->id ); ?>" class="ws404-row">
                        <td>
                            <code class="ws404-url"><?php echo esc_html( $log->url ); ?></code>
                            <?php if ( $log->referrer ) : ?>
                            <br><span class="ws404-ref">from: <?php echo esc_html( $log->referrer ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <span class="ws404-hits <?php echo $log->hits >= 10 ? 'ws404-hits-high' : ''; ?>">
                                <?php echo esc_html( $log->hits ); ?>
                            </span>
                        </td>
                        <td style="font-size:12px;color:#666;"><?php echo esc_html( human_time_diff( strtotime( $log->last_seen ), time() ) . ' ago' ); ?></td>
                        <td class="ws404-suggestion-cell">
                            <?php if ( $log->redirect_saved ) : ?>
                                <span class="ws404-badge ws404-badge-ok">✓ Redirected</span>
                            <?php elseif ( $log->suggested_url ) : ?>
                                <div class="ws404-suggestion">
                                    <a href="<?php echo esc_url( $log->suggested_url ); ?>" target="_blank" class="ws404-suggest-link">
                                        <?php echo esc_html( $log->suggested_title ?: $log->suggested_url ); ?>
                                    </a>
                                    <span class="ws404-confidence ws404-conf-<?php echo esc_attr( $log->confidence ); ?>">
                                        <?php echo esc_html( $log->confidence ); ?>
                                    </span>
                                </div>
                            <?php else : ?>
                                <span class="ws404-badge ws404-badge-none">Not matched</span>
                            <?php endif; ?>
                        </td>
                        <td class="ws404-action-cell">
                            <?php if ( ! $log->redirect_saved ) : ?>
                                <?php if ( ! $log->suggested_url ) : ?>
                                <button class="button button-small ws404-find-btn"
                                        data-id="<?php echo esc_attr( $log->id ); ?>"
                                        data-url="<?php echo esc_attr( $log->url ); ?>">
                                    🔍 Find Match
                                </button>
                                <?php else : ?>
                                <button class="button button-primary button-small ws404-save-btn"
                                        data-id="<?php echo esc_attr( $log->id ); ?>"
                                        data-from="<?php echo esc_attr( $log->url ); ?>"
                                        data-to="<?php echo esc_attr( $log->suggested_url ); ?>">
                                    ↪ Save Redirect
                                </button>
                                <button class="button button-small ws404-find-btn"
                                        data-id="<?php echo esc_attr( $log->id ); ?>"
                                        data-url="<?php echo esc_attr( $log->url ); ?>"
                                        style="margin-top:4px;">
                                    🔄 Re-match
                                </button>
                                <?php endif; ?>
                            <?php endif; ?>
                            <button class="button button-small ws404-delete-btn"
                                    data-id="<?php echo esc_attr( $log->id ); ?>"
                                    style="margin-top:4px;color:#a00;">
                                🗑 Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ( $pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links( array(
                        'base'    => $base_url . '&filter=' . $filter . '&paged=%#%',
                        'format'  => '',
                        'current' => $page_num,
                        'total'   => $pages,
                    ) );
                    ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_settings() {
        if ( isset( $_POST['ws404_save'] ) && check_admin_referer( 'ws404_settings' ) ) {
            update_option( 'ws404_claude_api_key', sanitize_text_field( $_POST['ws404_api_key'] ?? '' ) );
            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>Smart 404 — Settings</h1>
            <form method="post" style="max-width:600px;margin-top:20px;">
                <?php wp_nonce_field( 'ws404_settings' ); ?>
                <input type="hidden" name="ws404_save" value="1" />
                <table class="form-table">
                    <tr>
                        <th>Claude API Key</th>
                        <td>
                            <input type="password" name="ws404_api_key"
                                   value="<?php echo esc_attr( get_option( 'ws404_claude_api_key', '' ) ); ?>"
                                   style="width:100%;" placeholder="sk-ant-..." autocomplete="off" />
                            <p class="description">
                                Get your key at <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a>.
                                Used to find the best matching page for each 404 URL.
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Save Settings' ); ?>
            </form>
        </div>
        <?php
    }

    // ── AJAX handlers ──────────────────────────────────────────────────────────

    public function handle_find_match() {
        while ( ob_get_level() ) ob_end_clean();
        check_ajax_referer( 'ws404_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Unauthorized.' ) );

        $id  = intval( $_POST['id'] ?? 0 );
        $url = sanitize_text_field( wp_unslash( $_POST['url'] ?? '' ) );

        if ( ! $id || ! $url ) wp_send_json_error( array( 'message' => 'Missing parameters.' ) );

        $matcher = new WS404_Matcher();
        $result  = $matcher->find_match( $url );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        WS404_Database::save_suggestion( $id, $result['url'], $result['title'], $result['confidence'] );

        wp_send_json_success( array(
            'url'        => $result['url'],
            'title'      => $result['title'],
            'confidence' => $result['confidence'],
            'reason'     => $result['reason'],
        ) );
    }

    public function handle_save_redirect() {
        while ( ob_get_level() ) ob_end_clean();
        check_ajax_referer( 'ws404_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Unauthorized.' ) );

        $id       = intval( $_POST['id'] ?? 0 );
        $from_url = sanitize_text_field( wp_unslash( $_POST['from_url'] ?? '' ) );
        $to_url   = esc_url_raw( wp_unslash( $_POST['to_url'] ?? '' ) );

        if ( ! $id || ! $from_url || ! $to_url ) wp_send_json_error( array( 'message' => 'Missing parameters.' ) );

        WS404_Redirects::add( $from_url, $to_url );
        WS404_Database::save_redirect( $id );

        wp_send_json_success( array( 'message' => 'Redirect saved.' ) );
    }

    public function handle_delete_log() {
        while ( ob_get_level() ) ob_end_clean();
        check_ajax_referer( 'ws404_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Unauthorized.' ) );

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( array( 'message' => 'Missing ID.' ) );

        $log = WS404_Database::get_by_id( $id );
        if ( $log && $log->redirect_saved ) {
            WS404_Redirects::remove( $log->url );
        }

        WS404_Database::delete_log( $id );
        wp_send_json_success();
    }

    public function handle_auto_match_all() {
        while ( ob_get_level() ) ob_end_clean();
        check_ajax_referer( 'ws404_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Unauthorized.' ) );

        $logs    = WS404_Database::get_unmatched( 50 );
        $matcher = new WS404_Matcher();
        $done    = 0;
        $errors  = 0;

        foreach ( $logs as $log ) {
            $result = $matcher->find_match( $log->url );
            if ( is_wp_error( $result ) ) {
                $errors++;
                continue;
            }
            WS404_Database::save_suggestion( $log->id, $result['url'], $result['title'], $result['confidence'] );
            $done++;
        }

        wp_send_json_success( array(
            'matched' => $done,
            'errors'  => $errors,
            'message' => "Matched {$done} URLs" . ( $errors ? ", {$errors} failed." : '.' ),
        ) );
    }

    public function handle_save_settings() {
        while ( ob_get_level() ) ob_end_clean();
        check_ajax_referer( 'ws404_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
        update_option( 'ws404_claude_api_key', sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) ) );
        wp_send_json_success();
    }
}
