<?php
/**
 * WPHEKA > Server Info: the plugin's page, with Overview, PHP, Database and Help tabs.
 *
 * Built on the WPHEKA admin UI kit (1.9.0). The setting saves through admin-post.php,
 * so the server decides what the next page says.
 *
 * @package WPHEKA_Web_Server_Info
 * @since   1.9
 */

defined( 'ABSPATH' ) || exit;

use WPHEKA_Web_Server_Info\AdminUI\Fields;
use WPHEKA_Web_Server_Info\AdminUI\Icons;
use WPHEKA_Web_Server_Info\AdminUI\Page;

/**
 * WPHEKA_Web_Server_Info_Page Class.
 */
class WPHEKA_Web_Server_Info_Page {

	/**
	 * Page slug (the same as before 1.9.0, so links and bookmarks keep working).
	 */
	const PAGE = 'wpheka-information';

	/**
	 * The wordpress.org slug.
	 */
	const SLUG = 'wpheka-web-server-information';

	/**
	 * The admin-post action that saves the setting.
	 */
	const SAVE_ACTION = 'wpheka_wsi_save';

	/**
	 * The admin-post action that reads the server and database details again.
	 */
	const REFRESH_ACTION = 'wpheka_wsi_refresh';

	/**
	 * The page hook WordPress returned, for loading assets on this page only.
	 *
	 * @var string
	 */
	private static $hook = '';

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, 'wpheka_web_server_info_save_settings' );
		add_action( 'admin_post_' . self::REFRESH_ACTION, array( __CLASS__, 'handle_refresh' ) );
	}

	/**
	 * Remember the page hook, for enqueue().
	 *
	 * @param string|false $hook What add_submenu_page() returned.
	 */
	public static function set_hook( $hook ) {
		self::$hook = is_string( $hook ) ? $hook : '';
	}

	/**
	 * URL of a tab.
	 *
	 * @param string $tab  webserver, phpinfo, dbinfo or help.
	 * @param array  $args Extra query args.
	 * @return string
	 */
	public static function url( $tab = 'webserver', array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE, 'tab' => $tab ), $args ), admin_url( 'admin.php' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- short query args.
	}

	/**
	 * Load the kit on this page only.
	 *
	 * @param string $hook Page hook.
	 */
	public static function enqueue( $hook ) {
		if ( '' === self::$hook || self::$hook !== $hook ) {
			return;
		}
		Page::enqueue( self::SLUG, plugins_url( 'assets/wpheka-ui/', WPHEKA_WEB_SERVER_INFO_MAIN_FILE ) );
	}

	/**
	 * Read the server and database details again (admin-post).
	 */
	public static function handle_refresh() {
		check_admin_referer( self::REFRESH_ACTION );
		if ( ! current_user_can( wpheka_web_server_info_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wpheka-web-server-information' ), '', array( 'response' => 403 ) );
		}

		require_once WPHEKA_WEB_SERVER_INFO_PLUGIN_PATH . '/includes/class-wpheka-info-admin-dbinfo.php';
		WPHEKA_Info_Admin_Dbinfo::refresh();

		wp_safe_redirect( self::url( 'dbinfo', array( 'wsi-refreshed' => 1 ) ) );
		exit;
	}

	/**
	 * Links for the header, rail and Help tab.
	 *
	 * @return array
	 */
	private static function links() {
		return array(
			'docs'    => 'https://www.wpheka.com/product/web-server-information/',
			'support' => 'https://wordpress.org/support/plugin/' . self::SLUG . '/',
			'changes' => 'https://wordpress.org/plugins/' . self::SLUG . '/#developers',
			'review'  => wpheka_web_server_info_review_url(),
			'contact' => 'https://www.wpheka.com/contact/',
		);
	}

	/**
	 * The tab being shown.
	 *
	 * @return string
	 */
	private static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'webserver'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which tab to show.

		return in_array( $tab, array( 'phpinfo', 'dbinfo', 'help' ), true ) ? $tab : 'webserver';
	}

	/**
	 * Whether the settings form is shown: the user may save and the framework that
	 * stores the setting booted.
	 *
	 * @return bool
	 */
	private static function has_form() {
		return wpheka_web_server_info_can_save() && wpheka_web_server_info_framework_ready();
	}

	/**
	 * The page. The caller checks the capability.
	 */
	public static function render() {
		$tab   = self::current_tab();
		$links = self::links();
		$name  = __( 'Web Server Information', 'wpheka-web-server-information' );

		Page::open(
			array(
				'icon'     => plugins_url( 'assets/wpheka-ui/icons/' . self::SLUG . '.png', WPHEKA_WEB_SERVER_INFO_MAIN_FILE ),
				'name'     => $name,
				'version'  => WPHEKA_WEB_SERVER_INFO_VERSION,
				'badges'   => array( array( __( 'Free', 'wpheka-web-server-information' ), 'mint' ) ),
				'subtitle' => __( 'Your server, PHP and database settings in one place.', 'wpheka-web-server-information' ),
				'crumb'    => __( 'Server Info', 'wpheka-web-server-information' ),
				'links'    => array(
					array( __( 'Docs', 'wpheka-web-server-information' ), $links['docs'], 'book' ),
					array( __( 'Support', 'wpheka-web-server-information' ), $links['support'], 'chat' ),
					array( __( "What's new", 'wpheka-web-server-information' ), $links['changes'], 'sparkle' ),
				),
				'cta'      => array( __( 'Rate on WordPress.org', 'wpheka-web-server-information' ), $links['review'], 'star', 'ghost' ),
				'tabs'     => array(
					array( __( 'Overview', 'wpheka-web-server-information' ), self::url( 'webserver' ), 'webserver' === $tab ),
					array( __( 'PHP', 'wpheka-web-server-information' ), self::url( 'phpinfo' ), 'phpinfo' === $tab ),
					array( __( 'Database', 'wpheka-web-server-information' ), self::url( 'dbinfo' ), 'dbinfo' === $tab ),
					array( __( 'Help', 'wpheka-web-server-information' ), self::url( 'help' ), 'help' === $tab ),
				),
			)
		);

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display flags after our own redirects.
		$error = 'webserver' === $tab && isset( $_GET['w-error'] )
			? __( 'The setting could not be saved because the WPHEKA framework did not load. Reinstall the plugin, then try again.', 'wpheka-web-server-information' )
			: '';
		$saved = 'webserver' === $tab && isset( $_GET['w-saved'] ) && '' === $error;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( 'phpinfo' === $tab ) {
			self::render_php();
		} elseif ( 'dbinfo' === $tab ) {
			self::render_database();
		} elseif ( 'help' === $tab ) {
			self::render_help( $links );
		} else {
			self::render_overview( $links );
		}

		Page::close(
			array(
				'slug'       => self::SLUG,
				'icons_url'  => plugins_url( 'assets/wpheka-ui/icons/', WPHEKA_WEB_SERVER_INFO_MAIN_FILE ),
				'short_name' => $name,
				'review_url' => $links['review'],
				'form'       => 'webserver' === $tab && self::has_form() ? 'wsi-settings' : '',
				'saved'      => $saved,
				'error'      => $error,
			)
		);
	}

	/**
	 * A php.ini value, or "Not set".
	 *
	 * @param string $key Directive.
	 * @return string
	 */
	private static function ini( $key ) {
		$value = ini_get( $key );

		return false === $value || '' === $value ? __( 'Not set', 'wpheka-web-server-information' ) : (string) $value;
	}

	/**
	 * The PHP limits that matter most, as key/value rows. Values only: the plugin has
	 * no thresholds to judge them by (owner decision Q5, 1.9.0).
	 *
	 * @return array[]
	 */
	private static function php_rows() {
		$seconds = (int) ini_get( 'max_execution_time' );
		$curl    = function_exists( 'curl_version' ) ? curl_version() : array();
		$opcache = extension_loaded( 'Zend OPcache' );

		return array(
			array(
				'label' => __( 'PHP version', 'wpheka-web-server-information' ),
				'value' => phpversion(),
			),
			array(
				'label' => __( 'PHP build', 'wpheka-web-server-information' ),
				/* translators: %d: 32 or 64. */
				'value' => sprintf( __( '%d-bit PHP', 'wpheka-web-server-information' ), PHP_INT_SIZE * 8 ),
			),
			array(
				'label' => __( 'Memory limit', 'wpheka-web-server-information' ),
				'value' => self::ini( 'memory_limit' ),
				'note'  => __( 'In wp-admin. WordPress raises it here, so the front end can be lower.', 'wpheka-web-server-information' ),
			),
			array(
				'label' => __( 'Max execution time', 'wpheka-web-server-information' ),
				/* translators: %s: number of seconds. */
				'value' => 0 === $seconds ? __( 'No limit', 'wpheka-web-server-information' ) : sprintf( _n( '%s second', '%s seconds', $seconds, 'wpheka-web-server-information' ), number_format_i18n( $seconds ) ),
			),
			array(
				'label' => __( 'Max upload size', 'wpheka-web-server-information' ),
				'value' => self::ini( 'upload_max_filesize' ),
			),
			array(
				'label' => __( 'Post max size', 'wpheka-web-server-information' ),
				'value' => self::ini( 'post_max_size' ),
			),
			array(
				'label' => __( 'Max input vars', 'wpheka-web-server-information' ),
				'value' => self::ini( 'max_input_vars' ),
			),
			array(
				'label' => __( 'cURL', 'wpheka-web-server-information' ),
				'value' => isset( $curl['version'] ) ? (string) $curl['version'] : __( 'Not installed', 'wpheka-web-server-information' ),
			),
			array(
				'label' => __( 'OPcache', 'wpheka-web-server-information' ),
				'value' => ! $opcache ? __( 'Not installed', 'wpheka-web-server-information' ) : ( ini_get( 'opcache.enable' ) ? __( 'On', 'wpheka-web-server-information' ) : __( 'Off', 'wpheka-web-server-information' ) ),
			),
		);
	}

	/**
	 * The Overview tab: stats, the server, PHP and database summaries, and the setting.
	 *
	 * @param array $links Help links.
	 */
	private static function render_overview( array $links ) {
		require_once WPHEKA_WEB_SERVER_INFO_PLUGIN_PATH . '/includes/class-wpheka-info-admin-webserver.php';
		require_once WPHEKA_WEB_SERVER_INFO_PLUGIN_PATH . '/includes/class-wpheka-info-admin-dbinfo.php';
		$server = new WPHEKA_Info_Admin_Webserver();
		$db     = new WPHEKA_Info_Admin_Dbinfo();

		$software = $server->server_var( 'SERVER_SOFTWARE' );
		// "Apache/2.4.62 (Unix) OpenSSL/3.0" reads as "Apache" on the card; the row has it all.
		$software = '' === $software ? __( 'Not available', 'wpheka-web-server-information' ) : strtok( $software, '/ ' );

		Page::stats(
			array(
				array(
					'label' => __( 'PHP', 'wpheka-web-server-information' ),
					'icon'  => 'code',
					'value' => esc_html( phpversion() ),
					/* translators: %d: 32 or 64. */
					'meta'  => sprintf( __( '%d-bit build', 'wpheka-web-server-information' ), PHP_INT_SIZE * 8 ),
				),
				array(
					'label' => __( 'Database', 'wpheka-web-server-information' ),
					'icon'  => 'db',
					'value' => esc_html( $db->database_software() . ' ' . $db->database_version() ),
					/* translators: 1: data size, 2: index size, for example "412 MB". */
					'meta'  => sprintf( __( '%1$s data, %2$s indexes', 'wpheka-web-server-information' ), $db->database_disk_usage(), $db->index_disk_usage() ),
				),
				array(
					'label' => __( 'Web server', 'wpheka-web-server-information' ),
					'icon'  => 'server',
					'value' => esc_html( $software ),
					'meta'  => $server->server_os(),
				),
				array(
					'label' => __( 'Memory limit', 'wpheka-web-server-information' ),
					'icon'  => 'gauge',
					'mint'  => true,
					'value' => esc_html( self::ini( 'memory_limit' ) ),
					'meta'  => __( 'PHP memory limit in wp-admin', 'wpheka-web-server-information' ),
				),
			)
		);

		$nav = array(
			array( 'server', 'server', __( 'Server', 'wpheka-web-server-information' ) ),
			array( 'php-summary', 'code', __( 'PHP', 'wpheka-web-server-information' ) ),
			array( 'db-summary', 'db', __( 'Database', 'wpheka-web-server-information' ) ),
			array( 'settings', 'sliders', __( 'Settings', 'wpheka-web-server-information' ) ),
		);

		echo '<div class="w-layout"><div>';
		Page::nav( $nav, false );
		echo '</div><div class="w-stack">';

		Fields::section_start( 'server', __( 'Server', 'wpheka-web-server-information' ), __( 'Your hosting server at a glance.', 'wpheka-web-server-information' ) );
		Fields::kv( $server->rows() );
		Fields::section_end();

		Fields::section_start( 'php-summary', __( 'PHP', 'wpheka-web-server-information' ), __( 'The PHP limits that matter most for a WordPress site. The PHP tab has every setting.', 'wpheka-web-server-information' ) );
		Fields::kv( self::php_rows() );
		Fields::section_end();

		Fields::section_start( 'db-summary', __( 'Database', 'wpheka-web-server-information' ), __( 'The Database tab has every database setting.', 'wpheka-web-server-information' ) );
		Fields::kv( $db->rows() );
		Fields::section_end();

		self::render_settings();

		echo '</div><aside class="w-rail" aria-label="' . esc_attr__( 'Review and help', 'wpheka-web-server-information' ) . '">';
		Page::review_card( __( 'Handy for support?', 'wpheka-web-server-information' ), __( 'A quick review helps other site owners find Web Server Information.', 'wpheka-web-server-information' ), $links['review'] );
		Page::help_card(
			array(
				array( __( 'Read the documentation', 'wpheka-web-server-information' ), $links['docs'], 'book' ),
				array( __( 'Ask in the support forum', 'wpheka-web-server-information' ), $links['support'], 'chat' ),
				array( __( 'Contact WPHEKA', 'wpheka-web-server-information' ), $links['contact'], 'mail' ),
			)
		);
		echo '</aside></div>';
	}

	/**
	 * The Settings section: the admin footer switch, or why it cannot be changed here.
	 */
	private static function render_settings() {
		if ( ! wpheka_web_server_info_can_save() ) {
			// A site admin on a network where the setting is network-wide sees no form.
			Fields::section_start( 'settings', __( 'Settings', 'wpheka-web-server-information' ) );
			Fields::info_row(
				array(
					'label' => __( 'Server details in the admin footer', 'wpheka-web-server-information' ),
					'help'  => __( 'Only a network administrator can change this setting.', 'wpheka-web-server-information' ),
					'pill'  => wpheka_web_server_info_footer_enabled() ? __( 'On', 'wpheka-web-server-information' ) : __( 'Off', 'wpheka-web-server-information' ),
					'kind'  => 'info',
				)
			);
			Fields::section_end();
			return;
		}

		if ( ! wpheka_web_server_info_framework_ready() ) {
			Fields::section_start( 'settings', __( 'Settings', 'wpheka-web-server-information' ) );
			Fields::info_row(
				array(
					'label' => __( 'Server details in the admin footer', 'wpheka-web-server-information' ),
					'help'  => __( 'This setting cannot be changed because the WPHEKA framework did not load. Reinstall the plugin to fix it. The footer keeps showing the server details.', 'wpheka-web-server-information' ),
					'pill'  => __( 'Unavailable', 'wpheka-web-server-information' ),
					'kind'  => 'warn',
				)
			);
			Fields::section_end();
			return;
		}

		echo '<form id="wsi-settings" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-w-form>';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::SAVE_ACTION ) . '">';
		wp_nonce_field( self::SAVE_ACTION );
		Fields::section_start( 'settings', __( 'Settings', 'wpheka-web-server-information' ) );
		Fields::switch_row(
			array(
				'name'    => 'wpheka_wsi_footer_info',
				'checked' => wpheka_web_server_info_footer_enabled(),
				'label'   => __( 'Server details in the admin footer', 'wpheka-web-server-information' ),
				'help'    => __( 'Replaces the WordPress version text in the footer of every admin page with the WordPress, PHP, server and MySQL versions. Only users who can open this page see them.', 'wpheka-web-server-information' ),
			)
		);
		Fields::section_end();
		// Without JavaScript the save bar never appears, so the form keeps a plain button.
		echo '<noscript><p><button type="submit" class="w-btn w-btn--primary">' . esc_html__( 'Save changes', 'wpheka-web-server-information' ) . '</button></p></noscript>';
		echo '</form>';
	}

	/**
	 * The PHP tab: every phpinfo() row, grouped by module, with a search box.
	 */
	private static function render_php() {
		require_once WPHEKA_WEB_SERVER_INFO_PLUGIN_PATH . '/includes/class-wpheka-info-admin-phpinfo.php';
		$groups = ( new WPHEKA_Info_Admin_Phpinfo() )->groups();

		echo '<div class="w-stack"><div class="w-card"><div class="w-toolbar">';
		Fields::kv_find( 'wsi-php', __( 'Search PHP settings and extensions', 'wpheka-web-server-information' ) );
		echo '</div></div><div id="wsi-php" class="w-stack">';
		foreach ( $groups as $group ) {
			Fields::section_start( $group['id'], $group['title'] );
			Fields::kv( $group['rows'] );
			Fields::section_end();
		}
		Fields::kv_empty( __( 'Nothing matches', 'wpheka-web-server-information' ), __( 'Try another word. Clear the search to see everything.', 'wpheka-web-server-information' ) );
		echo '</div></div>';
	}

	/**
	 * The Database tab: the basics, then every database variable with search and Refresh.
	 */
	private static function render_database() {
		require_once WPHEKA_WEB_SERVER_INFO_PLUGIN_PATH . '/includes/class-wpheka-info-admin-dbinfo.php';
		$db   = new WPHEKA_Info_Admin_Dbinfo();
		$vars = $db->variables();

		echo '<div class="w-stack">';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag after our own redirect.
		if ( isset( $_GET['wsi-refreshed'] ) ) {
			echo '<div class="w-notice" role="status"><p>' . esc_html__( 'Server and database details were read again.', 'wpheka-web-server-information' ) . '</p></div>';
		}

		Fields::section_start( 'database', __( 'Database', 'wpheka-web-server-information' ), __( 'Read once a week and kept until then. Refresh reads them again now.', 'wpheka-web-server-information' ) );
		Fields::kv( $db->rows() );
		Fields::section_end();

		Fields::section_start( 'db-variables', __( 'All database variables', 'wpheka-web-server-information' ), __( 'Every setting your database server reports.', 'wpheka-web-server-information' ) );
		echo '<div class="w-toolbar">';
		Fields::kv_find( 'wsi-db-vars', __( 'Search database variables', 'wpheka-web-server-information' ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::REFRESH_ACTION ) . '">';
		wp_nonce_field( self::REFRESH_ACTION );
		echo '<button type="submit" class="w-btn w-btn--secondary">' . Icons::get( 'refresh' ) . esc_html__( 'Refresh', 'wpheka-web-server-information' ) . '</button></form></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		echo '<div id="wsi-db-vars">';
		if ( $vars ) {
			Fields::kv( $vars );
			Fields::kv_empty( __( 'Nothing matches', 'wpheka-web-server-information' ), __( 'Try another word. Clear the search to see every variable.', 'wpheka-web-server-information' ) );
		} else {
			echo '<div class="w-empty"><h3>' . esc_html__( 'Could not read the database variables.', 'wpheka-web-server-information' ) . '</h3><p>' . esc_html__( 'Your database user may not be allowed to read them. Ask your host.', 'wpheka-web-server-information' ) . '</p></div>';
		}
		echo '</div>';
		Fields::section_end();
		echo '</div>';
	}

	/**
	 * The Help tab.
	 *
	 * @param array $links Help links.
	 */
	private static function render_help( array $links ) {
		$cards = array(
			array( 'book', __( 'Documentation', 'wpheka-web-server-information' ), __( 'What each value means and where your host sets it.', 'wpheka-web-server-information' ), $links['docs'] ),
			array( 'chat', __( 'Support forum', 'wpheka-web-server-information' ), __( 'Ask a question. The developers who built the plugin answer.', 'wpheka-web-server-information' ), $links['support'] ),
			array( 'sparkle', __( "What's new", 'wpheka-web-server-information' ), __( 'Every change in each version, in plain words.', 'wpheka-web-server-information' ), $links['changes'] ),
		);
		echo '<div class="w-flow">';
		foreach ( $cards as $card ) {
			echo '<a class="w-card w-flow-step" href="' . esc_url( $card[3] ) . '" target="_blank" rel="noopener"><span class="w-ico w-ico--lg">' . Icons::get( $card[0] ) . '</span><h3>' . esc_html( $card[1] ) . '</h3><p>' . esc_html( $card[2] ) . '<span class="w-sr"> ' . esc_html__( '(opens in a new tab)', 'wpheka-web-server-information' ) . '</span></p></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		}
		echo '</div>';
	}
}
