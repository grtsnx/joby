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
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_ajs_trigger_sync', array( $this, 'handle_trigger_sync' ) );
    }

    public function add_menu() {
        add_menu_page(
            'Joby Sync',
            'Joby Sync',
            'manage_options',
            'joby-sync',
            array( $this, 'render_page' ),
            'dashicons-cloud'
        );
    }

    public function enqueue_assets($hook) {
        if ( 'toplevel_page_joby-sync' !== $hook ) return;
        
        wp_enqueue_style( 'ajs-admin-css', JOBY_URL . 'assets/admin.css', array(), JOBY_VERSION );
        wp_enqueue_script( 'ajs-admin-js', JOBY_URL . 'assets/admin.js', array( 'jquery' ), JOBY_VERSION, true );
        
        wp_localize_script( 'ajs-admin-js', 'ajs_vars', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ajs_nonce' )
        ) );
    }

    public function register_settings() {
        register_setting( 'ajs_settings_group', 'ajs_app_id' );
        register_setting( 'ajs_settings_group', 'ajs_app_key' );
        register_setting( 'ajs_settings_group', 'ajs_countries' );
    }

    public function render_page() {
        $app_id    = get_option( 'ajs_app_id' );
        $app_key   = get_option( 'ajs_app_key' );
        $countries = get_option( 'ajs_countries', array() );
        $status    = get_option( 'ajs_sync_status', 'idle' );
        $queue     = get_option( 'ajs_sync_queue', array() );
        $last_sync = get_option( 'ajs_last_sync_completed' );
        ?>
        <div class="wrap ajs-admin-wrap">
            <div class="ajs-header">
                <img src="<?php echo JOBY_URL . 'assets/icon.png'; ?>" alt="Joby Sync" class="ajs-logo">
                <h1>Joby Sync Settings</h1>
            </div>
            
            <div class="ajs-card status-card <?php echo esc_attr($status); ?>">
                <h3>Sync Status: <span class="status-badge"><?php echo esc_html(ucfirst(str_replace('_', ' ', $status))); ?></span></h3>
                <?php if ( $status === 'in_progress' ) : ?>
                    <p>Tasks remaining: <strong><?php echo count($queue); ?></strong></p>
                    <div class="ajs-progress-container">
                        <div class="ajs-progress-bar" style="width: 0%;"></div>
                    </div>
                <?php else : ?>
                    <p>Last completed: <strong><?php echo $last_sync ? date('Y-m-d H:i:s', $last_sync) : 'Never'; ?></strong></p>
                <?php endif; ?>
                <button id="ajs-trigger-sync" class="button button-primary" <?php disabled($status, 'in_progress'); ?>>
                    <?php echo $status === 'in_progress' ? 'Syncing...' : 'Start Manual Sync'; ?>
                </button>
            </div>

            <form method="post" action="options.php" class="ajs-settings-form">
                <?php settings_fields( 'ajs_settings_group' ); ?>
                
                <div class="ajs-card">
                    <h3>API Credentials</h3>
                    <table class="form-table">
                        <tr>
                            <th>App ID</th>
                            <td><input type="text" name="ajs_app_id" value="<?php echo esc_attr( $app_id ); ?>" class="regular-text" placeholder="e.g. 1234abcd"></td>
                        </tr>
                        <tr>
                            <th>App Key</th>
                            <td><input type="password" name="ajs_app_key" value="<?php echo esc_attr( $app_key ); ?>" class="regular-text" placeholder="Your secret key"></td>
                        </tr>
                    </table>
                </div>

                <div class="ajs-card">
                    <h3>Countries & Job Targets</h3>
                    <p class="description">Add countries using their 2-letter ISO codes (e.g. 'ng' for Nigeria, 'us' for USA). Max 250 calls total across all countries allowed per day.</p>
                    <table class="wp-list-table widefat fixed striped" id="ajs-countries-table">
                        <thead>
                            <tr>
                                <th>Country Name</th>
                                <th>Code</th>
                                <th>Target Jobs</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $countries ) ) : foreach ( $countries as $index => $country ) : ?>
                                <tr>
                                    <td><input type="text" name="ajs_countries[<?php echo $index; ?>][name]" value="<?php echo esc_attr( $country['name'] ); ?>" required></td>
                                    <td><input type="text" name="ajs_countries[<?php echo $index; ?>][code]" value="<?php echo esc_attr( $country['code'] ); ?>" maxlength="2" required></td>
                                    <td><input type="number" name="ajs_countries[<?php echo $index; ?>][count]" value="<?php echo esc_attr( $country['count'] ); ?>" step="50" min="50" required></td>
                                    <td><button type="button" class="button ajs-remove-row">Remove</button></td>
                                </tr>
                            <?php endforeach; else : ?>
                                <tr class="empty-row">
                                    <td colspan="4">No countries added yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4">
                                    <button type="button" id="ajs-add-country" class="button">Add Country</button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <?php submit_button('Save Settings'); ?>
            </form>
        </div>

        <script id="ajs-row-template" type="text/template">
            <tr>
                <td><input type="text" name="ajs_countries[{{index}}][name]" required></td>
                <td><input type="text" name="ajs_countries[{{index}}][code]" maxlength="2" required></td>
                <td><input type="number" name="ajs_countries[{{index}}][count]" value="1000" step="50" min="50" required></td>
                <td><button type="button" class="button ajs-remove-row">Remove</button></td>
            </tr>
        </script>
        <?php
    }

    public function handle_trigger_sync() {
        check_ajax_referer( 'ajs_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        Joby_Sync_Engine::get_instance()->start_sync();
        wp_send_json_success( 'Sync started' );
    }
}
