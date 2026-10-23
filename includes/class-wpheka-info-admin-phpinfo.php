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

if ( ! class_exists( 'WPHEKA_Info_Admin_Phpinfo', false ) ) :

	/**
	 * WPHEKA_Info_Admin_Phpinfo Class.
	 */
	class WPHEKA_Info_Admin_Phpinfo {

		/**
		 * WPHEKA_Info_Admin_Phpinfo Constructor.
		 */
		public function __construct() {
			ob_start();
			phpinfo();
			$phpinfo = ob_get_contents();
			ob_end_clean();

			// Use DOMDocument to parse phpinfo().
			libxml_use_internal_errors( true );
			$html = new DOMDocument( '1.0', 'UTF-8' );
			$html->loadHTML( $phpinfo );

			// Style process.
			$tables = $html->getElementsByTagName( 'table' );
			foreach ( $tables as $table ) {
				$table->setAttribute( 'class', 'widefat' );
			}

			$this->remove_cookies( $html );

			// We only need the <body>.
			$xpath = new DOMXPath( $html );
			$body = $xpath->query( '/html/body' );

			// Save HTML fragment.
			libxml_use_internal_errors( false );
			$phpinfo_html = $html->saveXml( $body->item( 0 ) );

			/*
			 * Filtered, not escaped. This is the markup phpinfo() produced, so
			 * esc_html() would print the table as literal text and defeat the
			 * page. It is not trusted either: phpinfo() embeds $_SERVER values
			 * and request headers, which are attacker-influenced. So it is run
			 * through kses, which keeps the tables and strips script vectors.
			 *
			 * `data:` is added to the allowed protocols, and wp_kses_post() is
			 * therefore not usable here. phpinfo() ships the PHP and Zend logos
			 * as base64 `data:image/png` URIs, and `data` is not in
			 * wp_allowed_protocols(), so wp_kses_post() silently drops the src
			 * and the logos disappear. That is exactly what happened when this
			 * output was first filtered.
			 *
			 * Allowing `data:` here is narrow: the only markup reaching this
			 * line is what phpinfo() generated a few statements above, and
			 * scripts are still stripped -- verified, not assumed.
			 */
			echo wp_kses(
				$phpinfo_html,
				wp_kses_allowed_html( 'post' ),
				array_merge( wp_allowed_protocols(), array( 'data' ) )
			);
		}

		/**
		 * Take the viewer's cookies out of the phpinfo() output.
		 *
		 * The phpinfo() output holds the request's cookies, the WordPress login cookie
		 * included, in the Environment, PHP Variables and HTTP headers tables.
		 * A screenshot or a copy of this page posted in a support forum would
		 * carry them. The rows that hold cookies are removed, and any cookie
		 * value still found elsewhere on the page is replaced.
		 *
		 * @since 1.9
		 * @param DOMDocument $html Parsed phpinfo() output.
		 * @return void
		 */
		private function remove_cookies( DOMDocument $html ) {
			$rows = array();
			foreach ( $html->getElementsByTagName( 'tr' ) as $row ) {
				$first = $row->getElementsByTagName( 'td' )->item( 0 );
				if ( $first && preg_match( '/^(HTTP_COOKIE|\$_SERVER\[[\'"]HTTP_COOKIE[\'"]\]|\$_COOKIE\[.*\]|Cookie)$/i', trim( $first->textContent ) ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.
					$rows[] = $row;
				}
			}
			// Removed after the loop: removing while iterating a live node list skips rows.
			foreach ( $rows as $row ) {
				$row->parentNode->removeChild( $row ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.
			}

			$values = array();
			if ( ! empty( $_SERVER['HTTP_COOKIE'] ) && is_string( $_SERVER['HTTP_COOKIE'] ) ) {
				$values[] = wp_unslash( $_SERVER['HTTP_COOKIE'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only compared with page text, never output or stored.
			}
			foreach ( $_COOKIE as $value ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only compared with page text, never output or stored.
				if ( is_string( $value ) ) {
					$values[] = wp_unslash( $value );
					$values[] = rawurlencode( wp_unslash( $value ) );
				}
			}
			// Short values (a "1" or a "yes") would blank ordinary settings; real secrets are long.
			$values = array_unique(
				array_filter(
					$values,
					static function ( $value ) {
						return strlen( $value ) >= 8;
					}
				)
			);
			if ( ! $values ) {
				return;
			}
			usort(
				$values,
				static function ( $a, $b ) {
					return strlen( $b ) - strlen( $a );
				}
			);

			$xpath = new DOMXPath( $html );
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM properties.
			foreach ( $xpath->query( '//text()' ) as $text ) {
				$replaced = str_replace( $values, '[removed]', $text->nodeValue );
				if ( $replaced !== $text->nodeValue ) {
					$text->nodeValue = $replaced;
				}
			}
			// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

	}

endif;
