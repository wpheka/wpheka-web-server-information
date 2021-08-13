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
						<td class="v"><?php echo $this->server_os(); ?>&nbsp;/&nbsp;<?php echo ( PHP_INT_SIZE * 8 ) . __( 'Bit OS', 'wp-server-stats' ); ?></td>
					</tr>
					<tr>
						<td class="e"><?php esc_html_e( 'Server Software', 'wpheka-web-server-information' ); ?></td>
						<td class="v"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
					</tr>
					<tr>
						<td class="e"><?php esc_html_e( 'Server IP', 'wpheka-web-server-information' ); ?></td>
						<td class="v"><?php echo ( $this->validate_ip_address( $this->check_server_ip() ) ? $this->check_server_ip() : 'ERROR IP096T' ); ?></td>
					</tr>
					<tr>
						<td class="e"><?php esc_html_e( 'Server Port', 'wpheka-web-server-information' ); ?></td>
						<td class="v"><?php echo $_SERVER['SERVER_PORT']; ?></td>
					</tr>
					<tr>
						<td class="e"><?php esc_html_e( 'Server Location', 'wpheka-web-server-information' ); ?></td>
						<td class="v"><?php echo $this->check_server_location(); ?></td>
					</tr>
					<tr>
						<td class="e"><?php esc_html_e( 'Server Hostname', 'wpheka-web-server-information' ); ?></td>
						<td class="v"><?php echo gethostname(); ?></td>
					</tr>
					<tr>
						<td class="e"><?php esc_html_e( 'Site\'s Document Root', 'wpheka-web-server-information' ); ?></td>
						<td class="v"><?php echo $_SERVER['DOCUMENT_ROOT'] . '/'; ?></td>
					</tr>
				</tbody>
			</table>
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
