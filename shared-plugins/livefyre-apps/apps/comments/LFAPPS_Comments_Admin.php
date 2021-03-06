<?php

//Disallow direct access to this file
if (!defined('LFAPPS__PLUGIN_PATH'))
    die('Bye');

if (!class_exists('LFAPPS_Comments_Admin')) {

    class LFAPPS_Comments_Admin {

        private static $initiated = false;

        public static function init() {
            if (!self::$initiated) {
                self::$initiated = true;
                self::init_hooks();
            }
        }

        /**
         * Initialise WP hooks
         */
        private static function init_hooks() {
            add_action('admin_menu', array('LFAPPS_Comments_Admin', 'init_admin_menu'));
            add_action('admin_enqueue_scripts', array('LFAPPS_Comments_Admin', 'load_resources'));
        }

        /**
         * Initialise admin menu items
         */
        public static function init_admin_menu() {
            add_submenu_page('livefyre_apps', 'Comments', 'Comments', "manage_options", 'livefyre_apps_comments', array('LFAPPS_Comments_Admin', 'menu_comments'));
        }

        /**
         * Add assets required by Livefyre Apps Admin section
         */
        public static function load_resources() {
            wp_register_style('lfapps_comments.css', LFAPPS__PLUGIN_URL . 'apps/comments/assets/css/lfapps_comments.css', array(), LFAPPS__VERSION);
            wp_enqueue_style('lfapps_comments.css');
        }
        
        /**
         * Run Livefyre Comments page
         */
        public static function menu_comments() {
            //hide import message call
            if (isset($_GET['hide_import_message'])) {
                update_option('livefyre_apps-livefyre_import_status', 'complete');
                wp_redirect(Livefyre_Apps_Admin::get_page_url('livefyre_apps_comments'));
                exit;
            }

            //check for import status updates
            if ( isset( $_GET['status'] ) ) {
                update_option('livefyre_apps-'. 'livefyre_import_status', sanitize_text_field( $_GET['status'] ) );
                if ( isset( $_GET['message'] ) ) {
                    update_option('livefyre_apps-'. 'livefyre_import_message', urldecode( sanitize_text_field( $_GET['message'] ) ) );
                }
            }
            
            if( isset($_GET['allow_comments_id']) ) {
                $allow_id = sanitize_text_field( $_GET['allow_comments_id'] );

                if ( $allow_id == 'all_posts' ) {
                    self::update_posts_nc( false );
                }
                else if ( $allow_id == 'all_pages' ) {
                    self::update_posts_nc( false );
                }
                else {
                    self::update_posts_nc( $allow_id, false );
                }
            }
            
            //process settings form
            if (isset($_POST['livefyre_comments_general']) && check_admin_referer('form-livefyre_comments_general')) {
                $excludes = array('_builtin' => false);
                $post_types = get_post_types($args = $excludes);
                $post_types = array_merge(array('post' => 'post', 'page' => 'page'), $post_types);
                foreach ($post_types as $post_type) {
                    $post_type_name = 'livefyre_display_' . $post_type;
                    if (isset($_POST[$post_type_name])) {
                        update_option('livefyre_apps-'.$post_type_name, true);
                    } else {
                        update_option('livefyre_apps-'.$post_type_name, false);
                    }
                }
                Livefyre_Apps::$form_saved = true;
            }
            LFAPPS_View::render('general', array(), 'comments');
        }
                
        /**
         * Set posts to allow comments
         * @param mixed $id
         * @param string $post_type
         */
        public static function update_posts_nc( $id ) {
            wp_update_post( array( 'ID' => $id, 'comment_status' => 'open' ) );
        }
        
        /**
         * Select posts where comments are closed
         * @param string $post_type
         * @return mixed
         */
        public static function select_nc_posts($post_type) {
            $args = array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => 50
            );

            add_filter('posts_where', array('LFAPPS_Comments_Admin', 'posts_where_nc'));
            $query = new WP_Query($args);
            remove_filter('posts_where', array('LFAPPS_Comments_Admin', 'posts_where_nc'));
            $posts = $query->posts;
            return $posts;
        }

        /**
         * Filter for posts with closed comments
         * @global type $wpdb
         * @param string $where_clause
         * @return string
         */
        public static function posts_where_nc($where_clause) {
            global $wpdb;
            return $where_clause .= " AND comment_status = 'closed'";
        }

        /**
         * Get conflicting plugins that are active
         * @return array
         */
        public static function get_conflicting_plugins() {
            $plugins = array();
            foreach (self::$conflicting_plugins as $key => $value) {
                if (is_plugin_active($key)) {
                    $plugins[$key] = $value;
                }
            }
            return $plugins;
        }

        public static $conflicting_plugins = Array(
            'disqus-comment-system/disqus.php' => 'Disqus: Commenting plugin.',
            'cloudflare/cloudflare.php' => 'Cloudflare: May impact the look of the widget on the page. Be sure to turn off Rocket Loader in
                your <a href="https://support.cloudflare.com/entries/22088538-How-do-I-access-my-CloudFlare-Performance-Settings-" target="_blank">CloudFlare settings</a>!',
            'spam-free-wordpress/tl-spam-free-wordpress.php' => 'Spam Free: Disables 3rd party commenting widgets.',
        );

        /**
         * Get list of post types where LiveChat is enabled
         * @return array
         */
        public static function get_chat_display_post_types() {
            $used_types = array();
            if(Livefyre_Apps::is_app_enabled('chat')) {
                $excludes = array( '_builtin' => false );
                $post_types = get_post_types($excludes );
                $post_types = array_merge(array('post'=>'post', 'page'=>'page'), $post_types);
                foreach ($post_types as $post_type ) {
                    $post_type_name = 'livefyre_chat_display_' .$post_type;
                    if(get_option('livefyre_apps-'.$post_type_name) === true || get_option('livefyre_apps-'.$post_type_name) === 'true') {
                        $used_types[$post_type_name] = $post_type_name;
                    } 
                }
            }
            return $used_types;
        }
    }

}