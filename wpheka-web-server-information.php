<?php
/**
 * Plugin Name: Web Server Information
 * Plugin URI: https://www.wpheka.com/product/php-information/
 * Description: The <code><strong>Web Server Information</strong></code> plugin allows you to check full information about your web server PHP/Mysql configurations including libraries, system type and OS version.
 * Version: 1.7
 * Author: WPHEKA
 * Author URI: https://www.wpheka.com
 * Text Domain: wpheka-web-server-information
 * Domain Path: /languages/
 * Requires at least: 4.8
 * Requires PHP: 8.1
 * Tested up to: 6.9.1
 * License: GPLv3 or later
 *
 * @package   WPHEKA_Web_Server_Info
 * @author    WPHEKA
 * @link      https://wpheka.com
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Required minimums and constants
 */
define('WPHEKA_WEB_SERVER_INFO_VERSION', '1.7');
define('WPHEKA_WEB_SERVER_INFO_MAIN_FILE', __FILE__);
define('WPHEKA_WEB_SERVER_INFO_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
define('WPHEKA_WEB_SERVER_INFO_PLUGIN_PATH', untrailingslashit(plugin_dir_path(__FILE__)));
define('WPHEKA_WEB_SERVER_INFO_MIN_FRAMEWORK', '1.0.0');

/*
 * The framework loads here, at include time, and never on a hook: the registry
 * has to resolve before plugins_loaded so the winning build is known to every
 * plugin that bundles one. is_readable() first, because a truncated upload on
 * shared hosting is a real failure mode and a bare require would take the whole
 * site down with it.
 */
if (is_readable(__DIR__ . '/framework/register.php')) {
    require_once __DIR__ . '/framework/register.php';
}

/**
 * Whether a usable framework booted.
 *
 * Every class this plugin touches is listed, not just the version. Bundling is
 * modular and another plugin's bundle can win, so a build can be new enough and
 * still lack a module this one needs.
 *
 * @since 1.8
 * @return bool
 */
function wpheka_web_server_info_framework_ready()
{
    if (!class_exists('WPHEKA_Framework_Versions', false)) {
        return false;
    }

    $active = WPHEKA_Framework_Versions::active_version('1');

    if (!is_string($active) || !version_compare($active, WPHEKA_WEB_SERVER_INFO_MIN_FRAMEWORK, '>=')) {
        return false;
    }

    foreach (array(
        '\\WPHEKA\\Framework\\V1\\Core\\Options',
        '\\WPHEKA\\Framework\\V1\\Core\\Lifecycle',
    ) as $class) {
        if (!class_exists($class)) {
            return false;
        }
    }

    return true;
}

/**
 * Settings storage, scoped by how the plugin was activated.
 *
 * @since 1.8
 * @return \WPHEKA\Framework\V1\Core\Options
 */
function wpheka_web_server_info_options()
{
    static $options = null;

    if (null === $options) {
        $options = \WPHEKA\Framework\V1\Core\Options::for_plugin(
            'wpheka_web_server_info_settings',
            array('version' => ''),
            plugin_basename(WPHEKA_WEB_SERVER_INFO_MAIN_FILE)
        );
    }

    return $options;
}

/**
 * Record the installed version. Idempotent: it runs on activation, on every
 * site of a network, and again on sites created afterwards.
 *
 * @since 1.8
 * @return void
 */
function wpheka_web_server_info_provision()
{
    wpheka_web_server_info_options()->update(array('version' => WPHEKA_WEB_SERVER_INFO_VERSION));
}

register_activation_hook(WPHEKA_WEB_SERVER_INFO_MAIN_FILE, 'wpheka_web_server_info_activate');
add_action('wp_initialize_site', 'wpheka_web_server_info_new_site', 100);

/**
 * Activation.
 *
 * @since 1.8
 * @param bool $network_wide Whether the plugin is being network-activated.
 * @return void
 */
function wpheka_web_server_info_activate($network_wide = false)
{
    if (!wpheka_web_server_info_framework_ready()) {
        return;
    }

    \WPHEKA\Framework\V1\Core\Lifecycle::activate('wpheka_web_server_info_provision', (bool) $network_wide);
}

/**
 * A site created after activation. Without this, sites added to a network later
 * never get provisioned and nothing says so.
 *
 * @since 1.8
 * @param mixed $site New site.
 * @return void
 */
function wpheka_web_server_info_new_site($site)
{
    if (!wpheka_web_server_info_framework_ready()) {
        return;
    }

    \WPHEKA\Framework\V1\Core\Lifecycle::on_new_site($site, WPHEKA_WEB_SERVER_INFO_MAIN_FILE, 'wpheka_web_server_info_provision');
}

/**
 * DOMDocument fallback notice.
 *
 * @since 1.0
 * @return string
 */
function wpheka_web_server_missing_domdocument_notice()
{
    /* translators: 1. URL link. */
    echo '<div class="error"><p><strong>' . sprintf(esc_html__('Web Server Information requires %s extension to be enabled.', 'wpheka-web-server-information'), '<a href="http://php.net/manual/en/class.domdocument.php" target="_blank">DOMDocument</a>') . '</strong></p></div>';
}

add_action('plugins_loaded', 'wpheka_web_server_info_init');

function wpheka_web_server_info_init()
{
    load_plugin_textdomain('wpheka-web-server-information', false, plugin_basename(dirname(__FILE__)) . '/languages');

    if (!class_exists('DOMDocument')) {
        add_action('admin_notices', 'wpheka_web_server_missing_domdocument_notice');
        return;
    }

    if (!class_exists('WPHEKA_Web_Server_Info')) :

        class WPHEKA_Web_Server_Info
        {

            /**
             * @var Singleton The reference the *Singleton* instance of this class
             */
            private static $instance;

            /**
             * Returns the *Singleton* instance of this class.
             *
             * @return Singleton The *Singleton* instance.
             */
            public static function get_instance()
            {
                if (null === self::$instance) {
                    self::$instance = new self();
                }
                return self::$instance;
            }

            /**
             * Private clone method to prevent cloning of the instance of the
             * *Singleton* instance.
             *
             * @return void
             */
            private function __clone()
            {
            }

            /**
             * Private unserialize method to prevent unserializing of the *Singleton*
             * instance.
             *
             * @return void
             */
            public function __wakeup()
            {
            }

            /**
             * Protected constructor to prevent creating a new instance of the
             * *Singleton* via the `new` operator from outside of this class.
             */
            private function __construct()
            {
                $this->init();
            }

            /**
             * Init the plugin after plugins_loaded so environment variables are set.
             *
             * @since 1.0.0
             * @version 1.0
             */
            public function init()
            {
                if (is_admin()) {
                    require_once dirname(__FILE__) . '/includes/class-wpheka-web-server-info-admin.php';
                }
                add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
            }

            /**
             * Updates the plugin version in db
             *
             * @since 1.0
             * @version 1.0
             */
            public function update_plugin_version()
            {
                if (!wpheka_web_server_info_framework_ready()) {
                    return;
                }

                wpheka_web_server_info_provision();
            }

            /**
             * Handles upgrade routines.
             *
             * @since 1.0
             * @version 1.0
             */
            public function install()
            {
                /*
                 * Retained for anything that calls it. The version stamp is now
                 * written by the activation hook via Lifecycle, so this no
                 * longer needs to run on every admin request -- which is what
                 * the admin_init binding it used to carry was compensating for,
                 * in the absence of an activation hook.
                 *
                 * The legacy wpheka_web_server_info_version option is left
                 * where it is rather than migrated. Nothing has ever read it;
                 * it is a write-only stamp, so carrying it across would be
                 * ceremony rather than care.
                 */
                $this->update_plugin_version();
            }

            /**
             * Add plugin action links.
             *
             * @since 1.0
             * @version 1.0
             */
            public function plugin_action_links($links)
            {
                $plugin_links = array(
                    '<a href="admin.php?page=wpheka-information&tab=webserver">' . esc_html__('Information Page', 'wpheka-web-server-information') . '</a>',
                );
                return array_merge($plugin_links, $links);
            }
        }

        WPHEKA_Web_Server_Info::get_instance();
    endif;
}
