<?php
/**
 * WPHEKA_Web_Server_Info
 *
 * @package WPHEKA_Web_Server_Info
 * @author      WPHEKA
 * @link        https://www.wpheka.com
 * @since       1.0
 * @version     1.0
 */

defined('ABSPATH') || exit;

if (! class_exists('WPHEKA_Web_Server_Info_Admin', false)) :

    /**
     * WPHEKA_Web_Server_Info_Admin Class.
     */
    class WPHEKA_Web_Server_Info_Admin
    {

        /**
         * Setting tabs.
         *
         * @var array
         */
        private $tabs = array();

        /**
         * WPHEKA_Web_Server_Info_Admin Constructor.
         */
        public function __construct()
        {
            $this->tabs = apply_filters(
                'wpheka_web_server_info_tabs_array',
                array(
                    'webserver' => __('Overview', 'wpheka-web-server-information'),
                    'phpinfo'   => __('PHP Information', 'wpheka-web-server-information'),
                    'dbinfo'    => __('Database Information', 'wpheka-web-server-information'),
                )
            );

            // Admin Menu.
            add_action('admin_menu', array( &$this, 'wpheka_web_server_info_menu' ));

            // admin script and style.
            add_action('admin_enqueue_scripts', array( &$this, 'wpheka_web_server_info_admin_scripts_styles' ));

            // Tabs.
            add_action('info_page_webserver_tab_init', array( &$this, 'tab_init' ), 10, 1);
            add_action('info_page_phpinfo_tab_init', array( &$this, 'tab_init' ), 10, 1);
            add_action('info_page_dbinfo_tab_init', array( &$this, 'tab_init' ), 10, 1);

            // Display php/db info in footer, if the setting allows it. Checked
            // here rather than inside the callback so that when it is off, the
            // filter is never attached and the version query never runs.
            if (wpheka_web_server_info_footer_enabled()) {
                add_filter('update_footer', array( $this, 'version_info_in_footer' ), 11);
            }
        }

        /**
         * Admin Scripts
         */
        public function wpheka_web_server_info_admin_scripts_styles()
        {
            $screen    = get_current_screen();
            $screen_id = $screen ? $screen->id : '';
            $suffix    = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

            if ('wpheka_page_wpheka-information' == $screen_id) {
                wp_enqueue_style('wpheka_web_server_info_admin_css', WPHEKA_WEB_SERVER_INFO_PLUGIN_URL . '/assets/css/admin.css', array(), WPHEKA_WEB_SERVER_INFO_VERSION);
            }
        }

        public function wpheka_web_server_info_menu()
        {
            /*
             * The shared WPHEKA parent is coordinated by the framework now
             * (ADR-028), which replaces the fifteen lines this method used to
             * carry -- the same fifteen lines four sibling plugins still carry.
             *
             * The capability is not restated. add_page() inherits the parent's,
             * which is the point: this method previously filtered the parent's
             * capability and then hardcoded manage_options on its own submenu,
             * so the two disagreed the moment anyone used the filter.
             *
             * The page title no longer varies by tab. It used to read $_GET, so
             * menu registration depended on request state; the active tab is
             * already the heading on the page itself.
             */
            if (!wpheka_web_server_info_framework_ready()) {
                return;
            }

            $menu = new \WPHEKA\Framework\V1\Admin\Menu(
                untrailingslashit(plugins_url('/assets/images/wp-heka-menu-icon-22.svg', WPHEKA_WEB_SERVER_INFO_MAIN_FILE))
            );

            $menu->add_page(
                __('Web Server Information', 'wpheka-web-server-information'),
                __('Web Server Information', 'wpheka-web-server-information'),
                'wpheka-information',
                array( $this, 'wpheka_web_server_info_page_callback' )
            );

            /*
             * The "Duplicate Items Hack" that stood here is gone. WordPress
             * still mirrors the parent as its own first submenu; the framework
             * adapter removes it once, at PHP_INT_MAX, so every plugin sharing
             * the parent no longer needs its own copy of the removal (ADR-028).
             */
        }

        /**
         * Info page callback
         */
        public function wpheka_web_server_info_page_callback()
        {
            // Tab navigation, not a state change: the value only selects which
            // read-only panel renders, and it is passed through sanitize_title().
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only, no state change.
            $active_tab = empty($_GET['tab']) ? 'webserver' : sanitize_title(wp_unslash($_GET['tab']));
            ?>
                <div class="wrap webserver-info">
                    <h2><?php esc_html_e('WPHEKA Web Server Information', 'wpheka-web-server-information'); ?></h2>
                    <h2 class="nav-tab-wrapper">
                    <?php
                    foreach ($this->tabs as $tab_slug => $tab) {
                        $tab_url = admin_url('admin.php?page=wpheka-information&tab=' . $tab_slug);
                        $active_tab_class = '';
                        if ($active_tab == $tab_slug) {
                            $active_tab_class = 'nav-tab-active';
                        }
                        echo '<a class="nav-tab ' . esc_attr($active_tab_class) . '" href="' . esc_url($tab_url) . '">' . esc_html($tab) . '</a>';
                    }
                    ?>
                    </h2>
                    <div class="webserver-info-wrap">
                        <?php do_action("info_page_{$active_tab}_tab_init", $active_tab); ?>
                    </div>
                </div>
            <?php
            do_action('wpheka_web_server_info_admin_footer');
        }

        /**
         * Init tab
         */
        public function tab_init($tab)
        {
            $this->load_tab_class($tab);
            $tab_class_name = 'WPHEKA_Info_Admin_' . ucfirst($tab);
            new $tab_class_name($tab);
        }

        /**
         * Load tab class
         */
        public function load_tab_class($tab_class_name = '')
        {
            $admin_settings_token = 'wpheka-info-admin';
            if ('' != $tab_class_name) {
                require_once WPHEKA_WEB_SERVER_INFO_PLUGIN_PATH . '/includes/class-' . $admin_settings_token . '-' . $tab_class_name . '.php';
            }
        }

        /**
         * Display version info in footer
         */
        public function version_info_in_footer()
        {
            global $wpdb;
            $update     = core_update_footer();
            $wp_version = strpos($update, '<strong>') === 0 ? get_bloginfo('version') . ' (' . $update . ')' : get_bloginfo('version');

            return sprintf(
                /* translators: 1: WordPress version, 2: PHP version, 3: server software, 4: MySQL version. */
                esc_attr__('You are running WordPress %1$s | PHP %2$s | %3$s | MySQL %4$s', 'wpheka-web-server-information'),
                $wp_version,
                phpversion(),
                empty($_SERVER['SERVER_SOFTWARE']) ? esc_html__('Unknown', 'wpheka-web-server-information') : sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])),
                $wpdb->get_var('SELECT VERSION();')
            );
        }
    }

endif;

new WPHEKA_Web_Server_Info_Admin();
