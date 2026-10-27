<?php
/**
 * WPHEKA_Web_Server_Info
 *
 * @package WPHEKA_Web_Server_Info
 * @author      WPHEKA
 * @link        https://www.wpheka.com
 * @since       1.0
 * @version     1.9
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPHEKA_Info_Admin_Dbinfo', false ) ) :

	/**
	 * The database facts shown on the Overview and Database tabs. Reads only; the page
	 * prints them. Each value is cached for a week; refresh() clears the caches.
	 */
	class WPHEKA_Info_Admin_Dbinfo {

		/**
		 * Every transient this plugin caches server and database facts in.
		 *
		 * @since 1.9
		 * @return string[]
		 */
		public static function transients() {
			return array(
				'wpheka_web_server_os',
				'wpheka_web_server_location',
				'wpheka_web_server_db_software',
				'wpheka_web_server_db_version',
				'wpheka_web_server_db_max_connection',
				'wpheka_web_server_db_max_packet_size',
				'wpheka_web_server_db_disk_usage',
				'wpheka_web_server_db_index_disk_usage',
				'wpheka_web_server_db_advanced_info',
			);
		}

		/**
		 * Clear the cached facts, so the next view reads them again.
		 *
		 * @since 1.9
		 * @return void
		 */
		public static function refresh() {
			foreach ( self::transients() as $transient ) {
				delete_transient( $transient );
			}
		}

		/**
		 * One database variable, or '' when the server does not report it.
		 *
		 * @since 1.9
		 * @param string $name Variable name.
		 * @return string
		 */
		private function variable( $name ) {
			global $wpdb;

			$row = $wpdb->get_row( $wpdb->prepare( 'SHOW VARIABLES LIKE %s', $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- server variables, cached by the callers.

			return is_object( $row ) && isset( $row->Value ) ? (string) $row->Value : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column name.
		}

		/**
		 * The basic database facts, as kit key/value rows.
		 *
		 * @since 1.9
		 * @return array[]
		 */
		public function rows() {
			return array(
				array(
					'label' => __( 'Database software', 'wpheka-web-server-information' ),
					'value' => $this->database_software(),
				),
				array(
					'label' => __( 'Database version', 'wpheka-web-server-information' ),
					'value' => $this->database_version(),
				),
				array(
					'label' => __( 'Max connections', 'wpheka-web-server-information' ),
					'value' => $this->database_max_no_connection(),
				),
				array(
					'label' => __( 'Max packet size', 'wpheka-web-server-information' ),
					'value' => $this->database_max_packet_size(),
				),
				array(
					'label' => __( 'Database size', 'wpheka-web-server-information' ),
					'value' => $this->database_disk_usage(),
				),
				array(
					'label' => __( 'Index size', 'wpheka-web-server-information' ),
					'value' => $this->index_disk_usage(),
				),
			);
		}

		/**
		 * Every database variable (SHOW VARIABLES), cached for a week.
		 *
		 * @since 1.9
		 * @return array[] Kit key/value rows; empty when the server would not say.
		 */
		public function variables() {
			// Versions before 1.9.0 kept this in an autoloaded option, loaded on every
			// request and never refreshed. Drop it; a transient replaces it.
			if ( false !== get_option( 'wpheka_web_server_db_advanced_info' ) ) {
				delete_option( 'wpheka_web_server_db_advanced_info' );
			}

			$dbinfo = get_transient( 'wpheka_web_server_db_advanced_info' );
			if ( false === $dbinfo ) {
				global $wpdb;
				$dbinfo = $wpdb->get_results( 'SHOW VARIABLES' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- server variables, cached right below.
				if ( ! empty( $dbinfo ) ) {
					set_transient( 'wpheka_web_server_db_advanced_info', $dbinfo, WEEK_IN_SECONDS );
				}
			}

			$rows = array();
			foreach ( (array) $dbinfo as $info ) {
				if ( is_object( $info ) && isset( $info->Variable_name ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column name.
					$rows[] = array(
						'label' => (string) $info->Variable_name, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column name.
						'value' => isset( $info->Value ) ? (string) $info->Value : '', // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column name.
					);
				}
			}

			return $rows;
		}

		/**
		 * Get database software.
		 *
		 * @return string
		 */
		public function database_software() {
			$db_software = get_transient( 'wpheka_web_server_db_software' );

			if ( false === $db_software ) {
				$db_software_dump = $this->variable( 'version_comment' );
				if ( '' !== trim( $db_software_dump ) ) {
					$db_soft_array = explode( ' ', trim( $db_software_dump ) );
					$db_software   = $db_soft_array[0];
					set_transient( 'wpheka_web_server_db_software', $db_software, WEEK_IN_SECONDS );
				} else {
					$db_software = __( 'Not available', 'wpheka-web-server-information' );
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

			if ( false === $db_version ) {
				global $wpdb;
				$db_version_dump = (string) $wpdb->get_var( 'SELECT VERSION() AS version from DUAL' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- cached right below.
				if ( preg_match( '/\d+(?:\.\d+)+/', $db_version_dump, $matches ) ) {
					$db_version = $matches[0]; // returning the first match.
					set_transient( 'wpheka_web_server_db_version', $db_version, WEEK_IN_SECONDS );
				} else {
					$db_version = __( 'Not available', 'wpheka-web-server-information' );
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

			if ( false === $db_max_connection ) {
				$db_max_connection = $this->variable( 'max_connections' );
				if ( empty( $db_max_connection ) ) {
					$db_max_connection = __( 'Not available', 'wpheka-web-server-information' );
				} else {
					$db_max_connection = number_format_i18n( (float) $db_max_connection, 0 );
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

			if ( false === $db_max_packet_size ) {
				$db_max_packet_size = $this->variable( 'max_allowed_packet' );
				if ( empty( $db_max_packet_size ) ) {
					$db_max_packet_size = __( 'Not available', 'wpheka-web-server-information' );
				} else {
					$db_max_packet_size = $this->format_filesize( (float) $db_max_packet_size );
					set_transient( 'wpheka_web_server_db_max_packet_size', $db_max_packet_size, WEEK_IN_SECONDS );
				}
			}

			return $db_max_packet_size;
		}

		/**
		 * Format filesize.
		 *
		 * @param float $bytes Size in bytes.
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
		 * Sum one column of SHOW TABLE STATUS.
		 *
		 * @since 1.9
		 * @param string $column Data_length or Index_length.
		 * @return float
		 */
		private function table_status_sum( $column ) {
			global $wpdb;

			$total = 0;
			foreach ( (array) $wpdb->get_results( 'SHOW TABLE STATUS' ) as $status ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- cached by the callers.
				$total += is_object( $status ) && isset( $status->$column ) ? (float) $status->$column : 0;
			}

			return $total;
		}

		/**
		 * Get database disk usage.
		 *
		 * @return string
		 */
		public function database_disk_usage() {
			$db_disk_usage = get_transient( 'wpheka_web_server_db_disk_usage' );

			if ( false === $db_disk_usage ) {
				$db_disk_usage = $this->table_status_sum( 'Data_length' );
				if ( empty( $db_disk_usage ) ) {
					$db_disk_usage = __( 'Not available', 'wpheka-web-server-information' );
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

			if ( false === $db_index_disk_usage ) {
				$db_index_disk_usage = $this->table_status_sum( 'Index_length' );
				if ( empty( $db_index_disk_usage ) ) {
					$db_index_disk_usage = __( 'Not available', 'wpheka-web-server-information' );
				} else {
					$db_index_disk_usage = $this->format_filesize( $db_index_disk_usage );
					set_transient( 'wpheka_web_server_db_index_disk_usage', $db_index_disk_usage, WEEK_IN_SECONDS );
				}
			}

			return $db_index_disk_usage;
		}
	}

endif;
