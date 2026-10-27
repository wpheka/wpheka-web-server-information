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
		 * WPHEKA_Web_Server_Info_Admin Constructor.
		 */
		public function __construct() {
			// Admin Menu.
			add_action( 'admin_menu', array( $this, 'wpheka_web_server_info_menu' ) );

			// Review prompt.
			add_action( 'admin_notices', array( $this, 'wpheka_web_server_info_review_prompt' ) );
			add_action( 'admin_init', array( $this, 'wpheka_web_server_info_maybe_hide_review_prompt' ) );
			add_action( 'wp_ajax_wpheka_wsi_snooze_review', array( $this, 'wpheka_web_server_info_snooze_review_prompt' ) );

			// Display php/db info in footer, if the setting allows it. Checked
			// here rather than inside the callback so that when it is off, the
			// filter is never attached and the version query never runs.
			if ( wpheka_web_server_info_footer_enabled() ) {
				add_filter( 'update_footer', array( $this, 'version_info_in_footer' ), 11 );
			}
		}

		/**
		 * Ask for a review in an admin notice on the dashboard.
		 *
		 * The approach follows Elementor's rate-us notice -- dashboard only,
		 * dismissed per user rather than per site, and gated on a usage count
		 * rather than elapsed time. Written here rather than taken from
		 * Elementor, whose licence differs from this project's (ADR-008).
		 *
		 * Links to the review form (`reviews/#new-post`), the same link as the
		 * plugin page's header, rail and footer (owner decision Q14, 1.9.0).
		 *
		 * The dismissal is a plain option rather than the framework settings
		 * row, deliberately. It is one boolean that must read the same whether
		 * or not a framework booted; routing it through Options would make the
		 * answer depend on the boot guard, and a prompt that reappears after
		 * being dismissed is worse than one stored simply.
		 *
		 * @since 1.8
		 * @return void
		 */
		public function wpheka_web_server_info_review_prompt() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

			if ( ! $screen || 'dashboard' !== $screen->id ) {
				return;
			}

			// Per user, so one administrator cannot answer for the rest. The old
			// site-wide option is still honoured so nobody is asked twice.
			if ( get_option( 'wpheka_web_server_info_review_dismissed' )
				|| get_user_meta( get_current_user_id(), 'wpheka_wsi_review_dismissed', true ) ) {
				return;
			}

			// Closed with its X: asked again in 14 days, for this user only.
			$snoozed_until = (int) get_user_meta( get_current_user_id(), 'wpheka_wsi_review_snoozed_until', true );
			if ( $snoozed_until > time() ) {
				return;
			}

			/*
			 * Three visits to this plugin's own screen. It reports server
			 * configuration and has no success event to count, so repeated
			 * deliberate visits are the closest honest signal that someone finds
			 * it useful. Weaker than counting a job done, and still better than
			 * a timer, which measures nothing.
			 */
			if ( 3 > (int) get_option( 'wpheka_web_server_info_view_count', 0 ) ) {
				return;
			}

			$reviews = wpheka_web_server_info_review_url();
			$hide    = wp_nonce_url(
				add_query_arg( 'wpheka_wsi_hide_review', '1', admin_url( 'index.php' ) ),
				'wpheka_wsi_hide_review'
			);
			?>
			<div id="wpheka-wsi-review-notice" class="notice notice-info is-dismissible" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wpheka_wsi_snooze_review' ) ); ?>">
				<p>
				<?php
				printf(
				/* translators: 1: plugin name, 2: five-star rating link, 3: opening link tag to dismiss, 4: closing link tag */
					esc_html__( 'If %1$s is useful to you, please leave us a %2$s rating on WordPress.org. %3$sDon\'t ask again%4$s.', 'wpheka-web-server-information' ),
					'<strong>' . esc_html__( 'Web Server Information', 'wpheka-web-server-information' ) . '</strong>',
					'<a href="' . esc_url( $reviews ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__( 'five star', 'wpheka-web-server-information' ) . '">&#9733;&#9733;&#9733;&#9733;&#9733;</a>',
					'<a href="' . esc_url( $hide ) . '">',
					'</a>'
				);
				?>
				</p>
			</div>
			<script>
			// WordPress hides an is-dismissible notice on its X but remembers
			// nothing, so without this the prompt was back on the next load.
			jQuery(function ($) {
				$(document).on('click', '#wpheka-wsi-review-notice .notice-dismiss', function () {
					$.post(ajaxurl, { action: 'wpheka_wsi_snooze_review', _ajax_nonce: $('#wpheka-wsi-review-notice').data('nonce') });
				});
			});
			</script>
			<?php
		}

		/**
		 * Snooze the review prompt for 14 days when its X is clicked.
		 *
		 * Per user, like the permanent dismissal: closing a notice is one
		 * person's decision. "Don't ask again" still hides it for good.
		 *
		 * @since 1.8.3
		 * @return void
		 */
		public function wpheka_web_server_info_snooze_review_prompt() {
			check_ajax_referer( 'wpheka_wsi_snooze_review' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( null, 403 );
			}

			update_user_meta( get_current_user_id(), 'wpheka_wsi_review_snoozed_until', time() + 14 * DAY_IN_SECONDS );
			wp_send_json_success();
		}

		/**
		 * Count deliberate visits to this plugin's screen.
		 *
		 * Stops writing once the review prompt's threshold is met.
		 *
		 * @since 1.8
		 * @return void
		 */
		public function wpheka_web_server_info_count_view() {
			$count = (int) get_option( 'wpheka_web_server_info_view_count', 0 );

			if ( $count >= 3 ) {
				return;
			}

			update_option( 'wpheka_web_server_info_view_count', $count + 1, false );
		}

		/**
		 * Stop asking, when the user asks us to.
		 *
		 * @since 1.8
		 * @return void
		 */
		public function wpheka_web_server_info_maybe_hide_review_prompt() {
			if ( ! isset( $_GET['wpheka_wsi_hide_review'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked immediately below.
				return;
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			check_admin_referer( 'wpheka_wsi_hide_review' );

			update_user_meta( get_current_user_id(), 'wpheka_wsi_review_dismissed', 1 );

			wp_safe_redirect( admin_url( 'index.php' ) );
			exit;
		}

		/**
		 * Register the page under the shared WPHEKA menu.
		 */
		public function wpheka_web_server_info_menu() {
			// The kit's menu, not the framework's, so the page exists even when the
			// framework did not boot (owner decision Q8, 1.9.0). Same parent slug,
			// capability filter and position as the framework, so they share one menu.
			WPHEKA_Web_Server_Info_Page::set_hook(
				\WPHEKA_Web_Server_Info\AdminUI\Menu::add_page(
					__( 'Web Server Information', 'wpheka-web-server-information' ),
					__( 'Server Info', 'wpheka-web-server-information' ),
					wpheka_web_server_info_capability(),
					WPHEKA_Web_Server_Info_Page::PAGE,
					array( $this, 'wpheka_web_server_info_page_callback' ),
					\WPHEKA_Web_Server_Info\AdminUI\Icons::menu_icon()
				)
			);
		}

		/**
		 * Info page callback
		 */
		public function wpheka_web_server_info_page_callback() {
			// The menu entry hides the page from other users; the page checks too,
			// since it prints phpinfo() and database settings.
			if ( ! current_user_can( wpheka_web_server_info_capability() ) ) {
				wp_die( esc_html__( 'You do not have permission to view this page.', 'wpheka-web-server-information' ), 403 );
			}

			$this->wpheka_web_server_info_count_view();
			WPHEKA_Web_Server_Info_Page::render();
			do_action( 'wpheka_web_server_info_admin_footer' );
		}

		/**
		 * Display version info in footer
		 *
		 * Only for users who can open the information page. Everyone else with
		 * wp-admin access, subscribers included, keeps WordPress' own footer text.
		 *
		 * @param string $content The footer text WordPress would show.
		 * @return string
		 */
		public function version_info_in_footer( $content = '' ) {
			if ( ! current_user_can( wpheka_web_server_info_capability() ) ) {
				return $content;
			}
			// The cached version (a week), not a query on every admin page.
			require_once WPHEKA_WEB_SERVER_INFO_PLUGIN_PATH . '/includes/class-wpheka-info-admin-dbinfo.php';
			$update     = core_update_footer();
			$wp_version = strpos( $update, '<strong>' ) === 0 ? get_bloginfo( 'version' ) . ' (' . $update . ')' : get_bloginfo( 'version' );

			return sprintf(
				/* translators: 1: WordPress version, 2: PHP version, 3: server software, 4: MySQL version. */
				esc_attr__( 'You are running WordPress %1$s | PHP %2$s | %3$s | MySQL %4$s', 'wpheka-web-server-information' ),
				$wp_version,
				phpversion(),
				empty( $_SERVER['SERVER_SOFTWARE'] ) ? esc_html__( 'Unknown', 'wpheka-web-server-information' ) : sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ),
				esc_html( ( new WPHEKA_Info_Admin_Dbinfo() )->database_version() )
			);
		}
	}

endif;

new WPHEKA_Web_Server_Info_Admin();
