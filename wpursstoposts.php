<?php

/*
Plugin Name: WPU RSS to posts
Plugin URI: https://github.com/WordPressUtilities/wpursstoposts
Version: 0.4
Description: Easily import RSS into posts
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class wpursstoposts {

    private $maxitems = 15;
    private $posttype = 'rssitems';
    private $taxonomy = 'rssfeeds';
    private $cacheduration = 600;
    private $importimg = true;
    private $hookcron = 'wpursstoposts_crontab';

    public function __construct() {
        add_action('init', array(&$this, 'init'));
        $this->maxitems = apply_filters('wpursstoposts_maxitems', $this->maxitems);
        $this->posttype = apply_filters('wpursstoposts_posttype', $this->posttype);
        $this->taxonomy = apply_filters('wpursstoposts_taxonomy', $this->taxonomy);
        $this->importimg = apply_filters('wpursstoposts_importimg', $this->importimg);
        $this->cacheduration = apply_filters('wpursstoposts_cacheduration', $this->cacheduration);
    }

    public function init() {

        // RSS Post type
        $posttype_info = apply_filters('wpursstoposts_posttype_info', array(
            'labels' => array(
                'name' => __('RSS items'),
                'singular_name' => __('RSS item')
            ),
            'public' => true,
            'has_archive' => true
        ));

        register_post_type($this->posttype, $posttype_info);

        $taxonomy_info = apply_filters('wpursstoposts_taxonomy_info', array(
            'label' => __('RSS Feeds'),
            'hierarchical' => true
        ));

        // RSS Taxonomy
        register_taxonomy(
            $this->taxonomy,
            $this->posttype,
            $taxonomy_info
        );

        add_filter('wputaxometas_fields', array(&$this, 'set_wputaxometas_fields'));

        // Launch cron every
        if (!wp_next_scheduled($this->hookcron)) {
            wp_schedule_event(time(), 'hourly', $this->hookcron);
        }

        add_action($this->hookcron, array(&$this, 'crontab_action'));
    }

    public function crontab_action() {
        $this->parse_feeds();
    }

    /* Import
    -------------------------- */

    public function parse_feeds() {

        global $wpdb;
        include_once ABSPATH . WPINC . '/feed.php';
        if ($this->importimg) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

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
        $feed = fetch_feed($url);
        remove_filter('wp_feed_cache_transient_lifetime', array(&$this, 'set_cache_duration'));

        if (is_wp_error($feed)) {
            return false;
        }

        $maxitems = $feed->get_item_quantity($this->maxitems);
        $feed_items = $feed->get_items(0, $maxitems);

        $feed_source = $this->get_feed_source_by_feed($feed);

        foreach ($feed_items as $item) {
            if (!in_array($item->get_permalink(), $latest_imports)) {
                $this->create_post_from_feed_item($item, $feed_source);
            }
        }

    }

    public function get_feed_source_by_feed($feed) {
        global $wpdb;
        $feed_title = $feed->get_title();
        $feed_source = get_term_by('name', $feed_title, $this->taxonomy);
        // Create feed
        if ($feed_source === false) {
            wp_insert_term($feed_title, $this->taxonomy, array(
                'description' => $feed->get_description()
            ));
            $feed_source = get_term_by('name', $feed_title, $this->taxonomy);
            // Insert feed image if available
            if ($feed_image = $feed->get_image_url()) {
                // Upload image
                $image = media_sideload_image($feed_image, false, 'src');
                // Extract attachment id
                $id = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE guid='$src'");
                add_term_meta($feed_source->term_id, 'rssfeeds_thumbnail', $id);
            }

        }
        return $feed_source;
    }

    public function create_post_from_feed_item($item, $feed_source) {
        $post_id = wp_insert_post(array(
            'post_title' => wp_strip_all_tags($item->get_title()),
            'post_content' => $item->get_content(),
            'post_status' => 'publish',
            'post_type' => $this->posttype,
            'post_date' => $item->get_date('Y-m-d H:i:s')
        ));

        if (!is_numeric($post_id)) {
            return false;
        }

        // Set feed
        wp_set_post_terms($post_id, $feed_source->term_id, $this->taxonomy);

        // Extract images
        if ($this->importimg) {
            $this->import_images_from_content($post_id, $item->get_content());
        }

        // Save permalink
        update_post_meta($post_id, 'rss_permalink', $item->get_permalink());

        return $post_id;
    }

    public function import_images_from_content($post_id, $post_content) {
        $regex_image = '/http:\/\/[^" \'?#]+\.(?:jpe?g|png|gif)/Ui';
        preg_match_all($regex_image, $post_content, $matches);

        if (!isset($matches[0]) || empty($matches[0])) {
            return false;
        }

        // For each image found
        foreach ($matches[0] as $img_url) {
            // Import image
            $image = media_sideload_image($img_url, $post_id, 'src');
            if (!is_wp_error($image)) {
                preg_match($regex_image, $image, $new_image_url);
                if (isset($new_image_url[0]) && !empty($new_image_url[0])) {
                    $post_content = str_replace($img_url, $new_image_url[0], $post_content);
                }
            }
        }

        // update content
        wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $post_content
        ));
    }

    /* Taxo metas
    -------------------------- */

    public function set_wputaxometas_fields($fields) {
        $fields['rssfeeds_thumbnail'] = array(
            'label' => 'Thumbnail',
            'taxonomies' => array($this->taxonomy),

            'type' => 'attachment'
        );
        return $fields;
    }

    /* Values
    -------------------------- */

    public function set_cache_duration() {
        return $this->cacheduration;
    }
}

$wpursstoposts = new wpursstoposts();
