<?php
/**
 * Joby Sync Engine v2.0
 */
class Joby_Sync_Engine {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'ajs_daily_sync_event', array( $this, 'start_sync' ) );
        add_action( 'ajs_process_queue_event', array( $this, 'process_queue' ) );
        
        if ( ! wp_next_scheduled( 'ajs_daily_sync_event' ) ) {
            wp_schedule_event( time(), 'daily', 'ajs_daily_sync_event' );
        }
    }

    public function start_sync() {
        $countries = get_option( 'ajs_countries', array() );
        $queue = array();
        $cycle_id = time();

        if (empty($countries)) {
            update_option('ajs_last_sync_error', 'No locations configured for sync.');
            update_option('ajs_sync_status', 'idle');
            return;
        }

        foreach ( $countries as $country ) {
            // Providers handle count differently
            $pages = ceil( $country['count'] / 50 );
            for ( $i = 1; $i <= $pages; $i++ ) {
                $queue[] = array(
                    'country_code' => $country['code'],
                    'country_name' => $country['name'],
                    'page'         => $i,
                    'cycle_id'     => $cycle_id,
                    'count'        => 50
                );
            }
        }

        update_option( 'ajs_sync_queue', $queue );
        update_option( 'ajs_current_sync_id', $cycle_id );
        update_option( 'ajs_sync_status', 'in_progress' );
        update_option( 'ajs_last_sync_error', '' );

        if ( ! wp_next_scheduled( 'ajs_process_queue_event' ) ) {
            wp_schedule_single_event( time() + 2, 'ajs_process_queue_event' );
        }
    }

    public function process_queue() {
        $status = get_option('ajs_sync_status');
        if ($status !== 'in_progress') return; // Handled cancellation

        $queue = get_option( 'ajs_sync_queue', array() );
        if ( empty( $queue ) ) {
            $this->complete_sync();
            return;
        }

        $task = array_shift( $queue );
        update_option( 'ajs_sync_queue', $queue );

        $provider_slug = get_option('ajs_provider', 'adzuna');
        $provider = Joby_API::get_provider($provider_slug);
        
        $jobs = $provider->fetch_jobs( $task['country_code'], $task['count'], $task['page'] );

        if ( is_wp_error( $jobs ) ) {
            update_option('ajs_last_sync_error', $jobs->get_error_message());
            // We don't stop the whole sync, just skip this task
        } else {
            foreach ( $jobs as $job ) {
                $this->upsert_job( $job, $task['country_name'], $task['cycle_id'] );
            }
        }

        if ( ! empty( $queue ) ) {
            // If Arbeitnow, we can go faster. If Adzuna, we wait 10s.
            $wait = ($provider_slug === 'arbeitnow') ? 2 : 10;
            wp_schedule_single_event( time() + $wait, 'ajs_process_queue_event' );
        } else {
            $this->complete_sync();
        }
    }

    private function upsert_job( $job_data, $country_name, $cycle_id ) {
        $remote_id = $job_data['id'];
        
        $existing_posts = get_posts( array(
            'post_type'  => 'ajs_job',
            'meta_key'   => '_ajs_remote_id',
            'meta_value' => $remote_id,
            'posts_per_page' => 1,
            'post_status' => 'any',
            'fields'      => 'ids'
        ) );

        $post_data = array(
            'post_title'   => $job_data['title'],
            'post_content' => $job_data['description'],
            'post_status'  => 'publish',
            'post_type'    => 'ajs_job',
        );

        if ( ! empty( $existing_posts ) ) {
            $post_id = $existing_posts[0];
            $post_data['ID'] = $post_id;
            wp_update_post( $post_data );
        } else {
            $post_id = wp_insert_post( $post_data );
        }

        if ( ! $post_id || is_wp_error($post_id) ) return;

        // Assign Taxonomy
        $term = get_term_by( 'name', $country_name, 'ajs_country' );
        if ( ! $term ) {
            $term = wp_insert_term( $country_name, 'ajs_country' );
            $term_id = is_wp_error( $term ) ? 0 : $term['term_id'];
        } else {
            $term_id = $term->term_id;
        }
        
        if ( $term_id ) wp_set_object_terms( $post_id, array( (int) $term_id ), 'ajs_country' );

        // Update meta using normalized provider data
        update_post_meta( $post_id, '_ajs_remote_id', $remote_id );
        update_post_meta( $post_id, '_ajs_last_cycle_id', $cycle_id );
        update_post_meta( $post_id, '_ajs_location', $job_data['location'] );
        update_post_meta( $post_id, '_ajs_company', $job_data['company'] );
        update_post_meta( $post_id, '_ajs_redirect_url', $job_data['url'] );
        update_post_meta( $post_id, '_ajs_type', $job_data['type'] );
        update_post_meta( $post_id, '_ajs_salary', $job_data['salary'] );
    }

    private function complete_sync() {
        $cycle_id = get_option( 'ajs_current_sync_id' );
        
        // Cleanup jobs not seen in this cycle
        $stale_jobs = get_posts( array(
            'post_type'      => 'ajs_job',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => '_ajs_last_cycle_id',
                    'value'   => $cycle_id,
                    'compare' => '!=',
                ),
            ),
        ) );

        foreach ( $stale_jobs as $post_id ) {
            wp_delete_post( $post_id, true );
        }

        update_option( 'ajs_sync_status', 'completed' );
        update_option( 'ajs_last_sync_completed', time() );
    }
}
