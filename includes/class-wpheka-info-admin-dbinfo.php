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

if ( ! class_exists( 'WPHEKA_Info_Admin_Dbinfo', false ) ) :

	/**
	 * WPHEKA_Info_Admin_Dbinfo Class.
	 */
	class WPHEKA_Info_Admin_Dbinfo {

		/**
		 * WPHEKA_Info_Admin_Dbinfo Constructor.
		 */
		public function __construct() {
			?>
			<h1><?php esc_html_e( 'This page will show you the in-depth information about your database', 'wpheka-web-server-information' ); ?></h1>
			<hr />
			<h2><?php esc_html_e( 'Basic Database Information', 'wpheka-web-server-information' ); ?></h2>
			<table class="widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Variable Name', 'wpheka-web-server-information' ); ?></th>
						<th><?php esc_html_e( 'Value', 'wpheka-web-server-information' ); ?></th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<td class="e"><?php esc_html_e( 'Variable Name', 'wpheka-web-server-information' ); ?></td>
						<td><?php esc_html_e( 'Value', 'wpheka-web-server-information' ); ?></td>
					</tr>
				</tfoot>
				<tbody>
					<tr>
						<td class="e"><?php esc_html_e( 'Database Software', 'wpheka-web-server-information' ); ?></td>
						<td class="v"><?php echo $this->database_software(); ?></td>
					</tr>
					<tr>
						<td class="e"><?php esc_html_e( 'Database Version', 'wpheka-web-server-information' ); ?></td>
						<td class="v"><?php echo $this->database_version(); ?></td>
					</tr>
					<tr>
						<td class="e"><?php esc_html_e( 'Maximum No. of Connections', 'wpheka-web-server-information' ); ?></td>
						<td class="v"><?php echo $this->database_max_no_connection(); ?></td>
					</tr>
					<tr>
						<td class="e"><?php esc_html_e( 'Maximum Packet Size', 'wpheka-web-server-information' ); ?></td>
						<td class="v"><?php echo $this->database_max_packet_size(); ?></td>
					</tr>
					<tr>
						<td class="e"><?php esc_html_e( 'Database Disk Usage', 'wpheka-web-server-information' ); ?></td>
						<td class="v"><?php echo $this->database_disk_usage(); ?></td>
					</tr>
					<tr>
						<td class="e"><?php esc_html_e( 'Index Disk Usage', 'wpheka-web-server-information' ); ?></td>
						<td class="v"><?php echo $this->index_disk_usage(); ?></td>
					</tr>
				</tbody>
			</table>
			<div class="clear give-some-space"></div>
			<hr />
			<h2><?php esc_html_e( 'Advanced Database Information', 'wpheka-web-server-information' ); ?></h2>
			<table class="widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Variable Name', 'wpheka-web-server-information' ); ?></th>
						<th><?php esc_html_e( 'Value', 'wpheka-web-server-information' ); ?></th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<td><?php esc_html_e( 'Variable Name', 'wpheka-web-server-information' ); ?></td>
						<td><?php esc_html_e( 'Value', 'wpheka-web-server-information' ); ?></td>
					</tr>
				</tfoot>
				<tbody>
				<?php
				if ( get_option( 'wpheka_web_server_db_advanced_info' ) ) {
					$dbinfo = get_option( 'wpheka_web_server_db_advanced_info' );
				} else {
					global $wpdb;
					$dbversion = $wpdb->get_var( 'SELECT VERSION() AS version' );
					$dbinfo = $wpdb->get_results( 'SHOW VARIABLES' );
					update_option( 'wpheka_web_server_db_advanced_info', $dbinfo );
				}

				if ( ! empty( $dbinfo ) ) {
					foreach ( $dbinfo as $info ) {
						echo '<tr><td class="e">' . $info->Variable_name . '</td><td class="v">' . htmlspecialchars( $info->Value ) . '</td></tr>';
					}
				} else {
					echo '<tr><td>' . __( 'Something went wrong!', 'wpheka-web-server-information' ) . '</td><td>' . __( 'Something went wrong!', 'wpheka-web-server-information' ) . '</td></tr>';
				}
				?>
				</tbody>
			</table>
			<?php
		}

		/**
		 * Get database software.
		 *
		 * @return string
		 */
		public function database_software() {
			$db_software = get_transient( 'wpheka_web_server_db_software' );

			if ( $db_software === false ) {
				global $wpdb;
				$db_software_query = $wpdb->get_row( "SHOW VARIABLES LIKE 'version_comment'" );
				$db_software_dump = $db_software_query->Value;
				if ( ! empty( $db_software_dump ) ) {
					$db_soft_array = explode( ' ', trim( $db_software_dump ) );
					$db_software = $db_soft_array[0];
					set_transient( 'wpheka_web_server_db_software', $db_software, WEEK_IN_SECONDS );
				} else {
					$db_software = __( 'N/A', 'wpheka-web-server-information' );
				}
			}

			return $db_software;
		}

		/**
		 * Get database version.
		 *
		 * @return string
		 */
		public function database_version() {
			$db_version = get_transient( 'wpheka_web_server_db_version' );

			if ( $db_version === false ) {
				global $wpdb;
				$db_version_dump = $wpdb->get_var( 'SELECT VERSION() AS version from DUAL' );
				if ( preg_match( '/\d+(?:\.\d+)+/', $db_version_dump, $matches ) ) {
					$db_version = $matches[0]; // returning the first match.
					set_transient( 'wpheka_web_server_db_version', $db_version, WEEK_IN_SECONDS );
				} else {
					$db_version = __( 'N/A', 'wpheka-web-server-information' );
				}
			}

			return $db_version;
		}

		/**
		 * Get database max connection.
		 *
		 * @return string
		 */
		public function database_max_no_connection() {
			$db_max_connection = get_transient( 'wpheka_web_server_db_max_connection' );

			if ( $db_max_connection === false ) {
				global $wpdb;
				$connection_max_query = $wpdb->get_row( "SHOW VARIABLES LIKE 'max_connections'" );
				$db_max_connection = $connection_max_query->Value;
				if ( empty( $db_max_connection ) ) {
					$db_max_connection = __( 'N/A', 'wpheka-web-server-information' );
				} else {
					$db_max_connection = number_format_i18n( $db_max_connection, 0 );
					set_transient( 'wpheka_web_server_db_max_connection', $db_max_connection, WEEK_IN_SECONDS );
				}
			}

			return $db_max_connection;
		}

		/**
		 * Get database max packet size.
		 *
		 * @return string
		 */
		public function database_max_packet_size() {
			$db_max_packet_size = get_transient( 'wpheka_web_server_db_max_packet_size' );

			if ( $db_max_packet_size === false ) {
				global $wpdb;
				$packet_max_query = $wpdb->get_row( "SHOW VARIABLES LIKE 'max_allowed_packet'" );
				$db_max_packet_size = $packet_max_query->Value;
				if ( empty( $db_max_packet_size ) ) {
					$db_max_packet_size = __( 'N/A', 'wpheka-web-server-information' );
				} else {
					$db_max_packet_size = $this->format_filesize( $db_max_packet_size );
					set_transient( 'wpheka_web_server_db_max_packet_size', $db_max_packet_size, WEEK_IN_SECONDS );
				}
			}

			return $db_max_packet_size;
		}

		/**
		 * Format filesize.
		 *
		 * @param  integer
		 * @return string
		 */
		public function format_filesize( $bytes ) {
			if ( ( $bytes / pow( 1024, 5 ) ) > 1 ) {
				return number_format_i18n( ( $bytes / pow( 1024, 5 ) ), 0 ) . ' ' . __( 'PB', 'wpheka-web-server-information' );
			} elseif ( ( $bytes / pow( 1024, 4 ) ) > 1 ) {
				return number_format_i18n( ( $bytes / pow( 1024, 4 ) ), 0 ) . ' ' . __( 'TB', 'wpheka-web-server-information' );
			} elseif ( ( $bytes / pow( 1024, 3 ) ) > 1 ) {
				return number_format_i18n( ( $bytes / pow( 1024, 3 ) ), 0 ) . ' ' . __( 'GB', 'wpheka-web-server-information' );
			} elseif ( ( $bytes / pow( 1024, 2 ) ) > 1 ) {
				return number_format_i18n( ( $bytes / pow( 1024, 2 ) ), 0 ) . ' ' . __( 'MB', 'wpheka-web-server-information' );
			} elseif ( $bytes / 1024 > 1 ) {
				return number_format_i18n( $bytes / 1024, 0 ) . ' ' . __( 'KB', 'wpheka-web-server-information' );
			} elseif ( $bytes >= 0 ) {
				return number_format_i18n( $bytes, 0 ) . ' ' . __( 'bytes', 'wpheka-web-server-information' );
			} else {
				return __( 'Unknown', 'wpheka-web-server-information' );
			}
		}

		/**
		 * Get database disk usage.
		 *
		 * @return string
		 */
		public function database_disk_usage() {
			$db_disk_usage = get_transient( 'wpheka_web_server_db_disk_usage' );

			if ( $db_disk_usage === false ) {
				global $wpdb;
				$db_disk_usage = 0;
				$tablesstatus = $wpdb->get_results( 'SHOW TABLE STATUS' );
				foreach ( $tablesstatus as $tablestatus ) {
					$db_disk_usage += $tablestatus->Data_length;
				}
				if ( empty( $db_disk_usage ) ) {
					$db_disk_usage = __( 'N/A', 'wpheka-web-server-information' );
				} else {
					$db_disk_usage = $this->format_filesize( $db_disk_usage );
					set_transient( 'wpheka_web_server_db_disk_usage', $db_disk_usage, WEEK_IN_SECONDS );
				}
			}

			return $db_disk_usage;
		}

		/**
		 * Get database index disk usage.
		 *
		 * @return string
		 */
		public function index_disk_usage() {
			$db_index_disk_usage = get_transient( 'wpheka_web_server_db_index_disk_usage' );

			if ( $db_index_disk_usage === false ) {
				global $wpdb;
				$db_index_disk_usage = 0;
				$tablesstatus = $wpdb->get_results( 'SHOW TABLE STATUS' );
				foreach ( $tablesstatus as $tablestatus ) {
					$db_index_disk_usage += $tablestatus->Index_length;
				}
				if ( empty( $db_index_disk_usage ) ) {
					$db_index_disk_usage = __( 'N/A', 'wpheka-web-server-information' );
				} else {
					$db_index_disk_usage = $this->format_filesize( $db_index_disk_usage );
					set_transient( 'wpheka_web_server_db_index_disk_usage', $db_index_disk_usage, WEEK_IN_SECONDS );
				}
			}

			return $db_index_disk_usage;
		}

	}

endif;
