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

			echo $phpinfo_html;
		}

	}

endif;
