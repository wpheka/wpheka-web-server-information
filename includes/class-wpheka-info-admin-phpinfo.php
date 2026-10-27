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

if ( ! class_exists( 'WPHEKA_Info_Admin_Phpinfo', false ) ) :

	/**
	 * The PHP tab's data: phpinfo() read into groups of rows, one group per module.
	 */
	class WPHEKA_Info_Admin_Phpinfo {

		/**
		 * The phpinfo() output as groups of key/value rows.
		 *
		 * Only the general, configuration and module sections are asked for. The
		 * Environment and PHP Variables sections are left out (owner decision Q7,
		 * 1.9.0): they hold the request's headers and cookies, which then end up in
		 * every screenshot of this page shared in a support forum. For the same
		 * reason the web server's own copies of them are skipped too: Apache's
		 * "Apache Environment" and "HTTP Headers Information" module tables, where
		 * hosts also keep environment variables such as database passwords.
		 *
		 * @since 1.9
		 * @return array[] Each { id, title, rows }; rows are kit key/value rows.
		 */
		public function groups() {
			ob_start();
			phpinfo( INFO_GENERAL | INFO_CONFIGURATION | INFO_MODULES ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_phpinfo -- the purpose of this screen, for users who manage the site.
			$phpinfo = (string) ob_get_clean();

			// Use DOMDocument to parse phpinfo().
			$previous = libxml_use_internal_errors( true );
			$html     = new DOMDocument( '1.0', 'UTF-8' );
			// loadHTML() reads ISO-8859-1 unless told otherwise; phpinfo() writes UTF-8.
			$html->loadHTML( '<?xml encoding="UTF-8">' . $phpinfo );
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );

			$this->remove_cookies( $html );

			$groups = array();
			$group  = null;
			$xpath  = new DOMXPath( $html );
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM properties.
			foreach ( $xpath->query( '//h1|//h2|//table' ) as $node ) {
				$name = strtolower( $node->nodeName );
				$text = trim( preg_replace( '/\s+/', ' ', $node->textContent ) );

				if ( 'h1' === $name ) {
					// The first heading is "PHP Version x.y.z": the general facts follow it.
					if ( preg_match( '/PHP Version\s+(\S+)/i', $text, $m ) ) {
						$groups[] = array(
							'title' => __( 'General', 'wpheka-web-server-information' ),
							'rows'  => array(
								array(
									'label' => __( 'PHP version', 'wpheka-web-server-information' ),
									'value' => $m[1],
								),
							),
						);
						$group    = count( $groups ) - 1;
					}
					continue;
				}
				if ( 'h2' === $name ) {
					if ( in_array( strtolower( $text ), array( 'apache environment', 'http headers information' ), true ) ) {
						$group = null;
						continue;
					}
					$groups[] = array(
						'title' => $text,
						'rows'  => array(),
					);
					$group    = count( $groups ) - 1;
					continue;
				}
				if ( null === $group ) {
					continue;
				}
				foreach ( $node->getElementsByTagName( 'tr' ) as $row ) {
					$cells = array();
					foreach ( $row->getElementsByTagName( 'td' ) as $cell ) {
						$cells[] = trim( preg_replace( '/\s+/', ' ', $cell->textContent ) );
					}
					// Header rows use <th>; a one-cell row is a caption, not a value.
					if ( count( $cells ) < 2 || '' === $cells[0] ) {
						continue;
					}
					$item = array(
						'label' => $cells[0],
						'value' => $cells[1],
					);
					// Directive, local value, master value: say so when they differ.
					if ( isset( $cells[2] ) && $cells[2] !== $cells[1] ) {
						/* translators: %s: the value set in php.ini. */
						$item['note'] = sprintf( __( 'Default: %s', 'wpheka-web-server-information' ), $cells[2] );
					}
					$groups[ $group ]['rows'][] = $item;
				}
			}
			// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			$out = array();
			$ids = array();
			foreach ( $groups as $g ) {
				if ( ! $g['rows'] ) {
					continue;
				}
				$id = 'php-' . sanitize_title( $g['title'] );
				while ( isset( $ids[ $id ] ) ) {
					$id .= '-2';
				}
				$ids[ $id ] = true;
				$out[]      = array(
					'id'    => $id,
					'title' => $g['title'],
					'rows'  => $g['rows'],
				);
			}

			return $out;
		}

		/**
		 * Take the viewer's cookies out of the phpinfo() output.
		 *
		 * Module sections can still hold request headers (Apache's environment and
		 * header tables), the WordPress login cookie included. A screenshot or a copy
		 * of this page posted in a support forum would carry them. The rows that hold
		 * cookies are removed, and any cookie value still found elsewhere on the page
		 * is replaced.
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
