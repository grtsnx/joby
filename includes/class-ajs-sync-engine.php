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
            $this->log_activity('Sync failed: No locations configured.');
            update_option('ajs_last_sync_error', 'No locations configured for sync.');
            update_option('ajs_sync_status', 'idle');
            return;
        }

        $this->log_activity('Starting sync for ' . count($countries) . ' regions...');

        $provider_slug = get_option('ajs_provider', 'adzuna');
        $provider = Joby_API::get_provider($provider_slug);
        $supported = array_keys( $provider->get_supported_countries() );

        foreach ( $countries as $country ) {
            // Validation: Skip if not supported by current provider (except Arbeitnow which is global)
            if ( $provider_slug !== 'arbeitnow' && ! in_array( $country['code'], $supported ) ) {
                $this->log_activity('⚠️ Skipped ' . $country['name'] . ' (' . $country['code'] . '): Not supported by ' . $provider->get_name());
                continue;
            }

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
        if ($status !== 'in_progress') return;

        $queue = get_option( 'ajs_sync_queue', array() );
        if ( empty( $queue ) ) {
            $this->complete_sync();
            return;
        }

        $provider_slug = get_option('ajs_provider', 'adzuna');
        $provider = Joby_API::get_provider($provider_slug);
        
        $batch_size = 5; // Process 5 tasks per batch
        $processed = 0;
        $total_new = 0;
        $cycle_id = get_option( 'ajs_current_sync_id' );

        while ( ! empty( $queue ) && $processed < $batch_size ) {
            $task = array_shift( $queue );
            $jobs = $provider->fetch_jobs( $task['country_code'], $task['count'], $task['page'] );

            if ( is_wp_error( $jobs ) ) {
                $this->log_activity('API Error (' . $task['country_code'] . '): ' . $jobs->get_error_message());
            } elseif ( ! empty( $jobs ) ) {
                $total_new += count( $jobs );
                
                // Optimized Bulk Upsert
                $remote_ids = array_column( $jobs, 'id' );
                $existing_map = $this->get_existing_job_map( $remote_ids );
                
                // Cache term ID for this task's country
                $term_id = $this->get_or_create_country_term( $task['country_name'] );
                
                foreach ( $jobs as $job ) {
                    $post_id = isset( $existing_map[ $job['id'] ] ) ? $existing_map[ $job['id'] ] : 0;
                    $this->upsert_job( $job, $term_id, $task['cycle_id'], $post_id );
                }
                
                $this->log_activity('⚡ Batch: Fetched ' . count($jobs) . ' jobs for ' . $task['country_name'] . ' (Page ' . $task['page'] . ')');
            }
            
            $processed++;
        }

        update_option( 'ajs_sync_queue', $queue );

        if ( ! empty( $queue ) ) {
            // Respect rate limits: 3s for Adzuna, 1s for others
            $wait = ($provider_slug === 'adzuna') ? 3 : 1;
            wp_schedule_single_event( time() + $wait, 'ajs_process_queue_event' );
        } else {
            $this->complete_sync();
        }
    }

    private function get_existing_job_map( $remote_ids ) {
        if ( empty( $remote_ids ) ) return array();

        $posts = get_posts( array(
            'post_type'      => 'ajs_job',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => '_ajs_remote_id',
                    'value'   => $remote_ids,
                    'compare' => 'IN'
                )
            )
        ) );

        $map = array();
        foreach ( $posts as $post_id ) {
            $remote_id = get_post_meta( $post_id, '_ajs_remote_id', true );
            if ( $remote_id ) $map[ $remote_id ] = $post_id;
        }
        return $map;
    }

    private function upsert_job( $job_data, $term_id, $cycle_id, $existing_post_id = 0 ) {
        $remote_id = $job_data['id'];
        
        $post_data = array(
            'post_title'   => $job_data['title'],
            'post_content' => $job_data['description'],
            'post_status'  => 'publish',
            'post_type'    => 'ajs_job',
        );

        if ( $existing_post_id ) {
            $post_id = $existing_post_id;
            $post_data['ID'] = $post_id;
            wp_update_post( $post_data );
        } else {
            $post_id = wp_insert_post( $post_data );
        }

        if ( ! $post_id || is_wp_error($post_id) ) return;

        // Assign Taxonomy (Optimized: term ID passed in)
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

    private function get_or_create_country_term( $country_name ) {
        $term = get_term_by( 'name', $country_name, 'ajs_country' );
        if ( ! $term ) {
            $term = wp_insert_term( $country_name, 'ajs_country' );
            return is_wp_error( $term ) ? 0 : $term['term_id'];
        }
        return $term->term_id;
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
        $this->log_activity('Sync completed successfully. Database cleaned.');
    }

    public function log_activity( $message ) {
        $logs = get_site_transient( 'ajs_sync_logs' );
        if ( ! is_array( $logs ) ) $logs = array();
        
        $logs[] = array(
            'time' => date( 'H:i:s' ),
            'msg'  => $message
        );
        
        // Keep only last 20 logs
        if ( count( $logs ) > 20 ) {
            $logs = array_slice( $logs, -20 );
        }
        
        set_site_transient( 'ajs_sync_logs', $logs, HOUR_IN_SECONDS );
    }

    public static function get_stats() {
        $stats = array(
            'total_jobs' => wp_count_posts( 'ajs_job' )->publish,
            'countries'  => count( get_option( 'ajs_countries', array() ) ),
            'last_sync'  => get_option( 'ajs_last_sync_completed' ),
            'provider'   => get_option( 'ajs_ajs_provider', 'Arbeitnow' )
        );
        return $stats;
    }
}
