<?php
/**
 * security.php [network]
 * PHP-level checks for the security fixes (laptop queue 2026-10-23, item 4): one
 * capability for the page, the save and the admin footer; unknown tabs; and, with
 * "network", that a network-wide setting needs a network admin. Prints PASS| and
 * FAIL| lines for run.sh and a last "ran to the end" line, so a fatal shows as a FAIL.
 *
 * Needs the users from `state.php setup` (ids.json in WSI_STATE).
 */
$dir   = rtrim( getenv( 'WSI_STATE' ), '/' );
$ids   = json_decode( file_get_contents( $dir . '/ids.json' ), true );
$mode  = isset( $args[0] ) ? $args[0] : '';
$check = function ( $name, $ok, $detail = "" ) {
	echo ( $ok ? 'PASS' : 'FAIL' ) . '|' . $name . ( $ok ? '' : ' -- ' . $detail ) . "\n";
};

if ( ! class_exists( 'WPHEKA_Web_Server_Info_Admin', false ) ) {
	require_once WPHEKA_WEB_SERVER_INFO_PLUGIN_PATH . '/includes/class-wpheka-web-server-info-admin.php';
}

// wp_die() ends the request; here it throws so a check can see it.
add_filter(
	'wp_die_handler',
	function () {
		return function ( $message ) {
			throw new RuntimeException( is_string( $message ) ? $message : 'wp_die' );
		};
	}
);
$dies = function ( callable $fn ) {
	ob_start();
	try {
		$fn();
		return false;
	} catch ( RuntimeException $e ) {
		return true;
	} finally {
		ob_end_clean();
	}
};

if ( 'network' === $mode ) {
	// Only on a multisite network with the plugin network-activated (the Mac's
	// MAMP multisite): WSI_NETWORK=1 bash tests/functional/run.sh
	wp_set_current_user( (int) $ids['admin'] );
	$scope = wpheka_web_server_info_options()->scope();
	$check( 'network: the setting is network-wide in this run', 'network' === $scope, "scope {$scope}" );
	$check( 'network: an administrator who is not a network admin cannot save it', ! wpheka_web_server_info_can_save() );
	$_POST['wpheka_wsi_nonce'] = wp_create_nonce( 'wpheka_wsi_save' );
	$check( 'network: the save is refused for that administrator', $dies( 'wpheka_web_server_info_save_settings' ) );
	unset( $_POST['wpheka_wsi_nonce'] );
	$check( 'network: a user with manage_network_options can save it', ( function () {
		$grant = function ( $allcaps ) {
			$allcaps['manage_network_options'] = true;
			return $allcaps;
		};
		add_filter( 'user_has_cap', $grant );
		$can = wpheka_web_server_info_can_save();
		remove_filter( 'user_has_cap', $grant );
		return $can;
	} )() );
	echo "PASS|security.php network: ran to the end\n";
	return;
}

$admin = new WPHEKA_Web_Server_Info_Admin();

// The admin footer.
wp_set_current_user( (int) $ids['sub'] );
$check( 'footer: a subscriber keeps WordPress\' own footer text', 'Version 9.9' === $admin->version_info_in_footer( 'Version 9.9' ) );
wp_set_current_user( (int) $ids['admin'] );
$text = $admin->version_info_in_footer( 'Version 9.9' );
$check( 'footer: an administrator sees the server versions', false !== strpos( $text, 'You are running WordPress' ) && false !== strpos( $text, 'MySQL' ), $text );

// One capability for the page and the save.
$check( 'capability: manage_options by default', 'manage_options' === wpheka_web_server_info_capability() );
$lower = function () {
	return 'edit_posts';
};
add_filter( 'wpheka_plugin_panel_menu_page_capability', $lower );
$editor = wp_insert_user(
	array(
		'user_login' => 'zz_wsi_editor',
		'user_pass'  => wp_generate_password( 24 ),
		'user_email' => 'zz-wsi-editor@example.invalid',
		'role'       => 'editor',
	)
);
wp_set_current_user( $editor );
$check( 'capability: the filter lowers the page and the save together', 'edit_posts' === wpheka_web_server_info_capability() && wpheka_web_server_info_can_save() );
$check( 'capability: that user can open the page', ! $dies( array( $admin, 'wpheka_web_server_info_page_callback' ) ) );
remove_filter( 'wpheka_plugin_panel_menu_page_capability', $lower );
$check( 'capability: back to manage_options, the same user can do neither', ! wpheka_web_server_info_can_save() && $dies( array( $admin, 'wpheka_web_server_info_page_callback' ) ) );
wp_set_current_user( (int) $ids['sub'] );
$check( 'capability: the page itself refuses a subscriber', $dies( array( $admin, 'wpheka_web_server_info_page_callback' ) ) );
wp_set_current_user( (int) $ids['admin'] );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $editor );

// A tab added through the filter, with no class file of its own.
$fatal = '';
try {
	ob_start();
	$admin->tab_init( 'custom' );
	$admin->tab_init( '../../../wp-config' );
	$admin->tab_init( '' );
} catch ( Throwable $e ) {
	$fatal = $e->getMessage();
} finally {
	ob_end_clean();
}
$check( 'tabs: a tab with no class file is skipped, not a fatal error', '' === $fatal, $fatal );
$check( 'tabs: a built-in tab still loads', $admin->load_tab_class( 'dbinfo' ) && ! $admin->load_tab_class( 'custom' ) );

echo "PASS|security.php: ran to the end\n";
