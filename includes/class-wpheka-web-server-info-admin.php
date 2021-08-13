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

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPHEKA_Web_Server_Info_Admin', false ) ) :

	/**
	 * WPHEKA_Web_Server_Info_Admin Class.
	 */
	class WPHEKA_Web_Server_Info_Admin {

		/**
		 * Setting tabs.
		 *
		 * @var array
		 */
		private $tabs = array();

		/**
		 * WPHEKA_Web_Server_Info_Admin Constructor.
		 */
		public function __construct() {

			$this->tabs = apply_filters(
				'wpheka_web_server_info_tabs_array',
				array(
					'webserver'       => __( 'Overview', 'wpheka-web-server-information' ),
					'phpinfo'   => __( 'PHP Information', 'wpheka-web-server-information' ),
					'dbinfo'   => __( 'Database Information', 'wpheka-web-server-information' ),
				)
			);

			// Admin Menu.
			add_action( 'admin_menu', array( &$this, 'wpheka_web_server_info_menu' ) );

			// admin script and style.
			add_action( 'admin_enqueue_scripts', array( &$this, 'wpheka_web_server_info_admin_scripts_styles' ) );

			// Tabs.
			add_action( 'info_page_webserver_tab_init', array( &$this, 'tab_init' ), 10, 1 );
			add_action( 'info_page_phpinfo_tab_init', array( &$this, 'tab_init' ), 10, 1 );
			add_action( 'info_page_dbinfo_tab_init', array( &$this, 'tab_init' ), 10, 1 );

			// Display php/db info in footer.
			add_filter( 'update_footer', array( $this, 'version_info_in_footer' ), 11 );
		}

		/**
		 * Admin Scripts
		 */
		public function wpheka_web_server_info_admin_scripts_styles() {
			$screen    = get_current_screen();
			$screen_id = $screen ? $screen->id : '';
			$suffix    = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			if ( 'wpheka_page_wpheka-information' == $screen_id ) {
				wp_enqueue_style( 'wpheka_web_server_info_admin_css', WPHEKA_WEB_SERVER_INFO_PLUGIN_URL . '/assets/css/admin.css', array(), WPHEKA_WEB_SERVER_INFO_VERSION );
			}
		}

		public function wpheka_web_server_info_menu() {
			global $admin_page_hooks;

			if ( ! isset( $admin_page_hooks['wpheka_plugin_panel'] ) ) {
				$position   = apply_filters( 'wpheka_plugins_menu_item_position', '55.5' );
				$capability = apply_filters( 'wpheka_plugin_panel_menu_page_capability', 'manage_options' );
				$show       = apply_filters( 'wpheka_plugin_panel_menu_page_show', true );

				// WPHEKA text must not be translated.
				if ( ! ! $show ) {
					add_menu_page( 'wpheka_plugin_panel', 'WPHEKA', $capability, 'wpheka_plugin_panel', null, untrailingslashit( plugins_url( '/assets/images/wp-heka-menu-icon-22.svg', WPHEKA_WEB_SERVER_INFO_MAIN_FILE ) ), $position );
				}
			}

			$active_tab = empty( $_GET['tab'] ) ? 'webserver' : sanitize_title( wp_unslash( $_GET['tab'] ) );
			$active_tab_label = $this->tabs[ $active_tab ];

			add_submenu_page(
				'wpheka_plugin_panel',
				$active_tab_label,
				'Web Server Information',
				'manage_options',
				'wpheka-information',
				array( $this, 'wpheka_web_server_info_page_callback' )
			);

			/* === Duplicate Items Hack === */
			remove_submenu_page( 'wpheka_plugin_panel', 'wpheka_plugin_panel' );
		}

		/**
		 * Info page callback
		 */
		public function wpheka_web_server_info_page_callback() {
			$active_tab = empty( $_GET['tab'] ) ? 'webserver' : sanitize_title( wp_unslash( $_GET['tab'] ) );
			?>
				<div class="wrap webserver-info">
					<h2><?php _e( 'WPHEKA Web Server Information', 'wpheka-web-server-information' ); ?></h2>
					<h2 class="nav-tab-wrapper">
					<?php
					foreach ( $this->tabs as $tab_slug => $tab ) {
						$tab_url = admin_url( 'admin.php?page=wpheka-information&tab=' . $tab_slug );
						$active_tab_class = '';
						if ( $active_tab == $tab_slug ) {
							$active_tab_class = 'nav-tab-active';
						}
						echo '<a class="nav-tab ' . $active_tab_class . '" href="' . $tab_url . '">' . $tab . '</a>';
					}
					?>
					</h2>
					<div class="webserver-info-wrap">
						<?php do_action( "info_page_{$active_tab}_tab_init", $active_tab ); ?>
					</div>
				</div>
			<?php
			do_action( 'wpheka_web_server_info_admin_footer' );
		}

		/**
		 * Init tab
		 */
		public function tab_init( $tab ) {
			$this->load_tab_class( $tab );
			$tab_class_name = 'WPHEKA_Info_Admin_' . ucfirst( $tab );
			new $tab_class_name( $tab );
		}

		/**
		 * Load tab class
		 */
		public function load_tab_class( $tab_class_name = '' ) {
			$admin_settings_token = 'wpheka-info-admin';
			if ( '' != $tab_class_name ) {
				require_once WPHEKA_WEB_SERVER_INFO_PLUGIN_PATH . '/includes/class-' . $admin_settings_token . '-' . $tab_class_name . '.php';
			}
		}

		/**
		 * Display version info in footer
		 */
		public function version_info_in_footer() {
			global $wpdb;
			$update     = core_update_footer();
			$wp_version = strpos( $update, '<strong>' ) === 0 ? get_bloginfo( 'version' ) . ' (' . $update . ')' : get_bloginfo( 'version' );

			return sprintf( esc_attr__( 'You are running WordPress %1$s  | PHP %2$s | %3$s | MySQL %4$s', 'version-info' ), $wp_version, phpversion(), $_SERVER['SERVER_SOFTWARE'], $wpdb->get_var( 'SELECT VERSION();' ) );
		}

	}

endif;

new WPHEKA_Web_Server_Info_Admin();
