<?php

/*
Plugin Name: WPU RSS to posts
Plugin URI: https://github.com/WordPressUtilities/wpursstoposts
Version: 1.4.2
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
    private $cacheduration = 30;
    private $user_cap = 'moderate_comments';
    private $importimg = true;
    private $importdraft = true;
    private $importonlyfirstimg = true;
    private $hookcron = 'wpursstoposts_crontab';
    private $option_id = 'wpursstoposts_options';

    public function __construct() {
        add_action('init', array(&$this,
            'init'
        ));
        add_action('init', array(&$this,
            'set_cron'
        ));
        add_action('plugins_loaded', array(&$this,
            'load_plugin_textdomain'
        ));
        add_action('plugins_loaded', array(&$this,
            'set_posttype_taxos'
        ));
        add_action('plugins_loaded', array(&$this,
            'plugins_loaded'
        ));
        add_action('init', array(&$this,
            'set_posttype_taxos_classic'
        ));
        $this->set_values();
    }

    public function set_values() {

        $this->user_cap = apply_filters('wpursstoposts_user_cap', $this->user_cap);

        $this->options = array(
            'admin_slug' => 'edit.php?post_type=' . $this->posttype,
            'plugin_publicname' => 'RSS to posts',
            'plugin_name' => 'RSS to posts',
            'plugin_userlevel' => $this->user_cap,
            'plugin_id' => 'wpursstoposts',
            'plugin_pageslug' => 'wpursstoposts'
        );

        $settings = get_option($this->option_id);

        // Max items nb
        if (isset($settings['maxitems']) && is_numeric($settings['maxitems'])) {
            $this->maxitems = $settings['maxitems'];
        }
        $this->maxitems = apply_filters('wpursstoposts_maxitems', $this->maxitems);

        // Import images
        if (isset($settings['import_images']) && in_array($settings['import_images'], array('0', '1'))) {
            $this->importimg = ($settings['import_images'] == '1');
        }
        $this->importimg = apply_filters('wpursstoposts_importimg', $this->importimg);

        // Import images
        if (isset($settings['import_onlyfirstimage']) && in_array($settings['import_onlyfirstimage'], array('0', '1'))) {
            $this->importonlyfirstimg = ($settings['import_onlyfirstimage'] == '1');
        }
        $this->importonlyfirstimg = apply_filters('wpursstoposts_importonlyfirstimg', $this->importonlyfirstimg);

        // Import as Draft
        if (isset($settings['import_draft']) && in_array($settings['import_draft'], array('0', '1'))) {
            $this->importdraft = ($settings['import_draft'] == '1');
        }
        $this->importdraft = apply_filters('wpursstoposts_importdraft', $this->importdraft);

        // Feeds
        $base_feeds = array();
        $feeds = array();
        if (isset($settings['feeds'])) {
            $feeds = explode("\n", $settings['feeds']);
        }
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

    public function init() {

        /* Settings */
        $this->settings_details = array(
            'plugin_id' => 'wpursstoposts',
            'option_id' => $this->option_id,
            'user_cap' => $this->user_cap,
            'sections' => array(
                'import' => array(
                    'user_cap' => $this->user_cap,
                    'name' => __('Import Settings', 'wpursstoposts')
                )
            )
        );

        $this->settings = array(
            'feeds' => array(
                'section' => 'import',
                'type' => 'textarea',
                'label' => __('Feeds', 'wpursstoposts')
            ),
            'maxitems' => array(
                'section' => 'import',
                'type' => 'number',
                'label' => __('Max items', 'wpursstoposts')
            ),
            'import_images' => array(
                'section' => 'import',
                'type' => 'checkbox',
                'label_check' => __('Import images in posts.', 'wpursstoposts'),
                'label' => __('Import images', 'wpursstoposts')
            ),
            'import_onlyfirstimage' => array(
                'section' => 'import',
                'type' => 'checkbox',
                'label_check' => __('Only the first image in the post content will be imported.', 'wpursstoposts'),
                'label' => __('Import only first', 'wpursstoposts')
            ),
            'import_draft' => array(
                'section' => 'import',
                'type' => 'checkbox',
                'label_check' => __('Posts are created with a draft status.', 'wpursstoposts'),
                'label' => __('Import as draft', 'wpursstoposts')
            )
        );

        if (is_admin()) {
            /* Messages */
            include 'inc/WPUBaseMessages.php';
            $this->messages = new \wpursstoposts\WPUBaseMessages($this->options['plugin_id']);

            /* Settings */

            include 'inc/WPUBaseSettings.php';
            new \wpursstoposts\WPUBaseSettings($this->settings_details, $this->settings);
        }
    }

    public function load_plugin_textdomain() {
        load_plugin_textdomain('wpursstoposts', false, dirname(plugin_basename(__FILE__)) . '/lang/');

    }

    public function plugins_loaded() {

        if (is_admin()) {
            // Admin page
            add_action('admin_menu', array(&$this,
                'admin_menu'
            ));
            add_filter("plugin_action_links_" . plugin_basename(__FILE__), array(&$this,
                'add_settings_link'
            ));
            add_action('admin_post_wpursstoposts_postaction', array(&$this,
                'postAction'
            ));
        }
    }

    public function set_posttype_taxos() {

        // RSS Post type
        $this->posttype_info = apply_filters('wpursstoposts_posttype_info', array(
            'name' => __('RSS item', 'wpursstoposts'),
            'plural' => __('RSS items', 'wpursstoposts'),
            'labels' => array(
                'name' => __('RSS items', 'wpursstoposts'),
                'singular_name' => __('RSS item', 'wpursstoposts')
            ),
            'menu_icon' => 'dashicons-rss',
            'public' => true,
            'has_archive' => true,
            'supports' => array('title', 'editor', 'thumbnail')
        ));

        $this->taxonomy_info = apply_filters('wpursstoposts_taxonomy_info', array(
            'label' => __('RSS Feeds', 'wpursstoposts'),
            'plural' => __('RSS Feeds', 'wpursstoposts'),
            'name' => __('RSS Feed', 'wpursstoposts'),
            'hierarchical' => true,
            'post_type' => $this->posttype
        ));

        if (class_exists('wputh_add_post_types_taxonomies')) {
            add_filter('wputh_get_posttypes', array(&$this, 'wputh_set_theme_posttypes'));
            add_filter('wputh_get_taxonomies', array(&$this, 'wputh_set_theme_taxonomies'));
        }

        // Taxo fields
        add_filter('wputaxometas_fields', array(&$this, 'set_wputaxometas_fields'));
    }

    public function set_posttype_taxos_classic() {
        if (class_exists('wputh_add_post_types_taxonomies')) {
            return;
        }
        // Register post type for items
        register_post_type($this->posttype, $this->posttype_info);

        // Register taxonomy for feeds
        register_taxonomy(
            $this->taxonomy,
            $this->posttype,
            $this->taxonomy_info
        );

    }
    public function wputh_set_theme_posttypes($post_types) {
        $post_types[$this->posttype] = $this->posttype_info;
        return $post_types;
    }

    public function wputh_set_theme_taxonomies($taxonomies) {
        $taxonomies[$this->taxonomy] = $this->taxonomy_info;
        return $taxonomies;
    }

    public function set_cron() {
        // Schedule cron
        $next_schedule = wp_next_scheduled($this->hookcron);
        if (!$next_schedule || $next_schedule + 10 < time()) {
            wp_clear_scheduled_hook($this->hookcron);
            wp_schedule_event(time() + 3600, 'hourly', $this->hookcron);
        }

        $callback_cron = array(&$this, 'crontab_action');
        if (!has_action($this->hookcron, $callback_cron)) {
            add_action($this->hookcron, $callback_cron);
        }
    }

    public function crontab_action() {
        set_time_limit(0);
        $this->parse_feeds(true);
    }

    /* Import
    -------------------------- */

    public function parse_feeds($from_cron = false) {

        global $wpdb;
        include_once ABSPATH . WPINC . '/feed.php';
        if ($this->importimg) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        // Extract RSS feeds
        $nb_imports = (count($this->feeds) + 1) * $this->maxitems;
        $nb_imported = 0;

        // Retrieve latest imports
        $latest_imports = $wpdb->get_col("SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'rss_permalink'  ORDER BY meta_id DESC LIMIT 0,200");
        add_filter('wp_feed_cache_transient_lifetime', array(&$this, 'set_cache_duration'));
        foreach ($this->feeds as $feed) {
            $nb_imported += $this->import_feed($feed, $latest_imports);
        }
        remove_filter('wp_feed_cache_transient_lifetime', array(&$this, 'set_cache_duration'));

        if (!$from_cron) {
            if ($nb_imported > 0) {
                $message = __('%s new item', 'wpursstoposts');
                if ($nb_imported > 1) {
                    $message = __('%s new items', 'wpursstoposts');
                }
                $this->messages->set_message('imported_nb', sprintf($message, $nb_imported));
            } else {
                $this->messages->set_message('imported_none', __('No new items imported', 'wpursstoposts'));
            }
        }
    }

    public function import_feed($url, $latest_imports) {

        $feed = fetch_feed($url);
        $feed->force_feed(true);
        if (is_wp_error($feed)) {
            return false;
        }

        $import_number = 0;

        $maxitems = $feed->get_item_quantity($this->maxitems);
        $feed_items = $feed->get_items(0, $maxitems);

        $feed_source = $this->get_feed_source_by_feed($feed);

        foreach ($feed_items as $item) {
            if (!in_array($item->get_permalink(), $latest_imports)) {

                $_post_creation = $this->create_post_from_feed_item($item, $feed_source);
                if (is_numeric($_post_creation)) {
                    $import_number++;
                }
            }
        }

        return $import_number;

    }

    public function get_feed_source_by_feed($feed) {
        global $wpdb;
        $feed_title = $feed->get_title();
        $feed_source = false;

        // Try to obtain feed by url
        $feed_id = $wpdb->get_var($wpdb->prepare(
            "SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = 'rssfeeds_src' && meta_value='%s'",
            $feed->feed_url
        ));
        if (!$feed_id) {
            $feed_source = get_term_by('name', $feed_title, $this->taxonomy);
            // Insert meta src
            if ($feed_source !== false) {
                add_term_meta($feed_source->term_id, 'rssfeeds_src', $feed->feed_url);
            }
        } else {
            $feed_source = get_term_by('id', $feed_id, $this->taxonomy);
        }

        // Create feed
        if ($feed_source === false) {
            wp_insert_term($feed_title, $this->taxonomy, array(
                'description' => $feed->get_description()
            ));
            $feed_source = get_term_by('name', $feed_title, $this->taxonomy);
            add_term_meta($feed_source->term_id, 'rssfeeds_src', $feed->feed_url);

            // Insert feed image if available
            if ($feed_image = $feed->get_image_url()) {
                // Upload image
                $src = media_sideload_image($feed_image, false, '', 'src');

                // Extract attachment id
                $att_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE guid='%s'",
                    $src
                ));

                add_term_meta($feed_source->term_id, 'rssfeeds_thumbnail', $att_id);
            }

        }
        return $feed_source;
    }

    public function create_post_from_feed_item($item, $feed_source) {
        $post_id = wp_insert_post(array(
            'post_title' => wp_strip_all_tags($item->get_title()),
            'post_content' => $item->get_content(),
            'post_status' => $this->importdraft ? 'draft' : 'publish',
            'post_type' => $this->posttype,
            'post_date' => $item->get_date('Y-m-d H:i:s')
        ));

        if (!is_numeric($post_id)) {
            return false;
        }

        // Set feed
        wp_set_post_terms($post_id, $feed_source->term_id, $this->taxonomy);

        // Enclosure
        if ($enclosure = $item->get_enclosure()) {
            $image = media_sideload_image($enclosure->link, $post_id, 'src');
            $this->set_first_image_as_thumbnail($post_id);
        }

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
                    $this->set_first_image_as_thumbnail($post_id);
                    if ($this->importonlyfirstimg) {
                        break;
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

    /* Set first image as thumbnail image
    -------------------------- */

    public function set_first_image_as_thumbnail($post_id) {
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

    /* ----------------------------------------------------------
      Admin menu
    ---------------------------------------------------------- */

    /* Settings link */

    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url($this->options['admin_slug'].'&page='. $this->options['plugin_pageslug']) . '">' . __('Settings') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function admin_menu() {
        add_submenu_page($this->options['admin_slug'], $this->options['plugin_name'] . ' - ' . __('Settings'), __('Import Settings', 'wpursstoposts'), $this->options['plugin_userlevel'], $this->options['plugin_pageslug'], array(&$this,
            'admin_settings'
        ), '', 110);

    }

    public function admin_settings() {

        echo '<div class="wrap"><h1>' . apply_filters('wpursstoposts_admin_page_title', get_admin_page_title()) . '</h1>';

        echo '<h2>' . __('Tools') . '</h2>';
        echo '<form action="' . admin_url('admin-post.php') . '" method="post">';
        echo '<input type="hidden" name="action" value="wpursstoposts_postaction">';
        $schedule = wp_next_scheduled($this->hookcron);
        $seconds = $schedule - time();
        $minutes = 0;
        if ($seconds >= 60) {
            $minutes = (int) ($seconds / 60);
            $seconds = $seconds % 60;
        }
        echo '<p>' . sprintf(__('Next automated import in %s’%s’’', 'wpursstoposts'), $minutes, $seconds) . '</p>';

        submit_button(__('Import now', 'wpursstoposts'), 'primary', 'import_now');

        echo '</form>';
        echo '<hr />';

        echo '<form action="' . admin_url('options.php') . '" method="post">';
        settings_fields($this->settings_details['option_id']);
        do_settings_sections($this->options['plugin_id']);
        echo submit_button(__('Save Changes', 'wpursstoposts'));
        echo '</form>';
        echo '</div>';
    }

    public function postAction() {
        if (isset($_POST['import_now'])) {
            set_time_limit(0);
            $this->parse_feeds();
        }

        wp_safe_redirect(wp_get_referer());
        die();
    }

    /* ----------------------------------------------------------
      Options for config
    ---------------------------------------------------------- */

    /* Taxo metas
    -------------------------- */

    public function set_wputaxometas_fields($fields) {
        $fields['rssfeeds_thumbnail'] = array(
            'label' => 'Thumbnail',
            'taxonomies' => array($this->taxonomy),
            'type' => 'attachment'
        );
        $fields['rssfeeds_src'] = array(
            'label' => 'Source',
            'taxonomies' => array($this->taxonomy),
            'type' => 'url'
        );
        return $fields;
    }

    /* Values
    -------------------------- */

    public function set_cache_duration() {
        return $this->cacheduration;
    }

    /* ----------------------------------------------------------
      Activation
    ---------------------------------------------------------- */

    public function install() {
        wp_clear_scheduled_hook($this->hookcron);
        wp_schedule_event(time() + 3600, 'hourly', $this->hookcron);
        flush_rewrite_rules();
    }

    public function deactivation() {
        wp_clear_scheduled_hook($this->hookcron);
        flush_rewrite_rules();
    }

}

$wpursstoposts = new wpursstoposts();

register_activation_hook(__FILE__, array(&$wpursstoposts,
    'install'
));
register_deactivation_hook(__FILE__, array(&$wpursstoposts,
    'deactivation'
));
