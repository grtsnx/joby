<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Joby_Shortcodes {
    public function __construct() {
        add_shortcode( 'joby_field', array( $this, 'render_field' ) );
        add_shortcode( 'joby_apply_button', array( $this, 'render_apply_button' ) );
    }

    /**
     * Render a job field
     * Usage: [joby_field key="company"]
     */
    public function render_field( $atts ) {
        $atts = shortcode_atts( array(
            'key' => 'company', // company, location, salary, provider
        ), $atts, 'joby_field' );

        $post_id = get_the_ID();
        if ( ! $post_id || get_post_type( $post_id ) !== 'ajs_job' ) {
            return '';
        }

        // Map user-friendly keys to internal meta keys
        $map = array(
            'company'  => '_ajs_company',
            'location' => '_ajs_location_name',
            'salary'   => '_ajs_salary',
            'provider' => '_ajs_provider',
            'id'       => '_ajs_remote_id'
        );

        $meta_key = isset( $map[$atts['key']] ) ? $map[$atts['key']] : $atts['key'];
        $value = get_post_meta( $post_id, $meta_key, true );

        return esc_html( $value );
    }

    /**
     * Render a styled apply button
     * Usage: [joby_apply_button text="Apply Now" class="my-custom-btn"]
     */
    public function render_apply_button( $atts ) {
        $atts = shortcode_atts( array(
            'text'  => 'Apply Now',
            'class' => 'ajs-apply-button'
        ), $atts, 'joby_apply_button' );

        $post_id = get_the_ID();
        if ( ! $post_id || get_post_type( $post_id ) !== 'ajs_job' ) {
            return '';
        }

        $url = get_post_meta( $post_id, '_ajs_apply_url', true );
        if ( ! $url ) return '';

        return sprintf(
            '<a href="%s" target="_blank" class="%s" rel="nofollow">%s</a>',
            esc_url( $url ),
            esc_attr( $atts['class'] ),
            esc_html( $atts['text'] )
        );
    }
}
