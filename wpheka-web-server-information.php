<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- the main class lives in the main plugin file.
/**
 * Plugin Name: Web Server Information
 * Plugin URI: https://www.wpheka.com/product/web-server-information/
 * Description: The <code><strong>Web Server Information</strong></code> plugin allows you to check full information about your web server PHP/Mysql configurations including libraries, system type and OS version.
 * Version: 1.9.1
 * Author: WPHEKA
 * Author URI: https://www.wpheka.com
 * Text Domain: wpheka-web-server-information
 * Domain Path: /languages/
 * Requires at least: 5.1
 * Requires PHP: 8.1
 * Tested up to: 7.1.3
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
define( 'WPHEKA_WEB_SERVER_INFO_VERSION', '1.9.1' );
define( 'WPHEKA_WEB_SERVER_INFO_MAIN_FILE', __FILE__ );
define( 'WPHEKA_WEB_SERVER_INFO_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WPHEKA_WEB_SERVER_INFO_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WPHEKA_WEB_SERVER_INFO_MIN_FRAMEWORK', '1.0.0' );

/*
 * The framework loads here, at include time, and never on a hook: the registry
 * has to resolve before plugins_loaded so the winning build is known to every
 * plugin that bundles one. is_readable() first, because a truncated upload on
 * shared hosting is a real failure mode and a bare require would take the whole
 * site down with it.
 */
if ( is_readable( __DIR__ . '/framework/register.php' ) ) {
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
function wpheka_web_server_info_framework_ready() {
	if ( ! class_exists( 'WPHEKA_Framework_Versions', false ) ) {
		return false;
	}

	$active = WPHEKA_Framework_Versions::active_version( '1' );

	if ( ! is_string( $active ) || ! version_compare( $active, WPHEKA_WEB_SERVER_INFO_MIN_FRAMEWORK, '>=' ) ) {
		return false;
	}

	/*
	 * Every framework class this plugin touches, not just the version. A
	 * version check passes while the module set is still incomplete, because
	 * bundling is modular and another plugin's bundle can win.
	 *
	 * Admin\Menu is on this list because wpheka_web_server_info_menu() calls
	 * `new Admin\Menu` after consulting this guard. Settings and Field living
	 * in the same module is not enough: a bundle built before ADR-028 carries
	 * Admin/Settings.php and Admin/Field.php and no Admin/Menu.php, so the
	 * guard passed and the constructor fataled on admin_menu -- a white screen
	 * in wp-admin, from a guard that had already said yes.
	 */
	foreach ( array(
		'\\WPHEKA\\Framework\\V1\\Core\\Options',
		'\\WPHEKA\\Framework\\V1\\Core\\Lifecycle',
		'\\WPHEKA\\Framework\\V1\\Admin\\Settings',
		'\\WPHEKA\\Framework\\V1\\Admin\\Field',
		'\\WPHEKA\\Framework\\V1\\Admin\\Menu',
	) as $class ) {
		if ( ! class_exists( $class ) ) {
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
function wpheka_web_server_info_options() {
	static $options = null;

	if ( null === $options ) {
		$options = \WPHEKA\Framework\V1\Core\Options::for_plugin(
			'wpheka_web_server_info_settings',
			array(
				'version'     => '',
				'footer_info' => true,
			),
			plugin_basename( WPHEKA_WEB_SERVER_INFO_MAIN_FILE )
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
function wpheka_web_server_info_provision() {
	/*
	 * Not wpheka_web_server_info_options(). WordPress fires the activation hook
	 * *before* it writes active_sitewide_plugins -- activate_plugin() does
	 * do_action( "activate_{$plugin}" ) and only then update_site_option() --
	 * so during activation Options::for_plugin() cannot see that this is a
	 * network activation and resolves per-site scope.
	 *
	 * Left alone it writes a per-site row on every site while runtime reads the
	 * network row, and nothing reports the mismatch: the settings simply read
	 * back empty. Reproduced on a five-site network before this was fixed.
	 *
	 * So the scope is passed explicitly, which is what for_plugin()'s fourth
	 * argument is for. Outside activation the flag is null and detection is
	 * correct again -- notably on wp_initialize_site, which runs long after
	 * active_sitewide_plugins was written.
	 */
	$network_activated = isset( $GLOBALS['wpheka_web_server_info_network_activating'] )
		? (bool) $GLOBALS['wpheka_web_server_info_network_activating']
		: null;

	$options = \WPHEKA\Framework\V1\Core\Options::for_plugin(
		'wpheka_web_server_info_settings',
		array(
			'version'     => '',
			'footer_info' => true,
		),
		plugin_basename( WPHEKA_WEB_SERVER_INFO_MAIN_FILE ),
		$network_activated
	);

	$options->update( array( 'version' => WPHEKA_WEB_SERVER_INFO_VERSION ) );
}

/**
 * The settings schema.
 *
 * Built on demand rather than at include time: the labels translate, and
 * WordPress 6.7+ silently refuses to translate a string requested before init.
 *
 * @since 1.8
 * @return \WPHEKA\Framework\V1\Admin\Settings
 */
function wpheka_web_server_info_schema() {
	$field    = '\WPHEKA\Framework\V1\Admin\Field';
	$settings = new \WPHEKA\Framework\V1\Admin\Settings();

	$settings->section( 'display', __( 'Display', 'wpheka-web-server-information' ) );
	$settings->add(
		( new $field( 'footer_info', $field::TOGGLE, __( 'Show server details in the admin footer', 'wpheka-web-server-information' ) ) )
			->describe( __( 'Replaces the WordPress version text in the footer of every admin page with the WordPress, PHP, server and MySQL versions.', 'wpheka-web-server-information' ) )
			->default_to( true )
	);

	return $settings;
}

/**
 * Whether the admin footer replacement is switched on.
 *
 * Defaults to true, which is what the plugin did unconditionally before the
 * setting existed. Defaulting to false would silently change what every
 * existing install shows after an update.
 *
 * @since 1.8
 * @return bool
 */
function wpheka_web_server_info_footer_enabled() {
	if ( ! wpheka_web_server_info_framework_ready() ) {
		return true;
	}

	return (bool) wpheka_web_server_info_options()->get( 'footer_info', true );
}

/**
 * The capability that opens the information page, saves its setting and sees the
 * versions in the admin footer.
 *
 * One place for all three. The page used to follow the shared WPHEKA menu filter
 * while the save always needed manage_options, so a site that lowered the filter
 * let those users read phpinfo() but not save. Same filter and fallback as the
 * framework's Menu::capability(), so the page and its menu entry always agree.
 *
 * @since 1.9
 * @return string
 */
function wpheka_web_server_info_capability() {
	$capability = apply_filters( 'wpheka_plugin_panel_menu_page_capability', 'manage_options' );

	return is_string( $capability ) && '' !== $capability ? $capability : 'manage_options';
}

/**
 * Whether the current user may change the setting.
 *
 * A network-activated plugin keeps the setting once for the whole network, so
 * changing it there needs a network admin, not any site's admin.
 *
 * @since 1.9
 * @return bool
 */
function wpheka_web_server_info_can_save() {
	if ( ! current_user_can( wpheka_web_server_info_capability() ) ) {
		return false;
	}

	if (
		wpheka_web_server_info_framework_ready()
		&& \WPHEKA\Framework\V1\Core\Options::SCOPE_NETWORK === wpheka_web_server_info_options()->scope()
	) {
		return current_user_can( 'manage_network_options' );
	}

	return true;
}

/**
 * The review form on wordpress.org: one link for the dashboard prompt and the page.
 *
 * @since 1.9
 * @return string
 */
function wpheka_web_server_info_review_url() {
	return 'https://wordpress.org/support/plugin/wpheka-web-server-information/reviews/#new-post';
}

/**
 * Save the settings form posted from the Overview tab (admin-post, wpheka_wsi_save).
 *
 * @since 1.8
 * @return void
 */
function wpheka_web_server_info_save_settings() {
	if ( ! wpheka_web_server_info_can_save() ) {
		wp_die( esc_html__( 'You do not have permission to change these settings.', 'wpheka-web-server-information' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'wpheka_wsi_save' );

	$back = array(
		'page' => 'wpheka-information',
		'tab'  => 'webserver',
	);

	if ( ! wpheka_web_server_info_framework_ready() ) {
		wp_safe_redirect( add_query_arg( $back + array( 'w-error' => 'framework' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	// The kit's switch posts "yes" when on and "no" when off; the schema casts the result.
	$on    = isset( $_POST['wpheka_wsi_footer_info'] ) && 'yes' === sanitize_key( wp_unslash( $_POST['wpheka_wsi_footer_info'] ) );
	$clean = wpheka_web_server_info_schema()->sanitize( array( 'footer_info' => $on ? '1' : '' ) );

	wpheka_web_server_info_options()->update( $clean );

	// Redirect so a refresh does not repost the form.
	wp_safe_redirect( add_query_arg( $back + array( 'w-saved' => '1' ), admin_url( 'admin.php' ) ) );
	exit;
}

register_activation_hook( WPHEKA_WEB_SERVER_INFO_MAIN_FILE, 'wpheka_web_server_info_activate' );
add_action( 'wp_initialize_site', 'wpheka_web_server_info_new_site', 100 );

/**
 * Activation.
 *
 * @since 1.8
 * @param bool $network_wide Whether the plugin is being network-activated.
 * @return void
 */
function wpheka_web_server_info_activate( $network_wide = false ) {
	if ( ! wpheka_web_server_info_framework_ready() ) {
		return;
	}

	// Threaded through a global because Lifecycle::activate() takes a callable
	// with no arguments, and provisioning has to know the scope. See the note
	// in wpheka_web_server_info_provision().
	$GLOBALS['wpheka_web_server_info_network_activating'] = (bool) $network_wide;

	try {
		\WPHEKA\Framework\V1\Core\Lifecycle::activate( 'wpheka_web_server_info_provision', (bool) $network_wide );
	} finally {
		unset( $GLOBALS['wpheka_web_server_info_network_activating'] );
	}
}

/**
 * A site created after activation. Without this, sites added to a network later
 * never get provisioned and nothing says so.
 *
 * @since 1.8
 * @param mixed $site New site.
 * @return void
 */
function wpheka_web_server_info_new_site( $site ) {
	if ( ! wpheka_web_server_info_framework_ready() ) {
		return;
	}

	\WPHEKA\Framework\V1\Core\Lifecycle::on_new_site( $site, WPHEKA_WEB_SERVER_INFO_MAIN_FILE, 'wpheka_web_server_info_provision' );
}

/**
 * DOMDocument fallback notice.
 *
 * @since 1.0
 * @return void
 */
function wpheka_web_server_missing_domdocument_notice() {
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Web Server Information requires %s extension to be enabled.', 'wpheka-web-server-information' ), '<a href="https://www.php.net/manual/en/class.domdocument.php" target="_blank" rel="noopener">DOMDocument</a>' ) . '</strong></p></div>';
}

add_action( 'plugins_loaded', 'wpheka_web_server_info_init' );

/**
 * Load the plugin once WordPress and the other plugins are loaded.
 *
 * @since 1.0
 * @return void
 */
function wpheka_web_server_info_init() {
	load_plugin_textdomain( 'wpheka-web-server-information', false, plugin_basename( __DIR__ ) . '/languages' );

	if ( ! class_exists( 'DOMDocument' ) ) {
		add_action( 'admin_notices', 'wpheka_web_server_missing_domdocument_notice' );
		return;
	}

	if ( ! class_exists( 'WPHEKA_Web_Server_Info' ) ) :

		/**
		 * The plugin's main class.
		 */
		class WPHEKA_Web_Server_Info {

			/**
			 * The single instance of this class.
			 *
			 * @var WPHEKA_Web_Server_Info|null
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
			private function __clone() {
			}

			/**
			 * Private unserialize method to prevent unserializing of the *Singleton*
			 * instance.
			 *
			 * @return void
			 */
			public function __wakeup() {
			}

			/**
			 * Protected constructor to prevent creating a new instance of the
			 * *Singleton* via the `new` operator from outside of this class.
			 */
			private function __construct() {
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
					require_once __DIR__ . '/includes/wpheka-ui/load.php';
					require_once __DIR__ . '/includes/class-wpheka-web-server-info-page.php';
					require_once __DIR__ . '/includes/class-wpheka-web-server-info-admin.php';
					WPHEKA_Web_Server_Info_Page::init();
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
				if ( ! wpheka_web_server_info_framework_ready() ) {
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
			public function install() {
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
			 * @param array $links Links on the Plugins screen.
			 * @return array
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
