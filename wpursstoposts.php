<?php

/*
Plugin Name: WPU RSS to posts
Plugin URI: https://github.com/WordPressUtilities/wpursstoposts
Version: 0.5
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
        add_action('init', array(&$this,
            'init'
        ));
        add_action('plugins_loaded', array(&$this,
            'plugins_loaded'
        ));
        $this->set_values();
    }

    public function set_values() {
        // Max items nb
        $maxitems = get_option('wpursstoposts_maxitems');
        if (is_numeric($maxitems)) {
            $this->maxitems = $maxitems;
        }
        $this->maxitems = apply_filters('wpursstoposts_maxitems', $this->maxitems);

        // Import images
        $importimg = get_option('wpursstoposts_importimg');
        if (in_array($importimg, array('0', '1'))) {
            $this->importimg = ($importimg == '1');
        }
        $this->importimg = apply_filters('wpursstoposts_importimg', $this->importimg);

        // Feeds
        $base_feeds = array();
        $feeds = explode("\n", get_option('wpursstoposts_feeds'));
        if (is_array($feeds)) {
            foreach ($feeds as $feed_url) {
                $url = trim($feed_url);
                if (filter_var($url, FILTER_VALIDATE_URL) !== FALSE) {
                    $base_feeds[] = $url;
                }
            }
        }

        $this->feeds = apply_filters('wpursstoposts_feeds', $base_feeds);

        // Core values
        $this->posttype = apply_filters('wpursstoposts_posttype', $this->posttype);
        $this->taxonomy = apply_filters('wpursstoposts_taxonomy', $this->taxonomy);
        $this->cacheduration = apply_filters('wpursstoposts_cacheduration', $this->cacheduration);
    }

    public function plugins_loaded() {
        // Options
        add_filter('wpu_options_tabs', array(&$this,
            'options_tabs'
        ), 11, 3);
        add_filter('wpu_options_boxes', array(&$this,
            'options_boxes'
        ), 11, 3);
        add_filter('wpu_options_fields', array(&$this,
            'options_fields'
        ), 11, 3);
    }

    public function init() {

        // RSS Post type
        $posttype_info = apply_filters('wpursstoposts_posttype_info', array(
            'labels' => array(
                'name' => __('RSS items'),
                'singular_name' => __('RSS item')
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array('title', 'editor', 'thumbnail')
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

        // Taxo fields
        add_filter('wputaxometas_fields', array(&$this, 'set_wputaxometas_fields'));

        // Launch cron every
        if (!wp_next_scheduled($this->hookcron)) {
            wp_schedule_event(time(), 'hourly', $this->hookcron);
        }

        add_action($this->hookcron, array(&$this, 'crontab_action'));
    }

    public function crontab_action() {
        set_time_limit(0);
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
        $nb_imports = (count($this->feeds) + 1) * $this->maxitems;

        // Retrieve latest imports
        $latest_imports = $wpdb->get_col("SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'rss_permalink' LIMIT 0,$nb_imports");
        foreach ($this->feeds as $feed) {
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
        foreach ($matches[0] as $match_nb => $img_url) {
            // Import image
            $image = media_sideload_image($img_url, $post_id, 'src');
            if (!is_wp_error($image)) {
                preg_match($regex_image, $image, $new_image_url);
                if (isset($new_image_url[0]) && !empty($new_image_url[0])) {
                    $post_content = str_replace($img_url, $new_image_url[0], $post_content);
                }
                if ($match_nb == 0) {
                    // Set first image as thumbnail image
                    $attachments = get_posts(array(
                        'post_type' => 'attachment',
                        'posts_per_page' => 1,
                        'post_status' => 'any',
                        'post_parent' => $post_id
                    ));
                    if (!empty($attachments)) {
                        set_post_thumbnail($post_id, $attachments[0]->ID);
                    }
                }
            }
        }

        // update content
        wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $post_content
        ));
    }

    /* ----------------------------------------------------------
      Options for config
    ---------------------------------------------------------- */

    public function options_tabs($tabs) {
        $tabs['rss_tab'] = array(
            'name' => 'Plugin : RSS to posts'
        );
        return $tabs;
    }

    public function options_boxes($boxes) {
        $boxes['rss_config'] = array(
            'tab' => 'rss_tab',
            'name' => 'RSS to posts'
        );
        return $boxes;
    }

    public function options_fields($options) {
        $options['wpursstoposts_maxitems'] = array(
            'label' => __('Max items'),
            'box' => 'rss_config',
            'type' => 'number'
        );
        $options['wpursstoposts_importimg'] = array(
            'label' => __('Import images'),
            'box' => 'rss_config',
            'type' => 'select'
        );
        $options['wpursstoposts_feeds'] = array(
            'label' => __('Feeds'),
            'box' => 'rss_config',
            'type' => 'textarea'
        );
        return $options;
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
