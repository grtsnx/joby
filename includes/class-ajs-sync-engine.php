<?php
/**
 * Joby Sync Engine
 */
class Joby_Sync_Engine {

    private static $instance = null;
    private $api;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->api = new Joby_API();
        
        add_action( 'ajs_daily_sync_event', array( $this, 'start_sync' ) );
        add_action( 'ajs_process_queue_event', array( $this, 'process_queue' ) );
        
        if ( ! wp_next_scheduled( 'ajs_daily_sync_event' ) ) {
            wp_schedule_event( time(), 'daily', 'ajs_daily_sync_event' );
        }
    }

    /**
     * Start the sync by populating the queue
     */
    public function start_sync() {
        $countries = get_option( 'ajs_countries', array() );
        $queue = array();
        $cycle_id = time(); // Unique ID for this sync cycle

        foreach ( $countries as $country ) {
            $pages = ceil( $country['count'] / 50 );
            for ( $i = 1; $i <= $pages; $i++ ) {
                $queue[] = array(
                    'country_code' => $country['code'],
                    'country_name' => $country['name'],
                    'page'         => $i,
                    'cycle_id'     => $cycle_id
                );
            }
        }

        // Shuffle queue to randomize country order and avoid hitting same country too hard
        shuffle($queue);

        update_option( 'ajs_sync_queue', $queue );
        update_option( 'ajs_current_sync_id', $cycle_id );
        update_option( 'ajs_sync_status', 'in_progress' );

        if ( ! wp_next_scheduled( 'ajs_process_queue_event' ) ) {
            wp_schedule_single_event( time() + 5, 'ajs_process_queue_event' );
        }
    }

    /**
     * Process one item from the queue
     */
    public function process_queue() {
        $queue = get_option( 'ajs_sync_queue', array() );
        
        if ( empty( $queue ) ) {
            $this->complete_sync();
            return;
        }

        $task = array_shift( $queue );
        update_option( 'ajs_sync_queue', $queue );

        $results = $this->api.fetch_jobs( $task['country_code'], $task['page'] );

        if ( ! is_wp_error( $results ) && isset( $results['results'] ) ) {
            foreach ( $results['results'] as $job ) {
                $this->upsert_job( $job, $task['country_name'], $task['cycle_id'] );
            }
        }

        // Schedule next task in 30 seconds to respect rate limits
        if ( ! empty( $queue ) ) {
            wp_schedule_single_event( time() + 30, 'ajs_process_queue_event' );
        } else {
            $this->complete_sync();
        }
    }

    /**
     * Insert or Update a job
     */
    private function upsert_job( $job_data, $country_name, $cycle_id ) {
        $remote_id = $job_data['id'];
        
        // Find existing post by Remote ID
        $existing_posts = get_posts( array(
            'post_type'  => 'ajs_job',
            'meta_key'   => '_ajs_remote_id',
            'meta_value' => $remote_id,
            'posts_per_page' => 1,
            'post_status' => 'any'
        ) );

        $post_data = array(
            'post_title'   => $job_data['title'],
            'post_content' => $job_data['description'],
            'post_status'  => 'publish',
            'post_type'    => 'ajs_job',
        );

        if ( ! empty( $existing_posts ) ) {
            $post_id = $existing_posts[0]->ID;
            $post_data['ID'] = $post_id;
            wp_update_post( $post_data );
        } else {
            $post_id = wp_insert_post( $post_data );
        }

        if ( ! $post_id || is_wp_error($post_id) ) return;

        // Ensure country term exists and is assigned
        $term = get_term_by( 'name', $country_name, 'ajs_country' );
        if ( ! $term ) {
            $term = wp_insert_term( $country_name, 'ajs_country' );
            $term_id = is_wp_error( $term ) ? 0 : $term['term_id'];
        } else {
            $term_id = $term->term_id;
        }
        
        if ( $term_id ) {
            wp_set_object_terms( $post_id, array( (int) $term_id ), 'ajs_country' );
        }

        // Update meta
        update_post_meta( $post_id, '_ajs_remote_id', $remote_id );
        update_post_meta( $post_id, '_ajs_last_cycle_id', $cycle_id );
        update_post_meta( $post_id, '_ajs_location', isset($job_data['location']['display_name']) ? $job_data['location']['display_name'] : '' );
        update_post_meta( $post_id, '_ajs_company', isset($job_data['company']['display_name']) ? $job_data['company']['display_name'] : '' );
        update_post_meta( $post_id, '_ajs_redirect_url', $job_data['redirect_url'] );
        update_post_meta( $post_id, '_ajs_salary_min', isset($job_data['salary_min']) ? $job_data['salary_min'] : '' );
        update_post_meta( $post_id, '_ajs_salary_max', isset($job_data['salary_max']) ? $job_data['salary_max'] : '' );
    }

    /**
     * Finish sync and cleanup stale jobs
     */
    private function complete_sync() {
        $cycle_id = get_option( 'ajs_current_sync_id' );
        
        // Delete jobs that weren't updated in this cycle
        $stale_jobs = get_posts( array(
            'post_type'      => 'ajs_job',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => '_ajs_last_cycle_id',
                    'value'   => $cycle_id,
                    'compare' => '!=',
                ),
            ),
        ) );

        foreach ( $stale_jobs as $job ) {
            wp_delete_post( $job->ID, true );
        }

        update_option( 'ajs_sync_status', 'idle' );
        update_option( 'ajs_last_sync_completed', time() );
    }
}
