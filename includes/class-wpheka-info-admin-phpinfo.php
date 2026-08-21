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

	}

endif;
