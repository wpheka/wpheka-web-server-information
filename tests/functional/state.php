<?php
/**
 * state.php save|restore|setup|cleanup|user-get <key>|user-clear <key>|opt <name>|location
 * One helper for the whole suite. save/restore keep the site's own settings,
 * view counter and location cache; setup creates an administrator and a
 * subscriber with login cookies, plus an author (reaches wp-admin, unlike a
 * subscriber on a WooCommerce site) for the admin footer check.
 */
require_once ABSPATH . 'wp-admin/includes/user.php';
$dir  = rtrim( getenv( 'WSI_STATE' ), '/' );
$file = $dir . '/ids.json';
$ids  = file_exists( $file ) ? json_decode( file_get_contents( $file ), true ) : array();
$save = function () use ( $file, &$ids ) { file_put_contents( $file, wp_json_encode( $ids ) ); };
$cmd  = $args[0];

if ( 'setup' === $cmd ) {
	foreach ( array( 'wpheka_web_server_info_settings', 'wpheka_web_server_info_view_count' ) as $opt ) {
		$ids['opt'][ $opt ] = get_option( $opt, '__unset__' );
	}
	$ids['location_cache'] = get_transient( 'wpheka_web_server_location' );
	$ids['was_active']     = is_plugin_active( 'wpheka-web-server-information/wpheka-web-server-information.php' );
	if ( ! $ids['was_active'] ) {
		activate_plugin( 'wpheka-web-server-information/wpheka-web-server-information.php' );
	}
	foreach ( array( 'admin' => 'administrator', 'sub' => 'subscriber', 'author' => 'author' ) as $key => $role ) {
		$uid = wp_insert_user( array( 'user_login' => 'zz_wsi_' . $key, 'user_pass' => wp_generate_password( 24 ), 'user_email' => 'zz-wsi-' . $key . '@example.invalid', 'role' => $role ) );
		$exp = time() + 2 * HOUR_IN_SECONDS;
		$ids[ $key ]              = $uid;
		$ids[ $key . '_cookies' ] = array(
			array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $uid, $exp, 'auth' ), 'domain' => 'localhost', 'path' => wp_parse_url( admin_url(), PHP_URL_PATH ) ),
			array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $uid, $exp, 'logged_in' ), 'domain' => 'localhost', 'path' => wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ),
		);
	}
	$ids['admin_url']     = admin_url();
	// "Another plugin" with notices hooked three ways, and one printed outside the notice hooks.
	$ids['notices_mu'] = WPMU_PLUGIN_DIR . '/zz-wsi-functional-notices.php';
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	file_put_contents(
		$ids['notices_mu'],
		'<?php
// Written by wpheka-web-server-information/tests/functional; deleted when the suite ends.
function zz_wsi_foreign_notice() { echo \'<div class="notice notice-warning zz-wsi-foreign"><p>ZZ WSI function notice</p></div>\'; }
class ZZ_Wsi_Foreign { public static function notice() { echo \'<div class="notice notice-info zz-wsi-foreign"><p>ZZ WSI method notice</p></div>\'; } }
add_action( \'admin_notices\', \'zz_wsi_foreign_notice\' );
add_action( \'admin_notices\', array( \'ZZ_Wsi_Foreign\', \'notice\' ) );
add_action( \'all_admin_notices\', function () { echo \'<div class="notice notice-error zz-wsi-foreign"><p>ZZ WSI closure notice</p></div>\'; } );
add_action( \'in_admin_header\', function () { echo \'<div class="notice notice-warning zz-wsi-stray"><p>ZZ WSI stray notice</p></div>\'; }, 1 );
'
	);
	$ids['wp_version']    = get_bloginfo( 'version' );
	$ids['php_version']   = phpversion();
	$ids['mysql_version'] = $GLOBALS['wpdb']->get_var( 'SELECT VERSION();' );
	update_option( 'wpheka_web_server_info_view_count', 0, false );
	$save();
	echo 'created an administrator and a subscriber; plugin was ', $ids['was_active'] ? 'active' : 'inactive (activated for the suite)', "\n";
} elseif ( 'cleanup' === $cmd ) {
	if ( ! empty( $ids['notices_mu'] ) && file_exists( $ids['notices_mu'] ) ) {
		unlink( $ids['notices_mu'] );
	}
	foreach ( (array) ( $ids['opt'] ?? array() ) as $opt => $value ) {
		'__unset__' === $value ? delete_option( $opt ) : update_option( $opt, $value );
	}
	false === ( $ids['location_cache'] ?? false ) ? delete_transient( 'wpheka_web_server_location' ) : set_transient( 'wpheka_web_server_location', $ids['location_cache'], WEEK_IN_SECONDS );
	foreach ( array( 'admin', 'sub', 'author' ) as $key ) {
		if ( ! empty( $ids[ $key ] ) ) {
			wp_delete_user( $ids[ $key ] );
		}
	}
	if ( isset( $ids['was_active'] ) && ! $ids['was_active'] ) {
		deactivate_plugins( 'wpheka-web-server-information/wpheka-web-server-information.php' );
	}
	echo "cleanup: settings, counter and location cache restored; test users deleted\n";
} elseif ( 'user-get' === $cmd ) {
	echo wp_json_encode( get_user_meta( (int) $ids['admin'], $args[1], true ) );
} elseif ( 'user-clear' === $cmd ) {
	delete_user_meta( (int) $ids['admin'], $args[1] );
	echo '"ok"';
} elseif ( 'opt' === $cmd ) {
	echo wp_json_encode( get_option( $args[1] ) );
} elseif ( 'set-count' === $cmd ) {
	update_option( 'wpheka_web_server_info_view_count', (int) $args[1], false );
	echo '"ok"';
} elseif ( 'clear-location' === $cmd ) {
	delete_transient( 'wpheka_web_server_location' );
	echo '"ok"';
} elseif ( 'set-transient' === $cmd ) {
	set_transient( $args[1], $args[2], WEEK_IN_SECONDS );
	echo '"ok"';
} elseif ( 'get-transient' === $cmd ) {
	echo wp_json_encode( get_transient( $args[1] ) );
}
