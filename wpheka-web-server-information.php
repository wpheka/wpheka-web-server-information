<?php
/**
 * Plugin Name: Web Server Information
 * Plugin URI: https://www.wpheka.com/product/php-information/
 * Description: The <code><strong>Web Server Information</strong></code> plugin allows you to check full information about your web server PHP/Mysql configurations including libraries, system type and OS version.
 * Version: 1.1
 * Author: WPHEKA
 * Author URI: https://www.wpheka.com
 * Text Domain: wpheka-web-server-information
 * Domain Path: /languages/
 * Requires at least: 4.8
 * Tested up to: 5.8
 * License: GPLv3 or later
 *
 * @package   WPHEKA_Web_Server_Info
 * @author    WPHEKA
 * @link      https://wpheka.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Required minimums and constants
 */
define( 'WPHEKA_WEB_SERVER_INFO_VERSION', '1.1' );
define( 'WPHEKA_WEB_SERVER_INFO_MAIN_FILE', __FILE__ );
define( 'WPHEKA_WEB_SERVER_INFO_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WPHEKA_WEB_SERVER_INFO_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

/**
 * DOMDocument fallback notice.
 *
 * @since 1.0
 * @return string
 */
function wpheka_web_server_missing_domdocument_notice() {
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Web Server Information requires %s extension to be enabled.', 'wpheka-web-server-information' ), '<a href="http://php.net/manual/en/class.domdocument.php" target="_blank">DOMDocument</a>' ) . '</strong></p></div>';
}

add_action( 'plugins_loaded', 'wpheka_web_server_info_init' );

function wpheka_web_server_info_init() {
	load_plugin_textdomain( 'wpheka-web-server-information', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

	if ( ! class_exists( 'DOMDocument' ) ) {
		add_action( 'admin_notices', 'wpheka_web_server_missing_domdocument_notice' );
		return;
	}

	if ( ! class_exists( 'WPHEKA_Web_Server_Info' ) ) :

		class WPHEKA_Web_Server_Info {

			/**
			 * @var Singleton The reference the *Singleton* instance of this class
			 */
			private static $instance;

			/**
			 * Returns the *Singleton* instance of this class.
			 *
			 * @return Singleton The *Singleton* instance.
			 */
			public static function get_instance() {
				if ( null === self::$instance ) {
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
			private function __clone() {}

			/**
			 * Private unserialize method to prevent unserializing of the *Singleton*
			 * instance.
			 *
			 * @return void
			 */
			private function __wakeup() {}

			/**
			 * Protected constructor to prevent creating a new instance of the
			 * *Singleton* via the `new` operator from outside of this class.
			 */
			private function __construct() {
				add_action( 'admin_init', array( $this, 'install' ) );
				$this->init();
			}

			/**
			 * Init the plugin after plugins_loaded so environment variables are set.
			 *
			 * @since 1.0.0
			 * @version 1.0
			 */
			public function init() {
				if ( is_admin() ) {
					require_once dirname( __FILE__ ) . '/includes/class-wpheka-web-server-info-admin.php';
				}
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			}

			/**
			 * Updates the plugin version in db
			 *
			 * @since 1.0
			 * @version 1.0
			 */
			public function update_plugin_version() {
				delete_option( 'wpheka_web_server_info_version' );
				update_option( 'wpheka_web_server_info_version', WPHEKA_WEB_SERVER_INFO_VERSION );
			}

			/**
			 * Handles upgrade routines.
			 *
			 * @since 1.0
			 * @version 1.0
			 */
			public function install() {
				if ( ! is_plugin_active( plugin_basename( __FILE__ ) ) ) {
					return;
				}

				$this->update_plugin_version();
			}

			/**
			 * Add plugin action links.
			 *
			 * @since 1.0
			 * @version 1.0
			 */
			public function plugin_action_links( $links ) {
				$plugin_links = array(
					'<a href="admin.php?page=wpheka-information&tab=webserver">' . esc_html__( 'Information Page', 'wpheka-web-server-information' ) . '</a>',
				);
				return array_merge( $plugin_links, $links );
			}
		}

		WPHEKA_Web_Server_Info::get_instance();
	endif;
}
