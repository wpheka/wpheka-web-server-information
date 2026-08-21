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

if ( ! class_exists( 'WPHEKA_Info_Admin_Webserver', false ) ) :

	/**
	 * WPHEKA_Info_Admin_Webserver Class.
	 */
	class WPHEKA_Info_Admin_Webserver {

		/**
		 * Setting tabs.
		 *
		 * @var array
		 */
		private $tabs = array();

		/**
		 * One $_SERVER value, safe to print.
		 *
		 * $_SERVER keys are not guaranteed to exist -- SERVER_SOFTWARE and
		 * SERVER_PORT are absent under CLI and some FastCGI setups -- so reading
		 * them unguarded emits an undefined-index warning on exactly the hosts
		 * this plugin exists to report on. The value is request data, so it is
		 * unslashed and sanitised before it is escaped for output.
		 *
		 * @since 1.8
		 * @param string $key $_SERVER key.
		 * @return string Empty when the key is absent.
		 */
		private function server_var( $key ) {
			if ( ! isset( $_SERVER[ $key ] ) ) {
				return '';
			}

			return sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
		}

		/**
		 * WPHEKA_Info_Admin_Webserver Constructor.
		 */
		public function __construct() {
			?>
			<h1><?php esc_html_e( 'Server Overview', 'wpheka-web-server-information' ); ?></h1>
			<hr />
			<table class="widefat">
				<tbody>
					<tr>
						<td class="e"><?php esc_html_e( 'Server OS', 'wpheka-web-server-information' ); ?></td>
						<td class="v"><?php echo esc_html( $this->server_os() ); ?>&nbsp;/&nbsp;<?php echo esc_html( ( PHP_INT_SIZE * 8 ) . __( 'Bit OS', 'wpheka-web-server-information' ) ); ?></td>
					</tr>
					<tr>
						<td class="e"><?php esc_html_e( 'Server Software', 'wpheka-web-server-information' ); ?></td>
						<td class="v"><?php echo esc_html( $this->server_var( 'SERVER_SOFTWARE' ) ); ?></td>
					</tr>
					<tr>
						<td class="e"><?php esc_html_e( 'Server IP', 'wpheka-web-server-information' ); ?></td>
						<td class="v"><?php echo esc_html( $this->validate_ip_address( $this->check_server_ip() ) ? $this->check_server_ip() : 'ERROR IP096T' ); ?></td>
					</tr>
					<tr>
						<td class="e"><?php esc_html_e( 'Server Port', 'wpheka-web-server-information' ); ?></td>
						<td class="v"><?php echo esc_html( $this->server_var( 'SERVER_PORT' ) ); ?></td>
					</tr>
					<tr>
						<td class="e"><?php esc_html_e( 'Server Location', 'wpheka-web-server-information' ); ?></td>
						<td class="v"><?php echo esc_html( $this->check_server_location() ); ?></td>
					</tr>
					<tr>
						<td class="e"><?php esc_html_e( 'Server Hostname', 'wpheka-web-server-information' ); ?></td>
						<td class="v"><?php echo esc_html( (string) gethostname() ); ?></td>
					</tr>
					<tr>
						<td class="e"><?php esc_html_e( 'Site\'s Document Root', 'wpheka-web-server-information' ); ?></td>
						<td class="v"><?php echo esc_html( $this->server_var( 'DOCUMENT_ROOT' ) . '/' ); ?></td>
					</tr>
				</tbody>
			</table>
			<?php
			$this->render_settings();
		}

		/**
		 * The plugin's one setting, rendered at the foot of this tab.
		 *
		 * Deliberately not a settings page of its own. There is a single option,
		 * and a whole page -- or a fourth tab -- holding one checkbox reads as
		 * thinner than the setting deserves. Screen Options would be the native
		 * home for a show/hide preference, but it is per-user and per-screen,
		 * while this governs every admin page for everyone.
		 *
		 * @since 1.8
		 * @return void
		 */
		private function render_settings() {
			if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'wpheka_web_server_info_footer_enabled' ) ) {
				return;
			}

			/*
			 * No nonce on this read, deliberately. It is a display flag set by
			 * the redirect after a successful save, and it changes nothing --
			 * the worst a crafted URL achieves is a spurious "Settings saved"
			 * notice. The save itself verifies both a nonce and the capability;
			 * that is the state-changing path. WordPress core uses the same
			 * pattern for settings-updated.
			 */
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag, no state change.
			if ( isset( $_GET['wpheka-updated'] ) ) {
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					esc_html__( 'Settings saved.', 'wpheka-web-server-information' )
				);
			}

			$enabled = wpheka_web_server_info_footer_enabled();
			?>
			<h2><?php esc_html_e( 'Settings', 'wpheka-web-server-information' ); ?></h2>
			<hr />
			<form method="post" action="">
				<?php wp_nonce_field( 'wpheka_wsi_save', 'wpheka_wsi_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Admin footer', 'wpheka-web-server-information' ); ?>
							</th>
							<td>
								<label for="wpheka_wsi_footer_info">
									<input
										type="checkbox"
										name="wpheka_wsi_footer_info"
										id="wpheka_wsi_footer_info"
										value="1"
										<?php checked( $enabled ); ?> />
									<?php esc_html_e( 'Show server details in the admin footer', 'wpheka-web-server-information' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Replaces the WordPress version text in the footer of every admin page with the WordPress, PHP, server and MySQL versions.', 'wpheka-web-server-information' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( __( 'Save Changes', 'wpheka-web-server-information' ) ); ?>
			</form>
			<?php
		}

		/**
		 * Get server os.
		 *
		 * @return string
		 */
		public function server_os() {
			$server_os = get_transient( 'wpheka_web_server_os' );

			if ( $server_os === false ) {
				$os_detail = php_uname();
				$just_os_name = explode( ' ', trim( $os_detail ) );
				$server_os = $just_os_name[0];
				set_transient( 'wpheka_web_server_os', $server_os, WEEK_IN_SECONDS );
			}

			return $server_os;
		}

		/**
		 * Check server ip.
		 */
		public function check_server_ip() {
			 return trim( gethostbyname( gethostname() ) );
		}

		/**
		 * Call IP-API.com to get server ip details.
		 *
		 * @return string
		 */
		public function check_server_location() {
			// get the server ip.
			$ip = $this->check_server_ip();

			$server_location = get_transient( 'wpheka_web_server_location' );

			if ( $server_location === false ) {
				// lets validate the ip.
				if ( $this->validate_ip_address( $ip ) ) {
					$query = @unserialize( wp_remote_retrieve_body( wp_remote_get( 'http://ip-api.com/php/' . $ip ) ) );
					if ( $query && $query['status'] == 'success' ) {
						$server_location = $query['city'] . ', ' . $query['country'];
						set_transient( 'wpheka_web_server_location', $server_location, WEEK_IN_SECONDS );
					} else {
						if ( empty( $query['message'] ) ) {
								$server_location = $query['status'];
						} else {
							$server_location = $query['message'];
						}
					}
				} else {
					$server_location = 'ERROR IP096T';
				}
			}

			return $server_location;
		}

		/**
		 * Validate IP address.
		 *
		 * @param  string
		 * @return bool
		 */
		public function validate_ip_address( $ip ) {
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) === false ) {
				return true; // $ip is a valid IP address.
			} else {
				return false; // $ip is NOT a valid IP address.
			}
		}

	}

endif;
