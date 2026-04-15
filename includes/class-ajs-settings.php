<?php
/**
 * Joby Settings Page
 */
class Joby_Settings {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'wp_ajax_ajs_purge_jobs', array( $this, 'handle_purge_jobs' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_head', array( $this, 'fix_menu_icon_size' ) );
        add_action( 'wp_ajax_ajs_trigger_sync', array( $this, 'handle_trigger_sync' ) );
        add_action( 'wp_ajax_ajs_cancel_sync', array( $this, 'handle_cancel_sync' ) );
        add_action( 'wp_ajax_ajs_check_updates', array( $this, 'handle_check_updates' ) );
        add_action( 'wp_ajax_ajs_get_logs', array( $this, 'handle_get_logs' ) );
        add_action( 'wp_ajax_ajs_force_batch', array( $this, 'handle_force_batch' ) );
        add_action( 'wp_ajax_ajs_clear_cache', array( $this, 'handle_clear_cache' ) );
        
        // Prevent duplicated footer by hiding default WP footer on this page
        add_action( 'admin_head', array( $this, 'hide_default_footer' ) );
    }

    public function add_menu() {
        add_menu_page(
            'Joby Sync',
            'Joby Sync',
            'manage_options',
            'joby-sync',
            array( $this, 'render_page' ),
            JOBY_URL . 'assets/icon.png'
        );
    }

    public function enqueue_assets($hook) {
        if ( 'toplevel_page_joby-sync' !== $hook ) return;
        
        wp_enqueue_style( 'ajs-admin-css', JOBY_URL . 'assets/admin.css', array(), JOBY_VERSION );
        wp_enqueue_script( 'ajs-admin-js', JOBY_URL . 'assets/admin.js', array( 'jquery' ), JOBY_VERSION, true );
        
        $all_providers = Joby_API::get_all_providers();
        $compatibility = array();
        foreach ( $all_providers as $slug => $obj ) {
            $compatibility[ $slug ] = $obj->get_supported_countries();
        }

        wp_localize_script( 'ajs-admin-js', 'ajs_vars', array(
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'ajs_nonce' ),
            'compatibility' => $compatibility,
            'all_countries' => Joby_Helper::get_all_countries()
        ) );
    }

    public function register_settings() {
        register_setting( 'ajs_settings_group', 'ajs_provider' );
        register_setting( 'ajs_settings_group', 'ajs_app_id' );
        register_setting( 'ajs_settings_group', 'ajs_app_key' );
        register_setting( 'ajs_settings_group', 'ajs_countries' );
        register_setting( 'ajs_settings_group', 'ajs_auto_sync' );

        // Handle auto-sync on save if enabled
        if ( isset($_GET['settings-updated']) && $_GET['settings-updated'] ) {
            if ( get_option('ajs_auto_sync') === 'yes' ) {
                Joby_Sync_Engine::get_instance()->start_sync();
            }
        }
    }

    public function render_page() {
        // Clear update cache on settings page load so user sees latest version
        delete_site_transient( 'joby_update_check' );
        delete_site_transient( 'update_plugins' ); // Force WP to re-scan for updates
        
        $provider    = get_option( 'ajs_provider', 'adzuna' );
        $app_id      = get_option( 'ajs_app_id' );
        $app_key     = get_option( 'ajs_app_key' );
        $auto_sync   = get_option( 'ajs_auto_sync', 'no' );
        $countries   = get_option( 'ajs_countries', array() );
        $status      = get_option( 'ajs_sync_status', 'idle' );
        $queue       = get_option( 'ajs_sync_queue', array() );
        $last_sync   = get_option( 'ajs_last_sync_completed' );
        $error       = get_option( 'ajs_last_sync_error' );

        $all_providers = Joby_API::get_all_providers();
        ?>
        <div class="wrap ajs-admin-wrap">
            <div class="ajs-header">
                <img src="<?php echo JOBY_URL . 'assets/icon-white.png'; ?>" alt="Joby Sync" class="ajs-logo">
                <h1>Joby Sync Settings</h1>
            </div>

            <div class="ajs-actions" style="margin-top: 20px; margin-bottom: 30px; display: flex; gap: 10px;">
                <a href="<?php echo admin_url('edit.php?post_type=ajs_job'); ?>" class="button button-primary">View Synced Jobs</a>
                <button type="button" id="ajs-check-updates" class="button button-secondary">Check for Updates</button>
                <button type="button" id="ajs-clear-cache" class="button button-secondary">Clear Cache</button>
                <button type="button" id="ajs-how-to-use" class="button button-secondary" style="background: #e7f1ff; color: #0073aa; border-color: #0073aa;">📖 How to Use</button>
                <button type="button" id="ajs-purge-jobs" class="button button-secondary" style="color: #D70000; border-color: #D70000;">Purge All Jobs</button>
            </div>
            
            <div class="ajs-dashboard-grid">
                <?php 
                $stats = Joby_Sync_Engine::get_stats();
                $cards = array(
                    'Total Jobs' => $stats['total_jobs'],
                    'Regions'    => $stats['countries'],
                    'Last Sync'  => $stats['last_sync'] ? human_time_diff($stats['last_sync'], time()) . ' ago' : 'Never'
                );
                foreach ($cards as $label => $val) : 
                    $slug = strtolower(str_replace(' ', '-', $label));
                ?>
                    <div class="ajs-stat-card">
                        <span class="ajs-stat-label"><?php echo esc_html($label); ?></span>
                        <span class="ajs-stat-value" id="ajs-stat-<?php echo esc_attr($slug); ?>"><?php echo esc_html($val); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="ajs-main-grid">
                <div class="ajs-grid-left">
                    <div class="ajs-card status-card <?php echo esc_attr($status); ?> premium-control">
                        <div class="status-top">
                            <h3>Real-time Sync Control</h3>
                            <span class="status-badge"><?php echo esc_html(ucfirst(str_replace('_', ' ', $status))); ?></span>
                        </div>

                        <div class="sync-info">
                            <?php if ( $status === 'in_progress' ) : ?>
                                <div class="progress-wrapper">
                                    <div class="progress-meta">
                                        <span class="tasks-count"><strong><?php echo count($queue); ?></strong> tasks in queue</span>
                                        <span class="progress-percent">0%</span>
                                    </div>
                                    <div class="ajs-progress-container premium">
                                        <div class="ajs-progress-bar gradient-pulse" style="width: 0%;"></div>
                                    </div>
                                    <p class="current-step">Analyzing regions...</p>
                                </div>
                            <?php else : ?>
                                <p class="status-msg">
                                    <?php echo $status === 'completed' ? 'System idle. All jobs are up to date.' : 'Ready to start synchronization.'; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="ajs-sync-controls premium-group">
                            <div class="control-left">
                                <button id="ajs-trigger-sync" class="button button-primary premium-btn" <?php disabled($status, 'in_progress'); ?>>
                                    <?php echo $status === 'in_progress' ? 'Syncing...' : 'Start Manual Sync'; ?>
                                </button>
                                <?php if ($status === 'in_progress') : ?>
                                    <button id="ajs-cancel-sync" class="button button-link-delete">Stop Syncing</button>
                                <?php endif; ?>
                            </div>
                            <div class="control-right">
                                <?php if ($status === 'in_progress' || $status === 'error') : ?>
                                    <button id="ajs-force-batch" class="button button-secondary nudge-btn">Force Next Batch</button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="status-footer">
                            <a href="#" id="ajs-view-diagnostics" class="diagnostic-link">🔍 View Diagnostic Data</a>
                        </div>

                        <?php if ($error && $status !== 'in_progress') : ?>
                            <div class="ajs-error-notice">
                                <strong>Last Error:</strong> <?php echo esc_html($error); ?>
                            </div>
                        <?php endif; ?>
                    </div>

            <form method="post" action="options.php" class="ajs-settings-form">
                <?php settings_fields( 'ajs_settings_group' ); ?>
                
                <div class="ajs-card">
                    <h3>General Configuration</h3>
                    <table class="form-table">
                        <tr>
                            <th>Job Provider</th>
                            <td>
                                <select name="ajs_provider" id="ajs_provider_select" class="regular-text" style="width: 100%;">
                                    <?php foreach ($all_providers as $p_slug => $p_obj) : ?>
                                        <option value="<?php echo $p_slug; ?>" <?php selected($provider, $p_slug); ?>><?php echo esc_html($p_obj->get_name()); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr class="provider-field provider-adzuna" <?php echo $provider !== 'adzuna' ? 'style="display:none;"' : ''; ?>>
                            <th>App ID</th>
                            <td><input type="text" name="ajs_app_id" value="<?php echo esc_attr( $app_id ); ?>" class="regular-text" placeholder="e.g. 1234abcd"></td>
                        </tr>
                        <tr class="provider-field provider-adzuna" <?php echo $provider !== 'adzuna' ? 'style="display:none;"' : ''; ?>>
                            <th>App Key</th>
                            <td>
                                <input type="password" name="ajs_app_key" value="<?php echo esc_attr( $app_key ); ?>" class="regular-text" placeholder="Your secret key">
                                <p class="description"><a href="https://developer.adzuna.com/signup" target="_blank" rel="noopener noreferrer">Get your Adzuna API keys here →</a></p>
                            </td>
                        </tr>
                        <tr>
                            <th>Auto-Sync</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="ajs_auto_sync" value="yes" <?php checked($auto_sync, 'yes'); ?>>
                                    Automatically start syncing after saving settings
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="ajs-card">
                    <h3>Search Locations & Targets</h3>
                    <p class="description">Select the countries you want to pull jobs from. Only countries supported by your chosen provider will appear here.</p>
                    
                    <div id="ajs-country-library" style="margin-top: 15px;">
                        <table class="wp-list-table widefat fixed striped" id="ajs-countries-table">
                            <thead>
                                <tr>
                                    <th>Country</th>
                                    <th>Target Jobs</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $countries ) ) : foreach ( $countries as $index => $country ) : ?>
                                    <tr>
                                        <td>
                                            <select name="ajs_countries[<?php echo $index; ?>][code]" class="ajs-country-selector">
                                                <?php 
                                                $supported = Joby_API::get_provider($provider)->get_supported_countries();
                                                foreach ($supported as $code => $name) : ?>
                                                    <option value="<?php echo $code; ?>" <?php selected($country['code'], $code); ?>><?php echo esc_html($name); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input type="number" name="ajs_countries[<?php echo $index; ?>][count]" value="<?php echo esc_attr( $country['count'] ); ?>" step="50" min="50" style="width: 100%;"></td>
                                        <td><button type="button" class="button ajs-remove-row">Remove</button></td>
                                    </tr>
                                <?php endforeach; else : ?>
                                    <tr class="empty-row">
                                        <td colspan="3">Click "Add Location" to get started.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3">
                                        <button type="button" id="ajs-add-country" class="button button-secondary">Add Location</button>
                                        <div id="ajs-country-warning" class="ajs-warning-banner" style="display: none; margin-top: 15px;">
                                            <span class="ajs-warning-icon">⚠️</span>
                                            <div class="ajs-warning-text">
                                                <strong>Provider Mismatch:</strong> Some locations are not supported by <span id="ajs-warning-provider-name">Adzuna</span>. These will be skipped during sync.
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <?php submit_button('Save Settings'); ?>
            </form>
                </div> <!-- .ajs-grid-left -->

                <div class="ajs-grid-right">
                    <div class="ajs-card">
                        <h3>Sync Activity Logs</h3>
                        <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                            <span id="ajs-toggle-logs" class="ajs-log-toggle">Show Logs Console</span>
                            <span id="ajs-view-raw-logs" class="ajs-log-toggle" style="color: var(--ajs-text-secondary);">View Raw Data</span>
                        </div>
                        <div id="ajs-log-console" class="ajs-log-console" style="display: none;">
                            <div class="ajs-log-placeholder">Wait for sync to start...</div>
                        </div>
                    </div>

                    <!-- Raw Data Modal -->
                    <div id="ajs-raw-modal" class="ajs-modal" style="display: none;">
                        <div class="ajs-modal-content">
                            <div class="ajs-modal-header">
                                <h3>Raw Sync Data</h3>
                                <span class="ajs-modal-close">&times;</span>
                            </div>
                            <div class="ajs-modal-body">
                                <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-size: 13px; color: var(--ajs-text-secondary);">Internal plugin state & last API response.</span>
                                </div>
                                <pre id="ajs-raw-json"></pre>
                            </div>
                        </div>
                    </div>

                    <div class="ajs-card">
                        <h3>System Info</h3>
                        <p style="font-size: 13px; color: #666;">
                            <strong>WP Version:</strong> <?php echo get_bloginfo('version'); ?><br>
                            <strong>PHP Version:</strong> <?php echo PHP_VERSION; ?><br>
                            <strong>Joby Version:</strong> <?php echo JOBY_VERSION; ?>
                        </p>
                    </div>
                </div> <!-- .ajs-grid-right -->
            </div> <!-- .ajs-main-grid -->

            <div class="ajs-footer" style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #E5E5E5; color: #86868B; font-size: 13px; display: flex; justify-content: space-between;">
                <span>&copy; <?php echo date('Y'); ?> Joby Sync by Abolade Greatness. All rights reserved.</span>
                <span>Version <?php echo JOBY_VERSION; ?></span>
            </div>
        </div>

        <!-- How to Use Modal -->
        <div id="ajs-guide-modal" class="ajs-modal" style="display:none;">
            <div class="ajs-modal-content">
                <div class="ajs-modal-header">
                    <h2>📖 Developer Guide: Using Joby Jobs</h2>
                    <span class="ajs-close-modal">&times;</span>
                </div>
                <div class="ajs-modal-body">
                    <section>
                        <h3>1. Querying Jobs in Templates</h3>
                        <p>Use the following snippet in your theme files (e.g., <code>archive.php</code> or <code>single-ajs_job.php</code>):</p>
                        <pre><code>$args = array(
    'post_type' => 'ajs_job',
    'posts_per_page' => 10,
    'tax_query' => array(
        array(
            'taxonomy' => 'ajs_country',
            'field'    => 'slug',
            'terms'    => 'us', // Slug of the country
        ),
    ),
);
$query = new WP_Query($args);</code></pre>
                    </section>

                    <section>
                        <h3>2. Displaying Metadata</h3>
                        <p>Available Post Meta keys for each job:</p>
                        <table class="ajs-guide-table">
                            <thead>
                                <tr><th>Feature</th><th>Meta Key</th><th>Description</th></tr>
                            </thead>
                            <tbody>
                                <tr><td>🏢 Company</td><td><code>_ajs_company</code></td><td>Name of the hiring company</td></tr>
                                <tr><td>📍 Location</td><td><code>_ajs_location_name</code></td><td>City/State description</td></tr>
                                <tr><td>💰 Salary</td><td><code>_ajs_salary</code></td><td>Formatted salary string</td></tr>
                                <tr><td>🔗 Apply URL</td><td><code>_ajs_apply_url</code></td><td>Direct link to the job ad</td></tr>
                                <tr><td>🆔 Remote ID</td><td><code>_ajs_remote_id</code></td><td>Unique ID from the provider</td></tr>
                                <tr><td>🤖 Provider</td><td><code>_ajs_provider</code></td><td>Origin (e.g., adzuna)</td></tr>
                            </tbody>
                        </table>
                    </section>

                    <section>
                        <h3>3. Displaying Job Details</h3>
                        <pre><code>&lt;h2&gt;&lt;?php the_title(); ?&gt;&lt;/h2&gt;
&lt;p&gt;Company: &lt;?php echo get_post_meta(get_the_ID(), '_ajs_company', true); ?&gt;&lt;/p&gt;
&lt;p&gt;Location: &lt;?php echo get_post_meta(get_the_ID(), '_ajs_location_name', true); ?&gt;&lt;/p&gt;
&lt;a href="&lt;?php echo get_post_meta(get_the_ID(), '_ajs_apply_url', true); ?&gt;" target="_blank"&gt;Apply Now&lt;/a&gt;</code></pre>
                    </section>
                </div>
            </div>
        </div>

        <script id="ajs-row-template" type="text/template">
            <tr>
                <td>
                    <select name="ajs_countries[{{index}}][code]" class="ajs-country-selector">
                        <?php 
                        $supported = Joby_API::get_provider($provider)->get_supported_countries();
                        foreach ($supported as $code => $name) : ?>
                            <option value="<?php echo $code; ?>"><?php echo esc_html($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" name="ajs_countries[{{index}}][count]" value="100" step="50" min="50" style="width: 100%;"></td>
                <td><button type="button" class="button ajs-remove-row">Remove</button></td>
            </tr>
        </script>
        <?php
    }

    public function handle_trigger_sync() {
        check_ajax_referer( 'ajs_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        wp_clear_scheduled_hook( 'ajs_process_queue_event' );
        Joby_Sync_Engine::get_instance()->start_sync();
        wp_send_json_success( 'Sync started' );
    }

    public function handle_cancel_sync() {
        check_ajax_referer( 'ajs_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        wp_clear_scheduled_hook( 'ajs_process_queue_event' );
        update_option( 'ajs_sync_status', 'cancelled' );
        update_option('ajs_sync_queue', array());
        update_option('ajs_last_sync_error', 'Sync cancelled by user.');
        
        wp_send_json_success( 'Sync cancelled' );
    }

    public function handle_check_updates() {
        check_ajax_referer( 'ajs_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        delete_site_transient( 'joby_update_check' );
        delete_site_transient( 'update_plugins' );
        
        // Trigger WP to re-scan for updates immediately
        if (function_exists('wp_update_plugins')) {
            wp_update_plugins();
        }
        
        wp_send_json_success( 'Update check complete. Please refresh the page or check the Plugins menu.' );
    }

    public function handle_purge_jobs() {
        check_ajax_referer( 'ajs_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        Joby_Sync_Engine::purge_all_jobs();
        wp_send_json_success( 'All jobs purged successfully.' );
    }

    public function handle_get_logs() {
        check_ajax_referer( 'ajs_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        $logs = get_site_transient( 'ajs_sync_logs' );
        if ( ! is_array( $logs ) ) $logs = array();

        wp_send_json_success( array(
            'logs'             => $logs,
            'status'           => get_option( 'ajs_sync_status', 'idle' ),
            'queue_count'      => count( get_option( 'ajs_sync_queue', array() ) ),
            'current_sync_id'  => get_option( 'ajs_current_sync_id' ),
            'total_jobs'       => wp_count_posts( 'ajs_job' )->publish,
            'last_sync_time'   => get_option( 'ajs_last_sync_completed' ),
            'last_error'       => get_option( 'ajs_last_sync_error' ),
            'config_countries' => get_option( 'ajs_countries' ),
            'provider'         => get_option( 'ajs_provider', 'adzuna' ),
            'last_api'         => get_site_transient( 'ajs_last_api_response' )
        ) );
    }

    public function handle_force_batch() {
        check_ajax_referer( 'ajs_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        // Cancel any pending cron schedules first
        wp_clear_scheduled_hook( 'ajs_process_queue_event' );

        $engine = Joby_Sync_Engine::get_instance();
        $engine->process_queue( true ); // This will process one batch (5 items) and NOT reschedule

        wp_send_json_success( 'Batch processed successfully and schedules cleared.' );
    }

    public function handle_clear_cache() {
        check_ajax_referer( 'ajs_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        delete_site_transient( 'ajs_sync_logs' );
        delete_site_transient( 'joby_update_check' );
        delete_site_transient( 'update_plugins' );
        
        wp_send_json_success( 'All plugin caches and transients have been cleared.' );
    }

    public function fix_menu_icon_size() {
        ?>
        <style>
            #toplevel_page_joby-sync .wp-menu-image img { width: 20px !important; height: auto !important; padding-top: 7px !important; }
        </style>
        <?php
    }

    public function hide_default_footer() {
        $screen = get_current_screen();
        if ( $screen && $screen->id === 'toplevel_page_joby-sync' ) {
            add_filter( 'admin_footer_text', '__return_empty_string', 999 );
            add_filter( 'update_footer', '__return_empty_string', 999 );
        }
    }
}
