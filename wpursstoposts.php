<?php

/*
Plugin Name: WPU RSS to posts
Plugin URI: https://github.com/WordPressUtilities/wpursstoposts
Version: 0.1
Description: Easily import RSS into posts
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class wpursstoposts {

    private $maxitems = 15;
    private $post_type = 'rss';

    public function __construct() {
        add_action('init', array(&$this, 'init'));
    }

    public function init() {
        register_post_type($this->post_type,
            array(
                'labels' => array(
                    'name' => __('RSS items'),
                    'singular_name' => __('RSS item')
                ),
                'public' => true,
                'has_archive' => true
            )
        );

        // Parse every hour.
        if (false === get_transient('wpursstoposts_import_feeds')) {
            set_transient('wpursstoposts_import_feeds', 1, 1 * HOUR_IN_SECONDS);
            $this->parse_feeds();
        }

    }

    /* Import
    -------------------------- */

    public function parse_feeds() {
        global $wpdb;
        include_once ABSPATH . WPINC . '/feed.php';

        // Extract RSS feeds
        $feeds = apply_filters('wpursstoposts_feeds', array());
        $nb_imports = (count($feeds) + 1) * $this->maxitems;

        // Retrieve latest imports
        $latest_imports = $wpdb->get_col("SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'rss_permalink' LIMIT 0,$nb_imports");
        foreach ($feeds as $feed) {
            $this->import_feed($feed, $latest_imports);
        }
    }

    public function import_feed($url, $latest_imports) {

        add_filter('wp_feed_cache_transient_lifetime', array(&$this, 'set_cache_duration'));
        $rss = fetch_feed($url);
        remove_filter('wp_feed_cache_transient_lifetime', array(&$this, 'set_cache_duration'));

        if (is_wp_error($rss)) {
            return false;
        }

        $maxitems = $rss->get_item_quantity($this->maxitems);
        $rss_items = $rss->get_items(0, $maxitems);

        foreach ($rss_items as $item) {
            if (!in_array($item->get_permalink(), $latest_imports)) {
                $this->create_post_from_feed_item($item);
            }
        }

    }

    public function create_post_from_feed_item($item) {
        $post_id = wp_insert_post(array(
            'post_title' => wp_strip_all_tags($item->get_title()),
            'post_content' => $item->get_content(),
            'post_status' => 'publish',
            'post_type' => $this->post_type,
            'post_date' => $item->get_date('Y-m-d H:i:s')
        ));

        if (!is_numeric($post_id)) {
            return false;
        }

        // Save permalink
        update_post_meta($post_id, 'rss_permalink', $item->get_permalink());

        return $post_id;
    }

    /* Values
    -------------------------- */

    public function set_cache_duration() {
        return 600;
    }
}

$wpursstoposts = new wpursstoposts();
