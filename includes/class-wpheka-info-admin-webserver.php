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

if ( ! class_exists( 'WPHEKA_Info_Admin_Webserver', false ) ) :

	/**
	 * The web server facts shown on the Overview tab. Reads only; the page prints them.
	 */
	class WPHEKA_Info_Admin_Webserver {

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
		public function server_var( $key ) {
			if ( ! isset( $_SERVER[ $key ] ) ) {
				return '';
			}

			return sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
		}

		/**
		 * The Server section, as kit key/value rows.
		 *
		 * @since 1.9
		 * @return array[]
		 */
		public function rows() {
			$na       = __( 'Not available', 'wpheka-web-server-information' );
			$ip       = $this->check_server_ip();
			$host     = (string) gethostname();
			$root     = $this->server_var( 'DOCUMENT_ROOT' );
			$or_na    = static function ( $value ) use ( $na ) {
				return '' === (string) $value ? $na : (string) $value;
			};
			$valid_ip = $this->validate_ip_address( $ip );

			return array(
				array(
					'label' => __( 'Server OS', 'wpheka-web-server-information' ),
					'value' => $or_na( $this->server_os() ),
				),
				array(
					'label' => __( 'Server software', 'wpheka-web-server-information' ),
					'value' => $or_na( $this->server_var( 'SERVER_SOFTWARE' ) ),
				),
				array(
					'label' => __( 'Server IP', 'wpheka-web-server-information' ),
					'value' => $valid_ip ? $ip : $na,
					'code'  => $valid_ip,
				),
				array(
					'label' => __( 'Server port', 'wpheka-web-server-information' ),
					'value' => $or_na( $this->server_var( 'SERVER_PORT' ) ),
				),
				array(
					'label' => __( 'Server location', 'wpheka-web-server-information' ),
					'value' => $or_na( $this->check_server_location() ),
				),
				array(
					'label' => __( 'Hostname', 'wpheka-web-server-information' ),
					'value' => $or_na( $host ),
					'code'  => '' !== $host,
				),
				array(
					'label' => __( 'Document root', 'wpheka-web-server-information' ),
					'value' => $or_na( $root ),
					'code'  => '' !== $root,
				),
			);
		}

		/**
		 * Get server os.
		 *
		 * @return string
		 */
		public function server_os() {
			$server_os = get_transient( 'wpheka_web_server_os' );

			if ( false === $server_os ) {
				$os_detail    = php_uname();
				$just_os_name = explode( ' ', trim( $os_detail ) );
				$server_os    = $just_os_name[0];
				set_transient( 'wpheka_web_server_os', $server_os, WEEK_IN_SECONDS );
			}

			return $server_os;
		}

		/**
		 * Check server ip.
		 *
		 * @return string
		 */
		public function check_server_ip() {
			return trim( gethostbyname( (string) gethostname() ) );
		}

		/**
		 * Call IP-API.com to get server ip details.
		 *
		 * @return string Empty when the location is not known.
		 */
		public function check_server_location() {
			// get the server ip.
			$ip = $this->check_server_ip();

			$server_location = get_transient( 'wpheka_web_server_location' );

			if ( false === $server_location ) {
				// lets validate the ip.
				if ( $this->validate_ip_address( $ip ) ) {
					/*
					 * JSON, not the /php/ endpoint. ip-api's free tier is plain
					 * HTTP only, so the body can be altered in transit, and
					 * unserialize() on it was a PHP object injection path.
					 * json_decode() only ever builds arrays and scalars.
					 */
					// $ip has passed FILTER_VALIDATE_IP, so it is only digits,
					// hex, dots and colons and is safe in the path as is.
					$response = wp_remote_get( 'http://ip-api.com/json/' . $ip, array( 'timeout' => 5 ) );

					// A failed lookup is cached for an hour, so an unreachable or
					// rate-limited ip-api does not slow every visit to this screen.
					if ( is_wp_error( $response ) ) {
						$server_location = $response->get_error_message();
						set_transient( 'wpheka_web_server_location', $server_location, HOUR_IN_SECONDS );
						return $server_location;
					}

					$query = json_decode( wp_remote_retrieve_body( $response ), true );

					if ( ! is_array( $query ) || empty( $query['status'] ) ) {
						set_transient( 'wpheka_web_server_location', '', HOUR_IN_SECONDS );
						return '';
					}

					if ( 'success' === $query['status'] && isset( $query['city'], $query['country'] ) ) {
						$server_location = $query['city'] . ', ' . $query['country'];
						set_transient( 'wpheka_web_server_location', $server_location, WEEK_IN_SECONDS );
					} else {
						if ( empty( $query['message'] ) ) {
								$server_location = $query['status'];
						} else {
							$server_location = $query['message'];
						}
						set_transient( 'wpheka_web_server_location', $server_location, HOUR_IN_SECONDS );
					}
				} else {
					$server_location = '';
				}
			}

			return (string) $server_location;
		}

		/**
		 * Validate IP address.
		 *
		 * @param string $ip Address.
		 * @return bool
		 */
		public function validate_ip_address( $ip ) {
			return false !== filter_var( $ip, FILTER_VALIDATE_IP );
		}
	}

endif;
